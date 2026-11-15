<?php

return [
    // Laravel MCP also checks Origin. Admit only the configured resource origin;
    // ValidatePassRequestOrigin remains the stricter exact-origin application gate.
    'allowed_origins' => array_filter([env('OAUTH_RESOURCE_URI')], is_string(...)),
];
