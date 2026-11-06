"""Real write flows through HTTP as different roles, then a DB fingerprint.

usage: python3 -I mutate.py <base_url> <out.json>
Same seed DB must be restored before each run so both runs start identical.
"""
import html, http.cookiejar, json, re, subprocess, sys, urllib.error, urllib.parse, urllib.request

BASE, OUT = sys.argv[1], sys.argv[2]
LOG = []


def sql(q):
    r = subprocess.run(['docker', 'exec', 'wm-mysql', 'mysql', '-uroot', '-preview', '--default-character-set=utf8mb4', 'wm_review', '-N', '-B', '-e', q], capture_output=True, text=True, encoding='utf-8', errors='replace')
    return [line.split('\t') for line in r.stdout.strip().split('\n') if line]


def one(q):
    rows = sql(q)
    return rows[0][0] if rows else None


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class User:
    def __init__(self, email):
        self.email = email
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
        _, _, body = self.raw('GET', '/login')
        st, loc, _ = self.raw('POST', '/login', {'_token': token(body), 'email': email, 'password': 'Demo@123456'})
        assert st == 302 and 'login' not in (loc or ''), (email, st, loc)
        self.refresh('/workspaces')

    def raw(self, method, path, data=None):
        body = urllib.parse.urlencode(flatten(data or {}), doseq=True).encode() if data is not None else None
        req = urllib.request.Request(BASE + path, data=body, method='GET' if method == 'GET' else 'POST', headers={'Accept': 'text/html'})
        try:
            r = self.op.open(req, timeout=60)
            return r.status, r.headers.get('Location'), r.read().decode('utf-8', 'replace')
        except urllib.error.HTTPError as e:
            return e.code, e.headers.get('Location'), e.read().decode('utf-8', 'replace')

    def refresh(self, page):
        """GET a page: sets the CSRF token and the 'previous URL' used by back()."""
        st, _, body = self.raw('GET', page)
        self.token = token(body) or getattr(self, 'token', None)
        return st, body

    def send(self, label, method, path, data=None, page=None):
        self.refresh(page or path.split('?')[0] if method != 'GET' else path)
        payload = dict(data or {})
        payload['_token'] = self.token
        if method not in ('GET', 'POST'):
            payload['_method'] = method
        st, loc, body = self.raw('GET' if method == 'GET' else 'POST', path, payload if method != 'GET' else None)
        entry = {'step': label, 'user': self.email, 'method': method, 'status': st, 'location': norm(loc)}
        if st in (301, 302) and loc:
            _, _, follow = self.raw('GET', norm(loc))
            entry['flash'] = re.findall(r'data-flash-type="(\w+)" data-flash-message="([^"]*)"', follow)
            entry['errors'] = sorted(set(re.findall(r'data-error-key="([^"]+)">([^<]*)<', follow)))
        elif st >= 400 or st == 409:
            m = re.search(r'<title>(.*?)</title>', body, re.S)
            entry['title'] = m.group(1).strip() if m else None
        LOG.append(entry)
        print(f"{label:<44} {self.email:<20} {st} {entry.get('flash') or entry.get('errors') or ''}"[:200], file=sys.stderr)
        return st, body


def flatten(d, prefix=''):
    out = []
    for k, v in d.items():
        key = f'{prefix}[{k}]' if prefix else str(k)
        if isinstance(v, dict):
            out += flatten(v, key)
        elif isinstance(v, list):
            out += [(key + '[]', x) for x in v]
        else:
            out.append((key, v))
    return out


def token(body):
    m = re.search(r'name="_token" value="([^"]+)"', body) or re.search(r'name="csrf-token" content="([^"]+)"', body)
    return m.group(1) if m else None


def norm(u):
    if not u:
        return None
    u = re.sub(r'^https?://[^/]+', '', u)
    return re.sub(r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}', lambda m: ID_NAMES.get(m.group(0), '<new-id>'), u)


ID_NAMES = {}


def name(i, label):
    ID_NAMES[i] = label
    return i


