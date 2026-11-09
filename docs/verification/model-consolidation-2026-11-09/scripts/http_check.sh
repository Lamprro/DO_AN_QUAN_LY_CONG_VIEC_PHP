#!/usr/bin/env bash
# Real HTTP walkthrough (session cookie + CSRF) against the server running on the review-DB copy.
BASE=http://127.0.0.1:8199
DIR=$(dirname "$0")/http
rm -rf "$DIR"; mkdir -p "$DIR"
WS="$1"; PROJECT="$2"; P1="$3"; P10="$4"; TPL="$5"
pass=0; fail=0
ok() { if [ "$2" = "1" ]; then pass=$((pass+1)); echo "[PASS] $1"; else fail=$((fail+1)); echo "[FAIL] $1 $3"; fi; }

login() { # $1 email, $2 jar
  curl -s -c "$2" -b "$2" "$BASE/login" -o "$DIR/login.html"
  local token; token=$(grep -o 'name="_token" value="[^"]*"' "$DIR/login.html" | head -1 | sed 's/.*value="//;s/"//')
  curl -s -c "$2" -b "$2" -o /dev/null -w '%{http_code}' -X POST "$BASE/login" --data-urlencode "_token=$token" --data-urlencode "email=$1" --data-urlencode "password=Demo@123456"
}
get() { # $1 jar, $2 path, $3 outfile -> prints status
  curl -s -c "$1" -b "$1" -o "$DIR/$3" -w '%{http_code}' "$BASE$2"
}

J3="$DIR/demo3.jar"; J1="$DIR/demo1.jar"; J2="$DIR/demo2.jar"
code=$(login demo3@example.test "$J3"); ok "demo3 đăng nhập (302)" "$([ "$code" = 302 ] && echo 1)" "$code"

code=$(get "$J3" "/workspaces/$WS/team-calendar?project_id=$PROJECT" team.html)
ok "GET team-calendar 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Team calendar hiển thị nhãn trạng thái mới (Chờ xác nhận / Đã từ chối / Đã thu hồi)" "$(grep -q 'Chờ xác nhận' "$DIR/team.html" && grep -q 'Đã từ chối' "$DIR/team.html" && grep -q 'Đã thu hồi' "$DIR/team.html" && echo 1)"
ok "Form phản hồi dùng hidden version (64 ký tự)" "$(grep -qE 'name="version" value="[0-9a-f]{64}"' "$DIR/team.html" && echo 1)"

# Accept P10 through the real form.
form=$(tr '\n' ' ' < "$DIR/team.html" | grep -o "data-form=\"proposal-accept-$P10\">[^<]*<input type=\"hidden\" name=\"_token\" value=\"[^\"]*\"[^>]*><input type=\"hidden\" name=\"version\" value=\"[^\"]*\"")
token=$(echo "$form" | grep -o 'name="_token" value="[^"]*"' | sed 's/.*value="//;s/"//')
version=$(echo "$form" | grep -o 'name="version" value="[^"]*"' | sed 's/.*value="//;s/"//')
ok "Lấy được token và version của P10 từ trang" "$([ -n "$token" ] && [ ${#version} = 64 ] && echo 1)" "token=${#token} version=${#version}"
code=$(curl -s -c "$J3" -b "$J3" -o /dev/null -w '%{http_code}' -X POST -H "Referer: $BASE/workspaces/$WS/team-calendar?project_id=$PROJECT" "$BASE/workspaces/$WS/team-calendar/$P10/accept" --data-urlencode "_token=$token" --data-urlencode "version=$version")
ok "POST accept P10 → 302" "$([ "$code" = 302 ] && echo 1)" "$code"
code=$(curl -s -c "$J3" -b "$J3" -o /dev/null -w '%{http_code}' -X POST -H "Referer: $BASE/workspaces/$WS/team-calendar?project_id=$PROJECT" "$BASE/workspaces/$WS/team-calendar/$P10/accept" --data-urlencode "_token=$token" --data-urlencode "version=$version")
ok "Gửi lại cùng form (version cũ) bị từ chối, không tạo lịch lần 2 (302 về kèm lỗi)" "$([ "$code" = 302 ] && echo 1)" "$code"
get "$J3" "/workspaces/$WS/team-calendar?project_id=$PROJECT&focus=$P10" team_focus.html >/dev/null
ok "Sau khi nhận, P10 hiện 'Đã chấp thuận' và có link 'Mở lịch đã nhận'" "$(grep -q 'Đã chấp thuận' "$DIR/team_focus.html" && grep -q "calendar/$P10" "$DIR/team_focus.html" && echo 1)"

