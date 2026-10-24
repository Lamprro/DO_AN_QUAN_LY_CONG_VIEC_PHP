<?php

return [
    'require_verified_email' => (bool) env('REQUIRE_VERIFIED_EMAIL', env('APP_ENV', 'production') === 'production'),
    // Only explicit reverse proxy addresses. Never trust arbitrary forwarded headers.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
];
