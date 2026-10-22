<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\TaskPolicy;
use App\Support\ProjectAccess;
use App\Support\SearchTerm;

class PlanEditorData
{
    public function build(User $actor, Workspace $workspace, ?Project $project, array $payload): array
    {
        $rawDraft = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $renderable = true;
        foreach (['tasks' => 100, 'dependencies' => 300, 'slots' => 300] as $list => $limit) {
            if (! is_array($payload[$list] ?? null) || ! array_is_list($payload[$list]) || count($payload[$list]) > $limit) {
                $renderable = false;
                break;
            }
            foreach ($payload[$list] as $row) {
                if (! is_array($row) || array_filter($row, fn ($v) => ! is_scalar($v) && $v !== null)) {
                    $renderable = false;
                    break 2;
                }
            }
        }
        if ($renderable) {
            foreach ($payload['tasks'] as $row) {
                if (! is_string($row['ref'] ?? null) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,39}$/D', $row['ref']) || ! in_array($row['task_type'] ?? null, ['GROUP', 'WORK', 'MILESTONE'], true) || ! in_array($row['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)) {
                    $renderable = false;
                    break;
                }
            }
        }
        if (! $renderable) {
            $payload = ['schema_version' => 1, 'tasks' => [], 'dependencies' => [], 'slots' => []];
        }
        $access = app(ProjectAccess::class);
        $members = $access->eligibleMembers($workspace, $project);
        $reviewers = $access->eligibleMembers($workspace, $project, 'task.review');
        $query = $workspace->tasks()->where('project_id', $project?->id)->whereNull('archived_at');
        if (! app(TaskPolicy::class)->scope($actor, $workspace, $project, 'task.view')) {
            $query->whereRaw('1 = 0');
        }
        $needle = request()->query('source_q');
        $choices = (clone $query)->when(is_string($needle) && $needle !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("code LIKE ? ESCAPE '!'", [SearchTerm::contains($needle)])->orWhereRaw("title LIKE ? ESCAPE '!'", [SearchTerm::contains($needle)])))->orderBy('code')->limit(201)->get();
        $hasMore = $choices->count() > 200;
        $choices = $choices->take(200);
        $selected = [];
        foreach ($payload['tasks'] as $row) {
            if (! empty($row['task_id'])) {
                $selected[] = $row['task_id'];
            }
        }
        $selected = array_unique(array_merge($selected, request()->query('source_selected', [])));
        if ($selected) {
            $choices = $choices->merge((clone $query)->whereIn('id', $selected)->limit(100)->get())->unique('id')->values();
        }
        // Include at most three ancestor batches so a searched child keeps its actual group.
        $parents = $choices->pluck('parent_task_id')->filter()->all();
        for ($depth = 0; $depth < 3 && $parents; $depth++) {
            $ancestors = (clone $query)->whereIn('id', $parents)->get();
            $choices = $choices->merge($ancestors)->unique('id')->values();
            $parents = $ancestors->pluck('parent_task_id')->filter()->all();
        }
        $tasks = $choices->map(fn ($t) => ['id' => $t->id, 'label' => $t->code.' — '.$t->title, 'title' => $t->title, 'description' => $t->description, 'task_type' => $t->task_type->value, 'priority' => $t->priority->value, 'estimate_minutes' => $t->estimate_minutes, 'assignee_id' => $t->assignee_id, 'reviewer_id' => $t->reviewer_id, 'parent_task_id' => $t->parent_task_id, 'editable' => $actor->can('update', $t) && $t->status->value === 'TODO' && ! $t->started_at])->all();
        $users = $members->map(fn ($m) => ['id' => $m->user_id, 'label' => $m->user->name])->all();
        $review = $reviewers->map(fn ($m) => ['id' => $m->user_id, 'label' => $m->user->name])->all();

        return ['payload' => $payload, 'renderable' => $renderable, 'source_limit_reached' => $hasMore, 'rawDraft' => $rawDraft, 'tasks' => $tasks, 'users' => $users, 'reviewers' => $review, 'timezone' => $workspace->timezone, 'limits' => ['tasks' => 100, 'dependencies' => 300, 'slots' => 300, 'depth' => 3]];
    }

    public function normalize(array $editor): array
    {
        $result = ['schema_version' => 1];
        foreach (['tasks', 'dependencies', 'slots'] as $list) {
            if (! isset($editor[$list])) {
                $result[$list] = [];

                continue;
            }
            if (! is_array($editor[$list])) {
                $result[$list] = $editor[$list];

                continue;
            }
            $result[$list] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    return $row;
                }
                foreach ($row as $field => $value) {
                    if ($value === '') {
                        $row[$field] = null;
                    }
                }

                return $row;
            }, $editor[$list]));
        }

        return $result;
    }
}
