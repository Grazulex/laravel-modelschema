<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Morphs field type implementation for polymorphic relationships
 */
final class MorphsFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'morph_name',
        'id_column',
        'type_column',
    ];

    public function getType(): string
    {
        return 'morphs';
    }

    public function getAliases(): array
    {
        return ['polymorphic'];
    }

    public function getMigrationMethod(): string
    {
        return 'morphs';
    }

    public function getCastType(): ?string
    {
        return null; // Morphs creates multiple columns
    }

    public function getValidationRules(array $config = []): array
    {
        // Morphs field creates two columns, so validation is more complex
        return [];
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        if (isset($config['morph_name'])) {
            $params[] = $config['morph_name'];
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate morph name
        if (isset($config['morph_name']) && ! is_string($config['morph_name'])) {
            $errors[] = 'Morph name must be a string';
        }

        return $errors;
    }

    public function transformConfig(array $config): array
    {
        // Set default morph name if not specified
        if (! isset($config['morph_name'])) {
            $config['morph_name'] = 'morphable';
        }

        return $config;
    }
}
