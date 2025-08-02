<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Medium Text field type implementation
 */
final class MediumTextFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'size',
    ];

    public function getType(): string
    {
        return 'mediumText';
    }

    public function getAliases(): array
    {
        return ['mediumtext'];
    }

    public function getMigrationMethod(): string
    {
        return 'mediumText';
    }

    public function getCastType(array $config = []): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Medium text type doesn't need parameters
    }
}
