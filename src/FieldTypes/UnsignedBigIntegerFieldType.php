<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Unsigned Big Integer field type implementation
 */
final class UnsignedBigIntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'auto_increment',
        'size',
    ];

    public function getType(): string
    {
        return 'unsignedBigInteger';
    }

    public function getAliases(): array
    {
        return ['unsigned_big_integer', 'unsigned_bigint'];
    }

    public function getMigrationMethod(): string
    {
        return 'unsignedBigInteger';
    }

    public function getCastType(): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';
        // Unsigned integers must be positive
        $rules[] = 'min:0';

        return $rules;
    }

    public function transformConfig(array $config): array
    {
        $config = parent::transformConfig($config);
        $config['unsigned'] = true;

        return $config;
    }
}
