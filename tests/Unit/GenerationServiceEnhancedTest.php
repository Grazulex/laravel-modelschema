<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

describe('GenerationService with Enhanced Generators', function () {
    beforeEach(function () {
        $this->service = new GenerationService();
        $this->schema = ModelSchema::fromArray('Product', [
            'table' => 'products',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'name' => ['type' => 'string', 'nullable' => false],
                'description' => ['type' => 'text', 'nullable' => true],
                'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
                'published' => ['type' => 'boolean', 'default' => false],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
            'relationships' => [
                'category' => [
                    'type' => 'belongsTo',
                    'model' => 'App\Models\Category',
                ],
                'reviews' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Review',
                ],
                'tags' => [
                    'type' => 'belongsToMany',
                    'model' => 'App\Models\Tag',
                    'pivot_table' => 'product_tags',
                ],
            ],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => false,
            ],
        ]);
    });

    it('includes ControllerGenerator in available generators', function () {
        $generators = $this->service->getAvailableGeneratorNames();

        expect($generators)->toContain('model');
        expect($generators)->toContain('migration');
        expect($generators)->toContain('request');
        expect($generators)->toContain('resource');
        expect($generators)->toContain('factory');
        expect($generators)->toContain('seeder');
        expect($generators)->toContain('controller');
        expect($generators)->toContain('test');
        expect($generators)->toContain('policies');

        expect(count($generators))->toBe(9); // Maintenant 9 avec TestGenerator et PolicyGenerator
    });

    it('can generate controllers through the service', function () {
        $result = $this->service->generateControllers($this->schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('controllers');

        $controllers = $jsonData['controllers'];
        expect($controllers)->toHaveKey('api_controller');
        expect($controllers)->toHaveKey('web_controller');
        expect($controllers)->toHaveKey('resource_routes');

        expect($controllers['api_controller']['name'])->toBe('ProductApiController');
        expect($controllers['web_controller']['name'])->toBe('ProductController');
    });

    it('can generate tests through the service', function () {
        $result = $this->service->generateTests($this->schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('tests');

        $tests = $jsonData['tests'];
        expect($tests)->toHaveKey('feature_tests');
        expect($tests)->toHaveKey('unit_tests');
        expect($tests)->toHaveKey('test_traits');
        expect($tests)->toHaveKey('factories_needed');

        expect($tests['feature_tests'])->toBeArray();
        expect($tests['unit_tests'])->toBeArray();
    });

    it('can generate all components including enhanced resources and controllers', function () {
        $generators = ['model', 'migration', 'request', 'resource', 'factory', 'seeder', 'controller'];
        $result = $this->service->generateMultiple($this->schema, $generators);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);

        // Should contain all generated components
        expect($jsonData)->toHaveKey('model');
        expect($jsonData)->toHaveKey('migration');
        expect($jsonData)->toHaveKey('requests');
        expect($jsonData)->toHaveKey('resources');
        expect($jsonData)->toHaveKey('factory');
        expect($jsonData)->toHaveKey('seeder');
        expect($jsonData)->toHaveKey('controllers');

        // Enhanced resources should have all new features
        $resources = $jsonData['resources'];
        expect($resources)->toHaveKey('main_resource');
        expect($resources)->toHaveKey('partial_resources');
        expect($resources)->toHaveKey('relationship_resources');

        // Controllers should have full configuration
        $controllers = $jsonData['controllers'];
        expect($controllers)->toHaveKey('api_controller');
        expect($controllers)->toHaveKey('web_controller');
        expect($controllers)->toHaveKey('middleware');
        expect($controllers)->toHaveKey('resource_routes');
    });

    it('generates cohesive fragments that work together', function () {
        $generators = ['resource', 'controller'];
        $result = $this->service->generateMultiple($this->schema, $generators);

        $jsonData = json_decode($result['json'], true);

        // Check that controller references the correct resource
        $apiController = $jsonData['controllers']['api_controller'];
        $mainResource = $jsonData['resources']['main_resource'];

        expect($apiController['response_resource'])->toBe($mainResource['name']);
        expect($apiController['collection_resource'])->toBe($jsonData['resources']['collection_resource']['name']);

        // Check that both use the same model
        expect($apiController['model'])->toBe('App\Models\Product');
        expect($mainResource['model'])->toBe('App\Models\Product');

        // Check that validation rules are consistent
        $controllerValidation = $apiController['validation']['store'];
        $resourceFields = $mainResource['fields'];

        foreach ($resourceFields as $fieldName => $fieldConfig) {
            if (($fieldConfig['original_type'] ?? $fieldConfig['type']) !== 'timestamp' && $fieldName !== 'id') {
                expect($controllerValidation)->toHaveKey($fieldName);
            }
        }
    });

    it('handles custom options for multiple generators', function () {
        $options = [
            'resource' => [
                'namespace' => 'App\Http\Resources\V2',
                'enable_filtering' => false,
            ],
            'controller' => [
                'api_controller_namespace' => 'App\Http\Controllers\Api\V2',
                'enable_policies' => false,
            ],
        ];

        $generators = ['resource', 'controller'];
        $result = $this->service->generateMultiple($this->schema, $generators, $options);

        $jsonData = json_decode($result['json'], true);

        // Check resource options
        $mainResource = $jsonData['resources']['main_resource'];
        expect($mainResource['namespace'])->toBe('App\Http\Resources\V2');

        $collectionResource = $jsonData['resources']['collection_resource'];
        expect($collectionResource['filtering']['enabled'])->toBe(false);

        // Check controller options
        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController['namespace'])->toBe('App\Http\Controllers\Api\V2');

        $policies = $jsonData['controllers']['policies'];
        expect($policies)->toBeEmpty();
    });

    it('validates schemas before generation when validation is enabled', function () {
        // Create a schema with potential issues
        $problematicSchema = ModelSchema::fromArray('ProblematicModel', [
            'table' => 'problematic_models',
            'fields' => array_merge(
                ['id' => ['type' => 'bigInteger']],
                // Many fields to trigger performance warnings
                array_fill_keys(
                    array_map(fn ($i) => "field_$i", range(1, 25)),
                    ['type' => 'string']
                )
            ),
            'relationships' => [
                'relation1' => ['type' => 'hasMany', 'model' => 'App\Models\Model1'],
                'relation2' => ['type' => 'hasMany', 'model' => 'App\Models\Model2'],
                'relation3' => ['type' => 'hasMany', 'model' => 'App\Models\Model3'],
                'relation4' => ['type' => 'hasMany', 'model' => 'App\Models\Model4'],
                'relation5' => ['type' => 'hasMany', 'model' => 'App\Models\Model5'],
            ],
        ]);

        $options = ['enable_validation' => true];
        $result = $this->service->generateMultiple($problematicSchema, ['model', 'resource'], $options);

        $jsonData = json_decode($result['json'], true);

        // Should include validation results
        expect($jsonData)->toHaveKey('validation_results');

        $validation = $jsonData['validation_results'];
        expect($validation)->toHaveKey('performance_analysis');
        expect($validation['performance_analysis'])->toHaveKey('warnings');
        expect($validation['performance_analysis']['warnings'])->not->toBeEmpty();
    });

    it('generates consistent naming across all components', function () {
        $generators = ['model', 'migration', 'request', 'resource', 'controller'];
        $result = $this->service->generateMultiple($this->schema, $generators);

        $jsonData = json_decode($result['json'], true);

        $modelName = 'Product';

        // Check consistent naming
        expect($jsonData['model']['name'])->toBe($modelName);
        expect($jsonData['migration']['table'])->toBe('products');
        expect($jsonData['requests']['store']['name'])->toBe('Store'.$modelName.'Request');
        expect($jsonData['requests']['update']['name'])->toBe('Update'.$modelName.'Request');
        expect($jsonData['resources']['main_resource']['name'])->toBe($modelName.'Resource');
        expect($jsonData['resources']['collection_resource']['name'])->toBe($modelName.'Collection');
        expect($jsonData['controllers']['api_controller']['name'])->toBe($modelName.'ApiController');
        expect($jsonData['controllers']['web_controller']['name'])->toBe($modelName.'Controller');
    });

    it('can generate single components with enhanced features', function () {
        // Test enhanced resource generation
        $resourceResult = $this->service->generateResources($this->schema, ['enhanced' => true]);
        $resourceData = json_decode($resourceResult['json'], true);

        expect($resourceData['resources'])->toHaveKey('partial_resources');
        expect($resourceData['resources'])->toHaveKey('relationship_resources');

        // Test controller generation
        $controllerResult = $this->service->generateControllers($this->schema, ['enhanced' => true]);
        $controllerData = json_decode($controllerResult['json'], true);

        expect($controllerData['controllers'])->toHaveKey('middleware');
        expect($controllerData['controllers'])->toHaveKey('policies');
        expect($controllerData['controllers'])->toHaveKey('resource_routes');
    });

    it('handles relationships consistently across generators', function () {
        $generators = ['resource', 'controller'];
        $result = $this->service->generateMultiple($this->schema, $generators);

        $jsonData = json_decode($result['json'], true);

        // Check that relationships are handled consistently
        $resourceRelationships = $jsonData['resources']['main_resource']['relationships'];
        $controllerRelationships = $jsonData['controllers']['api_controller']['relationships'];

        expect($resourceRelationships)->toHaveKey('category');
        expect($resourceRelationships)->toHaveKey('reviews');
        expect($resourceRelationships)->toHaveKey('tags');

        expect($controllerRelationships)->toHaveKey('category');
        expect($controllerRelationships)->toHaveKey('reviews');
        expect($controllerRelationships)->toHaveKey('tags');

        // Check relationship types match
        expect($resourceRelationships['category']['type'])->toBe('belongsTo');
        expect($controllerRelationships['category']['type'])->toBe('belongsTo');

        expect($resourceRelationships['reviews']['type'])->toBe('hasMany');
        expect($controllerRelationships['reviews']['type'])->toBe('hasMany');

        expect($resourceRelationships['tags']['type'])->toBe('belongsToMany');
        expect($controllerRelationships['tags']['type'])->toBe('belongsToMany');
    });
});
