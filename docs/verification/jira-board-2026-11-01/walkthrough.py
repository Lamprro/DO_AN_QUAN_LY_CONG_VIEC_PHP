"""Live HTTP walkthrough against the isolated review DB; no mocks or TestCase.
Start the existing Laravel routes on 127.0.0.1:8137 with the review DB first.
Only creates labeled review fixtures. SQLite access here is read-only.
"""
from pathlib import Path
import datetime
import html
import http.cookiejar
import json
import re
import sqlite3
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(__file__).resolve().parent
DB = ROOT / 'database/jira_board_review_20261101.sqlite'
BASE = 'http://127.0.0.1:8137'
JOURNAL = []


def rows(sql, params=()):
    with sqlite3.connect(f'file:{DB.as_posix()}?mode=ro', uri=True) as db:
        db.row_factory = sqlite3.Row
        return [dict(row) for row in db.execute(sql, params)]


def record(step, **data):
    JOURNAL.append(dict(step=step, **data))
    (OUT / 'http-flow.json').write_text(json.dumps(JOURNAL, ensure_ascii=False, indent=2), encoding='utf-8')
    print(step, flush=True)


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))


def call(session, path, data=None, method=None, token=None):
    headers = {'Accept': 'application/json' if token else 'text/html'}
    if token:
        headers.update({'X-CSRF-TOKEN': token, 'Content-Type': 'application/json'})
        body = json.dumps(data).encode()
    else:
        body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(path if path.startswith('http') else BASE + path, data=body, method=method, headers=headers)
    try:
        response = session.open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read().decode('utf-8'), response.geturl()


def csrf(page):
    return html.unescape(re.search(r'name="csrf-token" content="([^"]+)"', page)[1])


def login(email):
    session = client()
    code, page, _ = call(session, '/login')
    assert code == 200
    code, page, url = call(session, '/login', {'_token': csrf(page), 'email': email, 'password': 'Demo@123456'})
    assert code == 200 and not url.endswith('/login'), (code, url)
    return session, csrf(page)


def task_row(code):
    return rows('select id, code, status, revision, assignee_id, priority, deadline_at from tasks where code=?', (code,))[0]