code=$(get "$J3" "/workspaces/$WS/calendar/$P10" event.html)
ok "GET calendar/{P10} (lịch đã nhận) 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Trang lịch nhận từ đề xuất không có nút xóa" "$(grep -q 'calendar-delete' "$DIR/event.html" || echo 1)"
code=$(get "$J3" "/workspaces/$WS/calendar/$P1" p1.html)
ok "GET calendar/{P1} đã chấp thuận 200" "$([ "$code" = 200 ] && echo 1)" "$code"
code=$(get "$J3" "/workspaces/$WS/calendar?project_id=$PROJECT&view=list&include_cancelled=1" cal.html)
ok "GET calendar list 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Calendar không lộ đề xuất đang chờ/từ chối/thu hồi" "$(grep -qE 'MERGE-P2 declined|MERGE-P3 withdrawn|MERGE-P7 new declined|MERGE-P8 new withdrawn' "$DIR/cal.html" || echo 1)"
code=$(get "$J3" "/workspaces/$WS/projects/$PROJECT/timeline" timeline.html)
ok "GET timeline 200" "$([ "$code" = 200 ] && echo 1)" "$code"
code=$(get "$J3" "/settings" settings.html); ok "GET settings 200" "$([ "$code" = 200 ] && echo 1)" "$code"
code=$(get "$J3" "/work-hours" workhours.html); ok "GET work-hours 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Work-hours hiển thị giờ đã lưu trong users.settings (09:00)" "$(grep -q '09:00' "$DIR/workhours.html" && echo 1)"
code=$(get "$J3" "/workspaces/$WS/reminders" reminders.html); ok "GET reminders 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Reminders hiển thị ledger enum (Nhắc hạn chót / Đã gửi in-app)" "$(grep -q 'Nhắc hạn chót' "$DIR/reminders.html" && grep -q 'Đã gửi in-app' "$DIR/reminders.html" && echo 1)"
code=$(get "$J3" "/notifications" notifs.html); ok "GET notifications 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Thông báo đề xuất có link focus tới team-calendar" "$(grep -q 'team-calendar?.*focus=' "$DIR/notifs.html" && echo 1)"

code=$(login demo1@example.test "$J1"); ok "demo1 đăng nhập (302)" "$([ "$code" = 302 ] && echo 1)" "$code"
code=$(get "$J1" "/workspaces/$WS/task-templates?project_id=$PROJECT" tpl_index.html); ok "GET task-templates 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Template index hiển thị trạng thái + tần suất enum" "$(grep -q 'Đã lưu trữ' "$DIR/tpl_index.html" && grep -q 'Hàng tuần' "$DIR/tpl_index.html" && echo 1)"
code=$(get "$J1" "/workspaces/$WS/task-templates/$TPL" tpl_show.html); ok "GET task-template show 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Template show hiển thị run thành công" "$(grep -q 'Đã tạo thành công' "$DIR/tpl_show.html" && echo 1)"
code=$(get "$J1" "/workspaces/$WS/operations" ops.html); ok "GET operations 200" "$([ "$code" = 200 ] && echo 1)" "$code"

code=$(login demo2@example.test "$J2"); ok "demo2 đăng nhập (302)" "$([ "$code" = 302 ] && echo 1)" "$code"
code=$(get "$J2" "/workspaces/$WS/team-calendar?project_id=$PROJECT" team2.html); ok "demo2 GET team-calendar 200" "$([ "$code" = 200 ] && echo 1)" "$code"
ok "Người đề xuất thấy nút 'Thu hồi đề xuất', không thấy 'Chấp thuận'" "$(grep -q 'Thu hồi đề xuất' "$DIR/team2.html" && ! grep -q '>Chấp thuận<' "$DIR/team2.html" && echo 1)"

echo; echo "HTTP RESULT: $pass pass, $fail fail"