def task(code):
    return name(one(f"select id from tasks where workspace_id='{W}' and code='{code}'"), code)


def task_rev(code):
    return one(f"select revision from tasks where workspace_id='{W}' and code='{code}'")


W = name(one("select id from workspaces where name='Demo Team A'"), 'W')
PW = name(one("select id from workspaces where name='Demo Personal'"), 'PW')
P1 = name(one(f"select id from projects where workspace_id='{W}' and name='Demo Work Management'"), 'P1')
role = lambda n: name(one(f"select id from roles where workspace_id='{W}' and name='{n}'"), 'role:' + n)
uid = lambda e: name(one(f"select id from users where email='{e}'"), 'user:' + e)
wm = lambda e: name(one(f"select m.id from workspace_members m join users u on u.id=m.user_id where m.workspace_id='{W}' and u.email='{e}'"), 'wm:' + e)
w = f'/workspaces/{W}'
p1 = f'{w}/projects/{P1}'

O, M, A, B, V, X = (User(e) for e in ['demo1@example.test', 'demo2@example.test', 'demo3@example.test', 'demo4@example.test', 'demo5@example.test', 'dev6@example.test'])

# --- workspace, roles, members, invitations
V.send('ws.update by viewer', 'PATCH', w, {'name': 'X', 'timezone': 'Asia/Ho_Chi_Minh'}, page=w)
O.send('ws.update by owner', 'PATCH', w, {'name': 'Demo Team A edited', 'timezone': 'Asia/Ho_Chi_Minh'}, page=w)
X.send('ws.show by outsider', 'GET', w)
M.send('role.store by manager', 'POST', f'{w}/roles', {'name': 'QA', 'permissions': ['workspace.view', 'project.view', 'task.view']}, page=w)
O.send('role.store by owner', 'POST', f'{w}/roles', {'name': 'QA', 'permissions': ['workspace.view', 'project.view', 'task.view']}, page=f'{w}/roles/create')
O.send('role.store duplicate', 'POST', f'{w}/roles', {'name': 'QA', 'permissions': ['workspace.view']}, page=f'{w}/roles/create')
qa = name(one(f"select id from roles where workspace_id='{W}' and name='QA'"), 'role:QA')
O.send('role.update', 'PATCH', f'{w}/roles/{qa}', {'name': 'QA2', 'permissions': ['workspace.view']}, page=f'{w}/roles/{qa}/edit')
O.send('member.demote demo4 to Viewer', 'PATCH', f'{w}/members/{wm("demo4@example.test")}', {'role_id': role('Viewer'), 'status': 'active'}, page=f'{w}/members')
B.send('task.store by demoted demo4', 'POST', f'{p1}/tasks', {'title': 'by demoted', 'priority': 'medium'}, page=f'{p1}/tasks')
O.send('member.restore demo4 to Member', 'PATCH', f'{w}/members/{wm("demo4@example.test")}', {'role_id': role('Member'), 'status': 'active'}, page=f'{w}/members')
O.send('member.edit self', 'PATCH', f'{w}/members/{wm("demo1@example.test")}', {'role_id': role('Member'), 'status': 'active'}, page=f'{w}/members')
A.send('invite by member', 'POST', f'{w}/invitations', {'email': 'new1@example.test', 'role_id': role('Member')}, page=w)
M.send('invite by manager', 'POST', f'{w}/invitations', {'email': 'new1@example.test', 'role_id': role('Member')}, page=f'{w}/members')
M.send('invite bad email', 'POST', f'{w}/invitations', {'email': 'bad', 'role_id': role('Member')}, page=f'{w}/members')
M.send('invite as Owner role', 'POST', f'{w}/invitations', {'email': 'x2@example.test', 'role_id': role('Owner')}, page=f'{w}/members')
O.send('role.delete QA2', 'DELETE', f'{w}/roles/{qa}', {}, page=f'{w}/roles')

