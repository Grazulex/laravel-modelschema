<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;

describe('EnhancedValidationService', function () {
    beforeEach(function () {
        $this->service = new EnhancedValidationService();
    });

    it('validates relationships properly', function () {
        $schema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'length' => 255], // Add length to avoid warning
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Post',
                    'foreignKey' => 'user_id', // Add foreign key to avoid warning
                ],
                'profile' => [
                    'type' => 'hasOne',
                    'model' => 'App\Models\Profile',
                    'foreignKey' => 'user_id', // Add foreign key to avoid warning
                ],
                'roles' => [
                    'type' => 'belongsToMany',
                    'model' => 'App\Models\Role',
                    'pivot_table' => 'user_roles',
                ],
            ],
        ]);

        $result = $this->service->validateSchema($schema);

        expect($result['is_valid'])->toBe(true);
        expect($result['errors'])->toBeEmpty();
        expect($result['warnings'])->toBeEmpty();
        expect($result['recommendations'])->toBeArray();
    });

    it('detects circular dependencies in relationships', function () {
        $schemas = [
            ModelSchema::fromArray('User', [
                'table' => 'users',
                'fields' => ['id' => ['type' => 'bigInteger']],
                'relationships' => [
                    'posts' => ['type' => 'hasMany', 'model' => 'App\Models\Post'],
                ],
            ]),
            ModelSchema::fromArray('Post', [
                'table' => 'posts',
                'fields' => ['id' => ['type' => 'bigInteger']],
                'relationships' => [
                    'user' => ['type' => 'belongsTo', 'model' => 'App\Models\User'],
                    'comments' => ['type' => 'hasMany', 'model' => 'App\Models\Comment'],
                ],
            ]),
            ModelSchema::fromArray('Comment', [
                'table' => 'comments',
                'fields' => ['id' => ['type' => 'bigInteger']],
                'relationships' => [
                    'post' => ['type' => 'belongsTo', 'model' => 'App\Models\Post'],
                    'user' => ['type' => 'belongsTo', 'model' => 'App\Models\User'],
                ],
            ]),
        ];

        $result = $this->service->validateRelationshipConsistency($schemas);

        expect($result['circular_dependencies'])->toBeEmpty();
        expect($result['missing_reverse_relationships'])->toBeEmpty();
        expect($result['is_consistent'])->toBe(true);
    });

    it('validates field types and compatibility', function () {
        $schema = ModelSchema::fromArray('Product', [
            'table' => 'products',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'name' => ['type' => 'string', 'nullable' => false, 'rules' => ['required', 'string', 'max:255']],
                'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2, 'nullable' => false],
                'published' => ['type' => 'boolean', 'default' => false],
                'tags' => ['type' => 'json', 'nullable' => true],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
        ]);

        $result = $this->service->validateFieldTypes($schema);

        expect($result['is_valid'])->toBe(true);
        expect($result['field_errors'])->toBeEmpty();
        expect($result['type_compatibility'])->toBeArray();

        // Check that all fields have been validated
        expect($result['validated_fields'])->toContain('id');
        expect($result['validated_fields'])->toContain('name');
        expect($result['validated_fields'])->toContain('price');
        expect($result['validated_fields'])->toContain('published');
        expect($result['validated_fields'])->toContain('tags');
    });

    it('detects invalid field configurations', function () {
        $schema = ModelSchema::fromArray('InvalidModel', [
            'table' => 'invalid_models',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'invalid_decimal' => ['type' => 'decimal'], // Missing precision/scale
                'invalid_string' => ['type' => 'string', 'length' => -1], // Invalid length
                'conflicting_field' => ['type' => 'string', 'nullable' => false, 'default' => null], // Conflict
            ],
        ]);

        $result = $this->service->validateFieldTypes($schema);

        expect($result['is_valid'])->toBe(false);
        expect($result['field_errors'])->not->toBeEmpty();

        // Should detect decimal without precision
        expect($result['field_errors'])->toHaveKey('invalid_decimal');

        // Should detect invalid string length
        expect($result['field_errors'])->toHaveKey('invalid_string');

        // Should detect nullable/default conflict
        expect($result['field_errors'])->toHaveKey('conflicting_field');
    });

    it('provides performance analysis and recommendations', function () {
        $schema = ModelSchema::fromArray('LargeModel', [
            'table' => 'large_models',
            'fields' => array_merge(
                ['id' => ['type' => 'bigInteger']],
                // Create many fields to trigger performance warnings
                array_fill_keys(
                    array_map(fn ($i) => "field_$i", range(1, 30)),
                    ['type' => 'string']
                )
            ),
            'relationships' => [
                'relation1' => ['type' => 'hasMany', 'model' => 'App\Models\Model1'],
                'relation2' => ['type' => 'hasMany', 'model' => 'App\Models\Model2'],
                'relation3' => ['type' => 'hasMany', 'model' => 'App\Models\Model3'],
                'relation4' => ['type' => 'hasMany', 'model' => 'App\Models\Model4'],
                'relation5' => ['type' => 'hasMany', 'model' => 'App\Models\Model5'],
                'relation6' => ['type' => 'hasMany', 'model' => 'App\Models\Model6'],
            ],
        ]);

        $result = $this->service->analyzePerformance($schema);

        expect($result)->toHaveKey('field_count');
        expect($result)->toHaveKey('relationship_count');
        expect($result)->toHaveKey('warnings');
        expect($result)->toHaveKey('recommendations');

        expect($result['field_count'])->toBeGreaterThan(25);
        expect($result['relationship_count'])->toBe(6);

        // Should have performance warnings
        expect($result['warnings'])->not->toBeEmpty();
        expect($result['recommendations'])->not->toBeEmpty();
    });

    it('validates schema structure and required fields', function () {
        $validSchema = ModelSchema::fromArray('ValidModel', [
            'table' => 'valid_models',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
            ],
        ]);

        $result = $this->service->validateSchema($validSchema);
        expect($result['is_valid'])->toBe(true);

        // Test with missing required fields
        $invalidSchema = ModelSchema::fromArray('', [  // Empty name
            'table' => 'invalid_models',
            'fields' => [], // No fields
        ]);

        $result = $this->service->validateSchema($invalidSchema);
        expect($result['is_valid'])->toBe(false);
        expect($result['errors'])->not->toBeEmpty();
    });

    it('detects relationship inconsistencies', function () {
        $user = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'posts' => ['type' => 'hasMany', 'model' => 'App\Models\Post'],
            ],
        ]);

        $post = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                // Missing user relationship - should be detected
            ],
        ]);

        $result = $this->service->validateRelationshipConsistency([$user, $post]);

        expect($result['is_consistent'])->toBe(false);
        expect($result['missing_reverse_relationships'])->not->toBeEmpty();
    });

    it('provides comprehensive validation report', function () {
        $schema = ModelSchema::fromArray('TestModel', [
            'table' => 'test_models',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'rules' => ['required']],
                'email' => ['type' => 'string', 'unique' => true],
            ],
            'relationships' => [
                'items' => ['type' => 'hasMany', 'model' => 'App\Models\Item'],
            ],
        ]);

        $result = $this->service->validateSchema($schema);

        expect($result)->toHaveKey('is_valid');
        expect($result)->toHaveKey('errors');
        expect($result)->toHaveKey('warnings');
        expect($result)->toHaveKey('recommendations');
        expect($result)->toHaveKey('performance_analysis');
        expect($result)->toHaveKey('field_validation');
        expect($result)->toHaveKey('relationship_validation');

        // Performance analysis should be included
        expect($result['performance_analysis'])->toHaveKey('field_count');
        expect($result['performance_analysis'])->toHaveKey('relationship_count');

        // Field validation should be included
        expect($result['field_validation'])->toHaveKey('validated_fields');
        expect($result['field_validation'])->toHaveKey('type_compatibility');

        // Relationship validation should be included
        expect($result['relationship_validation'])->toHaveKey('relationship_types');
    });

    it('handles edge cases gracefully', function () {
        // Empty schema
        $emptySchema = ModelSchema::fromArray('', [
            'table' => '',
            'fields' => [],
        ]);

        $result = $this->service->validateSchema($emptySchema);
        expect($result['is_valid'])->toBe(false);
        expect($result['errors'])->not->toBeEmpty();

        // Schema with only relationships, no fields
        $relationshipOnlySchema = ModelSchema::fromArray('RelationshipOnly', [
            'table' => 'relationship_only',
            'fields' => [],
            'relationships' => [
                'items' => ['type' => 'hasMany', 'model' => 'App\Models\Item'],
            ],
        ]);

        $result = $this->service->validateSchema($relationshipOnlySchema);
        expect($result['is_valid'])->toBe(false);
        expect($result['warnings'])->not->toBeEmpty();
    });
});
