<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

it('reproduces TurboMaker seeder generation bug', function () {
    $generationService = app(GenerationService::class);

    // Create a basic model schema using fromArray method
    $modelSchema = ModelSchema::fromArray('Product', [
        'table' => 'products',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'name' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'text', 'nullable' => true],
            'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
            'active' => ['type' => 'boolean', 'default' => true],
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    // Test the exact scenario from TurboMaker bug report
    $options = ['seeder' => true, 'model' => true];
    $results = $generationService->generateAll($modelSchema, $options);

    // BUG ANALYSIS: TurboMaker expects a 'success' key that doesn't exist
    // The actual structure is: ['metadata' => [...], 'json' => '...', 'yaml' => '...']
    // NOT: ['success' => true, 'json' => '...', ...]

    // Correct assertions based on actual implementation
    expect($results)->toHaveKey('seeder');
    expect($results['seeder'])->toHaveKey('metadata');
    expect($results['seeder'])->toHaveKey('json');
    expect($results['seeder'])->toHaveKey('yaml');

    // Verify seeder content is properly generated
    $seederData = json_decode($results['seeder']['json'], true);
    expect($seederData)->toHaveKey('seeder');
    expect($seederData['seeder']['name'])->toBe('ProductSeeder');
    expect($seederData['seeder']['model_class'])->toBe('App\\Models\\Product');
    expect($seederData['seeder']['factory_class'])->toBe('ProductFactory');
});

it('tests generateAll with only seeder option enabled', function () {
    $generationService = app(GenerationService::class);

    $modelSchema = ModelSchema::fromArray('TestModel', [
        'table' => 'test_models',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'name' => ['type' => 'string', 'required' => true],
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    // Test with ONLY seeder enabled, explicitly disabling model and migration
    $options = ['seeder' => true, 'model' => false, 'migration' => false];
    $results = $generationService->generateAll($modelSchema, $options);

    expect($results)->toHaveKey('seeder');
    expect($results['seeder'])->toHaveKey('json');
    expect($results['seeder'])->toHaveKey('yaml');

    // Should only have seeder, not model or migration
    expect($results)->not->toHaveKey('model');
    expect($results)->not->toHaveKey('migration');

    // Verify the seeder content
    $seederData = json_decode($results['seeder']['json'], true);
    expect($seederData['seeder']['name'])->toBe('TestModelSeeder');
});

it('can use generateAllWithStatus for TurboMaker compatibility', function () {
    $generationService = app(GenerationService::class);

    $modelSchema = ModelSchema::fromArray('TurboModel', [
        'table' => 'turbo_models',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'name' => ['type' => 'string', 'required' => true],
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    // Test the TurboMaker-compatible method
    $options = ['seeder' => true, 'model' => false, 'migration' => false];
    $results = $generationService->generateAllWithStatus($modelSchema, $options);

    // This should have the 'success' key that TurboMaker expects
    expect($results)->toHaveKey('seeder');
    expect($results['seeder'])->toHaveKey('success');
    expect($results['seeder']['success'])->toBeTrue();
    expect($results['seeder'])->toHaveKey('json');
    expect($results['seeder'])->toHaveKey('yaml');
    expect($results['seeder'])->toHaveKey('metadata');
});

it('can get generator information for registry introspection', function () {
    $generationService = app(GenerationService::class);

    $info = $generationService->getGeneratorInfo();

    expect($info)->toBeArray();
    expect($info)->toHaveKey('seeder');
    expect($info)->toHaveKey('model');
    expect($info)->toHaveKey('migration');
    expect($info)->toHaveKey('policies');

    // Check seeder info specifically
    expect($info['seeder'])->toHaveKey('name');
    expect($info['seeder'])->toHaveKey('formats');
    expect($info['seeder'])->toHaveKey('class');
    expect($info['seeder'])->toHaveKey('description');

    expect($info['seeder']['name'])->toBe('seeder');
    expect($info['seeder']['formats'])->toContain('json');
    expect($info['seeder']['formats'])->toContain('yaml');
    expect($info['seeder']['description'])->toContain('Seeder');
});

it('verifies policy generator has all standard authorization methods', function () {
    $generationService = app(GenerationService::class);

    $modelSchema = ModelSchema::fromArray('Post', [
        'table' => 'posts',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'text'],
            'deleted_at' => ['type' => 'timestamp', 'nullable' => true], // Soft deletes
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    $result = $generationService->generatePolicies($modelSchema);

    expect($result)->toHaveKey('json');
    $policyData = json_decode($result['json'], true);

    expect($policyData)->toHaveKey('policies');
    $postPolicy = $policyData['policies']['PostPolicy'];

    // Check all standard authorization methods are present
    $expectedMethods = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

    foreach ($expectedMethods as $method) {
        expect($postPolicy['methods'])->toHaveKey($method);
        expect($postPolicy['methods'][$method])->toHaveKey('description');
        expect($postPolicy['methods'][$method])->toHaveKey('parameters');
        expect($postPolicy['methods'][$method])->toHaveKey('return_type');
        expect($postPolicy['methods'][$method])->toHaveKey('logic');
    }
});

it('verifies test generator completeness', function () {
    $generationService = app(GenerationService::class);

    $modelSchema = ModelSchema::fromArray('Article', [
        'table' => 'articles',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'title' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'text'],
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    $result = $generationService->generateTests($modelSchema);

    expect($result)->toHaveKey('json');
    $testData = json_decode($result['json'], true);

    expect($testData)->toHaveKey('tests');
    expect($testData['tests'])->toHaveKey('feature_tests');
    expect($testData['tests'])->toHaveKey('unit_tests');
    expect($testData['tests'])->toHaveKey('test_traits');
    expect($testData['tests'])->toHaveKey('factories_needed');
});

it('can use debug mode for detailed generation output', function () {
    $generationService = app(GenerationService::class);

    $modelSchema = ModelSchema::fromArray('DebugModel', [
        'table' => 'debug_models',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'name' => ['type' => 'string', 'required' => true],
        ],
        'relationships' => [],
        'timestamps' => true,
    ]);

    // Capture output to verify debug mode works
    ob_start();
    $results = $generationService->generateAllWithDebug($modelSchema, [
        'seeder' => true,
        'model' => false,
        'migration' => false,
        'debug' => true,
    ]);
    $output = ob_get_clean();

    // Verify debug output contains expected information
    expect($output)->toContain('🔍 Debug Mode Enabled for DebugModel');
    expect($output)->toContain('📋 Requested options:');
    expect($output)->toContain('🚀 Generating seeder...');
    expect($output)->toContain('✅ seeder generated successfully');
    expect($output)->toContain('📊 Generation Summary:');

    // Verify actual results are still returned
    expect($results)->toHaveKey('seeder');
    expect($results['seeder'])->toHaveKey('json');
    expect($results['seeder'])->toHaveKey('yaml');
});
