<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Form Requests Data
 */
class RequestGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'requests';
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
            // Enhanced structure with simplified keys
            $requestsData = [
                'store' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema, $options),
                    'messages' => $this->getCustomValidationMessages($schema, $options),
                    'authorization' => $this->getAuthorizationLogic($schema, 'store', $options),
                    'custom_methods' => $this->getCustomMethods($schema, 'store', $options),
                    'relationships_validation' => $this->getRelationshipValidationRules($schema, $options),
                    'conditional_rules' => $this->getConditionalRules($schema, 'store', $options),
                ],
                'update' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema, $options),
                    'messages' => $this->getCustomValidationMessages($schema, $options),
                    'authorization' => $this->getAuthorizationLogic($schema, 'update', $options),
                    'custom_methods' => $this->getCustomMethods($schema, 'update', $options),
                    'relationships_validation' => $this->getRelationshipValidationRules($schema, $options),
                    'conditional_rules' => $this->getConditionalRules($schema, 'update', $options),
                ],
            ];

            // Add custom request types if specified
            if (isset($options['custom_requests'])) {
                foreach ($options['custom_requests'] as $requestName => $requestConfig) {
                    $requestsData[$requestName] = $this->generateCustomRequest($schema, $requestName, $requestConfig, $options);
                }
            }
        } else {
            // Standard structure with full key names
            $requestsData = [
                'store_request' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema, $options),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update_request' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema, $options),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        }

        // Retourne la structure prête à être insérée : "requests": { ... }
        return $this->toJsonFormat(['requests' => $requestsData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Check if we want enhanced structure or simple structure
        $isEnhanced = $options['enhanced'] ?? true;

        if ($isEnhanced) {
            // Enhanced structure with simplified keys
            $requestsData = [
                'store' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema, $options),
                    'messages' => $this->getCustomValidationMessages($schema, $options),
                    'authorization' => $this->getAuthorizationLogic($schema, 'store', $options),
                    'custom_methods' => $this->getCustomMethods($schema, 'store', $options),
                    'relationships_validation' => $this->getRelationshipValidationRules($schema, $options),
                    'conditional_rules' => $this->getConditionalRules($schema, 'store', $options),
                ],
                'update' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema, $options),
                    'messages' => $this->getCustomValidationMessages($schema, $options),
                    'authorization' => $this->getAuthorizationLogic($schema, 'update', $options),
                    'custom_methods' => $this->getCustomMethods($schema, 'update', $options),
                    'relationships_validation' => $this->getRelationshipValidationRules($schema, $options),
                    'conditional_rules' => $this->getConditionalRules($schema, 'update', $options),
                ],
            ];

            // Add custom request types if specified
            if (isset($options['custom_requests'])) {
                foreach ($options['custom_requests'] as $requestName => $requestConfig) {
                    $requestsData[$requestName] = $this->generateCustomRequest($schema, $requestName, $requestConfig, $options);
                }
            }
        } else {
            // Standard structure with full key names
            $requestsData = [
                'store_request' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema, $options),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update_request' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema, $options),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        }

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['requests' => $requestsData], 4, 2);
    }

    protected function getStoreValidationRules(ModelSchema $schema, array $options = []): array
    {
        $rules = [];

        foreach ($schema->getFillableFields() as $field) {
            $fieldRules = $field->getValidationRules();
            if (! empty($fieldRules)) {
                $rules[$field->name] = $fieldRules;
            }
        }

        // Add custom rules from options
        if (isset($options['custom_validation_rules']['store'])) {
            return array_merge($rules, $options['custom_validation_rules']['store']);
        }

        return $rules;
    }

    protected function getUpdateValidationRules(ModelSchema $schema, array $options = []): array
    {
        $rules = $this->getStoreValidationRules($schema, $options);

        // Pour les updates, rendre les champs optionnels
        foreach ($rules as $field => $fieldRules) {
            if (is_array($fieldRules)) {
                // Remplacer 'required' par 'sometimes'
                $rules[$field] = array_map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                }, $fieldRules);
            } else {
                $rules[$field] = str_replace('required', 'sometimes', $fieldRules);
            }
        }

        // Add custom update rules
        if (isset($options['custom_validation_rules']['update'])) {
            return array_merge($rules, $options['custom_validation_rules']['update']);
        }

        return $rules;
    }

    /**
     * Get custom validation messages with enhanced features
     */
    protected function getCustomValidationMessages(ModelSchema $schema, array $options): array
    {
        $messages = $this->getValidationMessages($schema);

        // Add custom messages from options
        if (isset($options['custom_messages'])) {
            $messages = array_merge($messages, $options['custom_messages']);
        }

        // Add field-specific messages
        foreach ($schema->fields as $field) {
            $fieldName = $field->name;

            // Custom messages based on field type
            switch ($field->type) {
                case 'email':
                    $messages["{$fieldName}.email"] = "Please enter a valid email address for {$fieldName}.";
                    break;
                case 'uuid':
                    $messages["{$fieldName}.uuid"] = "The {$fieldName} must be a valid UUID.";
                    break;
                case 'date':
                    $messages["{$fieldName}.date"] = "The {$fieldName} must be a valid date.";
                    break;
                case 'enum':
                    if (isset($field->attributes['options'])) {
                        $allowedValues = implode(', ', $field->attributes['options']);
                        $messages["{$fieldName}.in"] = "The {$fieldName} must be one of: {$allowedValues}.";
                    }
                    break;
            }
        }

        return $messages;
    }

    /**
     * Get authorization logic for different actions
     */
    protected function getAuthorizationLogic(ModelSchema $schema, string $action, array $options): array
    {
        $authorization = [
            'enabled' => $options['enable_authorization'] ?? true,
            'method' => 'authorize',
            'logic' => [],
        ];

        if (! $authorization['enabled']) {
            $authorization['logic'] = ['return true;'];

            return $authorization;
        }

        // Default authorization logic based on action
        switch ($action) {
            case 'store':
                $authorization['logic'] = [
                    '// Check if user can create '.mb_strtolower($schema->name),
                    'return $this->user()->can("create", '.$schema->name.'::class);',
                ];
                break;
            case 'update':
                $authorization['logic'] = [
                    '// Check if user can update this '.mb_strtolower($schema->name),
                    'return $this->user()->can("update", $this->route("'.mb_strtolower($schema->name).'"));',
                ];
                break;
            default:
                $authorization['logic'] = [
                    '// Custom authorization for '.$action,
                    'return true; // Implement your authorization logic here',
                ];
        }

        // Override with custom authorization if provided
        if (isset($options['custom_authorization'][$action])) {
            $authorization['logic'] = $options['custom_authorization'][$action];
        }

        return $authorization;
    }

    /**
     * Get custom methods for the request class
     */
    protected function getCustomMethods(ModelSchema $schema, string $action, array $options): array
    {
        $methods = [];

        // Add data transformation method
        $methods['transformData'] = [
            'visibility' => 'protected',
            'return_type' => 'array',
            'parameters' => [],
            'body' => [
                '$data = $this->validated();',
                '',
                '// Transform data before validation',
                '// Add any custom transformations here',
                '',
                'return $data;',
            ],
        ];

        // Add prepare method
        $methods['prepareForValidation'] = [
            'visibility' => 'protected',
            'return_type' => 'void',
            'parameters' => [],
            'body' => [
                '// Prepare data before validation',
                '// This runs before validation rules are applied',
                '',
                '// Example: Format phone numbers, trim strings, etc.',
            ],
        ];

        // Add custom methods from options
        if (isset($options['custom_methods'][$action])) {
            return array_merge($methods, $options['custom_methods'][$action]);
        }

        return $methods;
    }

    /**
     * Get relationship validation rules
     */
    protected function getRelationshipValidationRules(ModelSchema $schema, array $options): array
    {
        $relationshipRules = [];

        foreach ($schema->relationships as $relationship) {
            $fieldName = $relationship->name;

            switch ($relationship->type) {
                case 'belongsTo':
                    $relationshipRules["{$fieldName}_id"] = [
                        'nullable',
                        'exists:'.mb_strtolower($relationship->model).'s,id',
                    ];
                    break;

                case 'belongsToMany':
                    $relationshipRules["{$fieldName}.*"] = [
                        'integer',
                        'exists:'.mb_strtolower($relationship->model).'s,id',
                    ];
                    break;

                case 'hasMany':
                    $relationshipRules["{$fieldName}"] = ['sometimes', 'array'];
                    $relationshipRules["{$fieldName}.*.id"] = [
                        'integer',
                        'exists:'.mb_strtolower($relationship->model).'s,id',
                    ];
                    break;
            }
        }

        return $relationshipRules;
    }

    /**
     * Get conditional validation rules
     */
    protected function getConditionalRules(ModelSchema $schema, string $action, array $options): array
    {
        $conditionalRules = [];

        // Add conditional rules from options
        if (isset($options['conditional_rules'][$action])) {
            $conditionalRules = $options['conditional_rules'][$action];
        }

        // Example: Add conditional rules based on field dependencies
        foreach ($schema->fields as $field) {
            if ($field->type === 'enum' && isset($field->attributes['options'])) {
                // Add conditional rules based on enum values
                $fieldName = $field->name;
                $conditionalRules[$fieldName] = [
                    'method' => 'Rule::when',
                    'condition' => 'function ($input) { return isset($input["status"]); }',
                    'rules' => ['required', 'in:'.implode(',', $field->attributes['options'])],
                ];
            }
        }

        return $conditionalRules;
    }

    /**
     * Generate a custom request type
     */
    protected function generateCustomRequest(ModelSchema $schema, string $requestName, array $config, array $options): array
    {
        $className = $config['class_name'] ?? ucfirst($requestName).$schema->name.'Request';

        return [
            'name' => $className,
            'namespace' => $config['namespace'] ?? ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
            'validation_rules' => $config['validation_rules'] ?? [],
            'messages' => $config['messages'] ?? $this->getValidationMessages($schema),
            'authorization' => $config['authorization'] ?? $this->getAuthorizationLogic($schema, $requestName, $options),
            'custom_methods' => $config['custom_methods'] ?? [],
            'conditional_rules' => $config['conditional_rules'] ?? [],
        ];
    }

    protected function getValidationMessages(ModelSchema $schema): array
    {
        // Messages de validation par défaut
        return [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'max' => 'The :attribute may not be greater than :max characters.',
        ];
    }
}
