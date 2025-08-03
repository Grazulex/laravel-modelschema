<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation;

use Exception;
use Grazulex\LaravelModelschema\Contracts\GeneratorInterface;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ControllerGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\FactoryGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\MigrationGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ModelGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\PolicyGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RequestGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ResourceGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\SeederGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\TestGenerator;
use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use InvalidArgumentException;

/**
 * Main generation service that coordinates all generators
 * This service provides the main API for file generation
 */
class GenerationService
{
    protected LoggingService $logger;

    public function __construct(
        protected ModelGenerator $modelGenerator = new ModelGenerator(),
        protected MigrationGenerator $migrationGenerator = new MigrationGenerator(),
        protected RequestGenerator $requestGenerator = new RequestGenerator(),
        protected ResourceGenerator $resourceGenerator = new ResourceGenerator(),
        protected FactoryGenerator $factoryGenerator = new FactoryGenerator(),
        protected SeederGenerator $seederGenerator = new SeederGenerator(),
        protected ControllerGenerator $controllerGenerator = new ControllerGenerator(),
        protected TestGenerator $testGenerator = new TestGenerator(),
        protected PolicyGenerator $policyGenerator = new PolicyGenerator(),
        ?LoggingService $logger = null
    ) {
        $this->logger = $logger ?? new LoggingService();
    }

    /**
     * Generate all files for a schema
     */
    public function generateAll(ModelSchema $schema, array $options = []): array
    {
        $this->logger->logOperationStart('generateAll', [
            'schema_name' => $schema->name,
            'options' => array_keys(array_filter($options, fn ($value) => $value)),
        ]);

        $startTime = microtime(true);
        $results = [];
        $generatedCount = 0;
        $errors = [];

        try {
            if ($options['model'] ?? true) {
                $result = $this->generateModel($schema, $options);
                $results['model'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Model generation failed';
                }
            }

            if ($options['migration'] ?? true) {
                $result = $this->generateMigration($schema, $options);
                $results['migration'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Migration generation failed';
                }
            }

            if ($options['requests'] ?? false) {
                $result = $this->generateRequests($schema, $options);
                $results['requests'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Requests generation failed';
                }
            }

            if ($options['resources'] ?? false) {
                $result = $this->generateResources($schema, $options);
                $results['resources'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Resources generation failed';
                }
            }

            if ($options['factory'] ?? false) {
                $result = $this->generateFactory($schema, $options);
                $results['factory'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Factory generation failed';
                }
            }

            if ($options['seeder'] ?? false) {
                $result = $this->generateSeeder($schema, $options);
                $results['seeder'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Seeder generation failed';
                }
            }

            if ($options['controllers'] ?? false) {
                $result = $this->generateControllers($schema, $options);
                $results['controllers'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Controllers generation failed';
                }
            }

            if ($options['tests'] ?? false) {
                $result = $this->generateTests($schema, $options);
                $results['tests'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Tests generation failed';
                }
            }

            if ($options['policies'] ?? false) {
                $result = $this->generatePolicies($schema, $options);
                $results['policies'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Policies generation failed';
                }
            }

            $totalTime = microtime(true) - $startTime;

            // Log generation performance
            $this->logger->logPerformance('generateAll', [
                'schema_name' => $schema->name,
                'total_time_ms' => round($totalTime * 1000, 2),
                'generated_count' => $generatedCount,
                'total_requested' => count(array_filter($options, fn ($value) => $value)),
                'success_rate' => $generatedCount > 0 ? round(($generatedCount / count(array_filter($options, fn ($value) => $value))) * 100, 2) : 0,
            ]);

            // Check performance threshold
            $totalTimeMs = $totalTime * 1000;
            $threshold = config('modelschema.logging.performance_thresholds.generation_ms', 3000);
            if ($totalTimeMs > $threshold) {
                $this->logger->logWarning(
                    'Generation exceeded threshold',
                    [
                        'schema_name' => $schema->name,
                        'total_time_ms' => round($totalTimeMs, 2),
                        'threshold_ms' => $threshold,
                        'generated_count' => $generatedCount,
                    ],
                    'Consider optimizing templates or reducing the number of generators used simultaneously'
                );
            }

            $this->logger->logOperationEnd('generateAll', [
                'success' => $errors === [],
                'generated_count' => $generatedCount,
                'error_count' => count($errors),
                'total_time_ms' => round($totalTime * 1000, 2),
            ]);

            return $results;

        } catch (Exception $e) {
            $this->logger->logError(
                "Generation failed for schema: {$schema->name}",
                $e,
                ['schema_name' => $schema->name, 'options' => $options]
            );
            throw $e;
        }
    }

