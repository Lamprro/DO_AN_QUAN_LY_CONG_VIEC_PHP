<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskTemplate extends DomainModel
{
    protected $fillable = ['workspace_id', 'project_id', 'created_by', 'name', 'definition', 'frequency', 'interval', 'starts_on', 'ends_on', 'local_time', 'timezone', 'deadline_offset_days', 'next_run_at', 'status', 'revision', 'last_error'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'next_run_at' => 'immutable_datetime', 'revision' => 'integer', 'interval' => 'integer', 'deadline_offset_days' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TaskTemplateRun::class, 'task_template_id');
    }
}