# --- projects
V.send('project.store by viewer', 'POST', f'{w}/projects', {'name': 'PV', 'status': 'active'}, page=f'{w}/projects')
A.send('project.store by member', 'POST', f'{w}/projects', {'name': 'PA', 'status': 'active'}, page=f'{w}/projects')
M.send('project.store by manager', 'POST', f'{w}/projects', {'name': 'Refactor Check', 'status': 'draft', 'description': 'd'}, page=f'{w}/projects/create')
NP = name(one(f"select id from projects where workspace_id='{W}' and name='Refactor Check'"), 'NP')
np_ = f'{w}/projects/{NP}'
M.send('project.update', 'PATCH', np_, {'expected_revision': 1, 'name': 'Refactor Check 2', 'status': 'draft'}, page=f'{np_}/edit')
M.send('project.update stale', 'PATCH', np_, {'expected_revision': 1, 'name': 'stale', 'status': 'draft'}, page=f'{np_}/edit')
M.send('project.member add demo3', 'POST', f'{np_}/members', {'workspace_member_id': wm('demo3@example.test'), 'role_id': role('Member'), 'status': 'active'}, page=f'{np_}/members')
A.send('project.member add by member', 'POST', f'{p1}/members', {'workspace_member_id': wm('demo4@example.test'), 'role_id': role('Member'), 'status': 'active'}, page=p1)
M.send('project.archive', 'POST', f'{np_}/archive', {}, page=np_)
M.send('project.restore', 'POST', f'{np_}/restore', {}, page=np_)
A.send('project.delete by member', 'DELETE', np_, {}, page=f'{w}/projects')
M.send('project.delete by manager', 'DELETE', np_, {}, page=f'{w}/projects')

