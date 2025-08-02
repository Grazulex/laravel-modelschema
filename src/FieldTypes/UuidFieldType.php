<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * UUID field type implementation
 */
final class UuidFieldType extends AbstractFieldType
{
    public function getType(): string
    {
        return 'uuid';
    }

    public function getAliases(): array
    {
        return ['guid'];
    }

    public function getMigrationMethod(): string
    {
        return 'uuid';
    }

    public function getCastType(): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'uuid';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // UUID type doesn't need parameters
    }

    public function transformConfig(array $config): array
    {
        // UUIDs are typically indexed for performance
        if (! isset($config['index'])) {
            $config['index'] = true;
        }

        return $config;
    }
}
