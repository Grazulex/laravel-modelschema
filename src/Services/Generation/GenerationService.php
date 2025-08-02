<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation;

use Grazulex\LaravelModelschema\Contracts\GeneratorInterface;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\FactoryGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\MigrationGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ModelGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RequestGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ResourceGenerator;
use Grazulex\LaravelModelschema\Services\Generation\Generators\SeederGenerator;
use InvalidArgumentException;

/**
 * Main generation service that coordinates all generators
 * This service provides the main API for file generation
 */
class GenerationService
{
    public function __construct(
        protected ModelGenerator $modelGenerator = new ModelGenerator(),
        protected MigrationGenerator $migrationGenerator = new MigrationGenerator(),
        protected RequestGenerator $requestGenerator = new RequestGenerator(),
        protected ResourceGenerator $resourceGenerator = new ResourceGenerator(),
        protected FactoryGenerator $factoryGenerator = new FactoryGenerator(),
        protected SeederGenerator $seederGenerator = new SeederGenerator(),
    ) {}

    /**
     * Generate all files for a schema
     */
    public function generateAll(ModelSchema $schema, array $options = []): array
    {
        $results = [];

        if ($options['model'] ?? true) {
            $results['model'] = $this->generateModel($schema, $options);
        }

        if ($options['migration'] ?? true) {
            $results['migration'] = $this->generateMigration($schema, $options);
        }

        if ($options['requests'] ?? false) {
            $results['requests'] = $this->generateRequests($schema, $options);
        }

        if ($options['resources'] ?? false) {
            $results['resources'] = $this->generateResources($schema, $options);
        }

        if ($options['factory'] ?? false) {
            $results['factory'] = $this->generateFactory($schema, $options);
        }

        if ($options['seeder'] ?? false) {
            $results['seeder'] = $this->generateSeeder($schema, $options);
        }

        return $results;
    }

    /**
     * Generate Laravel Model
     */
    public function generateModel(ModelSchema $schema, array $options = []): array
    {
        return $this->modelGenerator->generate($schema, $options);
    }

    /**
     * Generate Laravel Migration
     */
    public function generateMigration(ModelSchema $schema, array $options = []): array
    {
        return $this->migrationGenerator->generate($schema, $options);
    }

    /**
     * Generate Form Requests (Store and Update)
     */
    public function generateRequests(ModelSchema $schema, array $options = []): array
    {
        return $this->requestGenerator->generate($schema, $options);
    }

    /**
     * Generate API Resources
     */
    public function generateResources(ModelSchema $schema, array $options = []): array
    {
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
        ];
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
            default => throw new InvalidArgumentException("Unknown generator type: {$type}")
        };
    }
}
