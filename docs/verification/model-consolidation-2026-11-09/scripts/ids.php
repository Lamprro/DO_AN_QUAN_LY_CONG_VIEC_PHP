<?php
// Prepare one more pending proposal (P11) through the new service and print ids for the HTTP walkthrough.
use App\Models\CalendarEvent;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Calendar\TeamCalendarService;
use Carbon\CarbonImmutable;

$ws = Workspace::where('name', 'Demo Team A')->firstOrFail();
$project = $ws->projects()->where('name', 'Demo Work Management')->firstOrFail();
$day = CarbonImmutable::now($ws->timezone)->addDays(21)->startOfDay();
app(TeamCalendarService::class)->propose(User::where('email', 'demo2@example.test')->first(), $ws, ['project_id' => $project->id, 'recipient_id' => User::where('email', 'demo3@example.test')->value('id'), 'title' => 'MERGE-P11 pending for HTTP', 'event_type' => 'MEETING', 'visibility' => 'project', 'timezone' => $ws->timezone, 'starts_at' => $day->setTime(9, 0)->format('Y-m-d\TH:i'), 'ends_at' => $day->setTime(10, 0)->format('Y-m-d\TH:i')]);
$id = fn ($t) => CalendarEvent::proposals()->where('title', $t)->value('id');
echo 'IDS '.implode(' ', [$ws->id, $project->id, $id('MERGE-P1 pending'), $id('MERGE-P10 immutable'), TaskTemplate::where('name', 'like', 'MERGE template%')->value('id')]).PHP_EOL;
