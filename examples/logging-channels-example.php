<?php

declare(strict_types=1);

/**
 * Exemple de configuration de canal de logging pour ModelSchema
 *
 * Ajoutez cette configuration à votre fichier config/logging.php dans la section 'channels'
 * pour créer un canal de logging dédié pour ModelSchema.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Ajoutez ces canaux à config/logging.php dans la section 'channels'
    |--------------------------------------------------------------------------
    */

    'modelschema' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modelschema.log'),
        'level' => env('MODELSCHEMA_LOG_LEVEL', 'debug'),
        'days' => 14,
        'permission' => 0644,
        'formatter' => Monolog\Formatter\LineFormatter::class,
        'formatter_with' => [
            'format' => "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            'dateFormat' => 'Y-m-d H:i:s',
            'allowInlineLineBreaks' => true,
            'includeStacktraces' => true,
        ],
    ],

    'modelschema_performance' => [
        'driver' => 'single',
        'path' => storage_path('logs/modelschema-performance.log'),
        'level' => 'info',
        'permission' => 0644,
        'formatter' => Monolog\Formatter\JsonFormatter::class,
    ],

    'modelschema_errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/modelschema-errors.log'),
        'level' => 'warning',
        'days' => 30,
        'permission' => 0644,
        'formatter' => Monolog\Formatter\LineFormatter::class,
        'formatter_with' => [
            'format' => "[%datetime%] %level_name%: %message%\n%context%\n%extra%\n",
            'dateFormat' => 'Y-m-d H:i:s',
            'allowInlineLineBreaks' => true,
            'includeStacktraces' => true,
        ],
    ],
];