    /**
     * Generate Laravel Model
     */
    public function generateModel(ModelSchema $schema, array $options = []): array
    {
        $this->logger->logOperationStart('generateModel', [
            'schema_name' => $schema->name,
            'options' => $options,
        ]);

        try {
            $startTime = microtime(true);
            $result = $this->modelGenerator->generate($schema, $options);
            $generationTime = microtime(true) - $startTime;

            $success = $result['success'] ?? false;

            $this->logger->logGeneration(
                'model',
                $schema->name,
                $success,
                [
                    'generation_time_ms' => round($generationTime * 1000, 2),
                    'output_size' => isset($result['content']) ? mb_strlen($result['content']) : 0,
                ]
            );

            $this->logger->logOperationEnd('generateModel', [
                'success' => $success,
                'generation_time_ms' => round($generationTime * 1000, 2),
            ]);

            return $result;

        } catch (Exception $e) {
            $this->logger->logError(
                "Model generation failed for schema: {$schema->name}",
                $e,
                ['schema_name' => $schema->name, 'options' => $options]
            );
            throw $e;
        }
    }

    /**
     * Generate Laravel Migration
     */
    public function generateMigration(ModelSchema $schema, array $options = []): array
    {
        return $this->migrationGenerator->generate($schema, $options);
    }

    /**
     * Generate Laravel Form Requests (with or without enhanced features)
     */
    public function generateRequests(ModelSchema $schema, array $options = []): array
    {
        // By default, use simple mode for backward compatibility
        if (! isset($options['enhanced'])) {
            $options['enhanced'] = false;
        }

        return $this->requestGenerator->generate($schema, $options);
    }

    /**
     * Generate API Resources (with or without enhanced features)
     */
    public function generateResources(ModelSchema $schema, array $options = []): array
    {
        // By default, use simple mode for backward compatibility
        if (! isset($options['enhanced'])) {
            $options['enhanced'] = false;
        }

        return $this->resourceGenerator->generate($schema, $options);
    }

    /**
     * Generate Model Factory
     */
    public function generateFactory(ModelSchema $schema, array $options = []): array
    {
        return $this->factoryGenerator->generate($schema, $options);
    }

    /**
     * Generate Database Seeder
     */
    public function generateSeeder(ModelSchema $schema, array $options = []): array
    {
        return $this->seederGenerator->generate($schema, $options);
    }

    /**
     * Generate Controllers (API and Web)
     */
    public function generateControllers(ModelSchema $schema, array $options = []): array
    {
        return $this->controllerGenerator->generate($schema, $options);
    }

    /**
     * Generate Tests (Feature and Unit)
     */
    public function generateTests(ModelSchema $schema, array $options = []): array
    {
        return $this->testGenerator->generate($schema, $options);
    }

    /**
     * Generate Policies
     */
    public function generatePolicies(ModelSchema $schema, array $options = []): array
    {
        return $this->policyGenerator->generate($schema, $options);
    }

