<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Decimal field type implementation
 */
final class DecimalFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'precision',
        'scale',
        'unsigned',
    ];

    public function getType(): string
    {
        return 'decimal';
    }

    public function getAliases(): array
    {
        return ['numeric', 'money'];
    }

    public function getMigrationMethod(): string
    {
        return 'decimal';
    }

    public function getCastType(array $config = []): string
    {
        return 'decimal:'.$this->getScale();
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'numeric';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        $precision = $config['precision'] ?? 8;
        $scale = $config['scale'] ?? 2;

        $params[] = $precision;
        $params[] = $scale;

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate precision
        if (isset($config['precision']) && (! is_int($config['precision']) || $config['precision'] <= 0)) {
            $errors[] = 'Decimal precision must be a positive integer';
        }

        // Validate scale
        if (isset($config['scale']) && (! is_int($config['scale']) || $config['scale'] < 0)) {
            $errors[] = 'Decimal scale must be a non-negative integer';
        }

        // Scale cannot be greater than precision
        if (isset($config['precision']) && isset($config['scale']) && $config['scale'] > $config['precision']) {
            $errors[] = 'Decimal scale cannot be greater than precision';
        }

        return $errors;
    }

    private function getScale(): int
    {
        return 2; // Default scale
    }
}
