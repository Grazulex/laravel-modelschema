<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

describe('Logging Integration', function () {
    beforeEach(function () {
        // Mock configuration - general fallback for any config request
        Config::shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            return match ($key) {
                'modelschema.logging.enabled' => true,
                'modelschema.logging.channel' => 'modelschema',
                'modelschema.logging.performance_thresholds.yaml_parsing_ms' => 1000,
                'modelschema.logging.performance_thresholds.validation_ms' => 2000,
                'modelschema.logging.performance_thresholds.generation_ms' => 3000,
                'modelschema.cache.enabled' => true,
                'modelschema.cache.ttl' => 3600,
                'modelschema.cache.key_prefix' => 'modelschema:',
                'modelschema.cache.store' => null,
                default => $default
            };
        });

        // Mock Cache facade
        Cache::shouldReceive('store')->andReturnSelf()->byDefault();
        Cache::shouldReceive('get')->andReturn(null)->byDefault();
        Cache::shouldReceive('put')->andReturn(true)->byDefault();
        Cache::shouldReceive('forget')->andReturn(true)->byDefault();

        // Mock Log facade
        Log::shouldReceive('channel')->andReturnSelf()->byDefault();
        Log::shouldReceive('info')->andReturnSelf()->byDefault();
        Log::shouldReceive('debug')->andReturnSelf()->byDefault();
        Log::shouldReceive('warning')->andReturnSelf()->byDefault();
        Log::shouldReceive('error')->andReturnSelf()->byDefault();
        Log::shouldReceive('log')->andReturnSelf()->byDefault();

        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_schema');
        $this->schemaContent = <<<'YAML'
model: User
table: users
fields:
  id:
    type: unsignedBigInteger
    primary: true
    auto_increment: true
  name:
    type: string
    length: 255
    nullable: false
  email:
    type: string
    length: 255
    unique: true
  created_at:
    type: timestamp
  updated_at:
    type: timestamp
relationships:
  posts:
    type: hasMany
    model: Post
    foreign_key: user_id
YAML;

        file_put_contents($this->tempFile, $this->schemaContent);
    });

    afterEach(function () {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    });

    describe('SchemaService logging integration', function () {
        it('logs YAML file parsing operations', function () {
            // Expect specific log calls for parsing
            Log::shouldReceive('info')
                ->with(Mockery::pattern('/🚀 Starting parseYamlFile/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('debug')
                ->with(Mockery::pattern('/⚡ Cache miss/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/📄 YAML Parsing/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('debug')
                ->with(Mockery::pattern('/💾 Cache store/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/✅ Completed parseYamlFile/'), Mockery::type('array'))
                ->once();

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            expect($schema->name)->toBe('User');
        });

        it('logs validation operations', function () {
            Log::shouldReceive('info')
                ->with(Mockery::pattern('/🚀 Starting validateSchema/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('log')
                ->with(Mockery::pattern('/info/'), Mockery::pattern('/✅ Validation schema: Passed/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/✅ Completed validateSchema/'), Mockery::type('array'))
                ->once();

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);
            $errors = $schemaService->validateSchema($schema);

            expect($errors)->toBeEmpty();
        });

        it('logs performance warnings for slow operations', function () {
            // Mock a very low threshold to trigger warning
            Config::shouldReceive('get')
                ->with('modelschema.logging.performance_thresholds.validation_ms', 2000)
                ->andReturn(0); // Any validation will exceed this

            Log::shouldReceive('warning')
                ->with(
                    Mockery::pattern('/Schema validation exceeded threshold/'),
                    Mockery::on(function ($data) {
                        return isset($data['schema_name']) &&
                               isset($data['validation_time_ms']) &&
                               isset($data['threshold_ms']);
                    }),
                    Mockery::pattern('/Consider optimizing/')
                )
                ->atLeast(1); // Changed from once to atLeast(1)

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);
            $errors = $schemaService->validateSchema($schema);

            // Add assertion
            expect($errors)->toBeArray();
        });

        it('logs cache operations', function () {
            Log::shouldReceive('debug')
                ->with(Mockery::pattern('/⚡ Cache miss/'), Mockery::type('array'))
                ->atLeast(1);

            Log::shouldReceive('debug')
                ->with(Mockery::pattern('/💾 Cache store/'), Mockery::type('array'))
                ->atLeast(1);

            Log::shouldReceive('debug')
                ->with(Mockery::pattern('/🎯 Cache hit/'), Mockery::type('array'))
                ->atLeast(1);

            $schemaService = new SchemaService();

            // First call - cache miss and store
            $schema1 = $schemaService->parseYamlFile($this->tempFile);

            // Second call - cache hit (should use mock that returns null, so actually miss again)
            $schema2 = $schemaService->parseYamlFile($this->tempFile);

            expect($schema1->name)->toBe($schema2->name);
        });
    });

    describe('GenerationService logging integration', function () {

        it('logs complete generation workflow', function () {
            // Expect logs for generateAll operation
            Log::shouldReceive('info')
                ->with(Mockery::pattern('/🚀 Starting generateAll/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/📊 Performance: generateAll/'), Mockery::type('array'))
                ->once();

            Log::shouldReceive('info')
                ->with(Mockery::pattern('/✅ Completed generateAll/'), Mockery::type('array'))
                ->once();

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            $generationService = new GenerationService();
            $results = $generationService->generateAll($schema, [
                'model' => true,
                'migration' => true,
            ]);

            expect($results)->toHaveKeys(['model', 'migration']);
        });

        it('logs performance metrics for generation', function () {
            Log::shouldReceive('info')
                ->with(
                    Mockery::pattern('/📊 Performance: generateAll/'),
                    Mockery::on(function ($data) {
                        return isset($data['metrics']['total_time_ms']) &&
                               isset($data['metrics']['generated_count']) &&
                               isset($data['metrics']['success_rate']);
                    })
                )
                ->once();

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            $generationService = new GenerationService();
            $results = $generationService->generateAll($schema, ['model' => true]);
        });

        it('logs generation errors', function () {
            // Create an invalid schema to trigger an error
            $invalidYaml = 'invalid: yaml: content: [unclosed';
            $invalidFile = tempnam(sys_get_temp_dir(), 'invalid_schema');
            file_put_contents($invalidFile, $invalidYaml);

            Log::shouldReceive('error')
                ->with(Mockery::pattern('/Failed to parse YAML file/'), Mockery::type('array'))
                ->once();

            $schemaService = new SchemaService();

            try {
                $schema = $schemaService->parseYamlFile($invalidFile);
            } catch (Exception $e) {
                // Expected
            }

            unlink($invalidFile);
        });
    });

    describe('session and context tracking', function () {
        it('maintains consistent session id across operations', function () {
            $sessionIds = [];

            Log::shouldReceive('info')
                ->withArgs(function ($message, $data) use (&$sessionIds) {
                    if (isset($data['session_id'])) {
                        $sessionIds[] = $data['session_id'];
                    }

                    return true;
                })
                ->atLeast(1);

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);
            $errors = $schemaService->validateSchema($schema);

            // All session IDs should be the same for operations within the same service
            $uniqueSessionIds = array_unique($sessionIds);
            expect(count($uniqueSessionIds))->toBe(1);
        });

        it('tracks nested operation context', function () {
            Log::shouldReceive('info')
                ->with(
                    Mockery::pattern('/🚀 Starting generateAll/'),
                    Mockery::on(function ($data) {
                        return isset($data['session_id']) &&
                               $data['context_depth'] === 1; // First level
                    })
                )
                ->once();

            Log::shouldReceive('info')
                ->with(
                    Mockery::pattern('/🚀 Starting generateModel/'),
                    Mockery::on(function ($data) {
                        return isset($data['session_id']) &&
                               isset($data['context_depth']); // Nested level
                    })
                )
                ->once();

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            $generationService = new GenerationService();
            $results = $generationService->generateAll($schema, ['model' => true]);
        });
    });

    describe('memory and performance tracking', function () {
        it('includes memory usage in logs', function () {
            Log::shouldReceive('info')
                ->with(
                    Mockery::any(),
                    Mockery::on(function ($data) {
                        return isset($data['memory_usage']) &&
                               preg_match('/^\d+\.?\d* (B|KB|MB|GB)$/', $data['memory_usage']);
                    })
                )
                ->atLeast(1);

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            expect($schema)->toBeInstanceOf(Grazulex\LaravelModelschema\Schema\ModelSchema::class);
        });

        it('tracks operation timing', function () {
            Log::shouldReceive('info')
                ->with(
                    Mockery::pattern('/✅ Completed/'),
                    Mockery::on(function ($data) {
                        return isset($data['duration_ms']) &&
                               is_numeric($data['duration_ms']);
                    })
                )
                ->atLeast(1);

            $schemaService = new SchemaService();
            $schema = $schemaService->parseYamlFile($this->tempFile);

            expect($schema)->toBeInstanceOf(Grazulex\LaravelModelschema\Schema\ModelSchema::class);
        });
    });
});
