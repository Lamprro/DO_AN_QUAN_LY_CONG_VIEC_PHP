<?php

return ['provider' => env('AI_PROVIDER', 'unconfigured'), 'model' => env('AI_MODEL', 'unconfigured'), 'endpoint' => env('AI_ENDPOINT'), 'key' => env('AI_API_KEY'), 'queue' => 'work-management-ai', 'max_attempts' => 3, 'timeout_seconds' => 30, 'lease_seconds' => 180];
