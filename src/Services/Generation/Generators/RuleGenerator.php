<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Validation Rule Classes
 */
class RuleGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'rule';
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
        $rules = $this->getRuleClasses($schema, $options);
        $ruleData = [];

        foreach ($rules as $ruleName => $ruleInfo) {
            $ruleData[$ruleName] = [
                'class_name' => $ruleName,
                'namespace' => $options['rule_namespace'] ?? 'App\\Rules',
                'model_class' => $schema->getModelClass(),
                'type' => $ruleInfo['type'],
                'field' => $ruleInfo['field'] ?? null,
                'related_table' => $ruleInfo['related_table'] ?? null,
                'description' => $ruleInfo['description'],
                'implements' => $ruleInfo['implements'] ?? ['Rule'],
                'imports' => $this->getImports($schema, $ruleInfo, $options),
                'properties' => $this->getRuleProperties($schema, $ruleInfo, $options),
                'methods' => $this->getRuleMethods($schema, $ruleInfo, $options),
                'dependencies' => $this->getDependencies($schema, $ruleInfo, $options),
            ];
        }

        return $this->toJsonFormat(['rules' => $ruleData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        $rules = $this->getRuleClasses($schema, $options);
        $ruleData = [];

        foreach ($rules as $ruleName => $ruleInfo) {
            $ruleData[$ruleName] = [
                'class_name' => $ruleName,
                'namespace' => $options['rule_namespace'] ?? 'App\\Rules',
                'model_class' => $schema->getModelClass(),
                'type' => $ruleInfo['type'],
                'field' => $ruleInfo['field'] ?? null,
                'related_table' => $ruleInfo['related_table'] ?? null,
                'description' => $ruleInfo['description'],
                'implements' => $ruleInfo['implements'] ?? ['Rule'],
                'imports' => $this->getImports($schema, $ruleInfo, $options),
                'properties' => $this->getRuleProperties($schema, $ruleInfo, $options),
                'methods' => $this->getRuleMethods($schema, $ruleInfo, $options),
                'dependencies' => $this->getDependencies($schema, $ruleInfo, $options),
            ];
        }

        return \Symfony\Component\Yaml\Yaml::dump(['rules' => $ruleData], 4, 2);
    }

    protected function getRuleClasses(ModelSchema $schema, array $options): array
    {
        $modelName = $schema->name;
        $rules = [];

        // Unique field rules
        if ($options['include_unique_rules'] ?? true) {
            $uniqueFields = $this->getUniqueFields($schema);
            foreach ($uniqueFields as $field) {
                $fieldName = ucfirst($field);
                $rules["Unique{$modelName}{$fieldName}Rule"] = [
                    'type' => 'unique',
                    'field' => $field,
                    'description' => "Validation rule for unique {$field} in {$modelName}",
                    'implements' => ['Rule'],
                ];
            }
        }

        // Foreign key existence rules
        if ($options['include_foreign_key_rules'] ?? true) {
            $foreignKeys = $this->getForeignKeyFields($schema);
            foreach ($foreignKeys as $fk) {
                $fieldName = ucfirst(str_replace('_id', '', $fk['field']));
                $rules["{$fieldName}ExistsRule"] = [
                    'type' => 'exists',
                    'field' => $fk['field'],
                    'related_table' => $fk['table'],
                    'description' => "Validation rule for {$fk['field']} existence in {$fk['table']}",
                    'implements' => ['Rule'],
                ];
            }
        }

        // Business logic rules
        if ($options['include_business_rules'] ?? true) {
            $rules["{$modelName}StatusRule"] = [
                'type' => 'status',
                'description' => "Business validation rule for {$modelName} status transitions",
                'implements' => ['Rule'],
            ];

            $rules["{$modelName}PermissionRule"] = [
                'type' => 'permission',
                'description' => "Permission validation rule for {$modelName}",
                'implements' => ['Rule'],
            ];
        }

        // Complex validation rules
        if ($options['include_complex_rules'] ?? true) {
            $rules["{$modelName}ComplexValidationRule"] = [
                'type' => 'complex',
                'description' => "Complex validation rule for {$modelName} business logic",
                'implements' => ['Rule'],
            ];
        }

        // Custom rules
        if (isset($options['custom_rules'])) {
            foreach ($options['custom_rules'] as $customRule) {
                $ruleName = $customRule['name'] ?? "{$modelName}CustomRule";
                $rules[$ruleName] = [
                    'type' => 'custom',
                    'description' => $customRule['description'] ?? "Custom validation rule for {$modelName}",
                    'implements' => $customRule['implements'] ?? ['Rule'],
                    'logic' => $customRule['logic'] ?? null,
                ];
            }
        }

        return $rules;
    }

    protected function getUniqueFields(ModelSchema $schema): array
    {
        $uniqueFields = [];

        // Look for fields that should be unique
        foreach ($schema->fields as $field) {
            if (in_array($field->name, ['email', 'username', 'slug', 'code', 'sku'])) {
                $uniqueFields[] = $field->name;
            }
        }

        return $uniqueFields;
    }

    protected function getForeignKeyFields(ModelSchema $schema): array
    {
        $foreignKeys = [];

        foreach ($schema->fields as $field) {
            if (str_ends_with($field->name, '_id') && $field->name !== 'id') {
                $tableName = str_replace('_id', 's', $field->name); // Simple pluralization
                $foreignKeys[] = [
                    'field' => $field->name,
                    'table' => $tableName,
                ];
            }
        }

        return $foreignKeys;
    }

    protected function getImports(ModelSchema $schema, array $ruleInfo, array $options): array
    {
        $imports = [
            'Illuminate\\Contracts\\Validation\\Rule',
        ];

        // Add specific imports based on rule type
        switch ($ruleInfo['type']) {
            case 'unique':
                $imports[] = $schema->getModelClass();
                break;
            case 'exists':
                $imports[] = 'Illuminate\\Support\\Facades\\DB';
                break;
            case 'permission':
                $imports[] = 'Illuminate\\Support\\Facades\\Auth';
                break;
            case 'complex':
                $imports[] = $schema->getModelClass();
                $imports[] = 'Illuminate\\Support\\Facades\\Validator';
                break;
        }

        return array_unique($imports);
    }

    protected function getRuleProperties(ModelSchema $schema, array $ruleInfo, array $options): array
    {
        $properties = [];

        // Add rule-specific properties
        switch ($ruleInfo['type']) {
            case 'unique':
                $properties['ignoreId'] = [
                    'type' => '?int',
                    'visibility' => 'protected',
                    'value' => 'null',
                    'description' => 'ID to ignore during unique validation.',
                ];
                break;

            case 'exists':
                $properties['table'] = [
                    'type' => 'string',
                    'visibility' => 'protected',
                    'value' => "'{$ruleInfo['related_table']}'",
                    'description' => 'The table to check for existence.',
                ];

                $properties['column'] = [
                    'type' => 'string',
                    'visibility' => 'protected',
                    'value' => "'id'",
                    'description' => 'The column to check for existence.',
                ];
                break;

            case 'status':
                $properties['allowedTransitions'] = [
                    'type' => 'array',
                    'visibility' => 'protected',
                    'value' => "[\n        'draft' => ['published', 'archived'],\n        'published' => ['archived'],\n        'archived' => ['draft'],\n    ]",
                    'description' => 'Allowed status transitions.',
                ];
                break;

            case 'permission':
                $properties['requiredPermission'] = [
                    'type' => 'string',
                    'visibility' => 'protected',
                    'description' => 'The required permission for this operation.',
                ];
                break;
        }

        return $properties;
    }

    protected function getDependencies(ModelSchema $schema, array $ruleInfo, array $options): array
    {
        $dependencies = [];

        // Add dependencies based on rule type
        switch ($ruleInfo['type']) {
            case 'unique':
                if ($options['inject_model'] ?? false) {
                    $dependencies[] = [
                        'type' => $schema->getModelClass(),
                        'variable' => 'model',
                        'description' => 'The model instance for unique validation.',
                    ];
                }
                break;

            case 'permission':
                if ($options['inject_user'] ?? false) {
                    $dependencies[] = [
                        'type' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
                        'variable' => 'user',
                        'description' => 'The authenticated user.',
                    ];
                }
                break;
        }

        return $dependencies;
    }

    protected function getRuleMethods(ModelSchema $schema, array $ruleInfo, array $options): array
    {
        $methods = [];

        // Constructor if dependencies exist
        $dependencies = $this->getDependencies($schema, $ruleInfo, $options);
        if ($dependencies !== []) {
            $constructorParams = [];
            foreach ($dependencies as $dependency) {
                $constructorParams[] = "{$dependency['type']} \${$dependency['variable']}";
            }

            $methods['__construct'] = [
                'description' => 'Create a new rule instance.',
                'parameters' => $constructorParams,
                'return_type' => 'void',
                'logic' => $this->getConstructorLogic($dependencies),
            ];
        }

        // Main passes method (required by Rule interface)
        $methods['passes'] = [
            'description' => 'Determine if the validation rule passes.',
            'parameters' => ['string $attribute', '$value'],
            'return_type' => 'bool',
            'logic' => $this->getPassesLogic($schema, $ruleInfo, $options),
        ];

        // Message method (required by Rule interface)
        $methods['message'] = [
            'description' => 'Get the validation error message.',
            'parameters' => [],
            'return_type' => 'string',
            'logic' => $this->getMessageLogic($schema, $ruleInfo, $options),
        ];

        // Add rule-specific helper methods
        switch ($ruleInfo['type']) {
            case 'unique':
                $methods['ignore'] = [
                    'description' => 'Set the ID to ignore during unique validation.',
                    'parameters' => ['int $id'],
                    'return_type' => 'self',
                    'logic' => "\$this->ignoreId = \$id;\n        return \$this;",
                ];
                break;

            case 'status':
                $methods['isValidTransition'] = [
                    'description' => 'Check if the status transition is valid.',
                    'parameters' => ['string $from', 'string $to'],
                    'return_type' => 'bool',
                    'visibility' => 'protected',
                    'logic' => "return isset(\$this->allowedTransitions[\$from]) && \n               in_array(\$to, \$this->allowedTransitions[\$from]);",
                ];
                break;

            case 'permission':
                $methods['hasPermission'] = [
                    'description' => 'Check if the user has the required permission.',
                    'parameters' => ['string $permission'],
                    'return_type' => 'bool',
                    'visibility' => 'protected',
                    'logic' => 'return Auth::user()?->can($permission) ?? false;',
                ];
                break;
        }

        return $methods;
    }

    protected function getConstructorLogic(array $dependencies): string
    {
        $assignments = [];
        foreach ($dependencies as $dependency) {
            $assignments[] = "\$this->{$dependency['variable']} = \${$dependency['variable']};";
        }

        return implode("\n        ", $assignments);
    }

    protected function getPassesLogic(ModelSchema $schema, array $ruleInfo, array $options): string
    {
        return match ($ruleInfo['type']) {
            'unique' => $this->getUniquePassesLogic($schema, $ruleInfo),
            'exists' => $this->getExistsPassesLogic($schema, $ruleInfo),
            'status' => $this->getStatusPassesLogic($schema, $ruleInfo),
            'permission' => $this->getPermissionPassesLogic($schema, $ruleInfo),
            'complex' => $this->getComplexPassesLogic($schema, $ruleInfo),
            default => "// Implement custom validation logic\n        return true;"
        };
    }

    protected function getUniquePassesLogic(ModelSchema $schema, array $ruleInfo): string
    {
        $modelName = $schema->name;
        $field = $ruleInfo['field'] ?? 'field';

        $logic = [];
        $logic[] = '// Check if the value is unique';
        $logic[] = "\$query = {$modelName}::where('{$field}', \$value);";
        $logic[] = '';
        $logic[] = '// Ignore specific ID if provided';
        $logic[] = 'if ($this->ignoreId) {';
        $logic[] = "    \$query->where('id', '!=', \$this->ignoreId);";
        $logic[] = '}';
        $logic[] = '';
        $logic[] = 'return !$query->exists();';

        return implode("\n        ", $logic);
    }

    protected function getExistsPassesLogic(ModelSchema $schema, array $ruleInfo): string
    {
        $logic = [];
        $logic[] = '// Check if the value exists in the related table';
        $logic[] = 'return DB::table($this->table)';
        $logic[] = '    ->where($this->column, $value)';
        $logic[] = '    ->exists();';

        return implode("\n        ", $logic);
    }

    protected function getStatusPassesLogic(ModelSchema $schema, array $ruleInfo): string
    {
        $logic = [];
        $logic[] = '// Get the current status from request or model';
        $logic[] = "\$currentStatus = request()->get('current_status');";
        $logic[] = '';
        $logic[] = '// If no current status provided, allow any initial status';
        $logic[] = 'if (!$currentStatus) {';
        $logic[] = '    return in_array($value, array_keys($this->allowedTransitions));';
        $logic[] = '}';
        $logic[] = '';
        $logic[] = '// Check if transition is allowed';
        $logic[] = 'return $this->isValidTransition($currentStatus, $value);';

        return implode("\n        ", $logic);
    }

    protected function getPermissionPassesLogic(ModelSchema $schema, array $ruleInfo): string
    {
        $logic = [];
        $logic[] = '// Check if user has the required permission';
        $logic[] = 'return $this->hasPermission($this->requiredPermission);';

        return implode("\n        ", $logic);
    }

    protected function getComplexPassesLogic(ModelSchema $schema, array $ruleInfo): string
    {
        $logic = [];
        $logic[] = '// Implement complex business logic validation';
        $logic[] = '// Example: Check multiple related conditions';
        $logic[] = '';
        $logic[] = '// Get additional context from request';
        $logic[] = '$context = request()->all();';
        $logic[] = '';
        $logic[] = '// Perform complex validation logic here';
        $logic[] = '// Return true if validation passes, false otherwise';
        $logic[] = '';
        $logic[] = 'return true; // Replace with actual logic';

        return implode("\n        ", $logic);
    }

    protected function getMessageLogic(ModelSchema $schema, array $ruleInfo, array $options): string
    {
        $fieldName = $ruleInfo['field'] ?? 'field';

        return match ($ruleInfo['type']) {
            'unique' => "return 'The {$fieldName} has already been taken.';",
            'exists' => "return 'The selected {$fieldName} is invalid.';",
            'status' => "return 'The status transition is not allowed.';",
            'permission' => "return 'You do not have permission to perform this action.';",
            'complex' => "return 'The {$fieldName} does not meet the required business rules.';",
            default => "return 'The {$fieldName} is invalid.';"
        };
    }
}
