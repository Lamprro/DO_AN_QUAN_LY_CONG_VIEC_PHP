<?php

namespace App\Models;

use App\Enums\TaskTemplateRunStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable record of one template occurrence (unique per template and occurrence_key). */
class TaskTemplateRun extends DomainModel
{
    protected $fillable = ['task_template_id', 'occurrence_key', 'status', 'task_map', 'error'];

    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'task_map' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'status' => TaskTemplateRunStatus::class,
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }
}
