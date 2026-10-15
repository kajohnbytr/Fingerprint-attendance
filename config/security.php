<?php

return [
    'force_https' => env('FORCE_HTTPS', env('APP_ENV') === 'production'),
    'session_idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 30),
    'api_idle_minutes' => (int) env('API_IDLE_MINUTES', 30),
    'password_history' => (int) env('PASSWORD_HISTORY_COUNT', 5),
    'mfa_ttl_minutes' => (int) env('MFA_TTL_MINUTES', 10),
    'mfa_enabled' => (bool) env('MFA_ENABLED', false),
    'csp_connect_sources' => env('CSP_CONNECT_SOURCES', ''),
    'clamav_binary' => env('CLAMAV_BINARY', 'clamscan'),
    'require_malware_scan' => (bool) env('REQUIRE_MALWARE_SCAN', env('APP_ENV') === 'production'),
];
