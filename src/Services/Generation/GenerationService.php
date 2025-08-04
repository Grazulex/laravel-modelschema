<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation;

use Exception;
use Grazulex\LaravelModelschema\Contracts\GeneratorInterface;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ActionGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ControllerGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\FactoryGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\MigrationGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ModelGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ObserverGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\PolicyGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RequestGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ResourceGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RuleGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\SeederGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ServiceGenerator;
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
        protected ObserverGenerator $observerGenerator = new ObserverGenerator(),
        protected ServiceGenerator $serviceGenerator = new ServiceGenerator(),
        protected ActionGenerator $actionGenerator = new ActionGenerator(),
        protected RuleGenerator $ruleGenerator = new RuleGenerator(),
        ?LoggingService $logger = null
    ) {
        $this->logger = $logger ?? new LoggingService();
    }

    /**
     * Get detailed information about available generators and their capabilities
     */
    public function getGeneratorInfo(): array
    {
        $generators = $this->getGeneratorInstances();
        $info = [];

        foreach ($generators as $name => $generator) {
            $info[$name] = [
                'name' => $generator->getGeneratorName(),
                'formats' => $generator->getAvailableFormats(),
                'class' => get_class($generator),
                'description' => $this->getGeneratorDescription($name),
            ];
        }

        return $info;
    }

    /**
     * Get actual generator instances for introspection
     */
    public function getGeneratorInstances(): array
    {
        return [
            'model' => $this->modelGenerator,
            'migration' => $this->migrationGenerator,
            'requests' => $this->requestGenerator,
            'resources' => $this->resourceGenerator,
            'factory' => $this->factoryGenerator,
            'seeder' => $this->seederGenerator,
            'controllers' => $this->controllerGenerator,
            'tests' => $this->testGenerator,
            'policies' => $this->policyGenerator,
            'observers' => $this->observerGenerator,
            'services' => $this->serviceGenerator,
            'actions' => $this->actionGenerator,
            'rules' => $this->ruleGenerator,
        ];
    }

    /**
     * Generate all files for a schema with debug/verbose mode
     */
    public function generateAllWithDebug(ModelSchema $schema, array $options = []): array
    {
        $debugMode = $options['debug'] ?? false;
        $results = [];

        if ($debugMode) {
            echo "🔍 Debug Mode Enabled for {$schema->name}\n";
            echo '📋 Requested options: '.json_encode($options)."\n";
            echo '🔧 Available generators: '.implode(', ', array_keys($this->getGeneratorInstances()))."\n\n";
        }

        $this->logger->logOperationStart('generateAllWithDebug', [
            'schema_name' => $schema->name,
            'options' => array_keys(array_filter($options, fn ($value) => $value)),
            'debug_mode' => $debugMode,
        ]);

        $startTime = microtime(true);
        $generatedCount = 0;
        $errors = [];

        try {
            $generatorOptions = [
                'model' => ['enabled' => $options['model'] ?? true, 'generator' => 'modelGenerator'],
                'migration' => ['enabled' => $options['migration'] ?? true, 'generator' => 'migrationGenerator'],
                'requests' => ['enabled' => $options['requests'] ?? false, 'generator' => 'requestGenerator'],
                'resources' => ['enabled' => $options['resources'] ?? false, 'generator' => 'resourceGenerator'],
                'factory' => ['enabled' => $options['factory'] ?? false, 'generator' => 'factoryGenerator'],
                'seeder' => ['enabled' => $options['seeder'] ?? false, 'generator' => 'seederGenerator'],
                'controllers' => ['enabled' => $options['controllers'] ?? false, 'generator' => 'controllerGenerator'],
                'tests' => ['enabled' => $options['tests'] ?? false, 'generator' => 'testGenerator'],
                'policies' => ['enabled' => $options['policies'] ?? false, 'generator' => 'policyGenerator'],
                'observers' => ['enabled' => $options['observers'] ?? false, 'generator' => 'observerGenerator'],
                'services' => ['enabled' => $options['services'] ?? false, 'generator' => 'serviceGenerator'],
                'actions' => ['enabled' => $options['actions'] ?? false, 'generator' => 'actionGenerator'],
                'rules' => ['enabled' => $options['rules'] ?? false, 'generator' => 'ruleGenerator'],
            ];

            foreach ($generatorOptions as $type => $config) {
                if ($config['enabled']) {
                    if ($debugMode) {
                        echo "🚀 Generating {$type}...\n";
                    }

                    $generatorMethodName = 'generate'.ucfirst($type === 'requests' ? 'Requests' :
                                                              ($type === 'resources' ? 'Resources' :
                                                              ($type === 'controllers' ? 'Controllers' :
                                                              ($type === 'tests' ? 'Tests' :
                                                              ($type === 'policies' ? 'Policies' :
                                                              ($type === 'observers' ? 'Observers' :
                                                              ($type === 'services' ? 'Services' :
                                                              ($type === 'actions' ? 'Actions' :
                                                              ($type === 'rules' ? 'Rules' :
                                                              ucfirst($type))))))))));

                    try {
                        $result = $this->$generatorMethodName($schema, $options);
                        $results[$type] = $result;

                        if (isset($result['json']) && isset($result['yaml'])) {
                            $generatedCount++;
                            if ($debugMode) {
                                echo "✅ {$type} generated successfully\n";
                                echo '   - JSON size: '.mb_strlen($result['json'])." bytes\n";
                                echo '   - YAML size: '.mb_strlen($result['yaml'])." bytes\n";
                            }
                        } else {
                            $errors[] = "{$type} generation incomplete";
                            if ($debugMode) {
                                echo "⚠️  {$type} generation incomplete\n";
                            }
                        }
                    } catch (Exception $e) {
                        $errors[] = "{$type} generation failed: ".$e->getMessage();
                        if ($debugMode) {
                            echo "❌ {$type} generation failed: ".$e->getMessage()."\n";
                        }
                    }

                    if ($debugMode) {
                        echo "\n";
                    }
                }
            }

            $totalTime = microtime(true) - $startTime;

            if ($debugMode) {
                echo "📊 Generation Summary:\n";
                echo '   - Total time: '.round($totalTime * 1000, 2)."ms\n";
                echo "   - Generated: {$generatedCount} components\n";
                echo '   - Errors: '.count($errors)."\n";
                echo '   - Success rate: '.($generatedCount > 0 ? round(($generatedCount / count(array_filter($generatorOptions, fn ($c) => $c['enabled']))) * 100, 2) : 0)."%\n";

                if ($errors !== []) {
                    echo "❌ Errors encountered:\n";
                    foreach ($errors as $error) {
                        echo "   - {$error}\n";
                    }
                }
            }

            $this->logger->logOperationEnd('generateAllWithDebug', [
                'success' => $errors === [],
                'generated_count' => $generatedCount,
                'error_count' => count($errors),
                'total_time_ms' => round($totalTime * 1000, 2),
                'debug_mode' => $debugMode,
            ]);

            return $results;

        } catch (Exception $e) {
            if ($debugMode) {
                echo '💥 Fatal error during generation: '.$e->getMessage()."\n";
            }

            $this->logger->logError(
                "Debug generation failed for schema: {$schema->name}",
                $e,
                ['schema_name' => $schema->name, 'options' => $options, 'debug_mode' => $debugMode]
            );
            throw $e;
        }
    }

    /**
     * Generate all files for a schema with enhanced result format for TurboMaker compatibility
     */
    public function generateAllWithStatus(ModelSchema $schema, array $options = []): array
    {
        $results = $this->generateAll($schema, $options);

        // Transform results to include success status for TurboMaker compatibility
        $enhancedResults = [];

        foreach ($results as $generatorName => $result) {
            $enhancedResults[$generatorName] = [
                'success' => isset($result['json']) && isset($result['yaml']),
                'json' => $result['json'] ?? null,
                'yaml' => $result['yaml'] ?? null,
                'metadata' => $result['metadata'] ?? null,
            ];
        }

        return $enhancedResults;
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

            if ($options['observers'] ?? false) {
                $result = $this->generateObservers($schema, $options);
                $results['observers'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Observers generation failed';
                }
            }

            if ($options['services'] ?? false) {
                $result = $this->generateServices($schema, $options);
                $results['services'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Services generation failed';
                }
            }

            if ($options['actions'] ?? false) {
                $result = $this->generateActions($schema, $options);
                $results['actions'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Actions generation failed';
                }
            }

            if ($options['rules'] ?? false) {
                $result = $this->generateRules($schema, $options);
                $results['rules'] = $result;
                if ($result['success'] ?? false) {
                    $generatedCount++;
                } else {
                    $errors[] = 'Rules generation failed';
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

    public function generateObservers(ModelSchema $schema, array $options = []): array
    {
        return $this->observerGenerator->generate($schema, $options);
    }

    public function generateServices(ModelSchema $schema, array $options = []): array
    {
        return $this->serviceGenerator->generate($schema, $options);
    }

    public function generateActions(ModelSchema $schema, array $options = []): array
    {
        return $this->actionGenerator->generate($schema, $options);
    }

    public function generateRules(ModelSchema $schema, array $options = []): array
    {
        return $this->ruleGenerator->generate($schema, $options);
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
            'observers' => [
                'name' => 'Observers Data',
                'description' => 'Generate structured data for Eloquent Observer classes with model event handlers (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'services' => [
                'name' => 'Services Data',
                'description' => 'Generate structured data for Service classes with business logic and CRUD operations (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'actions' => [
                'name' => 'Actions Data',
                'description' => 'Generate structured data for Action classes with single responsibility operations (insertable in parent JSON/YAML)',
                'outputs' => ['json', 'yaml'],
            ],
            'rules' => [
                'name' => 'Validation Rules Data',
                'description' => 'Generate structured data for custom Validation Rule classes with business validation logic (insertable in parent JSON/YAML)',
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
                'policies' => 'policy',
                'observers' => 'observer',
                'services' => 'service',
                'actions' => 'action',
                'rules' => 'rule',
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
            'observers', 'observer' => $this->generateObservers($schema, $options),
            'services', 'service' => $this->generateServices($schema, $options),
            'actions', 'action' => $this->generateActions($schema, $options),
            'rules', 'rule' => $this->generateRules($schema, $options),
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
            'observers' => $this->observerGenerator,
            'services' => $this->serviceGenerator,
            'actions' => $this->actionGenerator,
            'rules' => $this->ruleGenerator,
            default => throw new InvalidArgumentException("Unknown generator type: {$type}")
        };
    }

    /**
     * Generate multiple components and return combined JSON/YAML fragments
     */
    public function generateMultiple(ModelSchema $schema, array $generators, array $options = []): array
    {
        $results = [];
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
                $results[$generatorType] = $result;

            } catch (Exception $e) {
                if ($options['enable_validation'] ?? false) {
                    $validationResults['is_valid'] = false;
                    $validationResults['errors'][] = "Failed to generate {$generatorType}: ".$e->getMessage();
                }
            }
        }

        // Add validation results if enabled
        if ($options['enable_validation'] ?? false) {
            $results['validation_results'] = [
                'json' => json_encode($validationResults, JSON_PRETTY_PRINT),
                'yaml' => \Symfony\Component\Yaml\Yaml::dump($validationResults, 4, 2),
            ];
        }

        // Aggregate all results into combined JSON and YAML formats
        $combinedData = [];
        foreach ($results as $generatorType => $result) {
            if (isset($result['json'])) {
                $jsonData = json_decode($result['json'], true);
                $combinedData[$generatorType] = $jsonData;
            }
        }

        return [
            'json' => json_encode($combinedData, JSON_PRETTY_PRINT),
            'yaml' => \Symfony\Component\Yaml\Yaml::dump($combinedData, 4, 2),
            'individual_results' => $results,
        ];
    }

    /**
     * Get description for a specific generator
     */
    protected function getGeneratorDescription(string $generatorName): string
    {
        return match ($generatorName) {
            'model' => 'Generates Laravel Eloquent model classes with relationships, casts, and configurations',
            'migration' => 'Generates Laravel database migration files with fields, indexes, and foreign keys',
            'requests' => 'Generates Laravel Form Request classes for validation (store/update)',
            'resources' => 'Generates Laravel API Resource classes for data transformation',
            'factory' => 'Generates Laravel Factory classes for model data generation and testing',
            'seeder' => 'Generates Laravel Seeder classes for database population',
            'controllers' => 'Generates Laravel Controller classes with CRUD operations and middleware',
            'tests' => 'Generates PHPUnit test classes for model and feature testing',
            'policies' => 'Generates Laravel Policy classes for authorization and access control',
            'observers' => 'Generates Laravel Observer classes with model event handlers for business logic',
            'services' => 'Generates Laravel Service classes with business logic layer and CRUD operations',
            'actions' => 'Generates Laravel Action classes with single responsibility operations and workflows',
            'rules' => 'Generates Laravel custom Validation Rule classes with business validation logic',
            default => "Generator for {$generatorName} components"
        };
    }
}
