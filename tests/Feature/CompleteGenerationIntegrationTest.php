<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;

describe('Integration: Complete Fragment Generation', function () {
    beforeEach(function () {
        $this->generationService = new GenerationService();
        $this->validationService = new EnhancedValidationService();

        // Complex schema for comprehensive testing
        $this->complexSchema = ModelSchema::fromArray('Article', [
            'table' => 'articles',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'title' => ['type' => 'string', 'nullable' => false, 'rules' => ['required', 'string', 'max:255']],
                'slug' => ['type' => 'string', 'unique' => true, 'rules' => ['required', 'string', 'unique:articles']],
                'content' => ['type' => 'longText', 'nullable' => true],
                'excerpt' => ['type' => 'text', 'nullable' => true],
                'published' => ['type' => 'boolean', 'default' => false],
                'published_at' => ['type' => 'datetime', 'nullable' => true],
                'views_count' => ['type' => 'integer', 'default' => 0],
                'meta_data' => ['type' => 'json', 'nullable' => true],
                'featured_image' => ['type' => 'string', 'nullable' => true],
                'author_id' => ['type' => 'bigInteger', 'nullable' => false],
                'category_id' => ['type' => 'bigInteger', 'nullable' => false],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
                'deleted_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'relationships' => [
                'author' => [
                    'type' => 'belongsTo',
                    'model' => 'App\Models\User',
                    'foreign_key' => 'author_id',
                ],
                'category' => [
                    'type' => 'belongsTo',
                    'model' => 'App\Models\Category',
                    'foreign_key' => 'category_id',
                ],
                'comments' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Comment',
                ],
                'tags' => [
                    'type' => 'belongsToMany',
                    'model' => 'App\Models\Tag',
                    'pivot_table' => 'article_tags',
                ],
                'bookmarks' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Bookmark',
                ],
            ],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => true,
            ],
        ]);
    });

    it('generates complete application layer for a complex model', function () {
        $generators = ['model', 'migration', 'request', 'resource', 'factory', 'seeder', 'controller'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);

        // Verify all components are generated
        expect($jsonData)->toHaveKey('model');
        expect($jsonData)->toHaveKey('migration');
        expect($jsonData)->toHaveKey('requests');
        expect($jsonData)->toHaveKey('resources');
        expect($jsonData)->toHaveKey('factory');
        expect($jsonData)->toHaveKey('seeder');
        expect($jsonData)->toHaveKey('controllers');

        // Verify model configuration
        $model = $jsonData['model'];
        expect($model['name'])->toBe('Article');
        expect($model['table'])->toBe('articles');
        expect($model['soft_deletes'])->toBe(true);
        expect($model['fillable'])->toContain('title');
        expect($model['fillable'])->toContain('slug');
        expect($model['fillable'])->toContain('content');

        // Verify migration structure
        $migration = $jsonData['migration'];
        expect($migration['table'])->toBe('articles');
        expect($migration['fields'])->toHaveKey('title');
        expect($migration['fields'])->toHaveKey('slug');
        expect($migration['fields']['slug']['unique'])->toBe(true);
        expect($migration['fields'])->toHaveKey('deleted_at');
        expect($migration['foreign_keys'])->toHaveKey('author_id');
        expect($migration['foreign_keys'])->toHaveKey('category_id');

        // Verify request validation
        $requests = $jsonData['requests'];
        expect($requests)->toHaveKey('store');
        expect($requests)->toHaveKey('update');
        expect($requests['store']['rules']['title'])->toContain('required');
        expect($requests['store']['rules']['slug'])->toContain('unique:articles');
        expect($requests['update']['rules']['slug'])->toContain('unique:articles');

        // Verify enhanced resources
        $resources = $jsonData['resources'];
        expect($resources)->toHaveKey('main_resource');
        expect($resources)->toHaveKey('collection_resource');
        expect($resources)->toHaveKey('partial_resources');
        expect($resources)->toHaveKey('relationship_resources');

        $mainResource = $resources['main_resource'];
        expect($mainResource['name'])->toBe('ArticleResource');
        expect($mainResource['relationships'])->toHaveKey('author');
        expect($mainResource['relationships'])->toHaveKey('category');
        expect($mainResource['relationships'])->toHaveKey('comments');
        expect($mainResource['relationships'])->toHaveKey('tags');

        // Verify enhanced controllers
        $controllers = $jsonData['controllers'];
        expect($controllers)->toHaveKey('api_controller');
        expect($controllers)->toHaveKey('web_controller');
        expect($controllers)->toHaveKey('middleware');
        expect($controllers)->toHaveKey('resource_routes');

        $apiController = $controllers['api_controller'];
        expect($apiController['name'])->toBe('ArticleApiController');
        expect($apiController['methods'])->toHaveKey('restore'); // Soft deletes
        expect($apiController['methods'])->toHaveKey('forceDestroy'); // Soft deletes
        expect($apiController['relationships'])->toHaveKey('author');
        expect($apiController['relationships'])->toHaveKey('comments');

        // Verify factory configuration
        $factory = $jsonData['factory'];
        expect($factory['name'])->toBe('ArticleFactory');
        expect($factory['fields'])->toHaveKey('title');
        expect($factory['fields'])->toHaveKey('slug');
        expect($factory['fields'])->toHaveKey('content');
        expect($factory['fields']['published']['type'])->toBe('boolean');

        // Verify seeder configuration
        $seeder = $jsonData['seeder'];
        expect($seeder['name'])->toBe('ArticleSeeder');
        expect($seeder['count'])->toBeGreaterThan(0);
        expect($seeder['relationships'])->toHaveKey('author');
        expect($seeder['relationships'])->toHaveKey('category');
    });

    it('validates schema before generation and includes results', function () {
        $options = ['enable_validation' => true];
        $generators = ['model', 'resource', 'controller'];

        $result = $this->generationService->generateMultiple($this->complexSchema, $generators, $options);
        $jsonData = json_decode($result['json'], true);

        // Should include validation results
        expect($jsonData)->toHaveKey('validation_results');

        $validation = $jsonData['validation_results'];
        expect($validation)->toHaveKey('is_valid');
        expect($validation)->toHaveKey('field_validation');
        expect($validation)->toHaveKey('relationship_validation');
        expect($validation)->toHaveKey('performance_analysis');

        // Should be valid schema
        expect($validation['is_valid'])->toBe(true);

        // Should have performance analysis
        expect($validation['performance_analysis'])->toHaveKey('field_count');
        expect($validation['performance_analysis'])->toHaveKey('relationship_count');
        expect($validation['performance_analysis']['field_count'])->toBe(14);
        expect($validation['performance_analysis']['relationship_count'])->toBe(5);
    });

    it('generates consistent cross-references between components', function () {
        $generators = ['request', 'resource', 'controller'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators);
        $jsonData = json_decode($result['json'], true);

        $requests = $jsonData['requests'];
        $resources = $jsonData['resources'];
        $controllers = $jsonData['controllers'];

        // Controller should reference correct request classes
        $apiController = $controllers['api_controller'];
        expect($apiController['requests']['store'])->toBe($requests['store']['name']);
        expect($apiController['requests']['update'])->toBe($requests['update']['name']);

        // Controller should reference correct resource classes
        expect($apiController['response_resource'])->toBe($resources['main_resource']['name']);
        expect($apiController['collection_resource'])->toBe($resources['collection_resource']['name']);

        // All should reference the same model
        expect($apiController['model'])->toBe('App\Models\Article');
        expect($resources['main_resource']['model'])->toBe('App\Models\Article');

        // Validation rules should be consistent between requests and controllers
        $storeRules = $requests['store']['rules'];
        $controllerStoreValidation = $apiController['validation']['store'];

        foreach ($storeRules as $field => $rules) {
            expect($controllerStoreValidation)->toHaveKey($field);
            expect($controllerStoreValidation[$field])->toBe($rules);
        }
    });

    it('handles relationships consistently across all generators', function () {
        $generators = ['model', 'migration', 'resource', 'controller', 'factory', 'seeder'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators);
        $jsonData = json_decode($result['json'], true);

        $relationships = $this->complexSchema->getRelationships();

        // Model relationships
        $modelRelationships = $jsonData['model']['relationships'];
        foreach ($relationships as $name => $config) {
            expect($modelRelationships)->toHaveKey($name);
            expect($modelRelationships[$name]['type'])->toBe($config['type']);
            expect($modelRelationships[$name]['model'])->toBe($config['model']);
        }

        // Migration foreign keys for belongsTo relationships
        $migrationForeignKeys = $jsonData['migration']['foreign_keys'];
        expect($migrationForeignKeys)->toHaveKey('author_id');
        expect($migrationForeignKeys)->toHaveKey('category_id');

        // Resource relationships
        $resourceRelationships = $jsonData['resources']['main_resource']['relationships'];
        foreach ($relationships as $name => $config) {
            expect($resourceRelationships)->toHaveKey($name);
            expect($resourceRelationships[$name]['type'])->toBe($config['type']);
        }

        // Controller relationships
        $controllerRelationships = $jsonData['controllers']['api_controller']['relationships'];
        foreach ($relationships as $name => $config) {
            expect($controllerRelationships)->toHaveKey($name);
            expect($controllerRelationships[$name]['type'])->toBe($config['type']);
        }

        // Factory relationships (for belongsTo)
        $factoryRelationships = $jsonData['factory']['relationships'];
        expect($factoryRelationships)->toHaveKey('author');
        expect($factoryRelationships)->toHaveKey('category');

        // Seeder relationships
        $seederRelationships = $jsonData['seeder']['relationships'];
        expect($seederRelationships)->toHaveKey('author');
        expect($seederRelationships)->toHaveKey('category');
    });

    it('generates proper soft delete configuration across components', function () {
        $generators = ['model', 'migration', 'controller', 'resource'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators);
        $jsonData = json_decode($result['json'], true);

        // Model should have soft deletes
        expect($jsonData['model']['soft_deletes'])->toBe(true);
        expect($jsonData['model']['imports'])->toContain('Illuminate\Database\Eloquent\SoftDeletes');

        // Migration should have deleted_at column
        expect($jsonData['migration']['fields'])->toHaveKey('deleted_at');
        expect($jsonData['migration']['fields']['deleted_at']['type'])->toBe('timestamp');
        expect($jsonData['migration']['fields']['deleted_at']['nullable'])->toBe(true);

        // Controller should have restore and forceDestroy methods
        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController['methods'])->toHaveKey('restore');
        expect($apiController['methods'])->toHaveKey('forceDestroy');

        // Routes should include additional routes for soft deletes
        $routes = $jsonData['controllers']['resource_routes'];
        expect($routes['additional_routes'])->toHaveKey('restore');
        expect($routes['additional_routes'])->toHaveKey('forceDestroy');

        // Resource should handle soft deleted models
        $mainResource = $jsonData['resources']['main_resource'];
        expect($mainResource['conditional_fields'])->toHaveKey('deleted_at');
        expect($mainResource['conditional_fields']['deleted_at']['condition'])->toBe('when_not_null');
    });

    it('generates valid JSON and YAML fragments', function () {
        $generators = ['model', 'migration', 'request', 'resource', 'controller'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators);

        // JSON should be valid
        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->not->toBeNull();
        expect(json_last_error())->toBe(JSON_ERROR_NONE);

        // YAML should be valid (basic check)
        $yamlContent = $result['yaml'];
        expect($yamlContent)->toContain('model:');
        expect($yamlContent)->toContain('migration:');
        expect($yamlContent)->toContain('requests:');
        expect($yamlContent)->toContain('resources:');
        expect($yamlContent)->toContain('controllers:');

        // Should not contain PHP syntax in YAML
        expect($yamlContent)->not->toContain('<?php');
        expect($yamlContent)->not->toContain('namespace');
        expect($yamlContent)->not->toContain('class');
    });

    it('handles custom options for all generators simultaneously', function () {
        $options = [
            'model' => [
                'namespace' => 'App\Domain\Article\Models',
                'enable_scopes' => true,
            ],
            'resource' => [
                'namespace' => 'App\Http\Resources\Api\V2',
                'enable_filtering' => false,
                'pagination_per_page' => 50,
            ],
            'controller' => [
                'api_controller_namespace' => 'App\Http\Controllers\Api\V2',
                'web_controller_namespace' => 'App\Http\Controllers\Web',
                'enable_policies' => false,
                'route_prefix' => 'admin',
            ],
            'factory' => [
                'namespace' => 'Database\Factories\Article',
            ],
        ];

        $generators = ['model', 'resource', 'controller', 'factory'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators, $options);
        $jsonData = json_decode($result['json'], true);

        // Check model options
        expect($jsonData['model']['namespace'])->toBe('App\Domain\Article\Models');
        expect($jsonData['model']['scopes'])->not->toBeEmpty();

        // Check resource options
        expect($jsonData['resources']['main_resource']['namespace'])->toBe('App\Http\Resources\Api\V2');
        expect($jsonData['resources']['collection_resource']['filtering']['enabled'])->toBe(false);
        expect($jsonData['resources']['collection_resource']['pagination']['per_page'])->toBe(50);

        // Check controller options
        expect($jsonData['controllers']['api_controller']['namespace'])->toBe('App\Http\Controllers\Api\V2');
        expect($jsonData['controllers']['web_controller']['namespace'])->toBe('App\Http\Controllers\Web');
        expect($jsonData['controllers']['policies'])->toBeEmpty();

        // Check factory options
        expect($jsonData['factory']['namespace'])->toBe('Database\Factories\Article');
    });

    it('performs comprehensive validation of generated components', function () {
        $generators = ['model', 'migration', 'request', 'resource', 'controller'];
        $result = $this->generationService->generateMultiple($this->complexSchema, $generators, ['enable_validation' => true]);
        $jsonData = json_decode($result['json'], true);

        $validation = $jsonData['validation_results'];

        // Should validate all field types
        expect($validation['field_validation']['validated_fields'])->toContain('title');
        expect($validation['field_validation']['validated_fields'])->toContain('slug');
        expect($validation['field_validation']['validated_fields'])->toContain('content');
        expect($validation['field_validation']['validated_fields'])->toContain('published');
        expect($validation['field_validation']['validated_fields'])->toContain('meta_data');

        // Should validate all relationships
        expect($validation['relationship_validation']['relationship_types'])->toHaveKey('author');
        expect($validation['relationship_validation']['relationship_types'])->toHaveKey('category');
        expect($validation['relationship_validation']['relationship_types'])->toHaveKey('comments');
        expect($validation['relationship_validation']['relationship_types'])->toHaveKey('tags');
        expect($validation['relationship_validation']['relationship_types'])->toHaveKey('bookmarks');

        // Should provide performance analysis
        expect($validation['performance_analysis'])->toHaveKey('field_count');
        expect($validation['performance_analysis'])->toHaveKey('relationship_count');
        expect($validation['performance_analysis'])->toHaveKey('warnings');
        expect($validation['performance_analysis'])->toHaveKey('recommendations');
    });
});
