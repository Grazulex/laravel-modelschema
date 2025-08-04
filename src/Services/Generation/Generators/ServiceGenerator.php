<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Service Classes
 */
class ServiceGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'service';
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
        $serviceName = "{$schema->name}Service";
        $serviceData = [
            'class_name' => $serviceName,
            'namespace' => $options['service_namespace'] ?? 'App\\Services',
            'model_class' => $schema->getModelClass(),
            'repository_class' => $options['repository_class'] ?? null,
            'implements' => $options['implements'] ?? [],
            'imports' => $this->getImports($schema, $options),
            'properties' => $this->getServiceProperties($schema, $options),
            'methods' => $this->getServiceMethods($schema, $options),
            'dependencies' => $this->getDependencies($schema, $options),
        ];

        return $this->toJsonFormat(['services' => [$serviceName => $serviceData]]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        $serviceName = "{$schema->name}Service";
        $serviceData = [
            'class_name' => $serviceName,
            'namespace' => $options['service_namespace'] ?? 'App\\Services',
            'model_class' => $schema->getModelClass(),
            'repository_class' => $options['repository_class'] ?? null,
            'implements' => $options['implements'] ?? [],
            'imports' => $this->getImports($schema, $options),
            'properties' => $this->getServiceProperties($schema, $options),
            'methods' => $this->getServiceMethods($schema, $options),
            'dependencies' => $this->getDependencies($schema, $options),
        ];

        return \Symfony\Component\Yaml\Yaml::dump(['services' => [$serviceName => $serviceData]], 4, 2);
    }

    protected function getImports(ModelSchema $schema, array $options): array
    {
        $imports = [
            $schema->getModelClass(),
            'Illuminate\\Database\\Eloquent\\Collection',
            'Illuminate\\Pagination\\LengthAwarePaginator',
        ];

        if ($options['use_repository'] ?? false) {
            $imports[] = $options['repository_class'] ?? "App\\Repositories\\{$schema->name}Repository";
        }

        if ($options['use_cache'] ?? true) {
            $imports[] = 'Illuminate\\Support\\Facades\\Cache';
        }

        if ($options['use_events'] ?? true) {
            $imports[] = 'Illuminate\\Support\\Facades\\Event';
        }

        if ($options['use_validation'] ?? true) {
            $imports[] = 'Illuminate\\Support\\Facades\\Validator';
            $imports[] = 'Illuminate\\Validation\\ValidationException';
        }

        return array_unique($imports);
    }

    protected function getServiceProperties(ModelSchema $schema, array $options): array
    {
        $properties = [];

        if ($options['use_repository'] ?? false) {
            $repositoryClass = $options['repository_class'] ?? "{$schema->name}Repository";
            $repositoryVariable = mb_strtolower($schema->name).'Repository';

            $properties[$repositoryVariable] = [
                'type' => $repositoryClass,
                'visibility' => 'protected',
                'description' => "The {$schema->name} repository instance.",
            ];
        }

        if ($options['cache_enabled'] ?? true) {
            $properties['cachePrefix'] = [
                'type' => 'string',
                'visibility' => 'protected',
                'value' => "'{$schema->table}'",
                'description' => 'Cache prefix for this service.',
            ];

            $properties['cacheTtl'] = [
                'type' => 'int',
                'visibility' => 'protected',
                'value' => '3600',
                'description' => 'Cache TTL in seconds.',
            ];
        }

        return $properties;
    }

    protected function getDependencies(ModelSchema $schema, array $options): array
    {
        $dependencies = [];

        if ($options['use_repository'] ?? false) {
            $repositoryClass = $options['repository_class'] ?? "App\\Repositories\\{$schema->name}Repository";
            $repositoryVariable = mb_strtolower($schema->name).'Repository';

            $dependencies[] = [
                'type' => $repositoryClass,
                'variable' => $repositoryVariable,
                'description' => "The {$schema->name} repository instance.",
            ];
        }

        return $dependencies;
    }

    protected function getServiceMethods(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $modelVariable = mb_strtolower($modelName);
        $methods = [];

        // CRUD Methods
        $methods['create'] = [
            'description' => "Create a new {$modelName}.",
            'parameters' => ['array $data'],
            'return_type' => $modelName,
            'logic' => $this->getCreateLogic($schema, $options, $modelVariable),
        ];

        $methods['update'] = [
            'description' => "Update an existing {$modelName}.",
            'parameters' => ["{$modelName} \${$modelVariable}", 'array $data'],
            'return_type' => $modelName,
            'logic' => $this->getUpdateLogic($schema, $options, $modelVariable),
        ];

        $methods['delete'] = [
            'description' => "Delete a {$modelName}.",
            'parameters' => ["{$modelName} \${$modelVariable}"],
            'return_type' => 'bool',
            'logic' => $this->getDeleteLogic($schema, $options, $modelVariable),
        ];

        $methods['findById'] = [
            'description' => "Find a {$modelName} by ID.",
            'parameters' => ['int $id'],
            'return_type' => "{$modelName}|null",
            'logic' => $this->getFindByIdLogic($schema, $options),
        ];

        $methods['getAll'] = [
            'description' => "Get all {$modelName} records.",
            'parameters' => ['array $filters = []'],
            'return_type' => 'Collection',
            'logic' => $this->getGetAllLogic($schema, $options),
        ];

        $methods['paginate'] = [
            'description' => "Get paginated {$modelName} records.",
            'parameters' => ['int $perPage = 15', 'array $filters = []'],
            'return_type' => 'LengthAwarePaginator',
            'logic' => $this->getPaginateLogic($schema, $options),
        ];

        // Business Logic Methods
        if ($options['include_business_methods'] ?? true) {
            return array_merge($methods, $this->getBusinessMethods($schema, $options));
        }

        return $methods;
    }

    protected function getCreateLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_validation'] ?? true) {
            $logic[] = '// Validate input data';
            $logic[] = "\$this->validateData(\$data, 'create');";
            $logic[] = '';
        }

        if ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = '// Create through repository';
            $logic[] = "\${$modelVariable} = \$this->{$repositoryVariable}->create(\$data);";
        } else {
            $logic[] = '// Create new instance';
            $logic[] = "\${$modelVariable} = {$modelName}::create(\$data);";
        }

        $logic[] = '';

        if ($options['use_events'] ?? true) {
            $logic[] = '// Fire created event';
            $logic[] = "Event::dispatch(new {$modelName}Created(\${$modelVariable}));";
            $logic[] = '';
        }

        if ($options['use_cache'] ?? true) {
            $logic[] = '// Clear relevant caches';
            $logic[] = '$this->clearCache();';
            $logic[] = '';
        }

        $logic[] = "return \${$modelVariable};";

        return implode("\n        ", $logic);
    }

    protected function getUpdateLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_validation'] ?? true) {
            $logic[] = '// Validate input data';
            $logic[] = "\$this->validateData(\$data, 'update', \${$modelVariable});";
            $logic[] = '';
        }

        if ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = '// Update through repository';
            $logic[] = "\${$modelVariable} = \$this->{$repositoryVariable}->update(\${$modelVariable}, \$data);";
        } else {
            $logic[] = '// Update instance';
            $logic[] = "\${$modelVariable}->update(\$data);";
        }

        $logic[] = '';

        if ($options['use_events'] ?? true) {
            $logic[] = '// Fire updated event';
            $logic[] = "Event::dispatch(new {$modelName}Updated(\${$modelVariable}));";
            $logic[] = '';
        }

        if ($options['use_cache'] ?? true) {
            $logic[] = '// Clear relevant caches';
            $logic[] = "\$this->clearCache(\${$modelVariable}->id);";
            $logic[] = '';
        }

        $logic[] = "return \${$modelVariable};";

        return implode("\n        ", $logic);
    }

    protected function getDeleteLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_events'] ?? true) {
            $logic[] = '// Fire deleting event';
            $logic[] = "Event::dispatch(new {$modelName}Deleting(\${$modelVariable}));";
            $logic[] = '';
        }

        if ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = '// Delete through repository';
            $logic[] = "\$result = \$this->{$repositoryVariable}->delete(\${$modelVariable});";
        } else {
            $logic[] = '// Delete instance';
            $logic[] = "\$result = \${$modelVariable}->delete();";
        }

        $logic[] = '';

        if ($options['use_events'] ?? true) {
            $logic[] = '// Fire deleted event';
            $logic[] = "Event::dispatch(new {$modelName}Deleted(\${$modelVariable}));";
            $logic[] = '';
        }

        if ($options['use_cache'] ?? true) {
            $logic[] = '// Clear relevant caches';
            $logic[] = "\$this->clearCache(\${$modelVariable}->id);";
            $logic[] = '';
        }

        $logic[] = 'return $result;';

        return implode("\n        ", $logic);
    }

    protected function getFindByIdLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_cache'] ?? true) {
            $logic[] = '// Try cache first';
            $logic[] = "\$cacheKey = \$this->cachePrefix . '.find.' . \$id;";
            $logic[] = '';
            $logic[] = 'return Cache::remember($cacheKey, $this->cacheTtl, function () use ($id) {';
            if ($options['use_repository'] ?? false) {
                $repositoryVariable = mb_strtolower($schema->name).'Repository';
                $logic[] = "    return \$this->{$repositoryVariable}->findById(\$id);";
            } else {
                $logic[] = "    return {$modelName}::find(\$id);";
            }
            $logic[] = '});';
        } elseif ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = "return \$this->{$repositoryVariable}->findById(\$id);";
        } else {
            $logic[] = "return {$modelName}::find(\$id);";
        }

        return implode("\n        ", $logic);
    }

    protected function getGetAllLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_cache'] ?? true) {
            $logic[] = '// Cache key based on filters';
            $logic[] = "\$cacheKey = \$this->cachePrefix . '.all.' . md5(serialize(\$filters));";
            $logic[] = '';
            $logic[] = 'return Cache::remember($cacheKey, $this->cacheTtl, function () use ($filters) {';
            if ($options['use_repository'] ?? false) {
                $repositoryVariable = mb_strtolower($schema->name).'Repository';
                $logic[] = "    return \$this->{$repositoryVariable}->getAll(\$filters);";
            } else {
                $logic[] = "    \$query = {$modelName}::query();";
                $logic[] = '    $this->applyFilters($query, $filters);';
                $logic[] = '    return $query->get();';
            }
            $logic[] = '});';
        } elseif ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = "return \$this->{$repositoryVariable}->getAll(\$filters);";
        } else {
            $logic[] = "\$query = {$modelName}::query();";
            $logic[] = '$this->applyFilters($query, $filters);';
            $logic[] = 'return $query->get();';
        }

        return implode("\n        ", $logic);
    }

    protected function getPaginateLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_repository'] ?? false) {
            $repositoryVariable = mb_strtolower($schema->name).'Repository';
            $logic[] = "return \$this->{$repositoryVariable}->paginate(\$perPage, \$filters);";
        } else {
            $logic[] = "\$query = {$modelName}::query();";
            $logic[] = '$this->applyFilters($query, $filters);';
            $logic[] = 'return $query->paginate($perPage);';
        }

        return implode("\n        ", $logic);
    }

    protected function getBusinessMethods(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $modelVariable = mb_strtolower($modelName);
        $methods = [];

        // Common business methods
        $methods['validateData'] = [
            'description' => "Validate {$modelName} data.",
            'parameters' => ['array $data', 'string $context = \'create\'', "{$modelName} \${$modelVariable} = null"],
            'return_type' => 'void',
            'visibility' => 'protected',
            'logic' => "// Implement validation rules based on context\n        // Throw ValidationException if validation fails",
        ];

        if ($options['use_cache'] ?? true) {
            $methods['clearCache'] = [
                'description' => "Clear {$modelName} related caches.",
                'parameters' => ['int $id = null'],
                'return_type' => 'void',
                'visibility' => 'protected',
                'logic' => "// Clear all related cache entries\n        Cache::tags([\$this->cachePrefix])->flush();",
            ];
        }

        $methods['applyFilters'] = [
            'description' => "Apply filters to {$modelName} query.",
            'parameters' => ['$query', 'array $filters'],
            'return_type' => 'void',
            'visibility' => 'protected',
            'logic' => "// Apply various filters based on \$filters array\n        // Example: search, status, date ranges, etc.",
        ];

        return $methods;
    }
}
