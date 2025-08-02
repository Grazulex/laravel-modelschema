<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Double field type implementation
 */
final class DoubleFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'precision',
        'scale',
        'unsigned',
    ];

    public function getType(): string
    {
        return 'double';
    }

    public function getAliases(): array
    {
        return ['double_precision'];
    }

    public function getMigrationMethod(): string
    {
        return 'double';
    }

    public function getCastType(array $config = []): string
    {
        return 'double';
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
            $errors[] = 'Double precision must be a positive integer';
        }

        // Validate scale
        if (isset($config['scale']) && (! is_int($config['scale']) || $config['scale'] < 0)) {
            $errors[] = 'Double scale must be a non-negative integer';
        }

        return $errors;
    }
}
