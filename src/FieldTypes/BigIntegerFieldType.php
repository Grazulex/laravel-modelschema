<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Big Integer field type implementation
 */
final class BigIntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'unsigned',
        'auto_increment',
        'size',
    ];

    public function getType(): string
    {
        return 'bigInteger';
    }

    public function getAliases(): array
    {
        return ['bigint', 'long'];
    }

    public function getMigrationMethod(): string
    {
        return 'bigInteger';
    }

    public function getCastType(): string
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
