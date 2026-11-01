<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Exceptions\AiProviderUnavailable;
use App\Models\AiRun;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Data\AiProviderResult;

class UnconfiguredAiProvider implements AiProvider
{
    public function complete(AiRun $run): AiProviderResult
    {
        throw new AiProviderUnavailable('AI chưa được cấu hình. Chưa gọi nhà cung cấp và chưa tạo nhận xét AI.');
    }
}
