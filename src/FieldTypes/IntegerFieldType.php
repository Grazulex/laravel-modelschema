<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Integer field type implementation
 */
final class IntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'unsigned',
        'auto_increment',
        'size', // tiny, small, medium, big
    ];

    public function getType(): string
    {
        return 'integer';
    }

    public function getAliases(): array
    {
        return ['int'];
    }

    public function getMigrationMethod(): string
    {
        return 'integer';
    }

    public function getCastType(array $config = []): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';

        return $rules;
    }
}
