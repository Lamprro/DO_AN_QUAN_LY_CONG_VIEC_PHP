<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskWatcher extends DomainModel
{
    protected $fillable = ['task_id', 'user_id', 'added_by'];

    protected function casts(): array
    {
        return [];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
