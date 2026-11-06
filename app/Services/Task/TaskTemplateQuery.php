<?php

namespace App\Services\Task;

use App\Authorization\TaskAccess;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Models\User;
use App\Models\Workspace;

/** Read model for template screens; asserts task.view on the template scope. */
class TaskTemplateQuery
{
    public function __construct(private TaskAccess $tasks) {}

    public function find(Workspace $workspace, string $id): TaskTemplate
    {
        return TaskTemplate::where('workspace_id', $workspace->id)->findOrFail($id);
    }

    public function index(User $actor, Workspace $workspace, ?Project $project): array
    {
        abort_unless($this->tasks->templates($actor, $workspace, $project), 403);

        return [
            'workspace' => $workspace, 'project' => $project,
            'templates' => TaskTemplate::where('workspace_id', $workspace->id)->where('project_id', $project?->id)->latest()->paginate(15)->withQueryString(),
            'sources' => $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at')->orderBy('sort_order')->get(),
            'canManage' => $this->tasks->templates($actor, $workspace, $project, true),
        ];
    }

    public function show(User $actor, Workspace $workspace, TaskTemplate $template): array
    {
        abort_unless($this->tasks->templates($actor, $workspace, $template->project), 403);
        $runs = TaskTemplateRun::where('task_template_id', $template->id)->latest()->paginate(15);
        $taskIds = $runs->getCollection()->flatMap(fn ($run) => array_values($run->task_map ?? []));

        return [
            'workspace' => $workspace, 'template' => $template, 'runs' => $runs,
            'generatedTasks' => Task::where('workspace_id', $workspace->id)->where('project_id', $template->project_id)->whereIn('id', $taskIds)->get(['id', 'code', 'title'])->keyBy('id'),
            'canManage' => $this->tasks->templates($actor, $workspace, $template->project, true),
        ];
    }
}
