<?php
declare(strict_types=1);

return [
    'name' => 'Normia SST',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Bogota',
    'demo' => filter_var(getenv('APP_DEMO') ?: 'false', FILTER_VALIDATE_BOOL),
    'session' => [
        'name' => 'normia_sst_session',
        'lifetime' => 7200,
    ],
];
