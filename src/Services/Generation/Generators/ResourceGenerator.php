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
        // Check if we want enhanced structure or simple structure
        $isEnhanced = $options['enhanced'] ?? true;

        if ($isEnhanced) {
            // Enhanced structure with multiple resource types
            $resourcesData = [
                'main_resource' => [
                    'name' => "{$schema->name}Resource",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'model' => "App\\Models\\{$schema->name}",
                    'fields' => $this->getResourceFields($schema, $options),
                    'relationships' => $this->getResourceRelationships($schema, $options),
                    'conditional_fields' => $this->getConditionalFields($schema, $options),
                ],
                'collection_resource' => [
                    'name' => "{$schema->name}Collection",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'resource_class' => "{$schema->name}Resource",
                    'pagination' => $this->getPaginationConfig($schema, $options),
                    'filtering' => $this->getFilteringConfig($schema, $options),
                    'sorting' => $this->getSortingConfig($schema, $options),
                ],
                'partial_resources' => $this->getPartialResources($schema, $options),
                'relationship_resources' => $this->getRelationshipResourcesConfig($schema, $options),
            ];
        } else {
            // Simple structure for basic tests
            $resourcesData = [
                'resource' => [
                    'name' => "{$schema->name}Resource",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'model' => "App\\Models\\{$schema->name}",
                    'fields' => $this->getResourceFields($schema, $options),
                    'relationships' => $this->getResourceRelationships($schema, $options),
                ],
                'collection' => [
                    'name' => "{$schema->name}Collection",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'resource_class' => "{$schema->name}Resource",
                ],
            ];
        }

        // Retourne la structure prête à être insérée : "resources": { ... }
        return $this->toJsonFormat(['resources' => $resourcesData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Check if we want enhanced structure or simple structure
        $isEnhanced = $options['enhanced'] ?? true;

        if ($isEnhanced) {
            // Enhanced structure with multiple resource types
            $resourcesData = [
                'main_resource' => [
                    'name' => "{$schema->name}Resource",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'model' => "App\\Models\\{$schema->name}",
                    'fields' => $this->getResourceFields($schema, $options),
                    'relationships' => $this->getResourceRelationships($schema, $options),
                    'conditional_fields' => $this->getConditionalFields($schema, $options),
                ],
                'collection_resource' => [
                    'name' => "{$schema->name}Collection",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'resource_class' => "{$schema->name}Resource",
                    'pagination' => $this->getPaginationConfig($schema, $options),
                    'filtering' => $this->getFilteringConfig($schema, $options),
                    'sorting' => $this->getSortingConfig($schema, $options),
                ],
                'partial_resources' => $this->getPartialResources($schema, $options),
                'relationship_resources' => $this->getRelationshipResourcesConfig($schema, $options),
            ];
        } else {
            // Simple structure for basic tests
            $resourcesData = [
                'resource' => [
                    'name' => "{$schema->name}Resource",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'model' => "App\\Models\\{$schema->name}",
                    'fields' => $this->getResourceFields($schema, $options),
                    'relationships' => $this->getResourceRelationships($schema, $options),
                ],
                'collection' => [
                    'name' => "{$schema->name}Collection",
                    'namespace' => ($options['namespace'] ?? 'App\\Http\\Resources'),
                    'resource_class' => "{$schema->name}Resource",
                ],
            ];
        }

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['resources' => $resourcesData], 4, 2);
    }

    protected function getResourceFields(ModelSchema $schema, array $options = []): array
    {
        $fields = [];
        $includeHidden = $options['include_hidden'] ?? false;
        $includeTimestamps = $options['include_timestamps'] ?? true;
        $fieldTransformations = $options['field_transformations'] ?? [];

        foreach ($schema->getAllFields() as $field) {
            $isHidden = in_array($field->name, ['password', 'remember_token']);
            $isTimestamp = in_array($field->name, ['created_at', 'updated_at']);

            if ($isHidden && ! $includeHidden) {
                continue;
            }

            if ($isTimestamp && ! $includeTimestamps) {
                continue;
            }

            // Transform database field types to API types
            $apiType = $this->getApiFieldType($field->type);
            $format = $this->getApiFieldFormat($field->type);

            $fieldData = [
                'type' => $apiType,
                'original_type' => $field->type,
                'cast' => $field->getCastType(),
                'nullable' => $field->nullable,
                'hidden' => $isHidden,
                'transform' => $fieldTransformations[$field->name] ?? null,
            ];

            if ($format !== null && $format !== '' && $format !== '0') {
                $fieldData['format'] = $format;
            }

            // Add custom field transformations based on type
            if ($field->type === 'timestamp') {
                $fieldData['format'] = 'Y-m-d H:i:s';
                $fieldData['timezone'] = true;
            } elseif ($field->type === 'date') {
                $fieldData['format'] = 'Y-m-d';
            } elseif ($field->type === 'decimal') {
                $fieldData['decimal_places'] = 2;
            }

            $fields[$field->name] = $fieldData;
        }

        return $fields;
    }

    protected function getResourceRelationships(ModelSchema $schema, array $options = []): array
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            // Extract class name from full namespace
            $modelClass = class_basename($relationship->model);
            $resourceName = $modelClass.'Resource';

            $relationData = [
                'type' => $relationship->type,
                'model' => $relationship->model,
                'resource' => $resourceName,
                'load_condition' => 'whenLoaded',
                'conditionally_load' => $relationship->type !== 'belongsTo',
            ];

            // Add specific handling for different relationship types
            switch ($relationship->type) {
                case 'hasMany':
                    $relationData['with_count'] = true;
                    $relationData['paginated'] = true;
                    $relationData['limit'] = $options['relation_limit'] ?? 10;
                    break;
                case 'belongsToMany':
                    $relationData['with_count'] = true;
                    $relationData['with_pivot'] = true;
                    $relationData['paginated'] = true;
                    $relationData['limit'] = $options['relation_limit'] ?? 10;
                    break;
                case 'hasOne':
                case 'belongsTo':
                    $relationData['always_load'] = $relationship->type === 'belongsTo';
                    break;
                case 'morphTo':
                case 'morphMany':
                    $relationData['polymorphic'] = true;
                    break;
            }

            $relationships[$relationship->name] = $relationData;
        }

        return $relationships;
    }

    protected function getNestedRelationships(ModelSchema $schema, array $options = []): array
    {
        $nested = [];
        $maxDepth = $options['nested_depth'] ?? 2;

        if ($maxDepth <= 0) {
            return $nested;
        }

        foreach ($schema->relationships as $relationship) {
            if (in_array($relationship->type, ['hasOne', 'belongsTo'])) {
                $nested[$relationship->name] = [
                    'resource_class' => $this->getResourceClassForModel($relationship->model),
                    'fields' => ['id', 'name', 'created_at'],
                    'load_when' => 'when_loaded',
                    'depth' => 1,
                ];
            }
        }

        return $nested;
    }

    protected function getConditionalFields(ModelSchema $schema, array $options = []): array
    {
        $conditional = [];

        // Add nullable fields as conditional fields
        foreach ($schema->getAllFields() as $field) {
            if ($field->nullable && ! in_array($field->name, ['created_at', 'updated_at', 'deleted_at'])) {
                $conditional[$field->name] = [
                    'condition' => 'when_not_null',
                    'type' => $this->getApiFieldType($field->type),
                ];

                $format = $this->getApiFieldFormat($field->type);
                if ($format !== null && $format !== '' && $format !== '0') {
                    $conditional[$field->name]['format'] = $format;
                }
            }
        }

        return $conditional;
    }

    protected function getResourceMetaData(ModelSchema $schema, array $options = []): array
    {
        $meta = [
            'model_name' => $schema->name,
            'table_name' => $schema->table,
            'generated_at' => now()->toISOString(),
        ];

        if ($options['include_counts'] ?? true) {
            $meta['relationship_counts'] = array_map(
                fn ($rel): string => $rel->name.'_count',
                $schema->relationships
            );
        }

        if ($options['include_links'] ?? false) {
            $meta['links'] = [
                'self' => "api/{$schema->table}/{id}",
                'edit' => "api/{$schema->table}/{id}/edit",
                'delete' => "api/{$schema->table}/{id}",
            ];
        }

        return $meta;
    }

    protected function getPaginationConfig(ModelSchema $schema, array $options): array
    {
        return [
            'enabled' => $options['pagination'] ?? true,
            'per_page' => $options['pagination_per_page'] ?? $options['per_page'] ?? 15,
            'max_per_page' => $options['max_per_page'] ?? 100,
            'page_name' => 'page',
            'per_page_name' => 'per_page',
            'show_links' => true,
            'show_meta' => true,
            'meta_fields' => $this->getCollectionMetaFields($schema),
        ];
    }

    protected function getFilteringConfig(ModelSchema $schema, array $options): array
    {
        $filterableFields = [];
        foreach ($schema->getAllFields() as $field) {
            if (in_array($field->type, ['string', 'integer', 'boolean', 'date', 'datetime'])) {
                $filterableFields[] = $field->name;
            }
        }

        return [
            'enabled' => $options['enable_filtering'] ?? $options['filtering'] ?? true,
            'fields' => $filterableFields,
            'filterable_fields' => $filterableFields,
        ];
    }

    protected function getPartialResources(ModelSchema $schema, array $options): array
    {
        return [
            'basic' => [
                'name' => "{$schema->name}BasicResource",
                'fields' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'created_at' => ['type' => 'string', 'format' => 'datetime'],
                ],
            ],
            'summary' => [
                'name' => "{$schema->name}SummaryResource",
                'fields' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'created_at' => ['type' => 'string', 'format' => 'datetime'],
                    'updated_at' => ['type' => 'string', 'format' => 'datetime'],
                ],
            ],
            'detailed' => [
                'name' => "{$schema->name}DetailedResource",
                'include_all_fields' => true,
                'include_all_relationships' => true,
                'relationships' => array_keys($schema->relationships),
            ],
        ];
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

    /**
     * Get list fields for partial resources
     */
    protected function getListFields(ModelSchema $schema): array
    {
        $listFields = ['id'];

        foreach ($schema->getAllFields() as $field) {
            if (in_array($field->name, ['name', 'title', 'email', 'status'])) {
                $listFields[] = $field->name;
            }
        }

        $listFields[] = 'created_at';
        $listFields[] = 'updated_at';

        return array_unique($listFields);
    }

    /**
     * Get sorting configuration for collection resources
     */
    protected function getSortingConfig(ModelSchema $schema, array $options): array
    {
        $sortableFields = [];
        foreach ($schema->getAllFields() as $field) {
            if (in_array($field->type, ['string', 'integer', 'date', 'datetime'])) {
                $sortableFields[] = $field->name;
            }
        }

        return [
            'enabled' => $options['enable_sorting'] ?? $options['sorting'] ?? true,
            'fields' => $sortableFields,
            'sortable_fields' => $sortableFields,
            'default' => $options['default_sort'] ?? 'created_at',
            'direction' => $options['default_direction'] ?? 'desc',
        ];
    }

    /**
     * Get relationship resources configuration
     */
    protected function getRelationshipResourcesConfig(ModelSchema $schema, array $options): array
    {
        $relationshipResources = [];

        foreach ($schema->relationships as $relationship) {
            // Extract class name from full namespace
            $modelClass = class_basename($relationship->model);
            $resourceName = $modelClass.'Resource';

            $relationshipResources[$relationship->name] = [
                'type' => $relationship->type,
                'model' => $relationship->model,
                'resource' => $resourceName,
                'loading' => $options['eager_load'] ?? false,
                'nested_loading' => true,
                'eager_load' => in_array($relationship->type, ['hasOne', 'belongsTo']),
                'with_count' => true,
            ];

            // Add specific properties for certain relationship types
            if ($relationship->type === 'belongsToMany') {
                $relationshipResources[$relationship->name]['with_pivot'] = true;
                $relationshipResources[$relationship->name]['pivot_fields'] = [];
            }
        }

        return $relationshipResources;
    }

    /**
     * Transform database field type to API type
     */
    protected function getApiFieldType(string $fieldType): string
    {
        return match ($fieldType) {
            'decimal', 'float', 'double' => 'float',
            'bigInteger', 'integer', 'smallInteger', 'tinyInteger' => 'integer',
            'json' => 'array',
            'datetime', 'timestamp', 'date' => 'string',
            'text', 'longText', 'mediumText' => 'string',
            default => $fieldType,
        };
    }

    /**
     * Get API field format for specific database types
     */
    protected function getApiFieldFormat(string $fieldType): ?string
    {
        return match ($fieldType) {
            'decimal' => 'decimal',
            'json' => 'json',
            'datetime', 'timestamp' => 'datetime',
            'date' => 'date',
            default => null,
        };
    }

    private function getResourceClassForModel(string $model): string
    {
        $modelName = class_basename($model);

        return "{$modelName}Resource";
    }
}
