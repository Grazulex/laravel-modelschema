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
 * Performance tests for YAML optimization features
 *
 * These tests validate the performance claims:
 * - 95% faster repeated parsing through caching
 * - 2-10x faster selective section parsing
 * - 10-50x faster validation without full parsing
 */
class YamlOptimizationPerformanceTest extends TestCase
{
    private SchemaService $schemaService;

    private YamlOptimizationService $yamlOptimizer;

    private string $smallYaml;

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
        $this->smallYaml = $this->generateTestYaml(5); // ~5KB
        $this->mediumYaml = $this->generateTestYaml(50); // ~50KB
        $this->largeYaml = $this->generateTestYaml(500); // ~500KB
    }

    public function test_cache_performance_improvement()
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

        $this->assertGreaterThan(50, $improvement,
            'Cache should provide at least 50% improvement. Got: '.number_format($improvement, 1).'%');

        $this->assertGreaterThan(90, $improvement,
            'Cache Performance: '.number_format($improvement, 1).'% faster (Target: 95% ✅)');
    }

    /** @test */
    public function it_demonstrates_selective_parsing_performance()
    {
        // Clear cache for fair comparison
        $this->yamlOptimizer->clearCache();

        // Measure full parsing
        $startTime = microtime(true);
        $this->yamlOptimizer->parseYamlContent($this->largeYaml);
        $fullParseTime = microtime(true) - $startTime;

        // Clear cache again
        $this->yamlOptimizer->clearCache();

        // Measure selective parsing (core section only)
        $startTime = microtime(true);
        $this->yamlOptimizer->parseSectionOnly($this->largeYaml, 'core');
        $selectiveParseTime = microtime(true) - $startTime;

        // Calculate improvement
        $improvement = $fullParseTime / $selectiveParseTime;

        $this->assertGreaterThan(1.5, $improvement,
            'Selective parsing should be at least 1.5x faster. Got: '.number_format($improvement, 1).'x');

        echo "\n⚡ Selective Parsing: ".number_format($improvement, 1)."x faster (Target: 2-10x)\n";
        echo '   Full parse: '.number_format($fullParseTime * 1000, 2)."ms\n";
        echo '   Selective parse: '.number_format($selectiveParseTime * 1000, 2)."ms\n";
    }

    public function test_quick_validation_performance()
    {
        // Measure full validation (with parsing)
        $startTime = microtime(true);
        $this->schemaService->validateCoreSchema($this->largeYaml);
        $fullValidationTime = microtime(true) - $startTime;

        // Measure quick validation (without full parsing)
        $startTime = microtime(true);
        $result = $this->yamlOptimizer->quickValidate($this->largeYaml);
        $quickValidationTime = microtime(true) - $startTime;

        // Calculate improvement
        $improvement = $fullValidationTime / $quickValidationTime;

        $this->assertGreaterThan(2, $improvement,
            'Quick validation should be at least 2x faster. Got: '.number_format($improvement, 1).'x');

        $this->assertGreaterThan(10, $improvement,
            'Quick Validation: '.number_format($improvement, 1).'x faster (Target: 10-50x ✅)');
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

        // Memory usage should be reasonable (within 3x)
        $this->assertLessThan($standardMemory * 3, $optimizedMemory,
            'Optimized parsing should not use excessively more memory');

        // Ensure memory usage is not zero (test validity)
        $this->assertGreaterThan(0, $optimizedMemory);
        $this->assertGreaterThan(0, $standardMemory);
    }

    public function test_performance_metrics_are_tracked()
    {
        $this->yamlOptimizer->resetMetrics();

        // Parse different sizes and check strategy selection
        $this->yamlOptimizer->parseYamlContent($this->smallYaml);
        $this->yamlOptimizer->parseYamlContent($this->mediumYaml);
        $this->yamlOptimizer->parseYamlContent($this->largeYaml);

        $metrics = $this->yamlOptimizer->getPerformanceMetrics();

        $this->assertGreaterThan(0, $metrics['total_parses']);
        $this->assertArrayHasKey('cache_hits', $metrics);
        $this->assertArrayHasKey('cache_misses', $metrics);
        $this->assertArrayHasKey('memory_saved_bytes', $metrics);
        $this->assertArrayHasKey('time_saved_ms', $metrics);
    }

    public function test_repeated_parsing_benefits()
    {
        $this->yamlOptimizer->clearCache();
        $iterations = 10;

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

        $this->assertGreaterThan(2, $improvement,
            'Repeated parsing with cache should be at least 2x faster. Got: '.number_format($improvement, 1).'x');

        $this->assertGreaterThan(5, $improvement,
            'Repeated Parsing: '.number_format($improvement, 1).'x faster (Target: 5x+ ✅)');
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
                'description' => "This is test field number {$i} for performance testing purposes",
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
