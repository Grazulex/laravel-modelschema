<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Medium Integer field type implementation
 */
final class MediumIntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'unsigned',
        'auto_increment',
        'size',
    ];

    public function getType(): string
    {
        return 'mediumInteger';
    }

    public function getAliases(): array
    {
        return ['mediumint'];
    }

    public function getMigrationMethod(): string
    {
        return 'mediumInteger';
    }

    public function getCastType(): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';

        // Add range validation for medium integer (-8,388,608 to 8,388,607, or 0 to 16,777,215 if unsigned)
        if (isset($config['unsigned']) && $config['unsigned']) {
            $rules[] = 'min:0';
            $rules[] = 'max:16777215';
        } else {
            $rules[] = 'min:-8388608';
            $rules[] = 'max:8388607';
        }

        return $rules;
    }
}
