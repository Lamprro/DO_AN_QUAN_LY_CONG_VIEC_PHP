<?php

namespace App\Models;

use App\Enums\ActivitySource;
use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskActivity extends DomainModel
{
    use SoftDeletes;

    protected $fillable = ['task_id', 'actor_id', 'type', 'event_type', 'content', 'data', 'plan_id', 'source'];

    protected $hidden = [];

    protected $attributes = [
        'source' => 'user',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'type' => ActivityType::class,
            'source' => ActivitySource::class,
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}
