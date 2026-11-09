<?php
// NEW CODE on the migrated review-DB copy: exercise the merged flows through real services.
use App\Enums\CalendarEventStatus;
use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Account\RegistrationService;
use App\Services\Calendar\CalendarService;
use App\Services\Calendar\CalendarViewService;
use App\Services\Calendar\TeamCalendarService;
use App\Services\Setting\SettingService;
use App\Services\Task\TaskReminderService;
use App\Services\Task\TaskTemplateService;
use App\Services\TimeTracking\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, string $extra = '') use (&$ok, &$bad) {
    $cond ? $ok++ : $bad++;
    echo ($cond ? '[PASS] ' : '[FAIL] ').$label.($extra !== '' ? ' — '.$extra : '').PHP_EOL;
};
$expectError = function (string $label, callable $fn, string $needle = '') use ($check) {
    try {
        $fn();
        $check($label, false, 'không có lỗi');
    } catch (Throwable $e) {
        $msg = $e instanceof Illuminate\Validation\ValidationException ? json_encode($e->errors(), JSON_UNESCAPED_UNICODE) : get_class($e).': '.$e->getMessage();
        $check($label, $needle === '' || str_contains($msg, $needle), $msg);
    }
};

$team = app(TeamCalendarService::class);
$cal = app(CalendarService::class);
$views = app(CalendarViewService::class);
$ws = Workspace::where('name', 'Demo Team A')->firstOrFail();
$project = $ws->projects()->where('name', 'Demo Work Management')->firstOrFail();
$owner = User::where('email', 'demo1@example.test')->firstOrFail();
$manager = User::where('email', 'demo2@example.test')->firstOrFail();
$member = User::where('email', 'demo3@example.test')->firstOrFail();
$day = CarbonImmutable::now($ws->timezone)->addDays(20)->startOfDay();
$slot = fn (int $h, int $len = 1) => [$day->setTime($h, 0)->format('Y-m-d\TH:i'), $day->setTime($h + $len, 0)->format('Y-m-d\TH:i')];
$byTitle = fn (string $t) => CalendarEvent::proposals()->where('title', $t)->firstOrFail();

echo "== 1. Global scope: proposal-only rows never reach calendar queries\n";
$p1 = $byTitle('MERGE-P1 pending');
$check('find() bỏ qua đề xuất đang chờ', CalendarEvent::find($p1->id) === null);
$check('proposals() vẫn đọc được đề xuất', CalendarEvent::proposals()->whereKey($p1->id)->exists());
$range = ['start' => $day, 'end' => $day->addDay(), 'timezone' => $ws->timezone, 'includeCancelled' => true];
$titles = collect($views->rows($member, $ws, $range, $project))->pluck('title')->filter(fn ($t) => str_starts_with((string) $t, 'MERGE-'))->sort()->values()->all();
$check('Calendar project (kể cả đã hủy) chỉ thấy lịch thật P4, P5, P6; không có P1-P3', $titles === ['MERGE-P4 accepted task block', 'MERGE-P5 accepted then cancelled', 'MERGE-P6 accepted then event deleted'], implode(', ', $titles));
$range2 = ['includeCancelled' => false] + $range;
$titles2 = collect($views->rows($member, $ws, $range2, $project))->pluck('title')->filter(fn ($t) => str_starts_with((string) $t, 'MERGE-'))->values()->all();
$check('Calendar mặc định (ẩn đã hủy) chỉ thấy P4', $titles2 === ['MERGE-P4 accepted task block'], implode(', ', $titles2));
$check('Timeline không có dòng đề xuất', collect($views->timeline($member, $ws, $project, $range))->flatten(1)->every(fn ($r) => ! in_array($r['title'], ['MERGE-P1 pending', 'MERGE-P2 declined', 'MERGE-P3 withdrawn'], true)));

