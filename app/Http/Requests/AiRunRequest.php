<?php

namespace App\Http\Requests;

class AiRunRequest extends ReportRequest
{
    public function rules(): array
    {
        return parent::rules() + ['user_prompt' => ['required', 'string', 'max:4000'], 'idempotency_token' => ['required', 'uuid'], 'operation' => ['prohibited'], 'status' => ['prohibited'], 'context_snapshot' => ['prohibited'], 'requested_by' => ['prohibited'], 'provider' => ['prohibited'], 'model' => ['prohibited']];
    }
}
