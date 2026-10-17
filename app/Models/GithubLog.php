<?php

namespace App\Models;

use App\Enums\GithubLogStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GithubLog extends DomainModel
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = ['project_id', 'task_id', 'integration_connection_id', 'delivery_id', 'event_type', 'repository_external_id', 'external_object_id', 'actor_external_id', 'summary', 'url', 'payload', 'status', 'attempt_count', 'error_message', 'occurred_at', 'received_at', 'processed_at'];

    protected $hidden = [];

    protected $attributes = [
        'status' => 'pending',
        'attempt_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt_count' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'status' => GithubLogStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function integrationConnection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }
}
