<?php

declare(strict_types=1);

/**
 * Example: Custom Field Types Validation Usage
 *
 * This example demonstrates how to use the custom field types validation
 * functionality to ensure your schema definitions are correct.
 */

require_once __DIR__.'/../vendor/autoload.php';

use Grazulex\LaravelModelschema\Services\SchemaService;

// Initialize the schema service
$schemaService = new SchemaService();

echo "=== Custom Field Types Validation Examples ===\n\n";

// Example 1: Valid enum field configuration
echo "1. Testing valid enum field configuration...\n";
$validEnumSchemas = [
    (object) [
        'name' => 'User',
        'fields' => [
            (object) [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'inactive', 'pending'],
                'default' => 'active',
            ],
            (object) [
                'name' => 'role',
                'type' => 'enum',
                'values' => ['admin', 'user', 'guest'],
                'default' => 'user',
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($validEnumSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo 'Custom fields found: '.$result['validation_summary']['custom_fields_found']."\n";
echo 'Custom types used: '.implode(', ', array_keys($result['custom_type_stats']))."\n\n";

// Example 2: Invalid enum field (missing values)
echo "2. Testing invalid enum field configuration...\n";
$invalidEnumSchemas = [
    (object) [
        'name' => 'Product',
        'fields' => [
            (object) [
                'name' => 'status',
                'type' => 'enum',
                // Missing 'values' array
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($invalidEnumSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo "Errors found:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo "\n";

// Example 3: Valid set field configuration
echo "3. Testing valid set field configuration...\n";
$validSetSchemas = [
    (object) [
        'name' => 'UserPermissions',
        'fields' => [
            (object) [
                'name' => 'permissions',
                'type' => 'set',
                'values' => ['read', 'write', 'delete', 'admin'],
                'default' => ['read'],
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($validSetSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo 'Custom types stats: '.json_encode($result['custom_type_stats'])."\n\n";

// Example 4: Invalid set field (too many values)
echo "4. Testing set field with too many values...\n";
$invalidSetSchemas = [
    (object) [
        'name' => 'BigPermissions',
        'fields' => [
            (object) [
                'name' => 'permissions',
                'type' => 'set',
                'values' => array_map(fn ($i) => "perm_$i", range(1, 70)), // Too many values for MySQL SET
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($invalidSetSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo "Errors found:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo "\n";

// Example 5: Valid geographic field configurations
echo "5. Testing geographic field configurations...\n";
$geoSchemas = [
    (object) [
        'name' => 'Location',
        'fields' => [
            (object) [
                'name' => 'coordinates',
                'type' => 'point',
                'srid' => 4326,
                'dimension' => 2,
            ],
            (object) [
                'name' => 'area',
                'type' => 'polygon',
                'srid' => 4326,
                'min_points' => 3,
                'max_points' => 1000,
            ],
            (object) [
                'name' => 'shape',
                'type' => 'geometry',
                'srid' => 3857,
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($geoSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo 'Geographic types found: '.json_encode($result['custom_type_stats'])."\n";
if (! empty($result['warnings'])) {
    echo "Warnings:\n";
    foreach ($result['warnings'] as $warning) {
        echo "  - $warning\n";
    }
}
echo "\n";

// Example 6: Invalid geographic field configuration
echo "6. Testing invalid geographic field configuration...\n";
$invalidGeoSchemas = [
    (object) [
        'name' => 'BadLocation',
        'fields' => [
            (object) [
                'name' => 'coordinates',
                'type' => 'point',
                'srid' => -1, // Invalid SRID
                'dimension' => 5, // Invalid dimension
            ],
            (object) [
                'name' => 'boundary',
                'type' => 'polygon',
                'min_points' => 2, // Too few points for polygon
                'max_points' => 1, // Max less than min
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($invalidGeoSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo "Errors found:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo "\n";

// Example 7: Field attribute validation
echo "7. Testing field attribute validation...\n";
$attributeSchemas = [
    (object) [
        'name' => 'Product',
        'fields' => [
            (object) [
                'name' => 'name',
                'type' => 'string',
                'length' => 255,
            ],
            (object) [
                'name' => 'price',
                'type' => 'decimal',
                'precision' => 10,
                'scale' => 2,
                'unsigned' => true,
            ],
            (object) [
                'name' => 'invalid_field',
                'type' => 'string',
                'length' => -5, // Invalid length
            ],
            (object) [
                'name' => 'bad_decimal',
                'type' => 'decimal',
                'precision' => 70, // Too large
                'scale' => 35, // Too large
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($attributeSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo "Errors found:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo "\n";

// Example 8: Unknown custom field type
echo "8. Testing unknown custom field type...\n";
$unknownTypeSchemas = [
    (object) [
        'name' => 'Unknown',
        'fields' => [
            (object) [
                'name' => 'weird_field',
                'type' => 'nonexistent_type',
            ],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($unknownTypeSchemas);
echo 'Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo "Errors found:\n";
foreach ($result['errors'] as $error) {
    echo "  - $error\n";
}
echo 'Available custom types: '.implode(', ', $result['available_custom_types'])."\n\n";

// Example 9: Comprehensive validation summary
echo "9. Comprehensive validation with mixed schemas...\n";
$mixedSchemas = [
    (object) [
        'name' => 'User',
        'fields' => [
            (object) ['name' => 'status', 'type' => 'enum', 'values' => ['active', 'inactive']],
            (object) ['name' => 'permissions', 'type' => 'set', 'values' => ['read', 'write']],
            (object) ['name' => 'name', 'type' => 'string', 'length' => 255],
        ],
    ],
    (object) [
        'name' => 'Location',
        'fields' => [
            (object) ['name' => 'coordinates', 'type' => 'point', 'srid' => 4326],
            (object) ['name' => 'boundary', 'type' => 'polygon'],
        ],
    ],
    (object) [
        'name' => 'Product',
        'fields' => [
            (object) ['name' => 'category', 'type' => 'enum', 'values' => ['electronics', 'clothing', 'books']],
            (object) ['name' => 'price', 'type' => 'decimal', 'precision' => 10, 'scale' => 2],
        ],
    ],
];

$result = $schemaService->validateCustomFieldTypes($mixedSchemas);
echo "Overall validation results:\n";
echo '  Valid: '.($result['is_valid'] ? 'YES' : 'NO')."\n";
echo '  Total schemas: '.count($mixedSchemas)."\n";
echo '  Total fields validated: '.$result['validation_summary']['total_fields_validated']."\n";
echo '  Custom fields found: '.$result['validation_summary']['custom_fields_found']."\n";
echo '  Unique custom types: '.$result['validation_summary']['unique_custom_types']."\n";
echo "  Custom type statistics:\n";
foreach ($result['custom_type_stats'] as $type => $count) {
    echo "    $type: $count\n";
}
echo '  Errors: '.$result['validation_summary']['errors_found']."\n";
echo '  Warnings: '.$result['validation_summary']['warnings_found']."\n";

echo "\n=== Custom Field Types Validation Examples Complete ===\n";