echo "== 2. Pending proposal does not block, accept re-checks busy overlap\n";
[$s, $e] = $slot(8);
$base = ['project_id' => null, 'task_id' => null, 'event_type' => 'BUSY', 'title' => 'MERGE-busy demo3', 'description' => null, 'starts_at' => $s, 'ends_at' => $e, 'timezone' => $ws->timezone, 'visibility' => 'private', 'status' => 'planned', 'all_day' => false, 'is_busy' => true, 'participants' => []];
$busy = $cal->save($member, $ws, $base);
$check('Tạo lịch bận trùng giờ P1 thành công (đề xuất chờ không chiếm chỗ)', $busy->exists);
$expectError('Chấp thuận P1 bị chặn do trùng lịch bận', fn () => $team->respond($member, $p1, 'accept', $cal->version($p1)), 'trùng lịch bận');
$check('P1 vẫn proposed sau khi lỗi', $p1->fresh()->status === CalendarEventStatus::PROPOSED);
$cal->save($member, $ws, ['status' => 'cancelled', 'version' => $cal->version($busy->fresh())] + $base, $busy->fresh());
$p1 = $p1->fresh();
$team->respond($member, $p1, 'accept', $cal->version($p1));
$p1 = CalendarEvent::find($p1->id);
$check('Chấp thuận P1: cùng id, status planned, có responded_at', $p1 && $p1->status === CalendarEventStatus::PLANNED && $p1->responded_at !== null && $p1->user_id === $member->id);
$check('Thông báo phản hồi dùng khóa :planned', DB::table('notifications')->where('dedupe_key', 'calendar-proposal:'.$p1->id.':planned')->exists());
$check('Người nhận sửa được lịch đã nhận (policy update)', $member->can('update', $p1));
$check('Không xóa được lịch đến từ đề xuất (policy delete)', ! $owner->can('delete', CalendarEvent::proposals()->where('title', 'MERGE-P6 accepted then event deleted')->first()) && ! $member->can('delete', $p1));

echo "== 3. New proposals: propose, decline, withdraw, accept TASK_BLOCK, cancel after accept\n";
$mk = function (string $title, int $h, string $type = 'MEETING', ?string $taskId = null) use ($team, $manager, $member, $ws, $project, $slot) {
    [$s, $e] = $slot($h);

    return $team->propose($manager, $ws, ['project_id' => $project->id, 'recipient_id' => $member->id, 'task_id' => $taskId, 'title' => $title, 'description' => 'new code', 'event_type' => $type, 'visibility' => 'project', 'timezone' => $ws->timezone, 'starts_at' => $s, 'ends_at' => $e]);
};
$p7 = $mk('MERGE-P7 new declined', 16);
$check('P7 lưu vào calendar_events với status proposed, user_id = người nhận', $p7->status === CalendarEventStatus::PROPOSED && $p7->user_id === $member->id && $p7->proposed_by === $manager->id && DB::table('calendar_events')->where('id', $p7->id)->exists());
$check('Thông báo đề xuất dùng khóa :proposed', DB::table('notifications')->where('dedupe_key', 'calendar-proposal:'.$p7->id.':proposed')->where('recipient_id', $member->id)->exists());
$expectError('Người đề xuất không được tự chấp thuận', fn () => $team->respond($manager, $p7, 'accept', $cal->version(CalendarEvent::proposals()->find($p7->id))), 'HttpException');
$expectError('Phiên bản cũ bị từ chối', fn () => $team->respond($member, $p7, 'decline', str_repeat('0', 64)), 'version');
$team->respond($member, $p7, 'decline', $cal->version($p7->fresh()));
$p7 = CalendarEvent::proposals()->find($p7->id);
$check('P7 declined', $p7->status === CalendarEventStatus::DECLINED);
$expectError('Không xử lý lại đề xuất đã từ chối', fn () => $team->respond($member, $p7, 'accept', $cal->version($p7)), 'đã được xử lý');

$p8 = $mk('MERGE-P8 new withdrawn', 17);
$team->respond($manager, $p8, 'cancel', $cal->version($p8->fresh()));
$check('P8 withdrawn', CalendarEvent::proposals()->find($p8->id)->status === CalendarEventStatus::WITHDRAWN);

