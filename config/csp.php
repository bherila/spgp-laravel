<?php

return [

    /*
     * Presets configure which CSP directives are enforced. Leave empty while
     * using report-only mode (see report_only_presets below).
     */
    'presets' => [],

    'directives' => [],

    /*
     * Report-only mode: violations are reported but not blocked. Use this to
     * validate the policy before switching to enforced mode.
     */
    'report_only_presets' => [
        App\Csp\CloudflareCspPolicy::class,
    ],

    'report_only_directives' => [],

    /*
     * Violations will be reported to this URI (leave blank to disable).
     */
    'report_uri' => env('CSP_REPORT_URI', ''),

    /*
     * CSP headers are only added when this is true.
     */
    'enabled' => env('CSP_ENABLED', true),

    /*
     * Add CSP headers even when Vite HMR is active (hot reloading).
     * Disabled by default because HMR uses inline scripts/websockets that
     * would be blocked by a strict policy.
     */
    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    /*
     * Generator class for producing a per-request CSP nonce.
     */
    'nonce_generator' => Spatie\Csp\Nonce\RandomString::class,

    /*
     * Set to false to disable nonce generation (makes policy less secure).
     */
    'nonce_enabled' => env('CSP_NONCE_ENABLED', true),

];
