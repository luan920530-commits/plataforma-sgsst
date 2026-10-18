<?php
return [
    'app' => [
        'nombre'   => 'Plataforma PPPRAE',
        'url'      => 'http://localhost/ppprae/public',
        'debug'    => true,
        'timezone' => 'America/Bogota',
        'moneda'   => 'COP',
    ],
    'db' => [
        'path' => __DIR__ . '/../storage/database.sqlite',
    ],
    'storage' => [
        'documentos' => __DIR__ . '/../storage/documentos/',
        'firmas'     => __DIR__ . '/../public/uploads/firmas/',
    ],
    'session' => [
        'nombre'   => 'PPPRAE_SESS',
        'lifetime' => 7200,
    ],
];
