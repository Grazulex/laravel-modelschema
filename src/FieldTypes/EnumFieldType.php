<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Enum field type implementation
 * For Laravel enum fields with predefined values
 */
final class EnumFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'values',        // array of allowed values
        'default_value', // default enum value
        'strict',        // strict validation mode
    ];

    public function getType(): string
    {
        return 'enum';
    }

    public function getAliases(): array
    {
        return ['enumeration'];
    }

    public function getMigrationMethod(): string
    {
        return 'enum';
    }

    public function getCastType(array $config = []): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        // Add enum validation rule with allowed values
        if (isset($config['values']) && is_array($config['values']) && $config['values'] !== []) {
            $allowedValues = implode(',', array_map('strval', $config['values']));
            $rules[] = "in:{$allowedValues}";
        }

        return $rules;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate that values are provided
        if (! isset($config['values'])) {
            $errors[] = 'Enum field type requires "values" to be specified';
        } elseif (! is_array($config['values'])) {
            $errors[] = 'Enum "values" must be an array';
        } elseif (empty($config['values'])) {
            $errors[] = 'Enum "values" cannot be empty';
        } else {
            // Validate each value is a string or number
            foreach ($config['values'] as $index => $value) {
                if (! is_string($value) && ! is_numeric($value)) {
                    $errors[] = "Enum value at index {$index} must be a string or number";
                }
            }

            // Check for duplicate values
            if (count($config['values']) !== count(array_unique($config['values']))) {
                $errors[] = 'Enum values must be unique';
            }
        }

        // Validate default value if provided
        if (isset($config['default_value']) && (! isset($config['values']) || ! in_array($config['default_value'], $config['values'], true))) {
            $errors[] = 'Enum default_value must be one of the specified values';
        }

        return $errors;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add enum values as the first parameter
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
            // Remove duplicates while preserving the original strings (including whitespace)
            $uniqueValues = [];
            foreach ($config['values'] as $value) {
                if (! in_array($value, $uniqueValues, true)) {
                    $uniqueValues[] = $value;
                }
            }
            $config['values'] = $uniqueValues;
        }

        // Set default value if not specified but values exist
        if (! isset($config['default_value']) && isset($config['values']) && ! empty($config['values'])) {
            $config['default_value'] = $config['values'][0];
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
     * Get the enum values as an array suitable for form generation
     */
    public function getEnumOptions(array $config): array
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
     * Check if a value is valid for this enum
     */
    public function isValidValue($value, array $config): bool
    {
        if (! isset($config['values']) || ! is_array($config['values'])) {
            return false;
        }

        return in_array($value, $config['values'], true);
    }
}
