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
                'load_condition' => $this->getOptimalLoadCondition($relationship->type, 0),
                'conditionally_load' => $relationship->type !== 'belongsTo',
                'fields' => $this->getNestedFields($relationship->type, 0, $options),
                'depth' => 1,
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
                    $relationData['always_include'] = $relationship->type === 'belongsTo';
                    break;
                case 'morphTo':
                case 'morphMany':
                    $relationData['polymorphic'] = true;
                    break;
            }

            // Add nested relationships if depth allows
            $relationData['nested_relationships'] = $this->getNestedRelationships($schema, array_merge($options, [
                'current_depth' => 1,
                'visited_models' => [$schema->name],
            ]));

            $relationships[$relationship->name] = $relationData;
        }

        return $relationships;
    }

    protected function getNestedRelationships(ModelSchema $schema, array $options = []): array
    {
        $maxDepth = $options['nested_depth'] ?? 2;
        $currentDepth = $options['current_depth'] ?? 0;
        $visitedModels = $options['visited_models'] ?? [];

        if ($maxDepth <= 0 || $currentDepth >= $maxDepth) {
            return [];
        }

        // Prevent circular references
        if (in_array($schema->name, $visitedModels)) {
            return [];
        }

        $nested = [];
        $newVisitedModels = array_merge($visitedModels, [$schema->name]);

        foreach ($schema->relationships as $relationship) {
            $relationConfig = $this->buildNestedRelationConfig(
                $relationship,
                $currentDepth,
                $maxDepth,
                $newVisitedModels,
                $options
            );

            if ($relationConfig !== null) {
                $nested[$relationship->name] = $relationConfig;
            }
        }

        return $nested;
    }

    protected function buildNestedRelationConfig($relationship, int $currentDepth, int $maxDepth, array $visitedModels, array $options): ?array
    {
        $modelClass = class_basename($relationship->model);
        $resourceClass = $this->getResourceClassForModel($relationship->model);

        // Base configuration for the nested relationship
        $config = [
            'resource_class' => $resourceClass,
            'model_class' => $relationship->model,
            'type' => $relationship->type,
            'depth' => $currentDepth + 1,
            'load_condition' => $this->getOptimalLoadCondition($relationship->type, $currentDepth),
            'fields' => $this->getNestedFields($relationship->type, $currentDepth, $options),
        ];

        // Add relationship-specific configurations
        switch ($relationship->type) {
            case 'belongsTo':
                $config['always_include'] = $currentDepth === 0; // Always include direct belongsTo
                $config['fields'] = array_merge($config['fields'], ['name', 'title']);
                break;

            case 'hasOne':
                $config['fields'] = $this->getDetailedFields($currentDepth);
                break;

            case 'hasMany':
                $config['limit'] = $this->getCollectionLimit($currentDepth, $options);
                $config['with_count'] = true;
                $config['paginated'] = $currentDepth === 0;
                $config['fields'] = $this->getSummaryFields($currentDepth);
                break;

            case 'belongsToMany':
                $config['limit'] = $this->getCollectionLimit($currentDepth, $options);
                $config['with_count'] = true;
                $config['with_pivot'] = $currentDepth === 0;
                $config['paginated'] = $currentDepth === 0;
                $config['fields'] = $this->getSummaryFields($currentDepth);
                break;

            case 'morphTo':
            case 'morphMany':
                $config['polymorphic'] = true;
                $config['fields'] = $this->getPolymorphicFields($currentDepth);
                break;
        }

        // For deeper nesting, generate sub-relationships if we haven't reached max depth
        if (($currentDepth + 1) < $maxDepth && ! in_array($modelClass, $visitedModels)) {
            // Note: In a real implementation, we would need access to the related model's schema
            // For now, we'll simulate this with basic relationship detection
            $config['nested_relationships'] = $this->getSimulatedNestedRelationships(
                $relationship,
                $currentDepth + 1,
                $maxDepth,
                $visitedModels,
                $options
            );
        }

        return $config;
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
        // Get basic fields that are commonly used in partial resources
        $basicFields = ['id' => ['type' => 'integer']];
        $summaryFields = ['id' => ['type' => 'integer']];

        // Add common fields if they exist in the schema
        $commonBasicFields = ['name', 'title', 'email'];
        $commonSummaryFields = ['name', 'title', 'email', 'status'];

        foreach ($schema->getAllFields() as $field) {
            $fieldType = match ($field->type) {
                'string', 'text' => 'string',
                'integer', 'bigInteger' => 'integer',
                'boolean' => 'boolean',
                'timestamp', 'datetime' => 'string',
                default => 'string'
            };

            if (in_array($field->name, $commonBasicFields)) {
                $basicFields[$field->name] = ['type' => $fieldType];
            }

            if (in_array($field->name, $commonSummaryFields)) {
                $summaryFields[$field->name] = ['type' => $fieldType];
            }

            // Always include timestamps in summary
            if (in_array($field->name, ['created_at', 'updated_at'])) {
                $summaryFields[$field->name] = ['type' => 'string', 'format' => 'datetime'];
            }

            // Include created_at in basic if timestamps are enabled
            if ($field->name === 'created_at') {
                $basicFields[$field->name] = ['type' => 'string', 'format' => 'datetime'];
            }
        }

        return [
            'basic' => [
                'name' => "{$schema->name}BasicResource",
                'fields' => $basicFields,
            ],
            'summary' => [
                'name' => "{$schema->name}SummaryResource",
                'fields' => $summaryFields,
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
            'default_sort' => $options['default_sort'] ?? 'created_at',
            'default_direction' => $options['default_direction'] ?? 'desc',
            'allowed_fields' => $sortableFields,
            'sortable_fields' => $sortableFields, // For backwards compatibility
        ];
    }

    /**
     * Get optimal load condition based on relationship type and depth
     */
    protected function getOptimalLoadCondition(string $relationshipType, int $currentDepth): string
    {
        if ($currentDepth === 0) {
            // Direct relationships - load based on type
            return match ($relationshipType) {
                'belongsTo' => 'whenLoaded', // Always try to load belongsTo
                'hasOne', 'hasMany', 'belongsToMany' => 'whenLoaded',
                'morphTo', 'morphMany' => 'whenLoaded',
                default => 'whenLoaded'
            };
        }

        // Deeper relationships - be more conservative
        return 'when_requested';
    }

    /**
     * Get appropriate fields based on relationship type and depth
     */
    protected function getNestedFields(string $relationshipType, int $currentDepth, array $options): array
    {
        if ($currentDepth === 0) {
            // Direct relationships - include more fields
            return ['id', 'name', 'title', 'slug', 'status', 'created_at'];
        }

        // Deeper relationships - minimal fields only
        return ['id', 'name', 'title'];
    }

    /**
     * Get detailed fields for hasOne relationships
     */
    protected function getDetailedFields(int $currentDepth): array
    {
        if ($currentDepth === 0) {
            return ['id', 'name', 'title', 'description', 'status', 'created_at', 'updated_at'];
        }

        return ['id', 'name', 'title', 'status'];
    }

    /**
     * Get summary fields for collection relationships
     */
    protected function getSummaryFields(int $currentDepth): array
    {
        if ($currentDepth === 0) {
            return ['id', 'name', 'title', 'slug', 'status', 'created_at'];
        }

        return ['id', 'name', 'title'];
    }

    /**
     * Get fields for polymorphic relationships
     */
    protected function getPolymorphicFields(int $currentDepth): array
    {
        $fields = ['id', 'name', 'title', 'type', 'created_at'];

        if ($currentDepth === 0) {
            $fields[] = 'updated_at';
        }

        return $fields;
    }

    /**
     * Get collection limit based on depth
     */
    protected function getCollectionLimit(int $currentDepth, array $options): int
    {
        $baseLimits = [
            0 => $options['relation_limit'] ?? 10,      // Direct relationships
            1 => $options['nested_limit'] ?? 5,         // First level nested
            2 => $options['deep_limit'] ?? 3,           // Deep nested
        ];

        return $baseLimits[$currentDepth] ?? 2;
    }

    /**
     * Simulate nested relationships for deeper levels
     * In a real implementation, this would load the actual related model schema
     */
    protected function getSimulatedNestedRelationships($relationship, int $currentDepth, int $maxDepth, array $visitedModels, array $options): array
    {
        // For now, we'll return common relationship patterns based on model names
        $modelClass = class_basename($relationship->model);
        $nestedRels = [];

        // Simulate common relationship patterns
        $commonPatterns = [
            'User' => ['profile' => 'hasOne', 'posts' => 'hasMany'],
            'Post' => ['user' => 'belongsTo', 'comments' => 'hasMany', 'categories' => 'belongsToMany'],
            'Comment' => ['user' => 'belongsTo', 'post' => 'belongsTo'],
            'Profile' => ['user' => 'belongsTo'],
            'Category' => ['posts' => 'belongsToMany'],
            'Role' => ['users' => 'belongsToMany', 'permissions' => 'belongsToMany'],
            'Permission' => ['roles' => 'belongsToMany'],
        ];

        if (isset($commonPatterns[$modelClass])) {
            foreach ($commonPatterns[$modelClass] as $relName => $relType) {
                $relatedModel = ucfirst($relName);
                if ($relType === 'hasMany' || $relType === 'belongsToMany') {
                    $relatedModel = mb_rtrim($relatedModel, 's'); // Simple singularization
                }

                if (! in_array($relatedModel, $visitedModels)) {
                    $nestedRels[$relName] = [
                        'resource_class' => $relatedModel.'Resource',
                        'type' => $relType,
                        'depth' => $currentDepth,
                        'load_condition' => 'when_requested',
                        'fields' => ['id', 'name'],
                        'limited' => true,
                    ];
                }
            }
        }

        return $nestedRels;
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
