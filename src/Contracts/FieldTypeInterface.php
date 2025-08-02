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
     * Get aliases for this field type
     */
    public function getAliases(): array;

    /**
     * Validate field configuration
     */
    public function validate(array $config): array;

    /**
     * Check if attribute is supported
     */
    public function supportsAttribute(string $attribute): bool;

    /**
     * Get cast type for Laravel model
     */
    public function getCastType(array $config): ?string;

    /**
     * Get validation rules for this field type
     */
    public function getValidationRules(array $config): array;

    /**
     * Get migration parameters for this field
     */
    public function getMigrationParameters(array $config): array;

    /**
     * Transform configuration array
     */
    public function transformConfig(array $config): array;

    /**
     * Get the migration method call with parameters
     */
    public function getMigrationCall(string $fieldName, array $config): string;
}
