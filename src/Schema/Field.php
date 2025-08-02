<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Schema;

/**
 * Represents a single field in a model schema
 */
final class Field
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = false,
        public readonly bool $unique = false,
        public readonly bool $index = false,
        public readonly mixed $default = null,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly array $rules = [],
        public readonly array $validation = [],
        public readonly ?string $comment = null,
        public readonly array $attributes = [],
    ) {}

    /**
     * Create a Field from array configuration
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            type: $config['type'] ?? 'string',
            nullable: $config['nullable'] ?? false,
            unique: $config['unique'] ?? false,
            index: $config['index'] ?? false,
            default: $config['default'] ?? null,
            length: $config['length'] ?? null,
            precision: $config['precision'] ?? null,
            scale: $config['scale'] ?? null,
            rules: $config['rules'] ?? [],
            validation: $config['validation'] ?? [],
            comment: $config['comment'] ?? null,
            attributes: $config['attributes'] ?? [],
        );
    }

    /**
     * Convert to array representation
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'unique' => $this->unique,
            'index' => $this->index,
            'default' => $this->default,
            'length' => $this->length,
            'precision' => $this->precision,
            'scale' => $this->scale,
            'rules' => $this->rules,
            'validation' => $this->validation,
            'comment' => $this->comment,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Get validation rules for this field
     * 
     * @return array<string>
     */
    public function getValidationRules(): array
    {
        $rules = [];

        // Required/nullable
        $rules[] = $this->nullable ? 'nullable' : 'required';

        // Type-based rules
        switch ($this->type) {
            case 'string':
                $rules[] = 'string';
                if ($this->length !== null && $this->length !== 0) {
                    $rules[] = "max:{$this->length}";
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'email':
                $rules[] = 'email';
                break;
            case 'uuid':
                $rules[] = 'uuid';
                break;
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'decimal':
                $rules[] = 'numeric';
                break;
        }

        // Unique validation
        if ($this->unique) {
            $rules[] = 'unique';
        }

        // Custom validation rules
        $rules = array_merge($rules, $this->validation, $this->rules);

        return array_unique($rules);
    }

    /**
     * Check if field should be fillable in model
     */
    public function isFillable(): bool
    {
        // Timestamps and IDs are typically not fillable
        return ! in_array($this->name, ['id', 'created_at', 'updated_at', 'deleted_at']);
    }

    /**
     * Get cast type for Laravel model
     */
    public function getCastType(): ?string
    {
        return match ($this->type) {
            'boolean' => 'boolean',
            'integer' => 'integer',
            'decimal' => 'decimal:'.($this->scale ?? 2),
            'datetime' => 'datetime',
            'date' => 'date',
            'json' => 'array',
            'uuid' => 'string',
            default => null,
        };
    }
}