# --- tasks
A.send('task.store by member', 'POST', f'{p1}/tasks', {'title': 'Task by member', 'priority': 'high'}, page=f'{p1}/tasks/create')
V.send('task.store by viewer', 'POST', f'{p1}/tasks', {'title': 'by viewer', 'priority': 'low'}, page=f'{p1}/tasks')
X.send('task.store by outsider', 'POST', f'{p1}/tasks', {'title': 'by outsider', 'priority': 'low'}, page='/workspaces')
A.send('task.store invalid', 'POST', f'{p1}/tasks', {'title': '', 'priority': 'nope'}, page=f'{p1}/tasks/create')
NT = name(one(f"select id from tasks where workspace_id='{W}' and title='Task by member'"), 'NT')
A.send('task.update', 'PATCH', f'{p1}/tasks/{NT}', {'expected_revision': 1, 'title': 'Task by member edited', 'priority': 'high'}, page=f'{p1}/tasks/{NT}/edit')
A.send('task.update stale -> conflict', 'PATCH', f'{p1}/tasks/{NT}', {'expected_revision': 1, 'title': 'stale', 'priority': 'low'}, page=f'{p1}/tasks/{NT}/edit')
V.send('task.update by viewer', 'PATCH', f'{p1}/tasks/{NT}', {'expected_revision': 2, 'title': 'v', 'priority': 'low'}, page=f'{p1}/tasks/{NT}')
A.send('task.assign by member', 'PATCH', f'{p1}/tasks/{NT}', {'expected_revision': 2, 'title': 'Task by member edited', 'priority': 'high', 'assignee_id': uid('demo4@example.test')}, page=f'{p1}/tasks/{NT}/edit')
M.send('task.assign by manager', 'PATCH', f'{p1}/tasks/{NT}', {'expected_revision': 2, 'title': 'Task by member edited', 'priority': 'high', 'assignee_id': uid('demo4@example.test'), 'reviewer_id': uid('demo2@example.test')}, page=f'{p1}/tasks/{NT}/edit')
A.send('status 002 IN_PROGRESS->REVIEW', 'PATCH', f'{p1}/tasks/{task("DEMO-002")}/status', {'expected_status': 'IN_PROGRESS', 'status': 'REVIEW'}, page=f'{p1}/tasks/{task("DEMO-002")}')
A.send('status 006 stale expected', 'PATCH', f'{p1}/tasks/{task("DEMO-006")}/status', {'expected_status': 'TODO', 'status': 'REVIEW'}, page=f'{p1}/tasks/{task("DEMO-006")}')
V.send('status 010 by viewer', 'PATCH', f'{p1}/tasks/{task("DEMO-010")}/status', {'expected_status': 'IN_PROGRESS', 'status': 'REVIEW'}, page=f'{p1}/tasks/{task("DEMO-010")}')
B.send('status 003 approve by non-reviewer', 'PATCH', f'{p1}/tasks/{task("DEMO-003")}/status', {'expected_status': 'REVIEW', 'status': 'DONE'}, page=f'{p1}/tasks/{task("DEMO-003")}')
M.send('status 003 approve by reviewer', 'PATCH', f'{p1}/tasks/{task("DEMO-003")}/status', {'expected_status': 'REVIEW', 'status': 'DONE'}, page=f'{p1}/tasks/{task("DEMO-003")}')
M.send('status 004 reopen without reason', 'PATCH', f'{p1}/tasks/{task("DEMO-004")}/status', {'expected_status': 'DONE', 'status': 'TODO'}, page=f'{p1}/tasks/{task("DEMO-004")}')
t6 = f'{p1}/tasks/{task("DEMO-006")}'
A.send('comment.store', 'POST', f'{t6}/comments', {'content': 'hello', 'mentions': [uid('demo2@example.test')]}, page=t6)
V.send('comment.store by viewer', 'POST', f'{t6}/comments', {'content': 'viewer'}, page=t6)
A.send('comment.store mention outsider', 'POST', f'{t6}/comments', {'content': 'x', 'mentions': [uid('dev6@example.test')]}, page=t6)
C = name(one(f"select id from task_activities where type='COMMENT' and content='hello'"), 'comment')
A.send('comment.update by author', 'PATCH', f'{t6}/comments/{C}', {'content': 'hello edited'}, page=t6)
B.send('comment.delete by other member', 'DELETE', f'{t6}/comments/{C}', {}, page=t6)
M.send('comment.delete by manager', 'DELETE', f'{t6}/comments/{C}', {}, page=t6)
A.send('blocker.store', 'POST', f'{t6}/blockers', {'reason': 'waiting api'}, page=t6)
BL = name(one(f"select id from task_blockers where reason='waiting api'"), 'blocker')
V.send('blocker.update by viewer', 'PATCH', f'{t6}/blockers/{BL}', {'status': 'resolved'}, page=t6)
M.send('blocker.resolve by manager', 'PATCH', f'{t6}/blockers/{BL}', {'status': 'resolved', 'reason': 'done'}, page=t6)
t9 = f'{p1}/tasks/{task("DEMO-009")}'
A.send('dependency.store by member', 'POST', f'{t9}/dependencies', {'predecessor_task_id': task('DEMO-004'), 'lag_minutes': 0}, page=t9)
M.send('dependency.store by manager', 'POST', f'{t9}/dependencies', {'predecessor_task_id': task('DEMO-004'), 'lag_minutes': 0}, page=t9)
M.send('dependency.store duplicate', 'POST', f'{t9}/dependencies', {'predecessor_task_id': task('DEMO-004'), 'lag_minutes': 0}, page=t9)
D = name(one(f"select id from task_dependencies where successor_task_id='{task('DEMO-009')}'"), 'dep')
A.send('dependency.remove by member', 'DELETE', f'{t9}/dependencies/{D}', {}, page=t9)
M.send('dependency.remove by manager', 'DELETE', f'{t9}/dependencies/{D}', {}, page=t9)
t13 = f'{p1}/tasks/{task("DEMO-013")}'
A.send('task.archive by member', 'POST', f'{t13}/archive', {}, page=t13)
M.send('task.archive by manager', 'POST', f'{t13}/archive', {}, page=t13)
M.send('task.restore by manager', 'POST', f'{t13}/restore', {}, page=t13)
M.send('tasks.bulk priority', 'POST', f'{p1}/tasks/bulk', {'action': 'priority', 'priority': 'urgent', 'items': {task('DEMO-017'): task_rev('DEMO-017'), task('DEMO-021'): task_rev('DEMO-021')}}, page=f'{p1}/tasks')
V.send('tasks.bulk by viewer', 'POST', f'{p1}/tasks/bulk', {'action': 'priority', 'priority': 'low', 'items': {task('DEMO-025'): 1}}, page=f'{p1}/tasks')
M.send('tasks.bulk stale revision', 'POST', f'{p1}/tasks/bulk', {'action': 'priority', 'priority': 'low', 'items': {task('DEMO-017'): 1}}, page=f'{p1}/tasks')
A.send('filter.store', 'POST', f'{p1}/task-filters', {'name': 'Mine', 'query': {'preset': 'mine'}}, page=f'{p1}/tasks')
F = name(one("select id from saved_task_filters where name='Mine'"), 'filter')
A.send('filter.open', 'GET', f'{p1}/task-filters/{F}/open')
B.send('filter.open by other user', 'GET', f'{p1}/task-filters/{F}/open')
A.send('filter.delete', 'DELETE', f'{p1}/task-filters/{F}', {}, page=f'{p1}/tasks')
M.send('task.delete with history', 'DELETE', f'{p1}/tasks/{NT}', {}, page=f'{p1}/tasks/{NT}')
M.send('watcher.add demo3', 'POST', f'{w}/collaboration/tasks/{task("DEMO-006")}/watchers', {'user_id': uid('demo3@example.test')}, page=t6)
V.send('watcher.add by viewer', 'POST', f'{w}/collaboration/tasks/{task("DEMO-006")}/watchers', {'user_id': uid('demo4@example.test')}, page=t6)

