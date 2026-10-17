<?php

namespace App\Models;

use App\Enums\BlockerStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskBlocker extends DomainModel
{
    protected $fillable = ['task_id', 'reported_by', 'reason', 'status', 'resolved_by', 'resolved_at'];

    protected $hidden = [];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'status' => BlockerStatus::class,
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
