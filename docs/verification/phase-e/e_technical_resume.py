from pathlib import Path
s=(Path(__file__).parent/'e_technical.py').read_text(encoding='utf-8')
exec(s.split('files=subprocess.check_output')[0])
report=json.loads((O/'technical.json').read_text(encoding='utf-8'))
record('resume SQL observation helper',reason='Corrected table name blockers to task_blockers; no application error or database mutation.')
exec(s[s.index('con=sqlite3.connect'):])