# --- calendar
ev = {'title': 'Focus', 'event_type': 'MEETING', 'status': 'planned', 'visibility': 'project', 'timezone': 'Asia/Ho_Chi_Minh', 'all_day': 0, 'is_busy': 1, 'starts_at': '2026-11-10T09:00', 'ends_at': '2026-11-10T10:00', 'project_id': P1}
A.send('calendar.store by member', 'POST', f'{w}/calendar', ev, page=f'{w}/calendar/create')
V.send('calendar.store by viewer', 'POST', f'{w}/calendar', ev, page=f'{w}/calendar')
A.send('calendar.store invalid range', 'POST', f'{w}/calendar', {**ev, 'ends_at': '2026-11-10T08:00'}, page=f'{w}/calendar/create')
E = name(one("select id from calendar_events where title='Focus'"), 'event')
st, body = A.refresh(f'{w}/calendar/{E}/edit')
ver = re.search(r'name="version" value="([0-9a-f]{64})"', body)
A.send('calendar.update', 'PATCH', f'{w}/calendar/{E}', {**ev, 'title': 'Focus 2', 'version': ver.group(1) if ver else ''}, page=f'{w}/calendar/{E}/edit')
B.send('calendar.delete by other', 'DELETE', f'{w}/calendar/{E}', {'version': 'x' * 64}, page=f'{w}/calendar')
st, body = A.refresh(f'{w}/calendar/{E}')
ver = re.search(r'name="version" value="([0-9a-f]{64})"', body)
A.send('calendar.delete by owner', 'DELETE', f'{w}/calendar/{E}', {'version': ver.group(1) if ver else ''}, page=f'{w}/calendar/{E}')

