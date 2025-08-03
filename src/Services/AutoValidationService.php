<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Support\FieldTypePlugin;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Illuminate\Support\Str;

/**
 * Service for automatically generating Laravel validation rules based on field types and custom attributes
 */
class AutoValidationService
{
    private FieldTypePluginManager $pluginManager;

    private array $defaultRulesByType = [
        'string' => ['string'],
        'text' => ['string'],
        'longText' => ['string'],
        'mediumText' => ['string'],
        'integer' => ['integer'],
        'bigInteger' => ['integer'],
        'smallInteger' => ['integer'],
        'tinyInteger' => ['integer'],
        'unsignedBigInteger' => ['integer', 'min:0'],
        'float' => ['numeric'],
        'double' => ['numeric'],
        'decimal' => ['numeric'],
        'boolean' => ['boolean'],
        'date' => ['date'],
        'dateTime' => ['date'],
        'time' => ['date_format:H:i:s'],
        'timestamp' => ['date'],
        'json' => ['json'],
        'uuid' => ['uuid'],
        'email' => ['email'],
        'binary' => ['string'],
        'foreignId' => ['integer', 'exists:{{table}},id'],
        'enum' => ['string', 'in:{{values}}'],
        'set' => ['array'],
        'point' => ['string'], // WKT format validation
        'geometry' => ['string'], // WKT format validation
        'polygon' => ['string'], // WKT format validation
    ];

    public function __construct(FieldTypePluginManager $pluginManager)
    {
        $this->pluginManager = $pluginManager;
    }

