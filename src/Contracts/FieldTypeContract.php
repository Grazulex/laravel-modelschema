<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Contracts;

/**
 * Contract for field type handlers
 */
interface FieldTypeInterface
{
    /**
     * Get the field type identifier
     */
    public function getType(): string;

    /**
     * Get validation rules for this field type
     */
    public function getValidationRules(array $config): array;

    /**
     * Get cast type for Laravel model
     */
    public function getCastType(array $config): ?string;

    /**
     * Get migration column definition
     */
    public function getMigrationDefinition(array $config): string;

    /**
     * Validate field configuration
     */
    public function validateConfig(array $config): array;

    /**
     * Get default value for this field type
     */
    public function getDefaultValue(array $config): mixed;

    /**
     * Check if field should be fillable
     */
    public function isFillable(string $fieldName, array $config): bool;

    /**
     * Get factory definition for this field
     */
    public function getFactoryDefinition(array $config): string;
}
