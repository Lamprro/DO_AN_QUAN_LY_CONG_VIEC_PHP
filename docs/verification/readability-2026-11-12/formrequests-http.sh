#!/usr/bin/env bash
# Real HTTP checks for the three forms whose validation moved to FormRequests.
BASE=http://127.0.0.1:8199
DIR=$(dirname "$0")/http2; rm -rf "$DIR"; mkdir -p "$DIR"
DB="$(dirname "$0")/jira_board_review_20261101.copy.sqlite"
WS=01a197e6-92df-7293-8b36-4d78f76bb25b; PROJECT=01a197e6-92f6-7026-a356-a2b5df0a3b6c
DEMO3=01a197e6-9116-70a0-a723-4ff85fed9349
pass=0; fail=0
ok() { if [ "$2" = "1" ]; then pass=$((pass+1)); echo "[PASS] $1"; else fail=$((fail+1)); echo "[FAIL] $1 $3"; fi; }
q() { python -c "import sqlite3,sys;print(sqlite3.connect(sys.argv[1]).execute(sys.argv[2]).fetchone()[0])" "$DB" "$1"; }
login() { curl -s -c "$2" -b "$2" "$BASE/login" -o "$DIR/login.html"; local t; t=$(grep -o 'name="_token" value="[^"]*"' "$DIR/login.html" | head -1 | sed 's/.*value="//;s/"//'); curl -s -c "$2" -b "$2" -o /dev/null -w '%{http_code}' -X POST "$BASE/login" --data-urlencode "_token=$t" --data-urlencode "email=$1" --data-urlencode "password=Demo@123456"; }
token() { curl -s -c "$1" -b "$1" "$2" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
# post JAR URL REFERER args... -> prints "code|location"
post() { local jar=$1 url=$2 ref=$3; shift 3; curl -s -c "$jar" -b "$jar" -o "$DIR/last.html" -w '%{http_code}|%{redirect_url}' -X POST -H "Referer: $ref" "$url" "$@"; }
errors() { curl -s -c "$1" -b "$1" "$2" -o "$DIR/err.html"; grep -c 'id="error-summary"\|class="error\|is-invalid\|<li>' "$DIR/err.html"; }

J1="$DIR/d1.jar"; J2="$DIR/d2.jar"
c=$(login demo1@example.test "$J1"); ok "demo1 login" "$([ "$c" = 302 ] && echo 1)" "$c"

# ---- Task template: store
TPL_URL="$BASE/workspaces/$WS/task-templates?project_id=$PROJECT"
T=$(token "$J1" "$TPL_URL")
TASK=$(q "select id from tasks where project_id='$PROJECT' and task_type='WORK' and parent_task_id is null and archived_at is null order by code limit 1")
before=$(q "select count(*) from task_templates")
r=$(post "$J1" "$BASE/workspaces/$WS/task-templates" "$TPL_URL" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "name=FR check template" --data-urlencode "frequency=" --data-urlencode "interval=1" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "task_ids[]=$TASK")
after=$(q "select count(*) from task_templates")
ok "template store (valid) → 302 to show, +1 row" "$([ "${r%%|*}" = 302 ] && [[ "${r#*|}" == */task-templates/* ]] && [ $after -eq $((before+1)) ] && echo 1)" "$r rows $before→$after"
TPL=$(q "select id from task_templates where name='FR check template'")
r=$(post "$J1" "$BASE/workspaces/$WS/task-templates" "$TPL_URL" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "name=" --data-urlencode "interval=99" --data-urlencode "timezone=Asia/Ho_Chi_Minh")
after2=$(q "select count(*) from task_templates")
ok "template store (invalid: no name, interval 99, no task_ids) → 302 back, no new row" "$([ "${r%%|*}" = 302 ] && [ "${r#*|}" = "$TPL_URL" ] && [ $after2 -eq $after ] && echo 1)" "$r"
r=$(post "$J1" "$BASE/workspaces/$WS/task-templates" "$TPL_URL" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "name=Prohibited revision" --data-urlencode "interval=1" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "task_ids[]=$TASK" --data-urlencode "revision=1")
ok "template store with prohibited 'revision' → rejected" "$([ "${r%%|*}" = 302 ] && [ "${r#*|}" = "$TPL_URL" ] && [ "$(q "select count(*) from task_templates where name='Prohibited revision'")" = 0 ] && echo 1)" "$r"

# ---- Task template: update (PUT with revision)
SHOW="$BASE/workspaces/$WS/task-templates/$TPL"; T=$(token "$J1" "$SHOW")
r=$(post "$J1" "$SHOW" "$SHOW" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "revision=1" --data-urlencode "name=FR check template v2" --data-urlencode "frequency=weekly" --data-urlencode "interval=2" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "starts_on=$(date -d '+1 day' +%F 2>/dev/null || python -c 'import datetime;print((datetime.date.today()+datetime.timedelta(1)).isoformat())')" --data-urlencode "local_time=08:00")
ok "template update (valid) → 302, name/frequency/revision changed" "$([ "${r%%|*}" = 302 ] && [ "$(q "select name||'|'||frequency||'|'||revision||'|'||status from task_templates where id='$TPL'")" = "FR check template v2|weekly|2|active" ] && echo 1)" "$r $(q "select name||'|'||ifnull(frequency,'')||'|'||revision||'|'||status from task_templates where id='$TPL'")"
r=$(post "$J1" "$SHOW" "$SHOW" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "name=stale" --data-urlencode "interval=1" --data-urlencode "timezone=Asia/Ho_Chi_Minh")
ok "template update without revision → rejected by FormRequest, row unchanged" "$([ "${r%%|*}" = 302 ] && [ "$(q "select name from task_templates where id='$TPL'")" = "FR check template v2" ] && echo 1)" "$r"
r=$(post "$J1" "$SHOW" "$SHOW" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "revision=1" --data-urlencode "name=stale" --data-urlencode "interval=1" --data-urlencode "timezone=Asia/Ho_Chi_Minh")
ok "template update with stale revision → service rejects, row unchanged" "$([ "${r%%|*}" = 302 ] && [ "$(q "select name from task_templates where id='$TPL'")" = "FR check template v2" ] && echo 1)" "$r"

# ---- Work hours (PUT) with an empty window row that must be ignored
WH="$BASE/work-hours"; T=$(token "$J1" "$WH")
V=$(curl -s -c "$J1" -b "$J1" "$WH" | grep -o 'name="version" value="[^"]*"' | sed 's/.*value="//;s/"//')
r=$(post "$J1" "$WH" "$WH" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "version=$V" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "weekdays[]=1" --data-urlencode "weekdays[]=3" --data-urlencode "windows[0][start]=08:00" --data-urlencode "windows[0][end]=11:30" --data-urlencode "windows[1][start]=" --data-urlencode "windows[1][end]=" --data-urlencode "slot_minutes=15")
WHS=$(q "select json_extract(settings,'$.work_hours.slot_minutes')||'|'||json_array_length(json_extract(settings,'$.work_hours.windows'))||'|'||json_extract(settings,'$.work_hours.weekdays[1]') from users where email='demo1@example.test'")
ok "work-hours save: empty row dropped, 1 window, slot 15, weekdays [1,3]" "$([ "${r%%|*}" = 302 ] && [ "$WHS" = "15|1|3" ] && echo 1)" "$r $WHS"
r=$(post "$J1" "$WH" "$WH" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "version=$V" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "weekdays[]=1" --data-urlencode "windows[0][start]=09:00" --data-urlencode "windows[0][end]=10:00" --data-urlencode "slot_minutes=7")
ok "work-hours invalid slot_minutes=7 → rejected, settings unchanged" "$([ "${r%%|*}" = 302 ] && [ "$(q "select json_extract(settings,'$.work_hours.slot_minutes') from users where email='demo1@example.test'")" = 15 ] && echo 1)" "$r"
r=$(post "$J1" "$WH" "$WH" --data-urlencode "_token=$T" --data-urlencode "_method=PUT" --data-urlencode "version=$V" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "weekdays[]=1" --data-urlencode "windows[0][start]=09:00" --data-urlencode "windows[0][end]=10:00" --data-urlencode "slot_minutes=15")
ok "work-hours stale version → service rejects (version check still in service)" "$([ "${r%%|*}" = 302 ] && [ "$(q "select json_extract(settings,'$.work_hours.windows[0].start') from users where email='demo1@example.test'")" = "08:00" ] && echo 1)" "$r"

# ---- Team calendar proposal (demo2 → demo3)
c=$(login demo2@example.test "$J2"); ok "demo2 login" "$([ "$c" = 302 ] && echo 1)" "$c"
TEAM="$BASE/workspaces/$WS/team-calendar?project_id=$PROJECT"; T=$(token "$J2" "$TEAM")
DAY=$(python -c 'import datetime;print((datetime.date.today()+datetime.timedelta(25)).isoformat())')
before=$(q "select count(*) from calendar_events where proposed_by is not null")
r=$(post "$J2" "$BASE/workspaces/$WS/team-calendar" "$TEAM" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "recipient_id=$DEMO3" --data-urlencode "title=FR proposal" --data-urlencode "event_type=MEETING" --data-urlencode "visibility=project" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "starts_at=${DAY}T14:00" --data-urlencode "ends_at=${DAY}T15:00")
after=$(q "select count(*) from calendar_events where proposed_by is not null")
ok "proposal (valid) → 302, +1 proposed row owned by demo3" "$([ "${r%%|*}" = 302 ] && [ $after -eq $((before+1)) ] && [ "$(q "select status||'|'||user_id from calendar_events where title='FR proposal'")" = "proposed|$DEMO3" ] && echo 1)" "$r"
r=$(post "$J2" "$BASE/workspaces/$WS/team-calendar" "$TEAM" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "recipient_id=$DEMO3" --data-urlencode "title=" --data-urlencode "event_type=BUSY" --data-urlencode "visibility=project" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "starts_at=${DAY}T14:00" --data-urlencode "ends_at=${DAY}T15:00")
ok "proposal invalid (no title, event_type BUSY) → back, no row" "$([ "${r%%|*}" = 302 ] && [ "${r#*|}" = "$TEAM" ] && [ "$(q "select count(*) from calendar_events where proposed_by is not null")" = $after ] && echo 1)" "$r"
r=$(post "$J2" "$BASE/workspaces/$WS/team-calendar" "$TEAM" --data-urlencode "_token=$T" --data-urlencode "project_id=$PROJECT" --data-urlencode "recipient_id=$DEMO3" --data-urlencode "title=Prohibited status" --data-urlencode "event_type=MEETING" --data-urlencode "visibility=project" --data-urlencode "timezone=Asia/Ho_Chi_Minh" --data-urlencode "starts_at=${DAY}T16:00" --data-urlencode "ends_at=${DAY}T17:00" --data-urlencode "status=planned")
ok "proposal with prohibited 'status=planned' → rejected" "$([ "${r%%|*}" = 302 ] && [ "$(q "select count(*) from calendar_events where title='Prohibited status'")" = 0 ] && echo 1)" "$r"
c=$(curl -s -c "$J2" -b "$J2" -o "$DIR/team.html" -w '%{http_code}' "$TEAM"); ok "team page renders after posts (200) and lists FR proposal" "$([ "$c" = 200 ] && grep -q "FR proposal" "$DIR/team.html" && echo 1)" "$c"
grep -ci "exception\|error" "$(dirname "$0")/server2.log" | sed 's/^/server log error lines: /'
echo; echo "FORMS RESULT: $pass pass, $fail fail"
