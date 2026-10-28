<?php
declare(strict_types=1);

return [
    'name' => 'Normia SST',
    'asset_version' => '2026.10.24.1',
    'url' => rtrim(getenv('APP_URL') ?: '', '/'),
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'America/Bogota',
    'demo' => filter_var(getenv('APP_DEMO') ?: 'false', FILTER_VALIDATE_BOOL),
    'company_seed' => trim((string) (getenv('APP_COMPANY_SEED') ?: '')),
    'session' => [
        'name' => 'normia_sst_session',
        'lifetime' => 7200,
    ],
];
