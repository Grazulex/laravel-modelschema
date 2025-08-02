<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Tiny Integer field type implementation
 */
final class TinyIntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'unsigned',
        'auto_increment',
        'size',
    ];

    public function getType(): string
    {
        return 'tinyInteger';
    }

    public function getAliases(): array
    {
        return ['tinyint'];
    }

    public function getMigrationMethod(): string
    {
        return 'tinyInteger';
    }

    public function getCastType(): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';

        // Add range validation for tiny integer (-128 to 127, or 0 to 255 if unsigned)
        if (isset($config['unsigned']) && $config['unsigned']) {
            $rules[] = 'min:0';
            $rules[] = 'max:255';
        } else {
            $rules[] = 'min:-128';
            $rules[] = 'max:127';
        }

        return $rules;
    }
}
