<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\SchemaService;

describe('Custom Field Types Validation Integration', function () {
    beforeEach(function () {
        $this->schemaService = new SchemaService();
    });

    it('validates enum field configuration through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'status',
                        'type' => 'enum',
                        'values' => ['active', 'inactive', 'pending'],
                        'default' => 'active',
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(true);
        expect($result['errors'])->toBeEmpty();
        expect($result['custom_type_stats']['enum'])->toBe(1);
        expect($result['validation_summary']['custom_fields_found'])->toBe(1);
    });

    it('detects enum field validation errors through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'status',
                        'type' => 'enum',
                        // Missing 'values' array
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(false);
        expect($result['errors'])->toContain("Enum field 'status' in schema 'TestModel' must have 'values' array defined");
    });

    it('validates set field configuration through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'permissions',
                        'type' => 'set',
                        'values' => ['read', 'write', 'delete'],
                        'default' => ['read', 'write'],
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(true);
        expect($result['errors'])->toBeEmpty();
        expect($result['custom_type_stats']['set'])->toBe(1);
    });

    it('validates point field configuration through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'location',
                        'type' => 'point',
                        'srid' => 4326,
                        'dimension' => 2,
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(true);
        expect($result['errors'])->toBeEmpty();
        expect($result['custom_type_stats']['point'])->toBe(1);
    });

    it('detects unknown custom field types through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'unknown_field',
                        'type' => 'nonexistent_type',
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(false);
        expect($result['errors'])->toContain("Unknown custom field type 'nonexistent_type' in field 'unknown_field' of schema 'TestModel'. Available custom types: enum, enumeration, set, multi_select, multiple_choice, point, geopoint, coordinates, latlng, geometry, geom, spatial, geo, polygon, area, boundary, region");
    });

    it('validates field attributes correctly through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
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
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(true);
        expect($result['errors'])->toBeEmpty();
    });

    it('detects invalid field attributes through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'TestModel',
                'fields' => [
                    (object) [
                        'name' => 'name',
                        'type' => 'string',
                        'length' => -5,  // Invalid length
                    ],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['is_valid'])->toBe(false);
        expect($result['errors'])->toContain("Field 'name' in schema 'TestModel' has invalid length: must be a positive number");
    });

    it('provides comprehensive validation summary through SchemaService', function () {
        $schemas = [
            (object) [
                'name' => 'User',
                'fields' => [
                    (object) ['name' => 'status', 'type' => 'enum', 'values' => ['active', 'inactive']],
                    (object) ['name' => 'permissions', 'type' => 'set', 'values' => ['read', 'write']],
                    (object) ['name' => 'name', 'type' => 'string'],
                ],
            ],
            (object) [
                'name' => 'Location',
                'fields' => [
                    (object) ['name' => 'coordinates', 'type' => 'point'],
                    (object) ['name' => 'boundary', 'type' => 'polygon'],
                ],
            ],
        ];

        $result = $this->schemaService->validateCustomFieldTypes($schemas);

        expect($result['validation_summary']['total_fields_validated'])->toBe(5);
        expect($result['validation_summary']['custom_fields_found'])->toBe(4);
        expect($result['validation_summary']['unique_custom_types'])->toBe(4);

        expect($result['custom_type_stats'])->toHaveKey('enum');
        expect($result['custom_type_stats'])->toHaveKey('set');
        expect($result['custom_type_stats'])->toHaveKey('point');
        expect($result['custom_type_stats'])->toHaveKey('polygon');
    });
});
