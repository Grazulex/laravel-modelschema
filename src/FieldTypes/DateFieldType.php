<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Date field type implementation
 */
final class DateFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'format',
        'use_current',
    ];

    public function getType(): string
    {
        return 'date';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMigrationMethod(): string
    {
        return 'date';
    }

    public function getCastType(): string
    {
        return 'date';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'date';

        if (isset($config['format'])) {
            $rules[] = "date_format:{$config['format']}";
        }

        if (isset($config['after'])) {
            $rules[] = "after:{$config['after']}";
        }

        if (isset($config['before'])) {
            $rules[] = "before:{$config['before']}";
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Date type doesn't need parameters
    }

    public function transformConfig(array $config): array
    {
        // Set default format if not specified
        if (! isset($config['format'])) {
            $config['format'] = 'Y-m-d';
        }

        return $config;
    }
}
