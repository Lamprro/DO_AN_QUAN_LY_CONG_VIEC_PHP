"""Real HTTP crawl of every GET route, as each seeded user. Output: one JSON line per request.

usage: python3 -I crawl.py <base_url> <routes.json> <out.jsonl>
"""
import hashlib, html, http.cookiejar, json, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request

BASE, ROUTES, OUT = sys.argv[1], sys.argv[2], sys.argv[3]
USERS = ['demo1@example.test', 'demo2@example.test', 'demo3@example.test', 'demo4@example.test', 'demo5@example.test', 'dev6@example.test']


def sql(q):
    r = subprocess.run(['docker', 'exec', 'wm-mysql', 'mysql', '-uroot', '-preview', '--default-character-set=utf8mb4', 'wm_review', '-N', '-B', '-e', q], capture_output=True, text=True, encoding='utf-8', errors='replace')
    return [line.split('\t') for line in r.stdout.strip().split('\n') if line]


def one(q):
    rows = sql(q)
    return rows[0][0] if rows else None


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def session():
    jar = http.cookiejar.CookieJar()
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), NoRedirect())


def call(op, method, path, data=None):
    body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    req = urllib.request.Request(BASE + path, data=body, method=method, headers={'Accept': 'text/html'})
    t = time.time()
    try:
        r = op.open(req, timeout=60)
        return r.status, r.headers.get('Location'), r.read().decode('utf-8', 'replace'), time.time() - t
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get('Location'), e.read().decode('utf-8', 'replace'), time.time() - t


def token(body):
    m = re.search(r'name="_token" value="([^"]+)"', body) or re.search(r'name="csrf-token" content="([^"]+)"', body)
    return m.group(1) if m else None


def login(email):
    op = session()
    _, _, body, _ = call(op, 'GET', '/login')
    st, loc, _, _ = call(op, 'POST', '/login', {'_token': token(body), 'email': email, 'password': 'Demo@123456'})
    assert st == 302 and 'login' not in (loc or ''), (email, st, loc)
    return op


def fingerprint(body):
    b = html.unescape(body)
    forms = sorted(set(re.findall(r'<form[^>]*action="([^"]*)"', b)))
    methods = sorted(set(re.findall(r'name="_method" value="([A-Z]+)"', b)))
    links = sorted(set(h for h in re.findall(r'href="([^"#]*)"', b) if '/workspaces' in h or h.startswith('/')))
    title = re.search(r'<title>(.*?)</title>', b, re.S)
    h1 = re.findall(r'<h1[^>]*>(.*?)</h1>', b, re.S)
    norm = lambda u: re.sub(r'^https?://[^/]+', '', u)
    return {
        'title': (title.group(1).strip() if title else None),
        'h1': [re.sub(r'\s+', ' ', re.sub('<[^>]+>', '', x)).strip() for x in h1],
        'forms': [norm(f) for f in forms], 'methods': methods,
        'links_hash': hashlib.sha1('\n'.join(norm(l) for l in links).encode()).hexdigest()[:12], 'links_n': len(links),
        'buttons_n': len(re.findall(r'<button', b)), 'disabled_n': len(re.findall(r'\bdisabled\b', b)),
        'error_text': 'Whoops' in b or 'Server Error' in b or 'Exception' in b[:3000],
    }


