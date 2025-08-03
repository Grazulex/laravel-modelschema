<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Set field type implementation
 * For MySQL SET fields that can store multiple values from a predefined list
 */
final class SetFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'values',        // array of allowed values
        'separator',     // separator for multiple values (default: comma)
        'max_selections', // maximum number of values that can be selected
    ];

    public function getType(): string
    {
        return 'set';
    }

    public function getAliases(): array
    {
        return ['multi_select', 'multiple_choice'];
    }

    public function getMigrationMethod(): string
    {
        return 'set';
    }

    public function getCastType(array $config = []): string
    {
        return 'array';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        // SET fields are typically stored as arrays or comma-separated strings
        $rules[] = 'array';

        // Validate each item in the array
        if (isset($config['values']) && is_array($config['values']) && $config['values'] !== []) {
            $allowedValues = implode(',', array_map('strval', $config['values']));
            $rules[] = "*.in:{$allowedValues}";
        }

        // Limit number of selections if specified
        if (isset($config['max_selections']) && is_numeric($config['max_selections'])) {
            $rules[] = "max:{$config['max_selections']}";
        }

        return $rules;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate that values are provided
        if (! isset($config['values'])) {
            $errors[] = 'Set field type requires "values" to be specified';
        } elseif (! is_array($config['values'])) {
            $errors[] = 'Set "values" must be an array';
        } elseif (empty($config['values'])) {
            $errors[] = 'Set "values" cannot be empty';
        } else {
            // Validate each value is a string or number
            foreach ($config['values'] as $index => $value) {
                if (! is_string($value) && ! is_numeric($value)) {
                    $errors[] = "Set value at index {$index} must be a string or number";
                }
            }

            // Check for duplicate values
            if (count($config['values']) !== count(array_unique($config['values']))) {
                $errors[] = 'Set values must be unique';
            }
        }

        // Validate max_selections if provided
        if (isset($config['max_selections'])) {
            if (! is_numeric($config['max_selections']) || $config['max_selections'] < 1) {
                $errors[] = 'Set max_selections must be a positive integer';
            } elseif (isset($config['values']) && $config['max_selections'] > count($config['values'])) {
                $errors[] = 'Set max_selections cannot be greater than the number of available values';
            }
        }

        // Validate separator if provided
        if (isset($config['separator']) && (! is_string($config['separator']) || mb_strlen($config['separator']) === 0)) {
            $errors[] = 'Set separator must be a non-empty string';
        }

        return $errors;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add set values as the first parameter
        if (isset($config['values']) && is_array($config['values'])) {
            $params[] = $config['values'];
        }

        // Add any additional parameters from parent
        $parentParams = parent::getMigrationParameters($config);

        return array_merge($params, $parentParams);
    }

    public function transformConfig(array $config): array
    {
        $config = parent::transformConfig($config);

        // Ensure values are properly formatted and remove duplicates
        if (isset($config['values']) && is_array($config['values'])) {
            // Remove duplicates while preserving the original strings
            $uniqueValues = [];
            foreach ($config['values'] as $value) {
                if (! in_array($value, $uniqueValues, true)) {
                    $uniqueValues[] = $value;
                }
            }
            $config['values'] = $uniqueValues;
        }

        // Set default separator if not specified
        if (! isset($config['separator'])) {
            $config['separator'] = ',';
        }

        // Set default max_selections if not specified
        if (! isset($config['max_selections']) && isset($config['values'])) {
            $config['max_selections'] = count($config['values']);
        }

        return $config;
    }

    public function supportsAttribute(string $attribute): bool
    {
        if (parent::supportsAttribute($attribute)) {
            return true;
        }

        return in_array($attribute, $this->specificAttributes);
    }

    /**
     * Get the set values as an array suitable for form generation
     */
    public function getSetOptions(array $config): array
    {
        if (! isset($config['values']) || ! is_array($config['values'])) {
            return [];
        }

        $options = [];
        foreach ($config['values'] as $value) {
            $options[$value] = ucfirst(str_replace(['_', '-'], ' ', $value));
        }

        return $options;
    }

    /**
     * Check if a set of values is valid for this set field
     */
    public function areValidValues(array $values, array $config): bool
    {
        if (! isset($config['values']) || ! is_array($config['values'])) {
            return false;
        }

        // Check if all provided values are in the allowed set
        foreach ($values as $value) {
            if (! in_array($value, $config['values'], true)) {
                return false;
            }
        }

        // Check max_selections limit
        return ! (isset($config['max_selections']) && count($values) > $config['max_selections']);
    }

    /**
     * Convert array of values to string representation
     */
    public function valuesToString(array $values, array $config): string
    {
        $separator = $config['separator'] ?? ',';

        return implode($separator, $values);
    }

    /**
     * Convert string representation to array of values
     */
    public function stringToValues(string $valueString, array $config): array
    {
        $separator = $config['separator'] ?? ',';

        return array_values(array_filter(array_map('trim', explode($separator, $valueString))));
    }
}
