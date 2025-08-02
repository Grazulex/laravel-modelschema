<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Time field type implementation
 */
final class TimeFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'format',
        'precision',
    ];

    public function getType(): string
    {
        return 'time';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMigrationMethod(): string
    {
        return 'time';
    }

    public function getCastType(array $config = []): string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'date_format:H:i:s';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        if (isset($config['precision'])) {
            $params[] = $config['precision'];
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate precision
        if (isset($config['precision']) && (! is_int($config['precision']) || $config['precision'] < 0 || $config['precision'] > 6)) {
            $errors[] = 'Time precision must be an integer between 0 and 6';
        }

        return $errors;
    }
}
