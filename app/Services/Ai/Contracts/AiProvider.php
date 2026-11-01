<?php

namespace App\Services\Ai\Contracts;

use App\Models\AiRun;
use App\Services\Ai\Data\AiProviderResult;

interface AiProvider
{
    /** Network adapters must enforce config('ai.timeout_seconds'); never mutate tasks or plans. */
    public function complete(AiRun $run): AiProviderResult;
}

