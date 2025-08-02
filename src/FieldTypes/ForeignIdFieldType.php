<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Foreign ID field type implementation
 * Used for foreign key relationships
 */
final class ForeignIdFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'references',
        'on',
        'onDelete',
        'onUpdate',
        'constrained',
    ];

    public function getType(): string
    {
        return 'foreignId';
    }

    public function getAliases(): array
    {
        return ['foreign_id', 'fk'];
    }

    public function getMigrationMethod(): string
    {
        return 'foreignId';
    }

    public function getCastType(array $config = []): string
    {
        return 'integer';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'integer';
        $rules[] = 'exists:'.($config['on'] ?? 'users').',id';

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        return []; // Foreign ID uses constrained() method
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate foreign key configuration
        if (isset($config['onDelete']) && ! in_array($config['onDelete'], ['cascade', 'restrict', 'set null', 'no action'])) {
            $errors[] = 'onDelete must be one of: cascade, restrict, set null, no action';
        }

        if (isset($config['onUpdate']) && ! in_array($config['onUpdate'], ['cascade', 'restrict', 'set null', 'no action'])) {
            $errors[] = 'onUpdate must be one of: cascade, restrict, set null, no action';
        }

        return $errors;
    }

    public function transformConfig(array $config): array
    {
        // Foreign IDs are always indexed
        $config['index'] = true;

        // Set default cascade behavior
        if (! isset($config['onDelete'])) {
            $config['onDelete'] = 'cascade';
        }

        return $config;
    }
}
