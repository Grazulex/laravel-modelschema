<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Float field type implementation
 */
final class FloatFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'precision',
        'scale',
        'unsigned',
    ];

    public function getType(): string
    {
        return 'float';
    }

    public function getAliases(): array
    {
        return ['real'];
    }

    public function getMigrationMethod(): string
    {
        return 'float';
    }

    public function getCastType(): string
    {
        return 'float';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'numeric';

        if (isset($config['min'])) {
            $rules[] = "min:{$config['min']}";
        }

        if (isset($config['max'])) {
            $rules[] = "max:{$config['max']}";
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        if (isset($config['precision'])) {
            $params[] = $config['precision'];
            if (isset($config['scale'])) {
                $params[] = $config['scale'];
            }
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate precision
        if (isset($config['precision']) && (! is_int($config['precision']) || $config['precision'] <= 0)) {
            $errors[] = 'Float precision must be a positive integer';
        }

        // Validate scale
        if (isset($config['scale']) && (! is_int($config['scale']) || $config['scale'] < 0)) {
            $errors[] = 'Float scale must be a non-negative integer';
        }

        return $errors;
    }
}