# --- plans, reports, ai
payload = json.dumps({'schema_version': 1, 'tasks': [{'ref': 'w1', 'title': 'Plan task', 'task_type': 'WORK', 'priority': 'medium', 'estimate_minutes': 60}], 'dependencies': [], 'slots': []})
A.send('plan.store by member', 'POST', f'{w}/plans?project_id={P1}', {'name': 'Plan A', 'input_mode': 'json', 'json_payload': payload}, page=f'{w}/plans/create?project_id={P1}')
V.send('plan.store by viewer', 'POST', f'{w}/plans?project_id={P1}', {'name': 'Plan V', 'input_mode': 'json', 'json_payload': payload}, page=f'{w}/plans?project_id={P1}')
A.send('plan.store bad json', 'POST', f'{w}/plans?project_id={P1}', {'name': 'Plan B', 'input_mode': 'json', 'json_payload': '{nope'}, page=f'{w}/plans/create?project_id={P1}')
PL = name(one("select id from plans where name='Plan A'"), 'planA')
A.send('plan.propose by member', 'POST', f'{w}/plans/{PL}/propose', {'expected_status': 'draft'}, page=f'{w}/plans/{PL}')
M.send('plan.propose by manager', 'POST', f'{w}/plans/{PL}/propose', {'expected_status': 'draft'}, page=f'{w}/plans/{PL}')
M.send('plan.approve by manager', 'POST', f'{w}/plans/{PL}/approve', {'expected_status': 'proposed'}, page=f'{w}/plans/{PL}')
M.send('plan.preview', 'GET', f'{w}/plans/{PL}/preview')
A.send('plan.preview by member', 'GET', f'{w}/plans/{PL}/preview')
M.send('plan.schedule', 'POST', f'{w}/plans/{PL}/schedule', {'from': '2026-11-09'}, page=f'{w}/plans/{PL}')
M.send('report.store by manager', 'POST', f'{w}/reports', {'from': '2026-11-01', 'to': '2026-12-01', 'project_id': P1}, page=f'{w}/dashboard?project_id={P1}')
A.send('report.store by member', 'POST', f'{w}/reports', {'from': '2026-11-01', 'to': '2026-12-01', 'project_id': P1}, page=f'{w}/dashboard?project_id={P1}')
M.send('report.store bad range', 'POST', f'{w}/reports', {'from': '2026-12-01', 'to': '2026-11-01'}, page=f'{w}/dashboard')
M.send('ai-run.store (AI disabled)', 'POST', f'{w}/ai-runs', {'from': '2026-11-01', 'to': '2026-12-01', 'project_id': P1, 'user_prompt': 'summary', 'idempotency_token': '11111111-1111-4111-8111-111111111111'}, page=f'{w}/ai-runs/create?project_id={P1}')
A.send('planning-ai.store', 'POST', f'{w}/planning-ai', {'project_id': P1, 'operation': 'planning', 'user_prompt': 'plan it', 'idempotency_token': '22222222-2222-4222-8222-222222222222'}, page=f'{w}/planning-ai/create?project_id={P1}')

# --- time, templates, integrations, reminders, personal, account
A.send('timer.start on own IN_PROGRESS', 'POST', f'{w}/timer/{task("DEMO-010")}', {}, page='/timers')
B.send('timer.start on others task', 'POST', f'{w}/timer/{task("DEMO-014")}', {}, page='/timers')
TE = name(one(f"select id from time_entries where task_id='{task('DEMO-010')}' and ended_at is null"), 'entry')
B.send('timer.stop by other user', 'POST', f'/timers/{TE}/stop', {}, page='/timers')
__import__('time').sleep(1.2)  # entries are second-precision; stop must be after start
A.send('timer.stop by owner', 'POST', f'/timers/{TE}/stop', {}, page='/timers')
A.send('timer.adjust page', 'GET', f'/timers/{TE}/adjust')
B.send('timer.adjust page by other', 'GET', f'/timers/{TE}/adjust')
M.send('template.store', 'POST', f'{w}/task-templates', {'project_id': P1, 'name': 'Tpl', 'interval': 1, 'timezone': 'Asia/Ho_Chi_Minh', 'task_ids': [task('DEMO-001')]}, page=f'{w}/task-templates?project_id={P1}')
A.send('template.store by member', 'POST', f'{w}/task-templates', {'project_id': P1, 'name': 'Tpl2', 'interval': 1, 'timezone': 'Asia/Ho_Chi_Minh', 'task_ids': [task('DEMO-001')]}, page=f'{w}/task-templates?project_id={P1}')
O.send('github.save by owner', 'POST', f'{p1}/github', {'repository_id': '123', 'enabled': 0}, page=f'{p1}/github')
O.send('github.enable without secret', 'POST', f'{p1}/github', {'repository_id': '123', 'enabled': 1}, page=f'{p1}/github')
A.send('github.save by member', 'POST', f'{p1}/github', {'repository_id': '123', 'enabled': 0}, page=p1)
A.send('reminders.scan', 'POST', f'{w}/reminders/scan', {}, page=f'{w}/reminders')
O.send('personal task.store', 'POST', f'/workspaces/{PW}/tasks', {'title': 'Personal one', 'priority': 'low'}, page=f'/workspaces/{PW}/tasks')
M.send('personal task.store by non-owner', 'POST', f'/workspaces/{PW}/tasks', {'title': 'nope', 'priority': 'low'}, page='/workspaces')
O.send('workspace.store', 'POST', '/workspaces', {'name': 'New WS', 'timezone': 'Asia/Ho_Chi_Minh'}, page='/workspaces/create')
O.send('notifications.read-all', 'POST', '/notifications/read-all', {}, page='/notifications')
M.send('profile.update', 'PATCH', '/profile', {'name': 'Manager Renamed', 'email': 'demo2@example.test', 'timezone': 'Asia/Ho_Chi_Minh'}, page='/profile')
B.send('workspace.leave', 'POST', f'{w}/leave', {}, page=w)
B.send('after leave: show', 'GET', w)
O.send('workspace.delete non-empty', 'DELETE', w, {}, page=w)


