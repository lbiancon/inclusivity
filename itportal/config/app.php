<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'JSS IT Portal',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8080', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Europe/Rome',
    'session_timeout' => (int) (getenv('SESSION_TIMEOUT') ?: 900),
    'auth_driver' => getenv('AUTH_DRIVER') ?: 'mock',
    'version' => '4.0.0',
];
