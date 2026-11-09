<?php
// OLD CODE (HEAD) on a review-DB copy: create proposals through the real TeamCalendarService/CalendarService.
use App\Models\CalendarProposal;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Calendar\CalendarService;
use App\Services\Calendar\TeamCalendarService;
use App\Services\TimeTracking\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

$team = app(TeamCalendarService::class);
$cal = app(CalendarService::class);
$ws = Workspace::where('name', 'Demo Team A')->firstOrFail();
$project = $ws->projects()->where('name', 'Demo Work Management')->firstOrFail();
$manager = User::where('email', 'demo2@example.test')->firstOrFail();
$member = User::where('email', 'demo3@example.test')->firstOrFail();
$task = Task::where('code', 'DEMO-002')->firstOrFail();
$day = CarbonImmutable::now($ws->timezone)->addDays(20)->startOfDay();
$slot = fn (int $h) => [$day->setTime($h, 0)->format('Y-m-d\TH:i'), $day->setTime($h + 1, 0)->format('Y-m-d\TH:i')];
$make = function (string $title, int $h, string $type = 'MEETING', string $to = 'demo3@example.test') use ($team, $manager, $ws, $project, $task, $slot) {
    [$s, $e] = $slot($h);

    return $team->propose($manager, $ws, ['project_id' => $project->id, 'recipient_id' => User::where('email', $to)->value('id'), 'task_id' => $type === 'TASK_BLOCK' ? $task->id : null, 'title' => $title, 'description' => 'Verify merge '.$title, 'event_type' => $type, 'visibility' => 'project', 'timezone' => $ws->timezone, 'starts_at' => $s, 'ends_at' => $e]);
};

$p1 = $make('MERGE-P1 pending', 8);
$p2 = $make('MERGE-P2 declined', 9);
$team->respond($member, $p2, 'decline', 1);
$p3 = $make('MERGE-P3 withdrawn', 10);
$team->respond($manager, $p3, 'cancel', 1);
$p4 = $make('MERGE-P4 accepted task block', 11, 'TASK_BLOCK');
$team->respond($member, $p4, 'accept', 1);
$p5 = $make('MERGE-P5 accepted then cancelled', 13);
$team->respond($member, $p5, 'accept', 1);
$team->respond($member, $p5->fresh(), 'cancel', 2);
$owner = User::where('email', 'demo1@example.test')->firstOrFail();
$p6 = $make('MERGE-P6 accepted then event deleted', 15, 'MEETING', 'demo1@example.test');
$team->respond($owner, $p6, 'accept', 1);
$event6 = $p6->fresh()->event;
$cal->delete($owner, $ws, $event6, $cal->version($event6));

// Custom work hours for demo3 stored in user_settings (old table).
$tt = app(TimeTrackingService::class);
$tt->saveWorkHours($member, ['version' => $tt->workHoursVersion($member), 'timezone' => 'Asia/Ho_Chi_Minh', 'weekdays' => [1, 2, 3, 4, 5], 'windows' => [['start' => '08:30', 'end' => '12:00'], ['start' => '13:30', 'end' => '17:30']], 'slot_minutes' => 30]);

foreach (CalendarProposal::orderBy('title')->get() as $p) {
    echo "proposal {$p->id} | {$p->title} | {$p->status} | rev {$p->revision} | event ".($p->calendar_event_id ?? '-')."\n";
}
foreach (DB::table('calendar_events')->where('title', 'like', 'MERGE-%')->orderBy('title')->get() as $e) {
    echo "event {$e->id} | {$e->title} | {$e->status} | owner ".($e->user_id === $member->id ? 'demo3' : $e->user_id)."\n";
}
foreach (DB::table('notifications')->where('dedupe_key', 'like', 'calendar-proposal:%')->orderBy('created_at')->get() as $n) {
    echo "notif {$n->dedupe_key}\n";
}
echo 'demo3 settings: '.DB::table('user_settings')->where('user_id', $member->id)->value('settings')."\n";
echo 'user_settings rows: '.DB::table('user_settings')->count()."\n";
