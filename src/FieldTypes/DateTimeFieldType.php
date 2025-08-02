<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * DateTime field type implementation
 */
final class DateTimeFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'format',
        'timezone',
        'use_current',
    ];

    public function getType(): string
    {
        return 'datetime';
    }

    public function getAliases(): array
    {
        return ['timestamp'];
    }

    public function getMigrationMethod(): string
    {
        return 'dateTime';
    }

    public function getCastType(array $config = []): string
    {
        return 'datetime';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'date';

        if (isset($config['format'])) {
            $rules[] = "date_format:{$config['format']}";
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add precision if specified
        if (isset($config['precision']) && is_int($config['precision'])) {
            $params[] = $config['precision'];
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate format
        if (isset($config['format']) && ! is_string($config['format'])) {
            $errors[] = 'DateTime format must be a string';
        }

        // Validate precision
        if (isset($config['precision']) && (! is_int($config['precision']) || $config['precision'] < 0 || $config['precision'] > 6)) {
            $errors[] = 'DateTime precision must be an integer between 0 and 6';
        }

        return $errors;
    }

    public function transformConfig(array $config): array
    {
        // Set default format if not specified
        if (! isset($config['format'])) {
            $config['format'] = 'Y-m-d H:i:s';
        }

        return $config;
    }
}
