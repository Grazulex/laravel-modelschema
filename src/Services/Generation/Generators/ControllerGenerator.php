<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Controllers (API and Web)
 */
class ControllerGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'controllers';
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
        $controllersData = [
            'api_controller' => $this->generateApiController($schema, $options),
            'web_controller' => $this->generateWebController($schema, $options),
            'resource_routes' => $this->generateResourceRoutes($schema, $options),
            'middleware' => $this->generateMiddleware($schema, $options),
            'policies' => $this->generatePolicyReferences($schema, $options),
        ];

        // Retourne la structure prête à être insérée : "controllers": { ... }
        return $this->toJsonFormat(['controllers' => $controllersData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $controllersData = [
            'api_controller' => $this->generateApiController($schema, $options),
            'web_controller' => $this->generateWebController($schema, $options),
            'resource_routes' => $this->generateResourceRoutes($schema, $options),
            'middleware' => $this->generateMiddleware($schema, $options),
            'policies' => $this->generatePolicyReferences($schema, $options),
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['controllers' => $controllersData], 4, 2);
    }

    protected function generateApiController(ModelSchema $schema, array $options): array
    {
        $controllerName = "{$schema->name}ApiController";
        $resourceClass = "{$schema->name}Resource";
        $collectionClass = "{$schema->name}Collection";
        $modelClass = $schema->name;

        return [
            'name' => $controllerName,
            'namespace' => $options['api_controller_namespace'] ?? 'App\\Http\\Controllers\\Api',
            'extends' => 'Controller',
            'traits' => ['AuthorizesRequests'],
            'model' => "App\\Models\\{$modelClass}",
            'response_resource' => $resourceClass,
            'collection_resource' => $collectionClass,
            'resource' => [
                'class' => $resourceClass,
                'collection' => $collectionClass,
            ],
            'methods' => $this->getApiControllerMethods($schema, $options),
            'validation' => $this->getValidationRules($schema, $options),
            'responses' => $this->getApiResponses($schema, $options),
            'filters' => $this->getApiFilters($schema, $options),
            'relationships' => $this->getControllerRelationships($schema, $options),
        ];
    }

    protected function generateWebController(ModelSchema $schema, array $options): array
    {
        $controllerName = "{$schema->name}Controller";
        $modelClass = $schema->name;

        return [
            'name' => $controllerName,
            'namespace' => $options['web_controller_namespace'] ?? 'App\\Http\\Controllers',
            'extends' => 'Controller',
            'traits' => ['AuthorizesRequests'],
            'model' => "App\\Models\\{$modelClass}",
            'views' => $this->getViewConfiguration($schema, $options),
            'methods' => $this->getWebControllerMethods($schema, $options),
            'validation' => $this->getValidationRules($schema, $options),
            'redirects' => $this->getRedirectConfiguration($schema, $options),
            'flash_messages' => $this->getFlashMessages($schema, $options),
            'breadcrumbs' => $this->getBreadcrumbConfiguration($schema, $options),
        ];
    }

    protected function generateResourceRoutes(ModelSchema $schema, array $options): array
    {
        $resourceName = mb_strtolower($schema->name);
        $routePrefix = $options['route_prefix'] ?? '';

        return [
            'api_routes' => [
                'prefix' => $routePrefix ? "{$routePrefix}/api" : 'api',
                'name' => "api.{$resourceName}",
                'resource' => $resourceName,
                'controller' => "{$schema->name}ApiController",
                'methods' => ['index', 'show', 'store', 'update', 'destroy'],
                'middleware' => ['api', 'auth:sanctum'],
                'parameters' => [
                    $resourceName => $this->getRouteKey($schema),
                ],
            ],
            'web_routes' => [
                'prefix' => $routePrefix,
                'name' => $resourceName,
                'resource' => $resourceName,
                'controller' => "{$schema->name}Controller",
                'methods' => ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'],
                'middleware' => ['web', 'auth'],
                'parameters' => [
                    $resourceName => $this->getRouteKey($schema),
                ],
            ],
            'additional_routes' => $this->getAdditionalRoutes($schema, $options),
        ];
    }

    protected function generateMiddleware(ModelSchema $schema, array $options): array
    {
        $middleware = [
            'global' => [
                'api' => ['api', 'throttle:api'],
                'web' => ['web'],
            ],
            'authentication' => [
                'api' => 'auth:sanctum',
                'web' => 'auth',
            ],
            'authorization' => [],
            'custom' => $options['custom_middleware'] ?? [],
        ];

        // Add authorization middleware based on schema
        if ($options['enable_policies'] ?? true) {
            $modelVariable = mb_strtolower($schema->name);
            $middleware['authorization'] = [
                'index' => "can:viewAny,App\\Models\\{$schema->name}",
                'show' => "can:view,{$modelVariable}",
                'create' => "can:create,App\\Models\\{$schema->name}",
                'store' => "can:create,App\\Models\\{$schema->name}",
                'edit' => "can:update,{$modelVariable}",
                'update' => "can:update,{$modelVariable}",
                'destroy' => "can:delete,{$modelVariable}",
            ];
        }

        return $middleware;
    }

    protected function generatePolicyReferences(ModelSchema $schema, array $options): array
    {
        if (! ($options['enable_policies'] ?? true)) {
            return [];
        }

        return [
            'class' => "{$schema->name}Policy",
            'namespace' => $options['policy_namespace'] ?? 'App\\Policies',
            'methods' => [
                'viewAny' => 'Can view any records',
                'view' => 'Can view specific record',
                'create' => 'Can create new records',
                'update' => 'Can update existing records',
                'delete' => 'Can delete records',
                'restore' => $schema->hasSoftDeletes() ? 'Can restore soft-deleted records' : null,
                'forceDelete' => $schema->hasSoftDeletes() ? 'Can permanently delete records' : null,
            ],
            'gates' => $this->generateGates($schema, $options),
        ];
    }

    protected function getApiControllerMethods(ModelSchema $schema, array $options): array
    {
        $methods = [
            'index' => [
                'description' => 'Display a listing of the resource',
                'parameters' => ['request'],
                'return_type' => 'JsonResponse',
                'features' => ['pagination', 'filtering', 'sorting'],
                'response_codes' => [200],
            ],
            'show' => [
                'description' => 'Display the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'JsonResponse',
                'features' => ['resource_loading', 'relationship_loading'],
                'response_codes' => [200, 404],
            ],
            'store' => [
                'description' => 'Store a newly created resource',
                'parameters' => ['request'],
                'return_type' => 'JsonResponse',
                'features' => ['validation', 'creation', 'resource_response'],
                'response_codes' => [201, 422],
            ],
            'update' => [
                'description' => 'Update the specified resource',
                'parameters' => ['request', mb_strtolower($schema->name)],
                'return_type' => 'JsonResponse',
                'features' => ['validation', 'updating', 'resource_response'],
                'response_codes' => [200, 404, 422],
            ],
            'destroy' => [
                'description' => 'Remove the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'JsonResponse',
                'features' => ['deletion', 'relationship_handling'],
                'response_codes' => [204, 404],
            ],
        ];

        // Add soft delete methods if applicable
        if ($schema->hasSoftDeletes()) {
            $methods['restore'] = [
                'description' => 'Restore the specified soft-deleted resource',
                'parameters' => ['id'],
                'return_type' => 'JsonResponse',
                'features' => ['soft_delete_restoration'],
                'response_codes' => [200, 404],
            ];
            $methods['forceDestroy'] = [
                'description' => 'Permanently delete the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'JsonResponse',
                'features' => ['permanent_deletion'],
                'response_codes' => [204, 404],
            ];
        }

        return $methods;
    }

    protected function getWebControllerMethods(ModelSchema $schema, array $options): array
    {
        return [
            'index' => [
                'description' => 'Display a listing of the resource',
                'parameters' => ['request'],
                'return_type' => 'View',
                'view' => "{$schema->table}.index",
                'features' => ['pagination', 'filtering', 'breadcrumbs'],
            ],
            'create' => [
                'description' => 'Show the form for creating a new resource',
                'parameters' => [],
                'return_type' => 'View',
                'view' => "{$schema->table}.create",
                'features' => ['form_data', 'breadcrumbs'],
            ],
            'store' => [
                'description' => 'Store a newly created resource',
                'parameters' => ['request'],
                'return_type' => 'RedirectResponse',
                'features' => ['validation', 'creation', 'flash_messages'],
                'redirect_to' => "{$schema->table}.show",
            ],
            'show' => [
                'description' => 'Display the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'View',
                'view' => "{$schema->table}.show",
                'features' => ['relationship_loading', 'breadcrumbs'],
            ],
            'edit' => [
                'description' => 'Show the form for editing the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'View',
                'view' => "{$schema->table}.edit",
                'features' => ['form_data', 'breadcrumbs'],
            ],
            'update' => [
                'description' => 'Update the specified resource',
                'parameters' => ['request', mb_strtolower($schema->name)],
                'return_type' => 'RedirectResponse',
                'features' => ['validation', 'updating', 'flash_messages'],
                'redirect_to' => "{$schema->table}.show",
            ],
            'destroy' => [
                'description' => 'Remove the specified resource',
                'parameters' => [mb_strtolower($schema->name)],
                'return_type' => 'RedirectResponse',
                'features' => ['deletion', 'flash_messages'],
                'redirect_to' => "{$schema->table}.index",
            ],
        ];
    }

    protected function getValidationRules(ModelSchema $schema, array $options): array
    {
        $rules = [
            'store' => [],
            'update' => [],
        ];

        foreach ($schema->getAllFields() as $field) {
            if (! in_array($field->name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                $fieldRules = $field->rules ?? [];

                // Add unique rule modifications for update
                $updateRules = array_map(function ($rule) use ($field) {
                    if (str_contains($rule, 'unique:')) {
                        // Transform unique:table to unique:table,field,{id}
                        if (preg_match('/^unique:([^,]+)$/', $rule, $matches)) {
                            return "unique:{$matches[1]},{$field->name},{id}";
                        }
                        // If already has field specified, just add {id}
                        if (preg_match('/^unique:([^,]+),([^,]+)$/', $rule, $matches)) {
                            return "unique:{$matches[1]},{$matches[2]},{id}";
                        }
                    }

                    return $rule;
                }, $fieldRules);

                $rules['store'][$field->name] = $fieldRules;
                $rules['update'][$field->name] = $updateRules;
            }
        }

        return $rules;
    }

    protected function getApiResponses(ModelSchema $schema, array $options): array
    {
        return [
            'success' => [
                'single' => 'Resource returned successfully',
                'collection' => 'Resources returned successfully',
                'created' => 'Resource created successfully',
                'updated' => 'Resource updated successfully',
                'deleted' => 'Resource deleted successfully',
            ],
            'error' => [
                'not_found' => 'Resource not found',
                'validation' => 'Validation failed',
                'unauthorized' => 'Unauthorized access',
                'forbidden' => 'Access forbidden',
                'server_error' => 'Internal server error',
            ],
            'structures' => [
                'single' => ['data' => 'ResourceClass'],
                'collection' => ['data' => 'array', 'meta' => 'pagination'],
                'error' => ['message' => 'string', 'errors' => 'array'],
            ],
        ];
    }

    protected function getApiFilters(ModelSchema $schema, array $options): array
    {
        $filters = [];

        foreach ($schema->getAllFields() as $field) {
            if (in_array($field->type, ['string', 'text'])) {
                $filters[$field->name] = ['type' => 'search', 'operator' => 'like'];
            } elseif (in_array($field->type, ['integer', 'bigInteger', 'decimal'])) {
                $filters[$field->name] = ['type' => 'range', 'operators' => ['=', '>', '<', '>=', '<=']];
            } elseif (in_array($field->type, ['date', 'datetime', 'timestamp'])) {
                $filters[$field->name] = ['type' => 'date_range', 'operators' => ['=', '>', '<', '>=', '<=']];
            } elseif ($field->type === 'boolean') {
                $filters[$field->name] = ['type' => 'boolean', 'operator' => '='];
            }
        }

        return $filters;
    }

    protected function getControllerRelationships(ModelSchema $schema, array $options): array
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            $relationships[$relationship->name] = [
                'type' => $relationship->type,
                'model' => $relationship->model,
                'eager_load' => $relationship->type === 'belongsTo',
                'load_count' => in_array($relationship->type, ['hasMany', 'belongsToMany']),
                'nested_routes' => $options['enable_nested_routes'] ?? false,
            ];
        }

        return $relationships;
    }

    protected function getViewConfiguration(ModelSchema $schema, array $options): array
    {
        $viewPrefix = $options['view_prefix'] ?? $schema->table;

        return [
            'prefix' => $viewPrefix,
            'layout' => $options['layout'] ?? 'layouts.app',
            'views' => [
                'index' => "{$viewPrefix}.index",
                'create' => "{$viewPrefix}.create",
                'show' => "{$viewPrefix}.show",
                'edit' => "{$viewPrefix}.edit",
            ],
            'partials' => [
                'form' => "{$viewPrefix}._form",
                'table' => "{$viewPrefix}._table",
                'card' => "{$viewPrefix}._card",
            ],
        ];
    }

    protected function getRedirectConfiguration(ModelSchema $schema, array $options): array
    {
        $routePrefix = $options['route_prefix'] ?? $schema->table;

        return [
            'after_store' => "{$routePrefix}.show",
            'after_update' => "{$routePrefix}.show",
            'after_destroy' => "{$routePrefix}.index",
            'fallback' => "{$routePrefix}.index",
        ];
    }

    protected function getFlashMessages(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;

        return [
            'success' => [
                'created' => "{$modelName} created successfully.",
                'updated' => "{$modelName} updated successfully.",
                'deleted' => "{$modelName} deleted successfully.",
                'restored' => "{$modelName} restored successfully.",
            ],
            'error' => [
                'not_found' => "{$modelName} not found.",
                'delete_failed' => "Failed to delete {$modelName}.",
                'update_failed' => "Failed to update {$modelName}.",
            ],
        ];
    }

    protected function getBreadcrumbConfiguration(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $routePrefix = $schema->table;

        return [
            'index' => [
                ['title' => 'Home', 'route' => 'dashboard'],
                ['title' => $modelName.'s', 'route' => null],
            ],
            'create' => [
                ['title' => 'Home', 'route' => 'dashboard'],
                ['title' => $modelName.'s', 'route' => "{$routePrefix}.index"],
                ['title' => 'Create', 'route' => null],
            ],
            'show' => [
                ['title' => 'Home', 'route' => 'dashboard'],
                ['title' => $modelName.'s', 'route' => "{$routePrefix}.index"],
                ['title' => 'View', 'route' => null],
            ],
            'edit' => [
                ['title' => 'Home', 'route' => 'dashboard'],
                ['title' => $modelName.'s', 'route' => "{$routePrefix}.index"],
                ['title' => 'Edit', 'route' => null],
            ],
        ];
    }

    protected function getAdditionalRoutes(ModelSchema $schema, array $options): array
    {
        $additional = [];

        if ($schema->hasSoftDeletes()) {
            $additional['trashed'] = [
                'method' => 'GET',
                'uri' => "{$schema->table}/trashed",
                'action' => 'trashed',
                'name' => "{$schema->table}.trashed",
            ];
            $additional['restore'] = [
                'method' => 'PATCH',
                'uri' => "{$schema->table}/{id}/restore",
                'action' => 'restore',
                'name' => "{$schema->table}.restore",
            ];
        }

        // Add bulk operations if requested
        if ($options['enable_bulk_operations'] ?? false) {
            $additional['bulk_destroy'] = [
                'method' => 'DELETE',
                'uri' => "{$schema->table}/bulk",
                'action' => 'bulkDestroy',
                'name' => "{$schema->table}.bulk.destroy",
            ];
        }

        return $additional;
    }

    protected function generateGates(ModelSchema $schema, array $options): array
    {
        return [
            'manage_'.mb_strtolower($schema->name) => 'Can manage all '.$schema->name.' operations',
            'view_'.mb_strtolower($schema->name) => 'Can view '.$schema->name.' data',
            'create_'.mb_strtolower($schema->name) => 'Can create '.$schema->name,
            'update_'.mb_strtolower($schema->name) => 'Can update '.$schema->name,
            'delete_'.mb_strtolower($schema->name) => 'Can delete '.$schema->name,
        ];
    }

    private function getRouteKey(ModelSchema $schema): string
    {
        // Check if there's a specific route key field, otherwise default to 'id'
        foreach ($schema->getAllFields() as $field) {
            if ($field->name === 'slug' || $field->name === 'uuid') {
                return $field->name;
            }
        }

        return 'id';
    }
}
