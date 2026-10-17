<?php

namespace App\Contracts;

use App\Data\AiProviderResult;
use App\Models\AiRun;

interface AiProvider
{
    /** Network adapters must enforce config('ai.timeout_seconds'); never mutate tasks or plans. */
    public function complete(AiRun $run): AiProviderResult;
}
