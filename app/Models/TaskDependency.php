<?php

namespace App\Models;

use App\Enums\DependencyType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskDependency extends DomainModel
{
    public const UPDATED_AT = null;

    protected $fillable = ['predecessor_task_id', 'successor_task_id', 'dependency_type', 'lag_minutes'];

    protected $hidden = [];

    protected $attributes = [
        'dependency_type' => 'FS',
        'lag_minutes' => 0,
    ];

    protected function casts(): array
    {
        return [
            'lag_minutes' => 'integer',
            'created_at' => 'immutable_datetime',
            'dependency_type' => DependencyType::class,
        ];
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_task_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_task_id');
    }
}
