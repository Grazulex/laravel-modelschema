<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Small Integer field type implementation
 */
final class SmallIntegerFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'unsigned',
        'auto_increment',
        'size',
    ];

    public function getType(): string
    {
        return 'smallInteger';
    }

    public function getAliases(): array
    {
        return ['smallint'];
    }

    public function getMigrationMethod(): string
    {
        return 'smallInteger';
    }

    public function getCastType(): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';

        // Add range validation for small integer (-32,768 to 32,767, or 0 to 65,535 if unsigned)
        if (isset($config['unsigned']) && $config['unsigned']) {
            $rules[] = 'min:0';
            $rules[] = 'max:65535';
        } else {
            $rules[] = 'min:-32768';
            $rules[] = 'max:32767';
        }

        return $rules;
    }
}
