from pathlib import Path
R=Path(r'D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP')
# Reuse the existing isolated installation walkthrough; source copy/new temporary
# DB only, no role/membership mutation against the running database.
s=(R/'docs/verification/final/release-install.py').read_text(encoding='utf-8').replace("OUT=R/'docs/verification/final'","OUT=R/'docs/verification/p0'")
exec(s)
