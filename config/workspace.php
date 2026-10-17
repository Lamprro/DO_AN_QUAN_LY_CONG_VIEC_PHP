<?php

return [
    // File is an explicit local mode; production must configure redis.
    'invitation_store' => env('INVITATION_CACHE_STORE', 'file'),
    'invitation_ttl_minutes' => max(1, min(10080, (int) env('INVITATION_TTL_MINUTES', 1440))),
    'send_invitation_mail' => (bool) env('INVITATION_SEND_MAIL', true),
];
