<?php

namespace App\Models;

use App\Enums\AiCallStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCall extends DomainModel
{
    public const UPDATED_AT = null;

    public $timestamps = false;

    protected $fillable = ['ai_run_id', 'step_key', 'attempt_no', 'provider', 'model', 'request_payload', 'response_payload', 'input_tokens', 'output_tokens', 'status', 'error_message', 'started_at', 'finished_at'];

    protected $hidden = [];

    protected $attributes = [
        'attempt_no' => 1,
        'status' => 'running',
    ];

    protected function casts(): array
    {
        return [
            'attempt_no' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'status' => AiCallStatus::class,
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
