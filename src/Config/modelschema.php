<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model Schema Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for Laravel ModelSchema.
    | You can customize how model schemas are parsed, validated, and processed.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Schema Paths
    |--------------------------------------------------------------------------
    |
    | Configure where schema files are stored and loaded from.
    |
    */
    'paths' => [
        'schemas' => resource_path('schemas'),
        'output' => storage_path('app/schemas'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Field Types
    |--------------------------------------------------------------------------
    |
    | Configure paths and namespaces for custom field type implementations.
    | This allows developers to create and register their own field types.
    |
    */
    'custom_field_types_path' => app_path('FieldTypes'),
    'custom_field_types_namespace' => 'App\\FieldTypes',

    /*
    |--------------------------------------------------------------------------
    | File Settings
    |--------------------------------------------------------------------------
    |
    | Configure file extensions and formats.
    |
    */
    'files' => [
        'extension' => '.schema.yml',
        'formats' => ['yaml', 'json', 'php'],
        'auto_discovery' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generation Settings
    |--------------------------------------------------------------------------
    |
    | Configure automatic generation behavior for various components.
    |
    */
    'generation' => [
        'auto_generate' => true,
        'models' => true,
        'migrations' => false,
        'factories' => true,
        'seeders' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Settings
    |--------------------------------------------------------------------------
    |
    | Configure documentation generation behavior.
    |
    */
    'documentation' => [
        'auto_generate' => true,
        'format' => 'markdown',
        'output_path' => storage_path('docs/schemas'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Migrations Settings
    |--------------------------------------------------------------------------
    |
    | Configure migration generation and behavior.
    |
    */
    'migrations' => [
        'auto_generate' => false,
        'path' => database_path('migrations'),
        'table_prefix' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configure schema validation behavior.
    |
    */
    'validation' => [
        'strict_mode' => true,
        'validate_on_load' => true,
        'custom_field_types' => [],
        'custom_relationship_types' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configure caching for parsed schemas to improve performance.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'key_prefix' => 'modelschema:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Model Settings
    |--------------------------------------------------------------------------
    |
    | Default values for model options when not specified in schema.
    |
    */
    'defaults' => [
        'namespace' => 'App\\Models',
        'timestamps' => true,
        'soft_deletes' => false,
        'fillable_guarded' => true,
        'cast_dates' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Settings
    |--------------------------------------------------------------------------
    |
    | Configure export formats and behavior.
    |
    */
    'export' => [
        'pretty_print' => true,
        'include_metadata' => true,
        'date_format' => 'Y-m-d H:i:s',
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Type Mappings
    |--------------------------------------------------------------------------
    |
    | Map schema field types to their corresponding Laravel migration types.
    |
    */
    'field_type_mappings' => [
        'string' => 'string',
        'text' => 'text',
        'longText' => 'longText',
        'mediumText' => 'mediumText',
        'integer' => 'integer',
        'bigInteger' => 'bigInteger',
        'tinyInteger' => 'tinyInteger',
        'smallInteger' => 'smallInteger',
        'mediumInteger' => 'mediumInteger',
        'unsignedBigInteger' => 'unsignedBigInteger',
        'unsignedInteger' => 'unsignedInteger',
        'unsignedTinyInteger' => 'unsignedTinyInteger',
        'unsignedSmallInteger' => 'unsignedSmallInteger',
        'unsignedMediumInteger' => 'unsignedMediumInteger',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'boolean' => 'boolean',
        'date' => 'date',
        'datetime' => 'dateTime',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'year' => 'year',
        'json' => 'json',
        'uuid' => 'uuid',
        'email' => 'string', // Email is validated, not a DB type
        'url' => 'string',   // URL is validated, not a DB type
        'binary' => 'binary',
        'enum' => 'enum',
        'set' => 'set',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure detailed logging for debugging and monitoring ModelSchema
    | operations. This includes performance metrics, validation results,
    | generation statistics, and error tracking.
    |
    */
    'logging' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Logging
        |--------------------------------------------------------------------------
        |
        | Enable or disable detailed logging. When disabled, no ModelSchema
        | logs will be written, improving performance for production use.
        |
        */
        'enabled' => filter_var($_ENV['MODELSCHEMA_LOGGING_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),

        /*
        |--------------------------------------------------------------------------
        | Log Channel
        |--------------------------------------------------------------------------
        |
        | The log channel to use for ModelSchema logs. You can create a custom
        | channel in config/logging.php for better organization.
        |
        */
        'channel' => $_ENV['MODELSCHEMA_LOG_CHANNEL'] ?? 'modelschema',

        /*
        |--------------------------------------------------------------------------
        | Log Levels
        |--------------------------------------------------------------------------
        |
        | Configure which types of events to log at different levels.
        |
        */
        'levels' => [
            'operations' => 'info',      // Start/end of major operations
            'performance' => 'info',     // Performance metrics
            'validation' => 'info',      // Validation results
            'generation' => 'info',      // File/fragment generation
            'cache' => 'debug',          // Cache operations
            'yaml_parsing' => 'info',    // YAML parsing events
            'debug' => 'debug',          // General debug information
        ],

        /*
        |--------------------------------------------------------------------------
        | Performance Thresholds
        |--------------------------------------------------------------------------
        |
        | Configure thresholds for performance warnings. Operations exceeding
        | these thresholds will be logged with warnings.
        |
        */
        'performance_thresholds' => [
            'yaml_parsing_ms' => 1000,      // Warn if YAML parsing takes > 1s
            'validation_ms' => 2000,        // Warn if validation takes > 2s
            'generation_ms' => 3000,        // Warn if generation takes > 3s
            'cache_operation_ms' => 100,    // Warn if cache op takes > 100ms
            'memory_usage_mb' => 128,       // Warn if memory usage > 128MB
        ],

        /*
        |--------------------------------------------------------------------------
        | Context Tracking
        |--------------------------------------------------------------------------
        |
        | Track execution context and call stacks for better debugging.
        |
        */
        'track_context' => true,
        'max_context_depth' => 10,
        'include_memory_usage' => true,
        'include_timing' => true,

        /*
        |--------------------------------------------------------------------------
        | Error Details
        |--------------------------------------------------------------------------
        |
        | Configure how much detail to include in error logs.
        |
        */
        'error_details' => [
            'include_stack_trace' => true,
            'include_context_stack' => true,
            'include_request_data' => false, // Be careful with sensitive data
            'max_trace_lines' => 20,
        ],
    ],
];
