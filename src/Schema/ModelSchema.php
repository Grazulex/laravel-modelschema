<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Schema;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Represents a complete model schema with fields, relationships, and metadata
 */
final class ModelSchema
{
    /**
     * @param  Field[]  $fields
     * @param  Relationship[]  $relationships
     */
    public function __construct(
        public readonly string $name,
        public readonly string $table,
        public readonly array $fields = [],
        public readonly array $relationships = [],
        public readonly array $options = [],
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a ModelSchema from array configuration
     */
    public static function fromArray(string $name, array $config): self
    {
        $fields = [];
        $relationships = [];

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
        return [
            'model' => $this->name,
            'table' => $this->table,
            'fields' => array_map(fn (Field $field): array => $field->toArray(), $this->fields),
            'relationships' => array_map(fn (Relationship $rel): array => $rel->toArray(), $this->relationships),
            'options' => $this->options,
            'metadata' => $this->metadata,
        ];
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
        return $this->options['soft_deletes'] ?? false;
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
