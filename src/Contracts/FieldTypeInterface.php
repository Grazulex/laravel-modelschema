<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Contracts;

/**
 * Contract for field type implementations
 */
interface FieldTypeInterface
{
    /**
     * Get the field type identifier
     */
    public function getType(): string;

    /**
     * Get supported aliases for this field type
     */
    public function getAliases(): array;

    /**
     * Validate field configuration
     */
    public function validate(array $config): array;

    /**
     * Get Laravel migration method for this field type
     */
    public function getMigrationMethod(): string;

    /**
     * Get Laravel cast type for this field
     */
    public function getCastType(): ?string;

    /**
     * Get default validation rules for this field type
     */
    public function getValidationRules(array $config = []): array;

    /**
     * Check if this field type supports a specific attribute
     */
    public function supportsAttribute(string $attribute): bool;

    /**
     * Get migration parameters for the field
     */
    public function getMigrationParameters(array $config): array;

    /**
     * Transform field configuration for specific use cases
     */
    public function transformConfig(array $config): array;
}
