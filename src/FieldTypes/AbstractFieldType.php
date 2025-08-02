<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

use Grazulex\LaravelModelschema\Contracts\FieldTypeInterface;

/**
 * Abstract base class for field types
 */
abstract class AbstractFieldType implements FieldTypeInterface
{
    /**
     * Common attributes supported by most field types
     */
    protected array $commonAttributes = [
        'nullable',
        'default',
        'comment',
        'index',
        'unique',
    ];

    /**
     * Attributes specific to this field type
     */
    protected array $specificAttributes = [];

    public function getAliases(): array
    {
        return [];
    }

    public function validate(array $config): array
    {
        $errors = [];

        // Validate supported attributes
        foreach (array_keys($config) as $attribute) {
            if (! $this->supportsAttribute($attribute)) {
                $errors[] = "Attribute '{$attribute}' is not supported for field type '{$this->getType()}'";
            }
        }

        return $errors;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, array_merge($this->commonAttributes, $this->specificAttributes), true);
    }

    public function getCastType(): ?string
    {
        return null;
    }

    public function getValidationRules(array $config = []): array
    {
        return [
            // Required/nullable
            $config['nullable'] ?? false ? 'nullable' : 'required',
        ];
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add length if supported and provided
        if ($this->supportsAttribute('length') && isset($config['length'])) {
            $params[] = $config['length'];
        }

        // Add precision and scale for decimal types
        if ($this->supportsAttribute('precision') && isset($config['precision'])) {
            $params[] = $config['precision'];
            if ($this->supportsAttribute('scale') && isset($config['scale'])) {
                $params[] = $config['scale'];
            }
        }

        return $params;
    }

    public function transformConfig(array $config): array
    {
        // Default implementation - no transformation
        return $config;
    }

    /**
     * Get the migration method call with parameters
     */
    public function getMigrationCall(string $fieldName, array $config): string
    {
        $method = $this->getMigrationMethod();
        $params = $this->getMigrationParameters($config);

        $paramString = $params === []
            ? "'{$fieldName}'"
            : "'{$fieldName}', ".implode(', ', array_map(fn ($p) => is_string($p) ? "'{$p}'" : $p, $params));

        return "\$table->{$method}({$paramString})";
    }
}