if __name__ == '__main__':
    owner, token = login('demo1@example.test')
    project = rows("select id, workspace_id from projects where name='Demo Work Management'")[0]
    owner_id = rows("select id from users where email='demo1@example.test'")[0]['id']
    route = f"/workspaces/{project['workspace_id']}/projects/{project['id']}/tasks"
    stamp = str(int(time.time()))
    fixture_codes = {}
    for label, assignee, priority, deadline in [
        ('MOVE', owner_id, 'urgent', '2026-10-01T10:00'),
        ('BLOCK', owner_id, 'high', '2026-10-02T10:00'),
        ('FREE', '', 'low', ''),
        ('SOON', owner_id, 'medium', (datetime.datetime.now() + datetime.timedelta(hours=12)).strftime('%Y-%m-%dT%H:%M')),
    ]:
        task_code = f'JIRA-{label}-{stamp}'
        fixture_codes[label] = task_code
        code, body, _ = call(owner, route, {
            'code': task_code, 'title': f'[Review Jira] {label} — Công việc <kiểm tra> & dữ liệu thật',
            'priority': priority, 'task_type': 'WORK', 'assignee_id': assignee, 'deadline_at': deadline,
        }, 'POST', token)
        assert code == 201, (code, body[:500])
        record('create_' + label, http=code, persisted=task_row(task_code))

    code, board, _ = call(owner, route + '?view=board')
    assert code == 200
    assert board.count('data-jira-board') == 1 and board.count('class="jira-column"') == 5
    assert 'css/jira-board.css' in board.split('</head>')[0]
    assert 'js/jira-board.js' in board.split('</body>')[0]
    assert '&lt;kiểm tra&gt; &amp;' in board
    for attr in ['task-id', 'code', 'title', 'current-status', 'assignee-id', 'priority', 'is-overdue', 'status-url']:
        assert 'data-' + attr + '=' in board
    assert board.count('data-quick-filter=') == 5
    assert 'page_todo=2' in board
    record('board_render', http=code, columns=5, chips=5, stylesheet_in_head=True, script_before_body_end=True, escaped_title=True, pagination=True)

    moving = task_row(fixture_codes['MOVE'])
    status_route = route + '/' + moving['id'] + '/status'
    for source, target in [('TODO', 'IN_PROGRESS'), ('IN_PROGRESS', 'REVIEW'), ('REVIEW', 'DONE')]:
        before = task_row(moving['code'])
        code, body, _ = call(owner, status_route, {'expected_status': source, 'status': target}, 'PATCH', token)
        result = json.loads(body)
        after = task_row(moving['code'])
        assert code == 200 and result['success'] and after['status'] == target
        assert after['revision'] > before['revision']
        record('transition_' + target, http=code, request={'expected_status': source, 'status': target}, before=before, response=result, after=after)
    code, body, _ = call(owner, status_route, {'expected_status': 'REVIEW', 'status': 'DONE'}, 'PATCH', token)
    assert code == 422 and task_row(moving['code'])['status'] == 'DONE'
    record('stale_rejected', http=code, response=json.loads(body), after=task_row(moving['code']))
    code, body, _ = call(owner, status_route, {'expected_status': 'DONE', 'status': 'TODO'}, 'PATCH', token)
    assert code == 422 and 'reason' in json.loads(body)['errors']
    record('reason_required', http=code, response=json.loads(body), after=task_row(moving['code']))

    blocked = task_row(fixture_codes['BLOCK'])
    code, body, _ = call(owner, route + '/' + blocked['id'] + '/blockers', {'reason': '[Review Jira] blocker for live verification'}, 'POST', token)
    assert code == 200, (code, body[:300])
    code, body, _ = call(owner, route + '/' + blocked['id'] + '/status', {'expected_status': 'TODO', 'status': 'IN_PROGRESS'}, 'PATCH', token)
    assert code == 422 and task_row(blocked['code'])['status'] == 'TODO'
    record('blocker_rejected', http=code, response=json.loads(body), after=task_row(blocked['code']))

    viewer, viewer_token = login('demo5@example.test')
    code, viewer_board, _ = call(viewer, route + '?view=board')
    assert code == 200 and 'draggable="true"' not in viewer_board
    actions = re.findall(r'<div class="jira-card-actions">(.*?)</div>', viewer_board, re.S)
    assert actions and all('<a' not in action for action in actions)
    record('viewer_render', http=code, draggable_cards=0, edit_or_status_links=0)
    code, body, _ = call(viewer, route + '/' + blocked['id'] + '/status', {'expected_status': 'TODO', 'status': 'IN_PROGRESS'}, 'PATCH', viewer_token)
    assert code == 403 and task_row(blocked['code'])['status'] == 'TODO'
    record('viewer_patch_rejected', http=code, response=json.loads(body), after=task_row(blocked['code']))

    code, body, _ = call(owner, route + '/' + blocked['id'] + '/status', {'expected_status': 'TODO', 'status': 'IN_PROGRESS'}, 'PATCH', 'invalid-review-csrf')
    assert code == 419
    record('csrf_rejected', http=code, after=task_row(blocked['code']))

    for label, query in [('advanced_search', '?view=board&q=' + fixture_codes['FREE']), ('status_filter', '?view=board&status=DONE'), ('pagination', '?view=board&page_todo=2')]:
        code, body, _ = call(owner, route + query)
        assert code == 200
        if label == 'advanced_search':
            assert body.count('class="jira-card"') == 1 and fixture_codes['FREE'] in body
        if label == 'status_filter':
            assert 'data-current-status="TODO"' not in body
        record(label, http=code, rendered_cards=body.count('class="jira-card"'))

    personal = rows("select id from workspaces where name='Demo Personal'")[0]['id']
    for path in [route, '/workspaces', f'/workspaces/{personal}/tasks?view=board', route + '/' + blocked['id'] + '?panel=1', route + '/' + blocked['id'] + '/status?panel=1']:
        code, body, _ = call(owner, path)
        assert code == 200, (path, code)
        if path == route or path == '/workspaces':
            assert 'jira-board.css' not in body and 'jira-board.js' not in body
        record('existing_page', path=path, http=code)
    for path in ['/css/jira-board.css', '/js/jira-board.js']:
        code, body, _ = call(owner, path)
        assert code == 200
        record('asset', path=path, http=code, bytes=len(body.encode()))
    history = rows("select type, event_type, data from task_activities where task_id=? order by created_at", (moving['id'],))
    record('read_back_history', task=moving['code'], entries=history)
    record('complete', review_database=DB.name, board_url=BASE + route + '?view=board', fixtures=fixture_codes, limitations=['Native browser tool denied Chrome access; drag/drop, instant filters and visual layout need browser verification.'])
