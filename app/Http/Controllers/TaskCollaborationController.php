<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\TaskUtilityService;
use Illuminate\Http\Request;

class TaskCollaborationController extends Controller
{
    public function store(Request $r, Workspace $workspace, string $task, TaskUtilityService $service)
    {
        $data = $r->validate(['user_id' => 'required|uuid']);
        $t = $workspace->tasks()->findOrFail($task);
        $service->watch($r->user(), $t, $data['user_id']);

        return back()->with('success', 'Đã theo dõi công việc.');
    }

    public function destroy(Request $r, Workspace $workspace, string $task, string $user, TaskUtilityService $service)
    {
        $t = $workspace->tasks()->findOrFail($task);
        $service->watch($r->user(), $t, $user, true);

        return back()->with('success', 'Đã dừng theo dõi.');
    }
}
