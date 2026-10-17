<?php
// Source-data preparation using the existing TimeEntry model and actual clock.
// This is NOT a timer endpoint or a simulated historical time fixture.
$root=dirname(__DIR__,3);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$state=json_decode(file_get_contents($root.'/storage/app/private/stage06-review/state.json'),true,512,JSON_THROW_ON_ERROR);
$task=\App\Models\Task::findOrFail($state['tasks']['b']);
$user=\App\Models\User::findOrFail($state['uids']['worker']);
if (($argv[1]??'')==='start') {
    \Illuminate\Support\Facades\Gate::forUser($user)->authorize('execute',$task);
    $entry=\App\Models\TimeEntry::create(['task_id'=>$task->id,'user_id'=>$user->id,'started_at'=>now(),'note'=>'P6 real elapsed CLI source preparation. Timer UI is not implemented.']);
} elseif (($argv[1]??'')==='end') {
    $entry=\App\Models\TimeEntry::where('task_id',$task->id)->where('user_id',$user->id)->whereNull('ended_at')->findOrFail($argv[2]??'');
    $entry->update(['ended_at'=>now()]);
} else { throw new \InvalidArgumentException('Use start or end <id>.'); }
echo json_encode($entry->fresh()->only(['id','task_id','user_id','started_at','ended_at']),JSON_THROW_ON_ERROR);
