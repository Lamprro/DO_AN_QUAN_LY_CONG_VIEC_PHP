<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateRun extends DomainModel
{
    protected $fillable = ['task_template_id', 'occurrence_key', 'status', 'task_map', 'error'];

    protected function casts(): array
    {
        return ['task_map' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }
}
