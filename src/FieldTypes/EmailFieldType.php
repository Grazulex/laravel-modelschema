<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Email field type implementation
 * Example of a specialized string field with email validation
 */
final class EmailFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'length',
        'fixed',
    ];

    public function getType(): string
    {
        return 'email';
    }

    public function getAliases(): array
    {
        return ['email_address'];
    }

    public function getMigrationMethod(): string
    {
        return 'string';
    }

    public function getCastType(): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        // Add email validation
        $rules[] = 'email';

        // Set reasonable max length for emails if not specified
        $rules[] = isset($config['length']) ? "max:{$config['length']}" : 'max:255';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        if (isset($config['length'])) {
            $params[] = $config['length'];
        } else {
            $params[] = 255; // Default email length
        }

        return $params;
    }

    public function transformConfig(array $config): array
    {
        // Set default length for email fields
        if (! isset($config['length'])) {
            $config['length'] = 255;
        }

        // Emails are usually indexed for performance
        if (! isset($config['index'])) {
            $config['index'] = true;
        }

        return $config;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Additional validation for email fields
        if (isset($config['length']) && $config['length'] < 50) {
            $errors[] = 'Email field length should be at least 50 characters';
        }

        return $errors;
    }
}
