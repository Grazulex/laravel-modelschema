<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel API Resources Data
 */
class ResourceGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'resources';
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
        $resourcesData = [
            'resource' => [
                'name' => "{$schema->name}Resource",
                'namespace' => ($options['resources_namespace'] ?? 'App\\Http\\Resources'),
                'fields' => $this->getResourceFields($schema),
                'relationships' => $this->getResourceRelationships($schema),
            ],
            'collection' => [
                'name' => "{$schema->name}Collection",
                'namespace' => ($options['resources_namespace'] ?? 'App\\Http\\Resources'),
                'resource_class' => "{$schema->name}Resource",
                'meta_fields' => $this->getCollectionMetaFields($schema),
            ],
        ];

        // Retourne la structure prête à être insérée : "resources": { ... }
        return $this->toJsonFormat(['resources' => $resourcesData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $resourcesData = [
            'resource' => [
                'name' => "{$schema->name}Resource",
                'namespace' => ($options['resources_namespace'] ?? 'App\\Http\\Resources'),
                'fields' => $this->getResourceFields($schema),
                'relationships' => $this->getResourceRelationships($schema),
            ],
            'collection' => [
                'name' => "{$schema->name}Collection",
                'namespace' => ($options['resources_namespace'] ?? 'App\\Http\\Resources'),
                'resource_class' => "{$schema->name}Resource",
                'meta_fields' => $this->getCollectionMetaFields($schema),
            ],
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['resources' => $resourcesData], 4, 2);
    }

    protected function getResourceFields(ModelSchema $schema): array
    {
        $fields = [];

        foreach ($schema->getAllFields() as $field) {
            $fields[$field->name] = [
                'type' => $field->type,
                'cast' => $field->getCastType(),
                'nullable' => $field->nullable,
                'hidden' => in_array($field->name, ['password', 'remember_token']),
            ];
        }

        return $fields;
    }

    protected function getResourceRelationships(ModelSchema $schema): array
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            $relationships[$relationship->name] = [
                'type' => $relationship->type,
                'model' => $relationship->model,
                'resource_class' => "{$relationship->model}Resource",
                'load_when' => $relationship->type === 'belongsTo' ? 'always' : 'when_loaded',
            ];
        }

        return $relationships;
    }

    protected function getCollectionMetaFields(ModelSchema $schema): array
    {
        return [
            'total' => 'Total number of records',
            'per_page' => 'Records per page',
            'current_page' => 'Current page number',
            'last_page' => 'Last page number',
        ];
    }
}
