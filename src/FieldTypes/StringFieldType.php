<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * String field type implementation
 */
final class StringFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'length',
        'fixed',
    ];

    public function getType(): string
    {
        return 'string';
    }

    public function getAliases(): array
    {
        return ['varchar', 'char'];
    }

    public function getMigrationMethod(): string
    {
        return 'string';
    }

    public function getCastType(array $config = []): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        if (isset($config['length'])) {
            $rules[] = "max:{$config['length']}";
        }

        return $rules;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate length
        if (isset($config['length']) && (! is_int($config['length']) || $config['length'] <= 0)) {
            $errors[] = 'String length must be a positive integer';
        }

        return $errors;
    }
}
