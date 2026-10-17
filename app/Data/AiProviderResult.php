<?php

namespace App\Data;

final readonly class AiProviderResult
{
    public function __construct(public string $text, public ?int $inputTokens = null, public ?int $outputTokens = null) {}
}