    /**
     * Generate validation rules for all fields in a schema
     */
    public function generateValidationRules(ModelSchema $schema): array
    {
        $rules = [];

        foreach ($schema->getAllFields() as $field) {
            $fieldRules = $this->generateFieldValidationRules($field);
            if ($fieldRules !== []) {
                $rules[$field->name] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Generate validation rules for a single field
     */
    public function generateFieldValidationRules(Field $field): array
    {
        $rules = [];

        // Add required rule if field is not nullable
        $rules[] = $field->nullable ? 'nullable' : 'required';

        // Get base rules for field type
        $baseRules = $this->getBaseRulesForType($field);
        $rules = array_merge($rules, $baseRules);

        // Add custom attribute rules
        $customRules = $this->generateCustomAttributeRules($field);
        $rules = array_merge($rules, $customRules);

        // Add Laravel attribute rules
        $laravelRules = $this->generateLaravelAttributeRules($field);
        $rules = array_merge($rules, $laravelRules);

        return array_unique($rules);
    }

    /**
     * Generate custom validation rules for specific field types
     */
    public function generateCustomValidationRules(Field $field): array
    {
        $customRules = [];
        $type = $field->type;

        switch ($type) {
            case 'point':
            case 'geometry':
            case 'polygon':
                $customRules[] = 'spatial_format';
                break;

            case 'json':
                $plugin = $this->pluginManager->getPlugin($type);
                if ($plugin instanceof FieldTypePlugin) {
                    $attributes = $plugin->getCustomAttributes();
                    if (isset($attributes['schema'])) {
                        $customRules[] = 'json_schema:'.$attributes['schema'];
                    }
                }
                break;
        }

        return $customRules;
    }

    /**
     * Generate validation rules in Laravel format (array or string)
     */
    public function generateValidationRulesForRequest(ModelSchema $schema, string $format = 'array'): array|string
    {
        $rules = $this->generateValidationRules($schema);

        if ($format === 'string') {
            return $this->convertRulesToString($rules);
        }

        return $rules;
    }

    /**
     * Generate validation messages for better user experience
     */
    public function generateValidationMessages(ModelSchema $schema): array
    {
        $messages = [];

        foreach ($schema->getAllFields() as $field) {
            $fieldName = $field->name;
            $fieldType = $field->type;

            // Generate user-friendly field name
            $displayName = ucwords(str_replace('_', ' ', $fieldName));

            // Required messages
            if (! $field->nullable) {
                $messages["{$fieldName}.required"] = "The {$displayName} field is required.";
            }

            // Type-specific messages
            switch ($fieldType) {
                case 'email':
                    $messages["{$fieldName}.email"] = "The {$displayName} must be a valid email address.";
                    break;
                case 'uuid':
                    $messages["{$fieldName}.uuid"] = "The {$displayName} must be a valid UUID.";
                    break;
                case 'integer':
                case 'bigInteger':
                case 'smallInteger':
                case 'tinyInteger':
                case 'unsignedBigInteger':
                    $messages["{$fieldName}.integer"] = "The {$displayName} must be an integer.";
                    break;
                case 'numeric':
                case 'float':
                case 'double':
                case 'decimal':
                    $messages["{$fieldName}.numeric"] = "The {$displayName} must be a number.";
                    break;
                case 'boolean':
                    $messages["{$fieldName}.boolean"] = "The {$displayName} must be true or false.";
                    break;
                case 'json':
                    $messages["{$fieldName}.json"] = "The {$displayName} must be valid JSON.";
                    break;
            }
        }

        return $messages;
    }

    /**
     * Get base validation rules for a field type
     */
    private function getBaseRulesForType(Field $field): array
    {
        $type = $field->type;
        $rules = $this->defaultRulesByType[$type] ?? ['string'];

        // Handle special cases that need dynamic values
        foreach ($rules as &$rule) {
            if (str_contains($rule, '{{table}}')) {
                // For foreignId fields, try to determine the target table
                $targetTable = $this->determineTargetTable($field);
                $rule = str_replace('{{table}}', $targetTable, $rule);
            }

            if (str_contains($rule, '{{values}}')) {
                // For enum fields, get the allowed values
                $values = $this->getEnumValues($field);
                $rule = str_replace('{{values}}', implode(',', $values), $rule);
            }
        }

        return $rules;
    }

    /**
     * Generate validation rules from custom attributes
     */
    private function generateCustomAttributeRules(Field $field): array
    {
        $rules = [];

        // Try to get a plugin for this field type
        $plugin = $this->pluginManager->getPlugin($field->type);

        // Only process if field type has a plugin with custom attributes
        if (! ($plugin instanceof FieldTypePlugin)) {
            return $rules;
        }

        $customAttributes = $plugin->getCustomAttributes();

        foreach ($customAttributes as $name) {
            $config = $plugin->getCustomAttributeConfig($name);
            if ($config === []) {
                continue;
            }

            // Get the value from plugin attributes (if set)
            $value = $config['default'] ?? null;

            // Generate rules based on attribute configuration
            $attributeRules = $this->generateRulesFromAttributeConfig($name, $value, $config);
            $rules = array_merge($rules, $attributeRules);
        }

        return $rules;
    }

    /**
     * Generate validation rules from Laravel field attributes
     */
    private function generateLaravelAttributeRules(Field $field): array
    {
        $rules = [];
        $attributes = $field->attributes;

        // String length constraints
        if (isset($attributes['length']) && is_numeric($attributes['length'])) {
            $rules[] = 'max:'.$attributes['length'];
        }

        // Decimal precision and scale
        if ($field->type === 'decimal' && (isset($attributes['precision']) && isset($attributes['scale']))) {
            $rules[] = 'decimal:0,'.$attributes['scale'];
        }

        // Unique constraint
        if (isset($attributes['unique']) && $attributes['unique']) {
            $rules[] = 'unique:{{table}},'.$field->name;
        }

        // Index hints (not validation rules but useful for documentation)
        if (isset($attributes['index']) && $attributes['index']) {
            // Could be used for custom validation logic
        }

        return $rules;
    }

    /**
     * Generate validation rules from custom attribute configuration
     */
    private function generateRulesFromAttributeConfig(string $name, $value, array $config): array
    {
        $rules = [];

        // Handle specific attribute patterns that map to validation rules
        switch ($name) {
            case 'min_length':
            case 'min_size':
                if (is_numeric($value)) {
                    $rules[] = 'min:'.$value;
                }
                break;

            case 'max_length':
            case 'max_size':
                if (is_numeric($value)) {
                    $rules[] = 'max:'.$value;
                }
                break;

            case 'schemes':
                if (is_array($value)) {
                    // For URL schemes, we could add a custom validation rule
                    $rules[] = 'url';
                }
                break;

            case 'domain_whitelist':
                if (is_array($value) && $value !== []) {
                    // Custom validation for domain whitelist
                    $rules[] = 'domain_whitelist:'.implode(',', $value);
                }
                break;

            case 'timeout':
            case 'max_redirects':
                if (is_numeric($value) && $value > 0) {
                    $rules[] = 'integer';
                    $rules[] = 'min:1';
                    if ($name === 'timeout') {
                        $rules[] = 'max:300'; // 5 minutes max
                    }
                    if ($name === 'max_redirects') {
                        $rules[] = 'max:10'; // Reasonable redirect limit
                    }
                }
                break;

            case 'verify_ssl':
            case 'allow_query_params':
            case 'strict_validation':
                if (isset($config['type']) && $config['type'] === 'boolean') {
                    $rules[] = 'boolean';
                }
                break;
        }

        // Handle enum constraints from custom attributes
        if (isset($config['enum']) && is_array($config['enum'])) {
            $rules[] = 'in:'.implode(',', $config['enum']);
        }

        // Handle min/max from custom attribute config
        if (isset($config['min']) && is_numeric($config['min'])) {
            $rules[] = 'min:'.$config['min'];
        }
        if (isset($config['max']) && is_numeric($config['max'])) {
            $rules[] = 'max:'.$config['max'];
        }

        return $rules;
    }

    /**
     * Determine target table for foreign key validation
     */
    private function determineTargetTable(Field $field): string
    {
        $attributes = $field->attributes;

        // Check for explicit references
        if (isset($attributes['references'])) {
            $references = $attributes['references'];
            if (isset($references['table'])) {
                return $references['table'];
            }
        }

        // Try to infer from field name
        $fieldName = $field->name;
        if (str_ends_with($fieldName, '_id')) {
            $tableName = mb_substr($fieldName, 0, -3);

            return Str::plural($tableName); // Convert to plural form
        }

        // Default fallback
        return 'unknown_table';
    }

    /**
     * Get enum values for validation
     */
    private function getEnumValues(Field $field): array
    {
        $attributes = $field->attributes;

        if (isset($attributes['values']) && is_array($attributes['values'])) {
            return $attributes['values'];
        }

        // Fallback to empty array
        return [];
    }

    /**
     * Convert rules array to string format for Laravel requests
     */
    private function convertRulesToString(array $rules): string
    {
        $ruleStrings = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleString = implode('|', $fieldRules);
            $ruleStrings[] = "'{$field}' => '{$ruleString}'";
        }

        return '['.implode(', ', $ruleStrings).']';
    }
}
