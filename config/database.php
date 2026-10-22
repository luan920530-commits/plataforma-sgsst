<?php
declare(strict_types=1);

return [
    'path' => getenv('SGSST_DB_PATH') ?: dirname(__DIR__) . '/database/sgsst.db',
    'busy_timeout' => 5000,
];
