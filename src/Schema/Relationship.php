<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Schema;

use InvalidArgumentException;

/**
 * Represents a relationship in a model schema
 */
final class Relationship
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $model,
        public readonly ?string $foreignKey = null,
        public readonly ?string $localKey = null,
        public readonly ?string $pivotTable = null,
        public readonly array $pivotFields = [],
        public readonly bool $withTimestamps = false,
        public readonly array $attributes = [],
    ) {}

    /**
     * Create a Relationship from array configuration
     */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            type: $config['type'] ?? 'belongsTo',
            model: $config['model'] ?? '',
            foreignKey: $config['foreign_key'] ?? $config['foreignKey'] ?? null,
            localKey: $config['local_key'] ?? $config['localKey'] ?? null,
            pivotTable: $config['pivot_table'] ?? $config['pivotTable'] ?? null,
            pivotFields: $config['pivot_fields'] ?? $config['pivotFields'] ?? [],
            withTimestamps: $config['with_timestamps'] ?? $config['withTimestamps'] ?? false,
            attributes: $config['attributes'] ?? [],
        );
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'model' => $this->model,
            'foreign_key' => $this->foreignKey,
            'local_key' => $this->localKey,
            'pivot_table' => $this->pivotTable,
            'pivot_fields' => $this->pivotFields,
            'with_timestamps' => $this->withTimestamps,
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Get the model relationship method definition for Laravel
     */
    public function getModelDefinition(): string
    {
        return match ($this->type) {
            'belongsTo' => $this->getBelongsToDefinition(),
            'hasOne' => $this->getHasOneDefinition(),
            'hasMany' => $this->getHasManyDefinition(),
            'belongsToMany' => $this->getBelongsToManyDefinition(),
            'morphTo' => $this->getMorphToDefinition(),
            'morphOne' => $this->getMorphOneDefinition(),
            'morphMany' => $this->getMorphManyDefinition(),
            default => throw new InvalidArgumentException("Unsupported relationship type: {$this->type}"),
        };
    }

    private function getBelongsToDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->foreignKey !== null && $this->foreignKey !== '' && $this->foreignKey !== '0') {
            $params[] = "'{$this->foreignKey}'";
        }

        if ($this->localKey !== null && $this->localKey !== '' && $this->localKey !== '0') {
            $params[] = "'{$this->localKey}'";
        }

        return 'return $this->belongsTo('.implode(', ', $params).');';
    }

    private function getHasOneDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->foreignKey !== null && $this->foreignKey !== '' && $this->foreignKey !== '0') {
            $params[] = "'{$this->foreignKey}'";
        }

        if ($this->localKey !== null && $this->localKey !== '' && $this->localKey !== '0') {
            $params[] = "'{$this->localKey}'";
        }

        return 'return $this->hasOne('.implode(', ', $params).');';
    }

    private function getHasManyDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->foreignKey !== null && $this->foreignKey !== '' && $this->foreignKey !== '0') {
            $params[] = "'{$this->foreignKey}'";
        }

        if ($this->localKey !== null && $this->localKey !== '' && $this->localKey !== '0') {
            $params[] = "'{$this->localKey}'";
        }

        return 'return $this->hasMany('.implode(', ', $params).');';
    }

    private function getBelongsToManyDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->pivotTable !== null && $this->pivotTable !== '' && $this->pivotTable !== '0') {
            $params[] = "'{$this->pivotTable}'";
        }

        $methodCall = 'return $this->belongsToMany('.implode(', ', $params).')';

        if ($this->withTimestamps) {
            $methodCall .= '->withTimestamps()';
        }

        if ($this->pivotFields !== []) {
            $pivotFields = "'".implode("', '", $this->pivotFields)."'";
            $methodCall .= "->withPivot({$pivotFields})";
        }

        return $methodCall.';';
    }

    private function getMorphToDefinition(): string
    {
        return 'return $this->morphTo();';
    }

    private function getMorphOneDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->foreignKey !== null && $this->foreignKey !== '' && $this->foreignKey !== '0') {
            $params[] = "'{$this->foreignKey}'";
        }

        return 'return $this->morphOne('.implode(', ', $params).');';
    }

    private function getMorphManyDefinition(): string
    {
        $modelClass = $this->formatModelClass($this->model);
        $params = [$modelClass.'::class'];

        if ($this->foreignKey !== null && $this->foreignKey !== '' && $this->foreignKey !== '0') {
            $params[] = "'{$this->foreignKey}'";
        }

        return 'return $this->morphMany('.implode(', ', $params).');';
    }

    /**
     * Format model class name for PHP code generation
     */
    private function formatModelClass(string $modelClass): string
    {
        // If it already starts with a backslash, return as is
        if (str_starts_with($modelClass, '\\')) {
            return $modelClass;
        }

        // If it contains double backslashes (from YAML), normalize them first
        $modelClass = str_replace('\\\\', '\\', $modelClass);

        // If it's just a class name without namespace, assume App\Models
        if (! str_contains($modelClass, '\\')) {
            return '\\App\\Models\\'.$modelClass;
        }

        // If it contains namespace but no leading backslash, add one
        return '\\'.$modelClass;
    }
}
