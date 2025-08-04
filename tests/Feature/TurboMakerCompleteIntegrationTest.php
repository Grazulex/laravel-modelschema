<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

it('demonstrates complete TurboMaker integration workflow', function () {
    $generationService = app(GenerationService::class);

    // Create a complex model schema similar to what TurboMaker would use
    $modelSchema = ModelSchema::fromArray('BlogPost', [
        'table' => 'blog_posts',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'auto_increment' => true, 'primary' => true],
            'title' => ['type' => 'string', 'required' => true, 'length' => 255],
            'slug' => ['type' => 'string', 'required' => true, 'length' => 255, 'unique' => true],
            'content' => ['type' => 'text', 'required' => true],
            'excerpt' => ['type' => 'string', 'nullable' => true, 'length' => 500],
            'published_at' => ['type' => 'timestamp', 'nullable' => true],
            'is_featured' => ['type' => 'boolean', 'default' => false],
            'view_count' => ['type' => 'integer', 'default' => 0],
            'meta_description' => ['type' => 'string', 'nullable' => true, 'length' => 160],
            'deleted_at' => ['type' => 'timestamp', 'nullable' => true], // Soft deletes
        ],
        'relationships' => [
            'author' => [
                'type' => 'belongsTo',
                'model' => 'User',
                'foreign_key' => 'user_id',
            ],
            'category' => [
                'type' => 'belongsTo',
                'model' => 'Category',
                'foreign_key' => 'category_id',
            ],
            'tags' => [
                'type' => 'belongsToMany',
                'model' => 'Tag',
                'pivot_table' => 'blog_post_tags',
            ],
            'comments' => [
                'type' => 'hasMany',
                'model' => 'Comment',
                'foreign_key' => 'blog_post_id',
            ],
        ],
        'timestamps' => true,
    ]);

    // 1. First, check what generators are available (Feature Request #2)
    $generatorInfo = $generationService->getGeneratorInfo();
    expect($generatorInfo)->toBeArray();
    expect($generatorInfo)->toHaveKey('seeder');
    expect($generatorInfo)->toHaveKey('policies');

    // 2. Generate all components using TurboMaker-compatible format (Bug #1 fix)
    $options = [
        'model' => true,
        'migration' => true,
        'requests' => true,
        'resources' => true,
        'factory' => true,
        'seeder' => true,
        'controllers' => true,
        'policies' => true,
        'tests' => true,
    ];

    $results = $generationService->generateAllWithStatus($modelSchema, $options);

    // 3. Verify TurboMaker compatibility format (Bug #1 resolution)
    foreach (['model', 'migration', 'seeder', 'policies'] as $component) {
        expect($results)->toHaveKey($component);
        expect($results[$component])->toHaveKey('success');
        expect($results[$component]['success'])->toBeTrue();
        expect($results[$component])->toHaveKey('json');
        expect($results[$component])->toHaveKey('yaml');
    }

    // 4. Verify seeder specifically (Bug #1 - seeder generation)
    $seederData = json_decode($results['seeder']['json'], true);
    expect($seederData)->toHaveKey('seeder');
    expect($seederData['seeder']['name'])->toBe('BlogPostSeeder');
    expect($seederData['seeder']['model_class'])->toBe('App\\Models\\BlogPost');
    expect($seederData['seeder']['dependencies'])->toContain('UserSeeder');
    expect($seederData['seeder']['dependencies'])->toContain('CategorySeeder');

    // 5. Verify policy has all authorization methods (Potential Issue #4)
    $policyData = json_decode($results['policies']['json'], true);
    $blogPostPolicy = $policyData['policies']['BlogPostPolicy'];

    $expectedMethods = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];
    foreach ($expectedMethods as $method) {
        expect($blogPostPolicy['methods'])->toHaveKey($method);
    }

    // 6. Test debug mode (Feature Request #1)
    ob_start();
    $debugResults = $generationService->generateAllWithDebug($modelSchema, [
        'seeder' => true,
        'model' => false,
        'migration' => false,
        'debug' => true,
    ]);
    $debugOutput = ob_get_clean();

    expect($debugOutput)->toContain('🔍 Debug Mode Enabled for BlogPost');
    expect($debugOutput)->toContain('📊 Generation Summary:');
    expect($debugResults)->toHaveKey('seeder');

    // 7. Verify performance is acceptable (Bug #2 - performance consistency)
    $startTime = microtime(true);
    $perfResults = $generationService->generateAll($modelSchema, ['seeder' => true]);
    $endTime = microtime(true);

    $generationTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    expect($generationTime)->toBeLessThan(100); // Should complete within 100ms
    expect($perfResults)->toHaveKey('seeder');

    // 8. Verify soft deletes detection enhancement
    expect($modelSchema->hasSoftDeletes())->toBeTrue(); // Should detect deleted_at field
});