$task = Task::where('code', 'DEMO-006')->firstOrFail();
$p9 = $mk('MERGE-P9 new task block', 18, 'TASK_BLOCK', $task->id);
$team->respond($member, $p9, 'accept', $cal->version($p9->fresh()));
$p9 = CalendarEvent::find($p9->id);
$check('P9 TASK_BLOCK accepted thành lịch planned', $p9 && $p9->status === CalendarEventStatus::PLANNED && $p9->task_id === $task->id);
$check('P9 ghi lịch sử task calendar.saved', DB::table('task_activities')->where('task_id', $task->id)->where('event_type', 'calendar.saved')->where('data', 'like', '%'.$p9->id.'%')->exists());
$team->respond($member, $p9, 'cancel', $cal->version($p9));
$p9 = CalendarEvent::proposals()->find($p9->id);
$check('P9 hủy sau khi nhận → cancelled', $p9->status === CalendarEventStatus::CANCELLED);

echo "== 4. Integrity rules on proposal rows\n";
$p10 = $mk('MERGE-P10 immutable', 19);
$expectError('Không sửa nội dung đề xuất đang chờ', fn () => $p10->fresh()->update(['title' => 'changed']), 'Nội dung đề xuất giữ nguyên');
$expectError('Không xóa dòng đề xuất', fn () => CalendarEvent::proposals()->find($p10->id)->delete(), 'Giữ lịch sử');
$expectError('Không mở lại đề xuất đã từ chối', fn () => CalendarEvent::proposals()->find($p7->id)->update(['status' => 'proposed']), 'Đề xuất đã kết thúc');
$rules = (new CalendarEventRequest)->rules();
$check('Form lịch thường từ chối status=proposed', Validator::make(['status' => 'proposed'], ['status' => $rules['status']])->fails());
$check('Form lịch thường chấp nhận status=planned', ! Validator::make(['status' => 'planned'], ['status' => $rules['status']])->fails());
$check('Form lịch thường cấm truyền proposed_by', Validator::make(['proposed_by' => $owner->id], ['proposed_by' => $rules['proposed_by']])->fails());

echo "== 5. Team calendar read model\n";
$data = $views->team($member, $ws, $project, null);
$check('Danh sách đề xuất của demo3 có đủ P1..P10 (trừ P6 của demo1)', $data['proposals']->total() === 9, 'total '.$data['proposals']->total());
$check('Mỗi đề xuất có version', count($data['versions']) === $data['proposals']->count());
$focus = $views->team($member, $ws, $project, $byTitle('MERGE-P4 accepted task block')->id);
$check('Liên kết thông báo cũ (đã trỏ sang id event) mở đúng P4', $focus['proposals']->total() === 1);

echo "== 6. users.settings\n";
$settings = app(SettingService::class);
$tt = app(TimeTrackingService::class);
$check('Đọc work_hours đã chuyển từ user_settings', $tt->workHoursValues($member)['windows'][1]['start'] === '13:30');
$settings->save($member, ['language' => 'en', 'theme' => 'dark', 'notifications' => ['in_app' => false]]);
$v = $settings->values($member->fresh());
$check('Lưu settings giữ work_hours và cập nhật theme', $v['theme'] === 'dark' && $v['notifications']['in_app'] === false && isset($settings->stored($member)['work_hours']));
$tt->saveWorkHours($member, ['version' => $tt->workHoursVersion($member), 'timezone' => 'Asia/Ho_Chi_Minh', 'weekdays' => [1, 2, 3], 'windows' => [['start' => '09:00', 'end' => '11:00']], 'slot_minutes' => 15]);
$check('Lưu giờ làm vào users.settings, giữ theme', $settings->stored($member)['work_hours']['slot_minutes'] === 15 && $settings->stored($member)['theme'] === 'dark');
$settings->save($member, ['language' => 'vi', 'theme' => 'system', 'notifications' => ['in_app' => true]]);
$new = app(RegistrationService::class)->register(['name' => 'Merge Verify', 'email' => 'merge-verify-'.time().'@example.test', 'password' => 'Merge@123456', 'timezone' => 'Asia/Ho_Chi_Minh']);
$check('Đăng ký mới ghi settings mặc định vào users', $settings->stored($new) === config('work_management.settings_defaults'));

