<?php

return [
    'enabled' => (bool) env('PASS_REQUEST_API_ENABLED', false),

    'scopes' => [
        'pass-requests:read' => 'Read your pass requests and available seasons',
        'pass-requests:write' => 'Create and change your pass requests',
        'admin:pass-requests:read' => 'Read pass requests as a local administrator',
        'admin:pass-requests:write' => 'Manage pass requests as a local administrator',
    ],
];
