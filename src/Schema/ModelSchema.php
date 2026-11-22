<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Schema;

use Exception;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents a complete model schema with fields, relationships, and metadata
 */
final class ModelSchema
{
    /**
     * Accepted version formats: "major", "major.minor" or "major.minor.patch"
     */
    public const VERSION_PATTERN = '/^(0|[1-9]\d{0,8})(\.(0|[1-9]\d{0,8})){0,2}$/';

    /**
     * Schema version, normalized to "major.minor.patch" (null when the schema is not versioned)
     */
    public readonly ?string $version;

    /**
     * @param  Field[]  $fields
     * @param  Relationship[]  $relationships
     * @param  string|int|float|null  $version  Schema version ("1", "1.2" or "1.2.3"), null for an unversioned schema
     *
     * @throws SchemaException When the version is not a valid version number
     */
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly array $fields = [],
        public readonly array $relationships = [],
        public readonly array $options = [],
        public readonly array $metadata = [],
        string|int|float|null $version = null,
    ) {
        $this->version = self::normalizeVersion($version);
    }

    /**
     * Validate a version value and normalize it to "major.minor.patch"
     *
     * Returns null when no version is given. Integers and floats are accepted because
     * YAML parses unquoted values such as `1` or `1.2` as numbers.
     *
     * @throws SchemaException When the value is not a valid version number
     */
    public static function normalizeVersion(mixed $version): ?string
    {
        if ($version === null) {
            return null;
        }

        if (is_int($version) || (is_float($version) && is_finite($version))) {
            $candidate = (string) $version;
        } elseif (is_string($version)) {
            $candidate = mb_trim($version);
        } else {
            throw SchemaException::invalidVersion($version);
        }

        if (preg_match(self::VERSION_PATTERN, $candidate) !== 1) {
            throw SchemaException::invalidVersion($version);
        }

        $segments = array_pad(explode('.', $candidate), 3, '0');

        return implode('.', $segments);
    }

    /**
     * Check whether a value is a valid schema version
     */
    public static function isValidVersion(mixed $version): bool
    {
        if ($version === null) {
            return false;
        }

        try {
            self::normalizeVersion($version);
        } catch (SchemaException) {
            return false;
        }

        return true;
    }

    /**
     * Create a ModelSchema from array configuration
     *
     * @throws SchemaException When the "version" key is present but invalid
     */
    public static function fromArray(string $name, array $config): self
    {
        $fields = [];
        $relationships = [];

        // Validate the version first so an invalid one is reported before anything else
        $version = self::normalizeVersion($config['version'] ?? null);

        // Parse fields
        foreach ($config['fields'] ?? [] as $fieldName => $fieldConfig) {
            $fields[$fieldName] = Field::fromArray($fieldName, $fieldConfig);
        }

        // Parse relationships
        foreach ($config['relationships'] ?? $config['relations'] ?? [] as $relationName => $relationConfig) {
            $relationships[$relationName] = Relationship::fromArray($relationName, $relationConfig);
        }

        // Determine table name
        $table = $config['table'] ??
                 $config['options']['table'] ??
                 \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly($name));

        return new self(
            name: $name,
            table: $table,
            fields: $fields,
            relationships: $relationships,
            options: $config['options'] ?? [],
            metadata: $config['metadata'] ?? [],
            version: $version,
        );
    }

    /**
     * Create from YAML file
     */
    public static function fromYamlFile(string $filePath): self
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Schema file not found: {$filePath}");
        }

        $content = file_get_contents($filePath);

        try {
            $config = Yaml::parse($content);
        } catch (Exception $e) {
            throw new InvalidArgumentException("Invalid YAML in file: {$filePath}. Error: ".$e->getMessage(), $e->getCode(), $e);
        }

        // Extract model name from config or filename
        $modelName = $config['model'] ??
                     $config['name'] ??
                     pathinfo($filePath, PATHINFO_FILENAME);

        return self::fromArray($modelName, $config);
    }

    /**
     * Create from YAML string
     */
    public static function fromYaml(string $yamlContent, string $name = 'UnknownModel'): self
    {
        try {
            $config = Yaml::parse($yamlContent);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid YAML content: '.$e->getMessage(), $e->getCode(), $e);
        }

        // Extract model name from config or use provided name
        $modelName = $config['model'] ?? $config['name'] ?? $name;

        return self::fromArray($modelName, $config);
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        $data = [
            'model' => $this->name,
            'table' => $this->table,
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $this->fields),
            'relationships' => array_map(fn (Relationship $rel): array => $rel->toArray(), $this->relationships),
            'options' => $this->options,
            'metadata' => $this->metadata,
        ];

        // Only versioned schemas expose the key, so unversioned schemas keep their previous shape
        if ($this->version !== null) {
            $data['version'] = $this->version;
        }

        return $data;
    }

    /**
     * Check if the schema declares a version
     */
    public function hasVersion(): bool
    {
        return $this->version !== null;
    }

    /**
     * Compare the schema version with another schema or version number
     *
     * Returns -1 when this schema is older, 0 when both versions are equal and 1 when this
     * schema is newer. An unversioned schema is compared as "0.0.0".
     *
     * @throws SchemaException When the given version is not a valid version number
     */
    public function compareVersion(self|string|int|float|null $other): int
    {
        $otherVersion = $other instanceof self ? $other->version : self::normalizeVersion($other);

        return version_compare($this->version ?? '0.0.0', $otherVersion ?? '0.0.0');
    }

    /**
     * Check if this schema is newer than another schema or version number
     *
     * @throws SchemaException When the given version is not a valid version number
     */
    public function isNewerThan(self|string|int|float|null $other): bool
    {
        return $this->compareVersion($other) > 0;
    }

    /**
     * Check if this schema is older than another schema or version number
     *
     * @throws SchemaException When the given version is not a valid version number
     */
    public function isOlderThan(self|string|int|float|null $other): bool
    {
        return $this->compareVersion($other) < 0;
    }

    /**
     * Convert to JSON
     */
    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $flags);
    }

    /**
     * Convert to PHP array string
     */
    public function toPhpArray(): string
    {
        return var_export($this->toArray(), true);
    }

    /**
     * Get all fields including foreign key fields from relationships
     */
    public function getAllFields(): array
    {
        $allFields = $this->fields;

        // Add foreign key fields from belongsTo relationships
        foreach ($this->relationships as $relationship) {
            if ($relationship->type === 'belongsTo') {
                $foreignKey = $relationship->foreignKey ?? $relationship->name.'_id';

                // Only add if not already defined
                if (! isset($allFields[$foreignKey])) {
                    $allFields[$foreignKey] = Field::fromArray($foreignKey, [
                        'type' => 'integer',
                        'nullable' => true,
                        'comment' => "Foreign key for {$relationship->name} relationship",
                    ]);
                }
            }
        }

        return $allFields;
    }

    /**
     * Get fillable fields for Laravel model
     */
    public function getFillableFields(): array
    {
        return array_filter($this->getAllFields(), fn (Field $field): bool => $field->isFillable());
    }

    /**
     * Get castable fields for Laravel model
     */
    public function getCastableFields(): array
    {
        $casts = [];

        foreach ($this->getAllFields() as $field) {
            $castType = $field->getCastType();
            if ($castType) {
                $casts[$field->name] = $castType;
            }
        }

        return $casts;
    }

    /**
     * Get validation rules for all fields
     */
    public function getValidationRules(): array
    {
        $rules = [];

        foreach ($this->getAllFields() as $field) {
            $fieldRules = $field->getValidationRules();
            if (! empty($fieldRules)) {
                $rules[$field->name] = $fieldRules;
            }
        }

        return $rules;
    }

    /**
     * Check if schema has timestamps
     */
    public function hasTimestamps(): bool
    {
        return $this->options['timestamps'] ?? true;
    }

    /**
     * Check if schema has soft deletes
     */
    public function hasSoftDeletes(): bool
    {
        // Check explicit option first
        if (isset($this->options['soft_deletes'])) {
            return $this->options['soft_deletes'];
        }

        // Check if deleted_at field exists
        return isset($this->fields['deleted_at']) &&
               $this->fields['deleted_at']->type === 'timestamp' &&
               $this->fields['deleted_at']->nullable === true;
    }

    /**
     * Get the model namespace
     */
    public function getModelNamespace(): string
    {
        return $this->options['namespace'] ?? 'App\\Models';
    }

    /**
     * Get the full model class name
     */
    public function getModelClass(): string
    {
        return $this->getModelNamespace().'\\'.$this->name;
    }

    /**
     * Get all relationships
     */
    public function getRelationships(): array
    {
        return $this->relationships;
    }
}
