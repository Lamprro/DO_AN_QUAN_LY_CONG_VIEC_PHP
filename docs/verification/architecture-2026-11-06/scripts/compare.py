import json, sys, collections
b = {(r['user'], r['path']): r for r in map(json.loads, open(sys.argv[1]))}
a = {(r['user'], r['path']): r for r in map(json.loads, open(sys.argv[2]))}
keys = ['status', 'location', 'title', 'h1', 'forms', 'methods', 'links_hash', 'links_n', 'buttons_n', 'disabled_n', 'error_text', 'error_title']
only_b = set(b) - set(a); only_a = set(a) - set(b)
print('only before:', collections.Counter(b[k]['route'] for k in only_b))
print('only after :', collections.Counter(a[k]['route'] for k in only_a))
diffs = collections.defaultdict(list)
for k in sorted(set(a) & set(b)):
    d = {f: (b[k].get(f), a[k].get(f)) for f in keys if b[k].get(f) != a[k].get(f)}
    if d:
        diffs[a[k]['route']].append((k, d))
print('compared', len(set(a) & set(b)), 'identical', len(set(a) & set(b)) - sum(map(len, diffs.values())))
for route, items in diffs.items():
    print('##', route, len(items))
    for k, d in items[:3]:
        print('   ', k[0], k[1][-70:], d)
tb = sum(r['ms'] for r in b.values() if (r['user'], r['path']) in a) ; ta = sum(r['ms'] for r in a.values() if (r['user'], r['path']) in b)
print('total ms before %d after %d' % (tb, ta))
