<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Schema Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for Laravel ModelSchema.
    | You can customize how models are generated, validated, and documented.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Schema Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configure how model schemas are generated and managed.
    |
    */
    'generation' => [
        'auto_generate' => true,
        'output_path' => storage_path('app/schemas'),
        'format' => 'json', // json, yaml, php
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define default validation rules for different field types.
    |
    */
    'validation' => [
        'strict_mode' => true,
        'auto_validate' => true,
        'custom_rules' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic documentation generation for models.
    |
    */
    'documentation' => [
        'auto_generate' => true,
        'format' => 'markdown',
        'output_path' => base_path('docs/models'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Helpers
    |--------------------------------------------------------------------------
    |
    | Settings for automatic migration generation and management.
    |
    */
    'migrations' => [
        'auto_generate' => false,
        'backup_existing' => true,
        'timestamp_format' => 'Y_m_d_His',
    ],
];
