<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Action Classes
 */
class ActionGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'action';
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
        $actions = $this->getActionClasses($schema, $options);
        $actionData = [];

        foreach ($actions as $actionName => $actionInfo) {
            $actionData[$actionName] = [
                'class_name' => $actionName,
                'namespace' => $options['action_namespace'] ?? 'App\\Actions',
                'model_class' => $schema->getModelClass(),
                'type' => $actionInfo['type'],
                'description' => $actionInfo['description'],
                'implements' => $actionInfo['implements'] ?? [],
                'imports' => $this->getImports($schema, $actionInfo, $options),
                'properties' => $this->getActionProperties($schema, $actionInfo, $options),
                'methods' => $this->getActionMethods($schema, $actionInfo, $options),
                'dependencies' => $this->getDependencies($schema, $actionInfo, $options),
            ];
        }

        return $this->toJsonFormat(['actions' => $actionData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        $actions = $this->getActionClasses($schema, $options);
        $actionData = [];

        foreach ($actions as $actionName => $actionInfo) {
            $actionData[$actionName] = [
                'class_name' => $actionName,
                'namespace' => $options['action_namespace'] ?? 'App\\Actions',
                'model_class' => $schema->getModelClass(),
                'type' => $actionInfo['type'],
                'description' => $actionInfo['description'],
                'implements' => $actionInfo['implements'] ?? [],
                'imports' => $this->getImports($schema, $actionInfo, $options),
                'properties' => $this->getActionProperties($schema, $actionInfo, $options),
                'methods' => $this->getActionMethods($schema, $actionInfo, $options),
                'dependencies' => $this->getDependencies($schema, $actionInfo, $options),
            ];
        }

        return \Symfony\Component\Yaml\Yaml::dump(['actions' => $actionData], 4, 2);
    }

    protected function getActionClasses(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $actions = [];

        // Standard CRUD Actions
        if ($options['include_crud_actions'] ?? true) {
            $actions["Create{$modelName}Action"] = [
                'type' => 'create',
                'description' => "Action for creating a new {$modelName}",
                'implements' => ['ShouldQueue'],
            ];

            $actions["Update{$modelName}Action"] = [
                'type' => 'update',
                'description' => "Action for updating an existing {$modelName}",
                'implements' => ['ShouldQueue'],
            ];

            $actions["Delete{$modelName}Action"] = [
                'type' => 'delete',
                'description' => "Action for deleting a {$modelName}",
                'implements' => ['ShouldQueue'],
            ];
        }

        // Business Actions
        if ($options['include_business_actions'] ?? true) {
            $actions["{$modelName}BulkUpdateAction"] = [
                'type' => 'bulk_update',
                'description' => "Action for bulk updating {$modelName} records",
                'implements' => ['ShouldQueue'],
            ];

            $actions["{$modelName}ExportAction"] = [
                'type' => 'export',
                'description' => "Action for exporting {$modelName} data",
                'implements' => ['ShouldQueue'],
            ];

            $actions["{$modelName}ImportAction"] = [
                'type' => 'import',
                'description' => "Action for importing {$modelName} data",
                'implements' => ['ShouldQueue'],
            ];
        }

        // Status Actions (if status field exists)
        if ($this->hasStatusField($schema)) {
            $actions["Activate{$modelName}Action"] = [
                'type' => 'activate',
                'description' => "Action for activating a {$modelName}",
            ];

            $actions["Deactivate{$modelName}Action"] = [
                'type' => 'deactivate',
                'description' => "Action for deactivating a {$modelName}",
            ];
        }

        // Custom Actions
        if (isset($options['custom_actions'])) {
            foreach ($options['custom_actions'] as $customAction) {
                $actionName = $customAction['name'] ?? "{$modelName}CustomAction";
                $actions[$actionName] = [
                    'type' => 'custom',
                    'description' => $customAction['description'] ?? "Custom action for {$modelName}",
                    'implements' => $customAction['implements'] ?? [],
                ];
            }
        }

        return $actions;
    }

    protected function hasStatusField(ModelSchema $schema): bool
    {
        return collect($schema->fields)->contains(fn ($field): bool => in_array($field->name, ['status', 'is_active', 'active', 'state'])
        );
    }

    protected function getImports(ModelSchema $schema, array $actionInfo, array $options): array
    {
        $imports = [
            $schema->getModelClass(),
        ];

        if (in_array('ShouldQueue', $actionInfo['implements'] ?? [])) {
            $imports[] = 'Illuminate\\Contracts\\Queue\\ShouldQueue';
            $imports[] = 'Illuminate\\Foundation\\Queue\\Queueable';
        }

        // Add specific imports based on action type
        switch ($actionInfo['type']) {
            case 'create':
            case 'update':
                $imports[] = 'Illuminate\\Support\\Facades\\Validator';
                $imports[] = 'Illuminate\\Validation\\ValidationException';
                break;
            case 'export':
                $imports[] = 'Illuminate\\Support\\Facades\\Storage';
                $imports[] = 'Maatwebsite\\Excel\\Facades\\Excel';
                break;
            case 'import':
                $imports[] = 'Illuminate\\Http\\UploadedFile';
                $imports[] = 'Maatwebsite\\Excel\\Facades\\Excel';
                break;
            case 'bulk_update':
                $imports[] = 'Illuminate\\Support\\Facades\\DB';
                break;
        }

        if ($options['use_events'] ?? true) {
            $imports[] = 'Illuminate\\Support\\Facades\\Event';
        }

        if ($options['use_notifications'] ?? false) {
            $imports[] = 'Illuminate\\Support\\Facades\\Notification';
        }

        return array_unique($imports);
    }

    protected function getActionProperties(ModelSchema $schema, array $actionInfo, array $options): array
    {
        $properties = [];

        if (in_array('ShouldQueue', $actionInfo['implements'] ?? [])) {
            $properties['queue'] = [
                'type' => 'string',
                'visibility' => 'public',
                'value' => "'default'",
                'description' => 'The queue this action should be dispatched to.',
            ];

            $properties['timeout'] = [
                'type' => 'int',
                'visibility' => 'public',
                'value' => '300',
                'description' => 'The number of seconds the action can run before timing out.',
            ];
        }

        // Add action-specific properties
        switch ($actionInfo['type']) {
            case 'export':
                $properties['chunkSize'] = [
                    'type' => 'int',
                    'visibility' => 'protected',
                    'value' => '1000',
                    'description' => 'Number of records to process in each chunk.',
                ];
                break;
            case 'import':
                $properties['batchSize'] = [
                    'type' => 'int',
                    'visibility' => 'protected',
                    'value' => '500',
                    'description' => 'Number of records to import in each batch.',
                ];
                break;
        }

        return $properties;
    }

    protected function getDependencies(ModelSchema $schema, array $actionInfo, array $options): array
    {
        $dependencies = [];

        // Add service dependency if service layer is used
        if ($options['use_service'] ?? false) {
            $serviceClass = $options['service_class'] ?? "App\\Services\\{$schema->name}Service";
            $serviceVariable = mb_strtolower($schema->name).'Service';

            $dependencies[] = [
                'type' => $serviceClass,
                'variable' => $serviceVariable,
                'description' => "The {$schema->name} service instance.",
            ];
        }

        return $dependencies;
    }

    protected function getActionMethods(ModelSchema $schema, array $actionInfo, array $options): array
    {
        $methods = [];
        $modelName = $schema->name;
        $modelVariable = mb_strtolower($modelName);

        // Constructor if dependencies exist
        $dependencies = $this->getDependencies($schema, $actionInfo, $options);
        if ($dependencies !== []) {
            $constructorParams = array_map(fn ($dep): string => "{$dep['type']} \${$dep['variable']}", $dependencies);
            $methods['__construct'] = [
                'description' => 'Create a new action instance.',
                'parameters' => $constructorParams,
                'return_type' => 'void',
                'logic' => $this->getConstructorLogic($dependencies),
            ];
        }

        // Main execute method
        $methods['execute'] = [
            'description' => $this->getExecuteDescription($actionInfo, $modelName),
            'parameters' => $this->getExecuteParameters($actionInfo, $modelName, $modelVariable),
            'return_type' => $this->getExecuteReturnType($actionInfo),
            'logic' => $this->getExecuteLogic($schema, $actionInfo, $options, $modelVariable),
        ];

        // Add queued job methods if action implements ShouldQueue
        if (in_array('ShouldQueue', $actionInfo['implements'] ?? [])) {
            $methods['handle'] = [
                'description' => 'Execute the queued action.',
                'parameters' => [],
                'return_type' => 'void',
                'logic' => $this->getHandleLogic($schema, $actionInfo, $options),
            ];
        }

        // Add action-specific helper methods
        $methods = array_merge($methods, $this->getHelperMethods($schema, $actionInfo, $options));

        return $methods;
    }

    protected function getConstructorLogic(array $dependencies): string
    {
        $assignments = array_map(fn ($dep): string => "\$this->{$dep['variable']} = \${$dep['variable']};", $dependencies);

        return implode("\n        ", $assignments);
    }

    protected function getExecuteDescription(array $actionInfo, string $modelName): string
    {
        return match ($actionInfo['type']) {
            'create' => "Execute the create {$modelName} action.",
            'update' => "Execute the update {$modelName} action.",
            'delete' => "Execute the delete {$modelName} action.",
            'bulk_update' => "Execute the bulk update {$modelName} action.",
            'export' => "Execute the export {$modelName} action.",
            'import' => "Execute the import {$modelName} action.",
            'activate' => "Execute the activate {$modelName} action.",
            'deactivate' => "Execute the deactivate {$modelName} action.",
            default => "Execute the {$modelName} action."
        };
    }

    protected function getExecuteParameters(array $actionInfo, string $modelName, string $modelVariable): array
    {
        return match ($actionInfo['type']) {
            'create' => ['array $data'],
            'update' => ["{$modelName} \${$modelVariable}", 'array $data'],
            'delete' => ["{$modelName} \${$modelVariable}"],
            'bulk_update' => ['array $ids', 'array $data'],
            'export' => ['array $filters = []'],
            'import' => ['UploadedFile $file'],
            'activate', 'deactivate' => ["{$modelName} \${$modelVariable}"],
            default => ['array $data = []']
        };
    }

    protected function getExecuteReturnType(array $actionInfo): string
    {
        return match ($actionInfo['type']) {
            'create', 'update' => $actionInfo['model'] ?? 'mixed',
            'delete', 'activate', 'deactivate' => 'bool',
            'bulk_update' => 'int',
            'export' => 'string',
            'import' => 'array',
            default => 'mixed'
        };
    }

    protected function getExecuteLogic(ModelSchema $schema, array $actionInfo, array $options, string $modelVariable): string
    {
        return match ($actionInfo['type']) {
            'create' => $this->getCreateActionLogic($schema, $options, $modelVariable),
            'update' => $this->getUpdateActionLogic($schema, $options, $modelVariable),
            'delete' => $this->getDeleteActionLogic($schema, $options, $modelVariable),
            'bulk_update' => $this->getBulkUpdateActionLogic($schema, $options),
            'export' => $this->getExportActionLogic($schema, $options),
            'import' => $this->getImportActionLogic($schema, $options),
            'activate' => $this->getActivateActionLogic($schema, $options, $modelVariable),
            'deactivate' => $this->getDeactivateActionLogic($schema, $options, $modelVariable),
            default => "// Implement custom action logic\n        return true;"
        };
    }

    protected function getCreateActionLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        $logic[] = '// Validate input data';
        $logic[] = "\$this->validateData(\$data, 'create');";
        $logic[] = '';

        if ($options['use_service'] ?? false) {
            $serviceVariable = mb_strtolower($schema->name).'Service';
            $logic[] = '// Create through service';
            $logic[] = "\${$modelVariable} = \$this->{$serviceVariable}->create(\$data);";
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

        $logic[] = "return \${$modelVariable};";

        return implode("\n        ", $logic);
    }

    protected function getUpdateActionLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        $logic[] = '// Validate input data';
        $logic[] = "\$this->validateData(\$data, 'update', \${$modelVariable});";
        $logic[] = '';

        if ($options['use_service'] ?? false) {
            $serviceVariable = mb_strtolower($schema->name).'Service';
            $logic[] = '// Update through service';
            $logic[] = "\${$modelVariable} = \$this->{$serviceVariable}->update(\${$modelVariable}, \$data);";
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

        $logic[] = "return \${$modelVariable};";

        return implode("\n        ", $logic);
    }

    protected function getDeleteActionLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $modelName = $schema->name;
        $logic = [];

        if ($options['use_events'] ?? true) {
            $logic[] = '// Fire deleting event';
            $logic[] = "Event::dispatch(new {$modelName}Deleting(\${$modelVariable}));";
            $logic[] = '';
        }

        if ($options['use_service'] ?? false) {
            $serviceVariable = mb_strtolower($schema->name).'Service';
            $logic[] = '// Delete through service';
            $logic[] = "\$result = \$this->{$serviceVariable}->delete(\${$modelVariable});";
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

        $logic[] = 'return $result;';

        return implode("\n        ", $logic);
    }

    protected function getBulkUpdateActionLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $logic = [];

        $logic[] = '// Validate input data';
        $logic[] = '$this->validateBulkData($data);';
        $logic[] = '';
        $logic[] = '// Perform bulk update using database transaction';
        $logic[] = 'return DB::transaction(function () use ($ids, $data) {';
        $logic[] = "    return {$modelName}::whereIn('id', \$ids)->update(\$data);";
        $logic[] = '});';

        return implode("\n        ", $logic);
    }

    protected function getExportActionLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $tableName = $schema->table;
        $logic = [];

        $logic[] = '// Build query with filters';
        $logic[] = "\$query = {$modelName}::query();";
        $logic[] = '$this->applyFilters($query, $filters);';
        $logic[] = '';
        $logic[] = '// Generate export file';
        $logic[] = "\$filename = '{$tableName}_export_' . now()->format('Y_m_d_H_i_s') . '.csv';";
        $logic[] = "\$filePath = 'exports/' . \$filename;";
        $logic[] = '';
        $logic[] = "Excel::store(new {$modelName}Export(\$query), \$filePath, 'local');";
        $logic[] = '';
        $logic[] = 'return $filePath;';

        return implode("\n        ", $logic);
    }

    protected function getImportActionLogic(ModelSchema $schema, array $options): string
    {
        $modelName = $schema->name;
        $logic = [];

        $logic[] = '// Validate file';
        $logic[] = '$this->validateImportFile($file);';
        $logic[] = '';
        $logic[] = '// Import data';
        $logic[] = "\$import = new {$modelName}Import();";
        $logic[] = 'Excel::import($import, $file);';
        $logic[] = '';
        $logic[] = 'return [';
        $logic[] = "    'imported' => \$import->getRowCount(),";
        $logic[] = "    'failed' => \$import->getFailures(),";
        $logic[] = '];';

        return implode("\n        ", $logic);
    }

    protected function getActivateActionLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $logic = [];
        $logic[] = '// Activate the model';
        $logic[] = "return \${$modelVariable}->update(['status' => 'active']);";

        return implode("\n        ", $logic);
    }

    protected function getDeactivateActionLogic(ModelSchema $schema, array $options, string $modelVariable): string
    {
        $logic = [];
        $logic[] = '// Deactivate the model';
        $logic[] = "return \${$modelVariable}->update(['status' => 'inactive']);";

        return implode("\n        ", $logic);
    }

    protected function getHandleLogic(ModelSchema $schema, array $actionInfo, array $options): string
    {
        return "// Handle the queued action\n        // This method is called when the action is processed from the queue";
    }

    protected function getHelperMethods(ModelSchema $schema, array $actionInfo, array $options): array
    {
        $methods = [];

        // Common validation method
        $methods['validateData'] = [
            'description' => 'Validate the input data.',
            'parameters' => ['array $data', 'string $context = \'create\'', "?{$schema->name} \$model = null"],
            'return_type' => 'void',
            'visibility' => 'protected',
            'logic' => "// Implement validation logic\n        // Throw ValidationException if validation fails",
        ];

        // Add action-specific helper methods
        switch ($actionInfo['type']) {
            case 'bulk_update':
                $methods['validateBulkData'] = [
                    'description' => 'Validate bulk update data.',
                    'parameters' => ['array $data'],
                    'return_type' => 'void',
                    'visibility' => 'protected',
                    'logic' => '// Validate bulk update data',
                ];
                break;

            case 'export':
                $methods['applyFilters'] = [
                    'description' => 'Apply filters to the query.',
                    'parameters' => ['$query', 'array $filters'],
                    'return_type' => 'void',
                    'visibility' => 'protected',
                    'logic' => '// Apply export filters to query',
                ];
                break;

            case 'import':
                $methods['validateImportFile'] = [
                    'description' => 'Validate the import file.',
                    'parameters' => ['UploadedFile $file'],
                    'return_type' => 'void',
                    'visibility' => 'protected',
                    'logic' => '// Validate file type, size, and structure',
                ];
                break;
        }

        return $methods;
    }
}
