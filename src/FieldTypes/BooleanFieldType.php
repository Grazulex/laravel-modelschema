<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Boolean field type implementation
 */
final class BooleanFieldType extends AbstractFieldType
{
    public function getType(): string
    {
        return 'boolean';
    }

    public function getAliases(): array
    {
        return ['bool'];
    }

    public function getMigrationMethod(): string
    {
        return 'boolean';
    }

    public function getCastType(): string
    {
        return 'boolean';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'boolean';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Boolean type doesn't need parameters
    }

    public function transformConfig(array $config): array
    {
        // Normalize boolean default values
        if (isset($config['default'])) {
            $config['default'] = (bool) $config['default'];
        }

        return $config;
    }
}
