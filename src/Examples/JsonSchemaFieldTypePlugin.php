<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Examples;

use Grazulex\LaravelModelschema\Support\FieldTypePlugin;

/**
 * Example plugin for JSON Schema field type
 *
 * Advanced plugin that validates JSON against a schema
 * and provides complex configuration options.
 */
class JsonSchemaFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.1.0';

    protected string $author = 'Laravel ModelSchema Team';

    protected string $description = 'Field type for storing and validating JSON against a schema';

    protected array $dependencies = ['json'];

    /**
     * Get the field type identifier
     */
    public function getType(): string
    {
        return 'json_schema';
    }

    /**
     * Get aliases for this field type
     */
    public function getAliases(): array
    {
        return ['structured_json', 'validated_json', 'schema_json'];
    }

    /**
     * Validate field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Schema is required
        if (! isset($config['schema'])) {
            $errors[] = 'schema configuration is required for json_schema fields';
        } elseif (! is_array($config['schema'])) {
            $errors[] = 'schema must be a valid array/object';
        } else {
            // Validate schema structure
            $schemaErrors = $this->validateJsonSchema($config['schema']);
            $errors = array_merge($errors, $schemaErrors);
        }

        // Validate strict_validation option
        if (isset($config['strict_validation']) && ! is_bool($config['strict_validation'])) {
            $errors[] = 'strict_validation must be a boolean';
        }

        // Validate default value against schema if both provided
        if (isset($config['default']) && isset($config['schema'])) {
            $validationErrors = $this->validateValueAgainstSchema($config['default'], $config['schema']);
            if ($validationErrors !== []) {
                $errors[] = 'default value does not match schema: '.implode(', ', $validationErrors);
            }
        }

        return $errors;
    }

    /**
     * Get cast type for Laravel model
     */
    public function getCastType(array $config): ?string
    {
        return 'array';
    }

    /**
     * Get validation rules for this field type
     */
    public function getValidationRules(array $config): array
    {
        $rules = ['json'];

        // Add nullable if configured
        $rules[] = isset($config['nullable']) && $config['nullable'] ? 'nullable' : 'required';

        // Add custom validation rule for schema validation
        if (isset($config['schema'])) {
            $rules[] = function ($attribute, $value, $fail) use ($config): void {
                if ($value === null && isset($config['nullable']) && $config['nullable']) {
                    return;
                }

                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail("The {$attribute} must be valid JSON.");

                    return;
                }

                $errors = $this->validateValueAgainstSchema($decoded, $config['schema']);
                if ($errors !== []) {
                    $fail("The {$attribute} does not match the required schema: ".implode(', ', $errors));
                }
            };
        }

        return $rules;
    }

    /**
     * Get migration parameters for this field
     */
    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add nullable parameter
        if (isset($config['nullable']) && $config['nullable']) {
            $params['nullable'] = true;
        }

        // Add default value
        if (isset($config['default'])) {
            $params['default'] = is_string($config['default'])
                ? $config['default']
                : json_encode($config['default']);
        }

        // Add schema comment for documentation
        if (isset($config['schema'])) {
            $params['comment'] = 'JSON Schema: '.json_encode($config['schema']);
        }

        return $params;
    }

    /**
     * Transform configuration array
     */
    public function transformConfig(array $config): array
    {
        // Set default strict_validation if not provided
        if (! isset($config['strict_validation'])) {
            $config['strict_validation'] = true;
        }

        // Ensure schema is properly formatted
        if (isset($config['schema']) && is_string($config['schema'])) {
            $decoded = json_decode($config['schema'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $config['schema'] = $decoded;
            }
        }

        return $config;
    }

    /**
     * Get the migration method call with parameters
     */
    public function getMigrationCall(string $fieldName, array $config): string
    {
        $call = "\$table->json('{$fieldName}')";

        // Add nullable if configured
        if (isset($config['nullable']) && $config['nullable']) {
            $call .= '->nullable()';
        }

        // Add default value
        if (isset($config['default'])) {
            $default = is_string($config['default'])
                ? $config['default']
                : json_encode($config['default']);
            $call .= "->default('{$default}')";
        }

        // Add comment with schema info
        if (isset($config['schema'])) {
            $schemaJson = json_encode($config['schema']);
            $call .= "->comment('JSON Schema: {$schemaJson}')";
        }

        return $call;
    }

    /**
     * Get supported databases
     */
    public function getSupportedDatabases(): array
    {
        return ['mysql', 'postgresql'];
    }

    /**
     * Get supported attributes
     */
    public function getSupportedAttributesList(): array
    {
        return ['nullable', 'default', 'schema', 'strict_validation'];
    }

    /**
     * Get example schema configurations
     */
    public function getExampleSchemas(): array
    {
        return [
            'user_profile' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                    'email' => ['type' => 'string'],
                    'preferences' => [
                        'type' => 'object',
                        'properties' => [
                            'theme' => ['type' => 'string'],
                            'notifications' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'required' => ['name', 'email'],
            ],
            'product_attributes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'value' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'value'],
                ],
            ],
        ];
    }

    /**
     * Validate JSON schema structure
     */
    protected function validateJsonSchema(array $schema): array
    {
        $errors = [];

        // Basic schema structure validation
        if (! isset($schema['type'])) {
            $errors[] = 'schema must have a type property';
        }

        if (isset($schema['type']) && ! in_array($schema['type'], ['object', 'array', 'string', 'number', 'integer', 'boolean', 'null'], true)) {
            $errors[] = 'schema type must be one of: object, array, string, number, integer, boolean, null';
        }

        // Validate properties for object type
        if (isset($schema['type']) && $schema['type'] === 'object') {
            if (isset($schema['properties']) && ! is_array($schema['properties'])) {
                $errors[] = 'schema properties must be an array';
            }

            if (isset($schema['required']) && ! is_array($schema['required'])) {
                $errors[] = 'schema required must be an array';
            }
        }

        // Validate items for array type
        if (isset($schema['type']) && $schema['type'] === 'array' && (isset($schema['items']) && ! is_array($schema['items']))) {
            $errors[] = 'schema items must be an array for array type';
        }

        return $errors;
    }

    /**
     * Validate value against JSON schema
     */
    protected function validateValueAgainstSchema(mixed $value, array $schema): array
    {
        $errors = [];

        if (! isset($schema['type'])) {
            return ['Schema type not defined'];
        }

        $type = $schema['type'];

        // Type validation
        switch ($type) {
            case 'object':
                if (! is_array($value) || array_is_list($value)) {
                    $errors[] = 'Value must be an object';
                } else {
                    $errors = array_merge($errors, $this->validateObjectSchema($value, $schema));
                }
                break;

            case 'array':
                if (! is_array($value) || ! array_is_list($value)) {
                    $errors[] = 'Value must be an array';
                } else {
                    $errors = array_merge($errors, $this->validateArraySchema($value, $schema));
                }
                break;

            case 'string':
                if (! is_string($value)) {
                    $errors[] = 'Value must be a string';
                }
                break;

            case 'integer':
                if (! is_int($value)) {
                    $errors[] = 'Value must be an integer';
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    $errors[] = 'Value must be a number';
                }
                break;

            case 'boolean':
                if (! is_bool($value)) {
                    $errors[] = 'Value must be a boolean';
                }
                break;

            case 'null':
                if ($value !== null) {
                    $errors[] = 'Value must be null';
                }
                break;
        }

        return $errors;
    }

    /**
     * Validate object against schema
     */
    protected function validateObjectSchema(array $value, array $schema): array
    {
        $errors = [];

        // Check required properties
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! array_key_exists($required, $value)) {
                    $errors[] = "Required property '{$required}' is missing";
                }
            }
        }

        // Validate properties
        if (isset($schema['properties'])) {
            foreach ($value as $key => $val) {
                if (isset($schema['properties'][$key])) {
                    $propertyErrors = $this->validateValueAgainstSchema($val, $schema['properties'][$key]);
                    foreach ($propertyErrors as $error) {
                        $errors[] = "Property '{$key}': {$error}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate array against schema
     */
    protected function validateArraySchema(array $value, array $schema): array
    {
        $errors = [];

        // Validate items
        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $itemErrors = $this->validateValueAgainstSchema($item, $schema['items']);
                foreach ($itemErrors as $error) {
                    $errors[] = "Item [{$index}]: {$error}";
                }
            }
        }

        return $errors;
    }
}
