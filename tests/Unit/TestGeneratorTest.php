<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\TestGenerator;

beforeEach(function () {
    $this->generator = new TestGenerator();
});

describe('TestGenerator', function () {
    test('it has correct basic properties', function () {
        expect($this->generator->getGeneratorName())->toBe('test');
        expect($this->generator->getAvailableFormats())->toBe(['json', 'yaml']);
    });

    test('it generates tests data in JSON format as insertable fragment', function () {
        $schema = ModelSchema::fromArray('User', [
            'model' => 'User',
            'table' => 'users',
            'fields' => [
                'name' => [
                    'type' => 'string',
                    'nullable' => false,
                    'length' => 255,
                ],
                'email' => [
                    'type' => 'email',
                    'nullable' => false,
                    'unique' => true,
                ],
                'active' => [
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'Post',
                ],
            ],
        ]);

        $result = $this->generator->generate($schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');
        expect($result)->toHaveKey('metadata');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('tests');
        expect($jsonData['tests'])->toHaveKey('feature_tests');
        expect($jsonData['tests'])->toHaveKey('unit_tests');
        expect($jsonData['tests'])->toHaveKey('test_traits');
        expect($jsonData['tests'])->toHaveKey('factories_needed');
    });

    test('it generates feature tests correctly', function () {
        $schema = ModelSchema::fromArray('Product', [
            'model' => 'Product',
            'table' => 'products',
            'fields' => [
                'name' => [
                    'type' => 'string',
                    'nullable' => false,
                ],
                'price' => [
                    'type' => 'decimal',
                    'nullable' => false,
                ],
            ],
        ]);

        $result = $this->generator->generate($schema, ['api_routes' => true, 'web_routes' => true]);
        $jsonData = json_decode($result['json'], true);

        $featureTests = $jsonData['tests']['feature_tests'];
        expect($featureTests)->toHaveCount(2); // API and Web tests

        // Vérifier les tests API
        $apiTest = collect($featureTests)->firstWhere('type', 'api_crud');
        expect($apiTest)->not->toBeNull();
        expect($apiTest['name'])->toBe('ProductApiTest');
        expect($apiTest['methods'])->toContain('test_can_create_product');
        expect($apiTest['methods'])->toContain('test_can_read_product');
        expect($apiTest['methods'])->toContain('test_validates_required_fields');

        // Vérifier les tests Web
        $webTest = collect($featureTests)->firstWhere('type', 'web_crud');
        expect($webTest)->not->toBeNull();
        expect($webTest['name'])->toBe('ProductWebTest');
        expect($webTest['methods'])->toContain('test_can_view_index_page');
        expect($webTest['methods'])->toContain('test_can_store_product');
    });

    test('it generates unit tests correctly', function () {
        $schema = ModelSchema::fromArray('Order', [
            'model' => 'Order',
            'table' => 'orders',
            'fields' => [
                'total' => [
                    'type' => 'decimal',
                    'nullable' => false,
                ],
                'status' => [
                    'type' => 'string',
                    'nullable' => false,
                ],
                'created_at' => [
                    'type' => 'timestamp',
                ],
                'deleted_at' => [
                    'type' => 'timestamp',
                ],
            ],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User',
                ],
                'items' => [
                    'type' => 'hasMany',
                    'model' => 'OrderItem',
                ],
            ],
        ]);

        $result = $this->generator->generate($schema);
        $jsonData = json_decode($result['json'], true);

        $unitTests = $jsonData['tests']['unit_tests'];
        expect($unitTests)->toHaveCount(3); // Order + 2 relationships

        // Test du modèle principal
        $modelTest = collect($unitTests)->firstWhere('type', 'model');
        expect($modelTest)->not->toBeNull();
        expect($modelTest['name'])->toBe('OrderTest');
        expect($modelTest['methods'])->toContain('test_fillable_attributes');
        expect($modelTest['methods'])->toContain('test_has_user_relationship');
        expect($modelTest['methods'])->toContain('test_has_items_relationship');

        // Tests des relations
        $relationshipTests = collect($unitTests)->where('type', 'relationship');
        expect($relationshipTests)->toHaveCount(2);
    });

    test('it identifies test traits correctly', function () {
        $schema = ModelSchema::fromArray('Post', [
            'model' => 'Post',
            'table' => 'posts',
            'fields' => [
                'title' => [
                    'type' => 'string',
                ],
                'created_at' => [
                    'type' => 'timestamp',
                ],
                'updated_at' => [
                    'type' => 'timestamp',
                ],
                'deleted_at' => [
                    'type' => 'timestamp',
                ],
                'featured_image' => [
                    'type' => 'string',
                ],
            ],
        ]);

        $result = $this->generator->generate($schema);
        $jsonData = json_decode($result['json'], true);

        $traits = $jsonData['tests']['test_traits'];
        expect($traits)->toContain('RefreshDatabase');
        expect($traits)->toContain('WithTimestamps');
        expect($traits)->toContain('WithSoftDeletes');
        expect($traits)->toContain('WithFileUploads');
    });

    test('it generates validation test values', function () {
        $schema = ModelSchema::fromArray('Article', [
            'model' => 'Article',
            'table' => 'articles',
            'fields' => [
                'title' => [
                    'type' => 'string',
                    'nullable' => false, // required
                    'length' => 100, // max_length
                ],
                'email' => [
                    'type' => 'email',
                    'nullable' => false, // required
                ],
                'published' => [
                    'type' => 'boolean',
                ],
            ],
        ]);

        $result = $this->generator->generate($schema);
        $jsonData = json_decode($result['json'], true);

        $fieldsToTest = $jsonData['tests']['feature_tests'][0]['fields_to_test'];

        // Vérifier le champ title
        $titleField = collect($fieldsToTest)->firstWhere('name', 'title');
        expect($titleField['test_values']['valid'])->toBe('Test Title');

        // Debug: voir ce qui est généré
        // dd($titleField['test_values']['invalid']);

        expect($titleField['test_values']['invalid'])->toContain(['value' => null, 'error' => 'required']);
        expect($titleField['test_values']['invalid'])->toContain(['value' => str_repeat('a', 101), 'error' => 'max']);

        // Vérifier le champ email
        $emailField = collect($fieldsToTest)->firstWhere('name', 'email');
        expect($emailField['test_values']['valid'])->toBe('test@example.com');
        expect($emailField['test_values']['invalid'])->toContain(['value' => 'invalid-email', 'error' => 'email']);
    });

    test('it can generate with custom options', function () {
        $schema = ModelSchema::fromArray('Comment', [
            'model' => 'Comment',
            'table' => 'comments',
            'fields' => [
                'content' => [
                    'type' => 'text',
                    'nullable' => false,
                ],
            ],
        ]);

        $options = [
            'feature_namespace' => 'Tests\\Custom\\Feature',
            'unit_namespace' => 'Tests\\Custom\\Unit',
            'api_routes' => false,
            'web_routes' => true,
            'route_prefix' => 'admin/comments',
            'middleware' => ['auth', 'admin'],
        ];

        $result = $this->generator->generate($schema, $options);
        $jsonData = json_decode($result['json'], true);

        expect($jsonData['tests']['feature_tests'])->toHaveCount(1); // Only web tests
        $webTest = $jsonData['tests']['feature_tests'][0];
        expect($webTest['namespace'])->toBe('Tests\\Custom\\Feature');
        expect($webTest['route_prefix'])->toBe('admin/comments');
        expect($webTest['middleware'])->toBe(['auth', 'admin']);
    });

    test('it generates proper YAML format', function () {
        $schema = ModelSchema::fromArray('Category', [
            'model' => 'Category',
            'table' => 'categories',
            'fields' => [
                'name' => [
                    'type' => 'string',
                    'nullable' => false,
                ],
            ],
        ]);

        $result = $this->generator->generate($schema);

        expect($result['yaml'])->toBeString();
        expect($result['yaml'])->toContain('tests:');
        expect($result['yaml'])->toContain('feature_tests:');
        expect($result['yaml'])->toContain('unit_tests:');

        // Vérifier que le YAML est valide
        $yamlData = Symfony\Component\Yaml\Yaml::parse($result['yaml']);
        expect($yamlData)->toHaveKey('tests');
    });
});
