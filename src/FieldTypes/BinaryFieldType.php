<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Binary field type implementation
 */
final class BinaryFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'size', // tiny, medium, long
    ];

    public function getType(): string
    {
        return 'binary';
    }

    public function getAliases(): array
    {
        return ['blob'];
    }

    public function getMigrationMethod(): string
    {
        return match ($this->getSize()) {
            'medium' => 'mediumBinary',
            'long' => 'longBinary',
            default => 'binary',
        };
    }

    public function getCastType(array $config = []): ?string
    {
        return null; // Binary data is not cast
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        if (isset($config['max_size'])) {
            $rules[] = "max:{$config['max_size']}";
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Binary type doesn't need parameters
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate size
        if (isset($config['size']) && ! in_array($config['size'], ['binary', 'medium', 'long'])) {
            $errors[] = 'Binary size must be one of: binary, medium, long';
        }

        return $errors;
    }

    private function getSize(): string
    {
        return 'binary'; // Default size
    }
}