    /**
     * Get all available generation types
     */
    public function getAvailableGenerators(): array
    {
        return [
            'model' => [
                'name' => 'Eloquent Model Data',
                'description' => 'Generate structured data for Laravel Eloquent Model (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'migration' => [
                'name' => 'Database Migration Data',
                'description' => 'Generate structured data for Laravel database migration (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'requests' => [
                'name' => 'Form Requests Data',
                'description' => 'Generate structured data for Store and Update Form Request classes (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'resources' => [
                'name' => 'API Resources Data',
                'description' => 'Generate structured data for API Resource and Collection classes (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'factory' => [
                'name' => 'Model Factory Data',
                'description' => 'Generate structured data for Model Factory for testing and seeding (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'seeder' => [
                'name' => 'Database Seeder Data',
                'description' => 'Generate structured data for Database Seeder class (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'controllers' => [
                'name' => 'Controllers Data (API and Web)',
                'description' => 'Generate structured data for API and Web Controllers with routes and middleware (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'tests' => [
                'name' => 'Tests Data (Feature and Unit)',
                'description' => 'Generate structured data for Feature and Unit Tests with factories and relationships (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'policies' => [
                'name' => 'Policies Data',
                'description' => 'Generate structured data for Policy classes with authorization logic and gate definitions (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
        ];
    }

    /**
     * Get available generator names only (for enhanced tests compatibility)
     */
    public function getAvailableGeneratorNames(): array
    {
        $generators = array_keys($this->getAvailableGenerators());

        // Map plural keys to singular for enhanced test compatibility
        return array_map(function ($key): int|string {
            return match ($key) {
                'requests' => 'request',
                'resources' => 'resource',
                'controllers' => 'controller',
                'tests' => 'test',
                default => $key
            };
        }, $generators);
    }

    /**
     * Generate a specific component type
     */
    public function generate(ModelSchema $schema, string $type, array $options = []): array
    {
        return match ($type) {
            'model' => $this->generateModel($schema, $options),
            'migration' => $this->generateMigration($schema, $options),
            'requests', 'request' => $this->generateRequests($schema, $options),
            'resources', 'resource' => $this->generateResources($schema, $options),
            'factory' => $this->generateFactory($schema, $options),
            'seeder' => $this->generateSeeder($schema, $options),
            'controllers', 'controller' => $this->generateControllers($schema, $options),
            'tests', 'test' => $this->generateTests($schema, $options),
            'policies', 'policy' => $this->generatePolicies($schema, $options),
            default => throw new InvalidArgumentException("Unknown generator type: {$type}")
        };
    }

    /**
     * Get specific generator instance
     */
    public function getGenerator(string $type): GeneratorInterface
    {
        return match ($type) {
            'model' => $this->modelGenerator,
            'migration' => $this->migrationGenerator,
            'requests' => $this->requestGenerator,
            'resources' => $this->resourceGenerator,
            'factory' => $this->factoryGenerator,
            'seeder' => $this->seederGenerator,
            'controllers' => $this->controllerGenerator,
            'tests' => $this->testGenerator,
            'policies' => $this->policyGenerator,
            default => throw new InvalidArgumentException("Unknown generator type: {$type}")
        };
    }

    /**
     * Generate multiple components and return combined JSON/YAML fragments
     */
    public function generateMultiple(ModelSchema $schema, array $generators, array $options = []): array
    {
        $jsonFragments = [];
        $yamlFragments = [];
        $validationResults = [];

        // Validate schema if requested
        if ($options['enable_validation'] ?? false) {
            // Use EnhancedValidationService for comprehensive validation
            $validationService = new EnhancedValidationService();
            $validationResults = $validationService->generateComprehensiveReport($schema);
        }

        // Generate each requested component
        foreach ($generators as $generatorType) {
            $generatorOptions = $options[$generatorType] ?? $options;

            // Enable enhanced mode for generateMultiple (used by Enhanced tests)
            if (! isset($generatorOptions['enhanced'])) {
                $generatorOptions['enhanced'] = true;
            }

            try {
                $result = $this->generate($schema, $generatorType, $generatorOptions);

                // Parse JSON and add to fragments
                $jsonData = json_decode($result['json'], true);
                if ($jsonData !== null) {
                    $jsonFragments = array_merge_recursive($jsonFragments, $jsonData);
                }

                // Add YAML fragment
                $yamlFragments[] = $result['yaml'];

            } catch (Exception $e) {
                if ($options['enable_validation'] ?? false) {
                    $validationResults['is_valid'] = false;
                    $validationResults['errors'][] = "Failed to generate {$generatorType}: ".$e->getMessage();
                }
            }
        }

        // Add validation results if enabled
        if ($options['enable_validation'] ?? false) {
            $jsonFragments['validation_results'] = $validationResults;
        }

        return [
            'json' => json_encode($jsonFragments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'yaml' => implode("\n---\n", $yamlFragments),
        ];
    }
}
