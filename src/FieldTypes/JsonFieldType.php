<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * JSON field type implementation
 */
final class JsonFieldType extends AbstractFieldType
{
    public function getType(): string
    {
        return 'json';
    }

    public function getAliases(): array
    {
        return ['jsonb'];
    }

    public function getMigrationMethod(): string
    {
        return 'json';
    }

    public function getCastType(): string
    {
        return 'array';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'array';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // JSON type doesn't need parameters
    }

    public function transformConfig(array $config): array
    {
        // JSON fields are typically not indexed by default
        if (! isset($config['index'])) {
            $config['index'] = false;
        }

        return $config;
    }
}
