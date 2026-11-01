<?php

namespace App\Services\Ai\Data;

class AiProviderResult
{
    public function __construct(
        public string $text = '',
        public ?int $inputTokens = null,
        public ?int $outputTokens = null
    ) {}
}