def fingerprint():
    q = {
        'workspaces': "select name, timezone, kind from workspaces order by name",
        'roles': "select w.name, r.name, r.permissions, r.is_system from roles r join workspaces w on w.id=r.workspace_id order by 1,2",
        'workspace_members': "select w.name, u.email, r.name, m.status from workspace_members m join workspaces w on w.id=m.workspace_id join users u on u.id=m.user_id join roles r on r.id=m.role_id order by 1,2",
        'projects': "select w.name, p.name, p.status, p.archived_at is not null, p.revision from projects p join workspaces w on w.id=p.workspace_id order by 1,2",
        'project_members': "select p.name, u.email, r.name, pm.status from project_members pm join projects p on p.id=pm.project_id join workspace_members wm on wm.id=pm.workspace_member_id join users u on u.id=wm.user_id join roles r on r.id=pm.role_id order by 1,2",
        'tasks': "select coalesce(t.code like 'T-%', 0), case when t.code like 'T-%' then '' else t.code end, t.title, t.status, t.priority, t.task_type, coalesce(a.email,''), coalesce(r.email,''), t.archived_at is not null, t.revision from tasks t left join users a on a.id=t.assignee_id left join users r on r.id=t.reviewer_id order by 2,3",
        'task_activities': "select type, coalesce(event_type,''), coalesce(content,''), deleted_at is not null, count(*) from task_activities group by 1,2,3,4 order by 1,2,3",
        'task_blockers': "select reason, status from task_blockers order by 1",
        'task_dependencies': "select count(*) from task_dependencies",
        'task_watchers': "select count(*) from task_watchers",
        'saved_task_filters': "select name from saved_task_filters",
        'calendar_events': "select title, status, event_type, visibility, source from calendar_events order by 1,2",
        'plans': "select name, status, version_no, is_active from plans order by 1,3",
        'report_snapshots': "select count(*) from report_snapshots",
        'ai_runs': "select operation, status from ai_runs order by 1,2",
        'time_entries': "select ended_at is not null, count(*) from time_entries group by 1",
        'task_templates': "select name, status from task_templates",
        'integration_connections': "select provider, status, account_ref from integration_connections",
        'notifications': "select type, read_at is not null, count(*) from notifications group by 1,2 order by 1,2",
        'task_reminders': "select kind, count(*) from task_reminders group by 1",
        'users': "select email, name, timezone, status from users order by 1",
        'jobs': "select queue, count(*) from jobs group by 1",
    }
    return {k: sql(v) for k, v in q.items()}


json.dump({'log': LOG, 'db': fingerprint()}, open(OUT, 'w'), ensure_ascii=False, indent=1)
