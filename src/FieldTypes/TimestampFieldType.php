<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Timestamp field type implementation
 */
final class TimestampFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'precision',
        'use_current',
        'on_update_current',
    ];

    public function getType(): string
    {
        return 'timestamp';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMigrationMethod(): string
    {
        return 'timestamp';
    }

    public function getCastType(array $config = []): string
    {
        return 'timestamp';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'date';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        if (isset($config['precision'])) {
            $params[] = $config['precision'];
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate precision
        if (isset($config['precision']) && (! is_int($config['precision']) || $config['precision'] < 0 || $config['precision'] > 6)) {
            $errors[] = 'Timestamp precision must be an integer between 0 and 6';
        }

        return $errors;
    }

    public function transformConfig(array $config): array
    {
        // Set default behaviors for timestamps
        if (! isset($config['use_current'])) {
            $config['use_current'] = true;
        }

        return $config;
    }
}
