<?php

namespace App\Services;

use App\Contracts\AiProvider;
use App\Data\AiProviderResult;
use App\Exceptions\AiProviderUnavailable;
use App\Models\AiRun;

class UnconfiguredAiProvider implements AiProvider
{
    public function complete(AiRun $run): AiProviderResult
    {
        throw new AiProviderUnavailable('AI chưa được cấu hình. Chưa gọi nhà cung cấp và chưa tạo nhận xét AI.');
    }
}