echo "== 7. Task templates with enums\n";
$tpl = app(TaskTemplateService::class);
$localNow = CarbonImmutable::now($ws->timezone);
$t = $tpl->create($owner, $ws, $project, ['name' => 'MERGE template', 'frequency' => 'daily', 'interval' => 1, 'timezone' => $ws->timezone, 'starts_on' => $localNow->toDateString(), 'local_time' => '00:00', 'deadline_offset_days' => 2, 'task_ids' => [Task::where('project_id', $project->id)->where('task_type', 'WORK')->whereNull('parent_task_id')->whereNull('archived_at')->orderBy('code')->value('id')]]);
$check('Template active, frequency là enum', $t->fresh()->status === App\Enums\TaskTemplateStatus::ACTIVE && $t->fresh()->frequency === App\Enums\TaskTemplateFrequency::DAILY);
$result = $tpl->due();
$runs = TaskTemplateRun::where('task_template_id', $t->id)->get();
$check('due() tạo kỳ đến hạn, run SUCCEEDED, next_run_at tiến lên', $result['succeeded'] >= 1 && $runs->first()?->status === App\Enums\TaskTemplateRunStatus::SUCCEEDED && $t->fresh()->next_run_at->isFuture(), json_encode($result));
$tpl->update($owner, $t->fresh(), ['name' => 'MERGE template weekly', 'frequency' => 'weekly', 'interval' => 1, 'timezone' => $ws->timezone, 'starts_on' => $localNow->toDateString(), 'local_time' => '07:00', 'revision' => $t->fresh()->revision]);
$check('Đổi lịch daily→weekly (so sánh enum trong update)', $t->fresh()->frequency === App\Enums\TaskTemplateFrequency::WEEKLY);
$tpl->state($owner, $t->fresh(), 'pause', $t->fresh()->revision);
$check('Tạm dừng → paused', $t->fresh()->status === App\Enums\TaskTemplateStatus::PAUSED);
$tpl->state($owner, $t->fresh(), 'resume', $t->fresh()->revision);
$check('Tiếp tục → active có next_run_at', $t->fresh()->status === App\Enums\TaskTemplateStatus::ACTIVE && $t->fresh()->next_run_at !== null);
$run = $tpl->instantiate($owner, $t->fresh(), 'manual:merge-verify', $t->fresh()->revision);
$check('Tạo thủ công từ mẫu', $run->status === App\Enums\TaskTemplateRunStatus::SUCCEEDED);
$tpl->state($owner, $t->fresh(), 'archive', $t->fresh()->revision);
$expectError('Mẫu đã lưu trữ không tạo việc', fn () => $tpl->instantiate($owner, $t->fresh(), 'manual:after-archive', $t->fresh()->revision), 'lưu trữ');

echo "== 8. Task reminders with enums\n";
$rem = app(TaskReminderService::class);
$dueTask = Task::where('code', 'DEMO-010')->firstOrFail();
$dueTask->forceFill(['deadline_at' => now()->addHours(5)])->saveQuietly();
$count = $rem->scan($member, $ws);
$r = TaskReminder::where('task_id', $dueTask->id)->where('user_id', $member->id)->latest()->first();
$check('scan tạo ledger kind=due, status=queued', $count >= 1 && $r?->kind === App\Enums\TaskReminderKind::DUE && $r->status === App\Enums\TaskReminderStatus::QUEUED, "count $count");
$rem->deliver($r->id);
$r = $r->fresh();
$check('deliver → sent và có thông báo task.reminder_due', $r->status === App\Enums\TaskReminderStatus::SENT && DB::table('notifications')->where('dedupe_key', 'reminder:'.$r->dedupe_key)->where('type', 'task.reminder_due')->exists());
$check('Quan hệ mới: Task::reminders, TaskReminder::user/workspace', $dueTask->reminders()->count() >= 1 && $r->user->is($member) && $r->workspace->is($ws));

echo PHP_EOL."RESULT: $ok pass, $bad fail".PHP_EOL;
