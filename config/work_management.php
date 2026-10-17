<?php

return [
    'schema_version' => 3,
    'plan_schema_version' => 1,
    'default_timezone' => 'Asia/Ho_Chi_Minh',
    'settings_defaults' => [
        'language' => 'vi',
        'theme' => 'system',
        'notifications' => ['in_app' => true, 'email' => false, 'telegram' => false],
    ],
    'integrations_enabled' => env('INTEGRATIONS_ENABLED', false),
    'ai_enabled' => env('AI_ENABLED', false),
];
