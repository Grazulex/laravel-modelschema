<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Long Text field type implementation
 */
final class LongTextFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'size',
    ];

    public function getType(): string
    {
        return 'longText';
    }

    public function getAliases(): array
    {
        return ['longtext'];
    }

    public function getMigrationMethod(): string
    {
        return 'longText';
    }

    public function getCastType(): string
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
        return []; // Long text type doesn't need parameters
    }
}
