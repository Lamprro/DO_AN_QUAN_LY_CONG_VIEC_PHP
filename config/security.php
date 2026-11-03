<?php

return [
    'require_verified_email' => true,
    // Only explicit reverse proxy addresses. Never trust arbitrary forwarded headers.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),
];
