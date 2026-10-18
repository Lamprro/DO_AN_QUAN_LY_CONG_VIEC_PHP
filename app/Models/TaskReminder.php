<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReminder extends DomainModel
{
    protected $fillable = ['workspace_id', 'task_id', 'user_id', 'kind', 'local_date', 'timezone', 'source_token', 'review_after_minutes', 'dedupe_key', 'status', 'skip_reason', 'sent_at'];

    protected function casts(): array
    {
        return ['local_date' => 'date:Y-m-d', 'review_after_minutes' => 'integer', 'sent_at' => 'immutable_datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
