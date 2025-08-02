<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Eloquent Models
 */
class ModelGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'model';
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
        $modelData = [
            'name' => $schema->name,
            'table' => $schema->table,
            'namespace' => $schema->getModelNamespace(),
            'fillable' => array_keys($schema->getFillableFields()),
            'casts' => $schema->getCastableFields(),
            'relationships' => $this->getRelationshipsData($schema),
            'options' => [
                'timestamps' => $schema->hasTimestamps(),
                'soft_deletes' => $schema->hasSoftDeletes(),
            ],
            'validation_rules' => $schema->getValidationRules(),
        ];

        // Retourne la structure prête à être insérée : "model": { ... }
        return $this->toJsonFormat(['model' => $modelData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $modelData = [
            'name' => $schema->name,
            'table' => $schema->table,
            'namespace' => $schema->getModelNamespace(),
            'fillable' => array_keys($schema->getFillableFields()),
            'casts' => $schema->getCastableFields(),
            'relationships' => $this->getRelationshipsData($schema),
            'options' => [
                'timestamps' => $schema->hasTimestamps(),
                'soft_deletes' => $schema->hasSoftDeletes(),
            ],
            'validation_rules' => $schema->getValidationRules(),
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['model' => $modelData], 4, 2);
    }

    protected function getRelationshipsData(ModelSchema $schema): array
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            $relationships[] = [
                'name' => $relationship->name,
                'type' => $relationship->type,
                'model' => $relationship->model,
                'foreign_key' => $relationship->foreignKey,
                'local_key' => $relationship->localKey,
                'pivot_table' => $relationship->pivotTable ?? null,
            ];
        }

        return $relationships;
    }
}
