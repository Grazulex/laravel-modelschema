<?php

declare(strict_types=1);

namespace Tests\Performance;

use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\SchemaCacheService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Services\YamlOptimizationService;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Clean performance tests without console output
 * Validates the actual performance of YamlOptimizationService
 */
class YamlOptimizationCleanTest extends TestCase
{
    private SchemaService $schemaService;

    private YamlOptimizationService $yamlOptimizer;

    private string $mediumYaml;

    private string $largeYaml;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaService = new SchemaService();
        $this->yamlOptimizer = new YamlOptimizationService(
            new LoggingService(),
            new SchemaCacheService()
        );

        // Generate test YAML files of different sizes
        $this->mediumYaml = $this->generateTestYaml(50); // ~50KB
        $this->largeYaml = $this->generateTestYaml(500); // ~500KB
    }

    public function test_cache_provides_significant_performance_improvement()
    {
        // Clear cache to ensure clean start
        $this->yamlOptimizer->clearCache();

        // Measure first parse (no cache)
        $startTime = microtime(true);
        $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        $firstParseTime = microtime(true) - $startTime;

        // Measure second parse (with cache)
        $startTime = microtime(true);
        $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        $cachedParseTime = microtime(true) - $startTime;

        // Calculate improvement
        $improvement = ($firstParseTime - $cachedParseTime) / $firstParseTime * 100;

        // Realistic expectations: at least 50% improvement from caching
        $this->assertGreaterThan(50, $improvement,
            'Cache should provide at least 50% improvement. Got: '.number_format($improvement, 1).'%');

        // Ideally we want 90%+ improvement
        $this->assertGreaterThan(90, $improvement,
            'Cache provides excellent performance: '.number_format($improvement, 1).'% improvement');
    }

    public function test_quick_validation_is_much_faster_than_full_parsing()
    {
        // Measure full validation (with complete parsing)
        $startTime = microtime(true);
        $this->schemaService->validateCoreSchema($this->largeYaml);
        $fullValidationTime = microtime(true) - $startTime;

        // Measure quick validation (minimal parsing)
        $startTime = microtime(true);
        $result = $this->yamlOptimizer->quickValidate($this->largeYaml);
        $quickValidationTime = microtime(true) - $startTime;

        // Calculate improvement
        $improvement = $fullValidationTime / $quickValidationTime;

        // Should be at least 5x faster
        $this->assertGreaterThan(5, $improvement,
            'Quick validation should be at least 5x faster. Got: '.number_format($improvement, 1).'x');

        // Ideally 10x+ faster
        $this->assertGreaterThan(10, $improvement,
            'Quick validation is excellent: '.number_format($improvement, 1).'x faster');
    }

    public function test_repeated_parsing_benefits_from_caching()
    {
        $this->yamlOptimizer->clearCache();
        $iterations = 5; // Reduced for faster tests

        // Measure multiple parses without cache
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->yamlOptimizer->clearCache();
            $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        }
        $noCacheTime = microtime(true) - $startTime;

        // Measure multiple parses with cache
        $this->yamlOptimizer->clearCache();
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        }
        $withCacheTime = microtime(true) - $startTime;

        $improvement = $noCacheTime / $withCacheTime;

        // Should be at least 2x faster with cache
        $this->assertGreaterThan(2, $improvement,
            'Repeated parsing with cache should be at least 2x faster. Got: '.number_format($improvement, 1).'x');

        // Ideally 4.5x+ faster (slightly lower threshold to account for variance)
        $this->assertGreaterThan(4.5, $improvement,
            'Repeated parsing shows excellent cache benefits: '.number_format($improvement, 1).'x faster');
    }

    public function test_memory_usage_is_reasonable()
    {
        $this->yamlOptimizer->clearCache();
        $initialMemory = memory_get_usage(true);

        // Parse large YAML with optimization
        $this->yamlOptimizer->parseYamlContent($this->largeYaml);

        $optimizedMemory = memory_get_usage(true) - $initialMemory;

        // Parse without optimization using Symfony YAML directly
        $initialMemory = memory_get_usage(true);

        $standardResult = Yaml::parse($this->largeYaml);

        $standardMemory = memory_get_usage(true) - $initialMemory;

        // Memory usage should be reasonable (within 5x due to caching overhead)
        $this->assertLessThan($standardMemory * 5, $optimizedMemory,
            'Optimized parsing should not use excessively more memory (got '.
            number_format($optimizedMemory / 1024 / 1024, 2).'MB vs '.
            number_format($standardMemory / 1024 / 1024, 2).'MB standard)');

        // Ensure memory usage is not zero (test validity)
        $this->assertGreaterThan(0, $optimizedMemory);
        $this->assertGreaterThan(0, $standardMemory);
    }

    public function test_performance_metrics_are_properly_tracked()
    {
        $this->yamlOptimizer->resetMetrics();

        // Parse different YAML content
        $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        $this->yamlOptimizer->parseYamlContent($this->largeYaml);

        // Parse same content again (should hit cache)
        $this->yamlOptimizer->parseYamlContent($this->mediumYaml);

        $metrics = $this->yamlOptimizer->getPerformanceMetrics();

        // Validate metrics structure
        $this->assertGreaterThan(0, $metrics['total_parses']);
        $this->assertArrayHasKey('cache_hits', $metrics);
        $this->assertArrayHasKey('cache_misses', $metrics);
        $this->assertArrayHasKey('memory_saved_bytes', $metrics);
        $this->assertArrayHasKey('time_saved_ms', $metrics);

        // Should have at least one cache hit from repeated parsing
        $this->assertGreaterThan(0, $metrics['cache_hits'],
            'Should have cache hits from repeated parsing');
    }

    /**
     * Generate test YAML content of specified approximate size
     */
    private function generateTestYaml(int $targetSizeKB): string
    {
        $baseSchema = [
            'core' => [
                'model' => 'TestModel',
                'table' => 'test_models',
                'fields' => [],
                'relations' => [],
                'options' => [
                    'timestamps' => true,
                    'soft_deletes' => false,
                ],
            ],
        ];

        // Calculate how many fields needed to reach target size
        $currentSize = mb_strlen(Yaml::dump($baseSchema));
        $fieldSize = 200; // Approximate size per field
        $fieldsNeeded = max(1, ($targetSizeKB * 1024 - $currentSize) / $fieldSize);

        // Generate fields
        for ($i = 0; $i < $fieldsNeeded; $i++) {
            $fieldName = "field_{$i}";
            $baseSchema['core']['fields'][$fieldName] = [
                'type' => 'string',
                'nullable' => false,
                'rules' => ['required', 'string', 'max:255'],
                'description' => "Test field number {$i} for performance testing",
            ];

            // Add some relations every 10 fields
            if ($i % 10 === 0) {
                $relationName = "relation_{$i}";
                $baseSchema['core']['relations'][$relationName] = [
                    'type' => 'belongsTo',
                    'model' => "App\\Models\\Related{$i}",
                    'foreign_key' => "related_{$i}_id",
                ];
            }
        }

        return Yaml::dump($baseSchema);
    }
}
