<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Database Seeder Data
 */
class SeederGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'seeder';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml'];
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        return match ($format) {
            'json' => $this->generateJson($schema, $options),
            'yaml' => $this->generateYaml($schema, $options),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    protected function generateJson(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son JSON
        $seederData = [
            'name' => "{$schema->name}Seeder",
            'namespace' => ($options['seeder_namespace'] ?? 'Database\\Seeders'),
            'model_class' => $schema->getModelClass(),
            'factory_class' => "{$schema->name}Factory",
            'count' => $options['seeder_count'] ?? 10,
            'dependencies' => $this->getSeederDependencies($schema),
            'data_sets' => $this->getSeederDataSets($schema, $options),
        ];

        // Retourne la structure prête à être insérée : "seeder": { ... }
        return $this->toJsonFormat(['seeder' => $seederData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $seederData = [
            'name' => "{$schema->name}Seeder",
            'namespace' => ($options['seeder_namespace'] ?? 'Database\\Seeders'),
            'model_class' => $schema->getModelClass(),
            'factory_class' => "{$schema->name}Factory",
            'count' => $options['seeder_count'] ?? 10,
            'dependencies' => $this->getSeederDependencies($schema),
            'data_sets' => $this->getSeederDataSets($schema, $options),
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['seeder' => $seederData], 4, 2);
    }

    protected function getSeederDependencies(ModelSchema $schema): array
    {
        $dependencies = [];

        foreach ($schema->relationships as $relationship) {
            if ($relationship->type === 'belongsTo') {
                $dependencies[] = "{$relationship->model}Seeder";
            }
        }

        return array_unique($dependencies);
    }

    protected function getSeederDataSets(ModelSchema $schema, array $options): array
    {
        $dataSets = [];

        // Set par défaut avec factory
        $dataSets['default'] = [
            'method' => 'factory',
            'count' => $options['seeder_count'] ?? 10,
            'using_factory' => true,
        ];

        // Set de données spécifiques si demandé
        if ($options['create_sample_data'] ?? false) {
            $dataSets['sample'] = [
                'method' => 'create',
                'count' => 3,
                'using_factory' => false,
                'data' => $this->getSampleData($schema),
            ];
        }

        return $dataSets;
    }

    protected function getSampleData(ModelSchema $schema): array
    {
        $sampleData = [];

        foreach ($schema->getFillableFields() as $field) {
            $sampleData[$field->name] = $this->getSampleValue($field);
        }

        return $sampleData;
    }

    protected function getSampleValue($field): mixed
    {
        return match ($field->type) {
            'string' => "Sample {$field->name}",
            'email' => 'sample@example.com',
            'text' => "Sample {$field->name} content",
            'integer', 'bigInteger' => 1,
            'decimal', 'float' => 10.50,
            'boolean' => true,
            'date' => '2024-01-01',
            'timestamp' => '2024-01-01 00:00:00',
            'json' => ['sample' => 'data'],
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            default => "Sample {$field->name}"
        };
    }
}