def contexts():
    """Real IDs per workspace: ws -> dict(param -> [ids])."""
    out = []
    for name in ['Demo Team A', 'Demo Personal', 'AlphaTech Enterprise', 'Demo Team B']:
        ws = one(f"select id from workspaces where name='{name}'")
        projects = [r[0] for r in sql(f"select id from projects where workspace_id='{ws}' order by name limit 2")]
        p = projects[0] if projects else None
        ctx = {'workspace': ws, 'project': p,
               'task': one(f"select id from tasks where workspace_id='{ws}' and " + (f"project_id='{p}'" if p else 'project_id is null') + " and task_type='WORK' order by code limit 1"),
               'group_task': one(f"select id from tasks where workspace_id='{ws}' and " + (f"project_id='{p}'" if p else 'project_id is null') + " and task_type='GROUP' order by code limit 1"),
               'personal_task': one(f"select id from tasks where workspace_id='{ws}' and project_id is null order by code limit 1"),
               'event': one(f"select id from calendar_events where workspace_id='{ws}' order by starts_at limit 1"),
               'plan': one(f"select id from plans where workspace_id='{ws}' limit 1"),
               'snapshot': one(f"select id from report_snapshots where workspace_id='{ws}' limit 1"),
               'run': one(f"select id from ai_runs where workspace_id='{ws}' limit 1"),
               'template': one(f"select id from task_templates where workspace_id='{ws}' limit 1"),
               'role': one(f"select id from roles where workspace_id='{ws}' and is_system=0 limit 1") or one(f"select id from roles where workspace_id='{ws}' and name='Member' limit 1"),
               'activity': one(f"select a.id from task_activities a join tasks t on t.id=a.task_id where t.workspace_id='{ws}' and a.type='ATTACHMENT' limit 1"),
               'projects': projects}
        out.append((name, ctx))
    return out


def urls(routes, ctxs):
    seen = []
    for name, ctx in ctxs:
        for r in routes:
            if 'GET' not in r['method'] or not r['uri'].startswith('workspaces/{workspace}'):
                continue
            uri = r['uri']
            params = re.findall(r'\{(\w+)\??\}', uri)
            values = {}
            ok = True
            for prm in params:
                key = prm
                if prm == 'task' and '/projects/' not in uri:
                    key = 'personal_task'
                v = ctx.get(key)
                if not v:
                    ok = False
                    break
                values[prm] = v
            if not ok:
                continue
            path = '/' + re.sub(r'\{(\w+)\??\}', lambda m: values[m.group(1)], uri)
            variants = ['']
            if uri.endswith('/tasks'):
                variants += ['?view=board', '?preset=mine', '?preset=review', '?preset=blocked&sort=priority&direction=asc', '?q=a&archived=all']
            if any(uri.endswith(s) for s in ['/calendar', '/team-calendar', '/plans', '/plan-impact', '/dashboard', '/task-templates', '/reports', '/ai-runs/create', '/planning-ai/create', '/plans/create']):
                variants += ['?project_id=' + p for p in ctx['projects']]
            if uri.endswith('/tasks/{task}') or uri.endswith('/tasks/{task}/edit') or uri.endswith('/tasks/{task}/status') or uri.endswith('/tasks/create'):
                variants += ['?panel=1']
            if uri.endswith('/tasks/{task}'):
                variants += ['?activity_type=COMMENT']
            for v in variants:
                seen.append((name, r['name'], path + v))
            if uri.endswith('/tasks/{task}') and '/projects/' in uri and ctx.get('group_task'):
                seen.append((name, r['name'] + '#group', path.replace(values['task'], ctx['group_task'])))
    return seen


def main():
    routes = json.load(open(ROUTES))
    ctxs = contexts()
    targets = urls(routes, ctxs)
    globals_ = [('-', r['name'], '/' + r['uri'].lstrip('/')) for r in routes if 'GET' in r['method'] and '{' not in r['uri'] and r['middleware'] and 'auth' in r['middleware'] and not r['uri'].startswith('workspaces/{')]
    with open(OUT, 'w') as f:
        for email in USERS:
            op = login(email)
            for ws, rname, path in globals_ + targets:
                st, loc, body, dt = call(op, 'GET', path)
                row = {'user': email, 'ws': ws, 'route': rname, 'path': path, 'status': st, 'location': re.sub(r'^https?://[^/]+', '', loc or '') or None, 'ms': int(dt * 1000)}
                if st == 200 and '<html' in body.lower():
                    row.update(fingerprint(body))
                elif st >= 400:
                    m = re.search(r'<title>(.*?)</title>', body, re.S)
                    row['error_title'] = m.group(1).strip() if m else None
                f.write(json.dumps(row, ensure_ascii=False) + '\n')
            print(email, 'done', file=sys.stderr)


main()
