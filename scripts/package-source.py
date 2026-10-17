"""Build source delivery only. Never includes live .env/database/private storage/vendor."""
from pathlib import Path
import zipfile,json,hashlib,datetime,re
root=Path(__file__).resolve().parent.parent
dist=root/'dist';dist.mkdir(exist_ok=True)
folders=['app','bootstrap','config','database','docs','lang','public','resources','routes','scripts','storage']
selected=[]
def allowed(path):
    rel=path.relative_to(root);parts=rel.parts
    if path.is_symlink() or not path.is_file():return False
    if '__pycache__' in parts or '.git' in parts or 'node_modules' in parts:return False
    if any(p.startswith('.env') and p!='.env.example' for p in parts):return False
    if any(p in ['private','sessions','logs','cache','views'] for p in parts) and parts[0]=='storage' and path.name!='.gitignore':return False
    if parts[0]=='storage' and path.name!='.gitignore':return False
    if parts[:2]==('bootstrap','cache') and path.name!='.gitignore':return False
    if parts[:2]==('public','storage'):return False
    if any('.sqlite' in p.lower() for p in parts) or path.suffix.lower() in ['.log','.key','.cookies','.pyc','.db']:return False
    return True
for name in folders:
    if (root/name).exists():selected.extend(p for p in (root/name).rglob('*') if allowed(p))
for p in root.iterdir():
    if p.is_file() and (not p.name.startswith('.') or p.name in ['.env.example','.gitignore','.editorconfig','.gitattributes']) and allowed(p):selected.append(p)
selected=sorted(set(selected),key=lambda p:p.relative_to(root).as_posix())
manifest={'generated_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'kind':'source-only','dependencies':'composer install required; vendor excluded','files':{p.relative_to(root).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in selected}}
archive=dist/'work-management-mvc-source.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED) as z:
    for p in selected:z.write(p,p.relative_to(root).as_posix())
    z.writestr('SOURCE_MANIFEST.json',json.dumps(manifest,ensure_ascii=False,indent=2))
# Verify the artifact actually contains the expected bytes and no live key.
with zipfile.ZipFile(archive) as z:
    for name,digest in manifest['files'].items():
        if hashlib.sha256(z.read(name)).hexdigest()!=digest:raise RuntimeError('Manifest mismatch: '+name)
    if (root/'.env').exists():
        match=re.search(r'^APP_KEY=(.+)$',(root/'.env').read_text(encoding='utf-8'),re.M)
        if match and len(match[1])>20 and any(match[1].encode() in z.read(name) for name in z.namelist()):raise RuntimeError('Live key detected; artifact rejected.')
manifest['archive_sha256']=hashlib.sha256(archive.read_bytes()).hexdigest();manifest['archive_bytes']=archive.stat().st_size
(dist/'source-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'archive':str(archive),'files':len(selected),'bytes':archive.stat().st_size,'sha256':manifest['archive_sha256'],'verified':True}))
