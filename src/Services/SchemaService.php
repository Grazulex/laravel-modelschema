<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Illuminate\Filesystem\Filesystem;

/**
 * Core schema service for YAML management
 * This service handles the basic operations that other packages can extend
 */
class SchemaService
{
    public function __construct(
        protected Filesystem $filesystem = new Filesystem()
    ) {}

    /**
     * Parse a YAML file into a ModelSchema
     * Core functionality that other packages can rely on
     */
    public function parseYamlFile(string $filePath): ModelSchema
    {
        if (!$this->filesystem->exists($filePath)) {
            throw SchemaException::fileNotFound($filePath);
        }

        return ModelSchema::fromYamlFile($filePath);
    }

    /**
     * Parse YAML content into a ModelSchema
     * Core functionality for parsing YAML strings
     */
    public function parseYamlContent(string $yamlContent, string $modelName = 'UnknownModel'): ModelSchema
    {
        return ModelSchema::fromYaml($yamlContent, $modelName);
    }

    /**
     * Validate a ModelSchema against core rules
     * Returns array of validation errors (empty if valid)
     */
    public function validateSchema(ModelSchema $schema): array
    {
        $errors = [];

        // Core validations that all packages should respect
        if (empty($schema->fields)) {
            $errors[] = "Schema '{$schema->name}' must have at least one field";
        }

        // Validate field types exist in registry
        foreach ($schema->fields as $field) {
            if (!$this->isValidFieldType($field->type)) {
                $errors[] = "Unknown field type '{$field->type}' in field '{$field->name}'";
            }
        }

        // Validate relationship types
        foreach ($schema->relationships as $relationship) {
            if (!$this->isValidRelationshipType($relationship->type)) {
                $errors[] = "Unknown relationship type '{$relationship->type}' in relationship '{$relationship->name}'";
            }
        }

        return $errors;
    }

    /**
     * Get the core schema structure (fields that this package manages)
     * Other packages can use this to know what's handled by the core
     */
    public function getCoreSchemaKeys(): array
    {
        return [
            'model',      // Model name
            'table',      // Table name
            'fields',     // Field definitions
            'relationships', // Relationship definitions (also 'relations')
            'options',    // Core options (timestamps, soft_deletes)
            'metadata',   // Metadata information
        ];
    }

    /**
     * Extract core schema data from a parsed YAML array
     * Returns only the parts this package handles
     */
    public function extractCoreSchema(array $yamlData): array
    {
        $coreKeys = $this->getCoreSchemaKeys();
        $coreSchema = [];

        foreach ($coreKeys as $key) {
            if (isset($yamlData[$key])) {
                $coreSchema[$key] = $yamlData[$key];
            }
        }

        // Handle 'relations' alias for 'relationships'
        if (isset($yamlData['relations']) && !isset($coreSchema['relationships'])) {
            $coreSchema['relationships'] = $yamlData['relations'];
        }

        return $coreSchema;
    }

    /**
     * Extract extension data (data not handled by core)
     * Other packages can use this to get their custom data
     */
    public function extractExtensionData(array $yamlData): array
    {
        $coreKeys = $this->getCoreSchemaKeys();
        $coreKeys[] = 'relations'; // Also exclude the alias
        
        $extensionData = [];

        foreach ($yamlData as $key => $value) {
            if (!in_array($key, $coreKeys, true)) {
                $extensionData[$key] = $value;
            }
        }

        return $extensionData;
    }

    /**
     * Check if a field type is valid in the registry
     */
    public function isValidFieldType(string $type): bool
    {
        return \Grazulex\LaravelModelschema\Support\FieldTypeRegistry::has($type);
    }

    /**
     * Check if a relationship type is valid
     */
    public function isValidRelationshipType(string $type): bool
    {
        $validTypes = [
            'belongsTo',
            'hasOne', 
            'hasMany',
            'belongsToMany',
            'hasOneThrough',
            'hasManyThrough',
            'morphTo',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphedByMany'
        ];

        return in_array($type, $validTypes, true);
    }

    /**
     * Get all supported field types from the registry
     */
    public function getSupportedFieldTypes(): array
    {
        return array_keys(\Grazulex\LaravelModelschema\Support\FieldTypeRegistry::all());
    }

    /**
     * Get all supported relationship types
     */
    public function getSupportedRelationshipTypes(): array
    {
        return [
            'belongsTo' => 'Belongs to a single related model',
            'hasOne' => 'Has one related model', 
            'hasMany' => 'Has many related models',
            'belongsToMany' => 'Many-to-many relationship',
            'hasOneThrough' => 'Has one through intermediate model',
            'hasManyThrough' => 'Has many through intermediate model',
            'morphTo' => 'Polymorphic belongs to',
            'morphOne' => 'Polymorphic has one',
            'morphMany' => 'Polymorphic has many',
            'morphToMany' => 'Polymorphic many-to-many',
            'morphedByMany' => 'Inverse polymorphic many-to-many'
        ];
    }

    /**
     * Save a ModelSchema to a YAML file
     * Core functionality for writing schemas
     */
    public function saveSchemaToYaml(ModelSchema $schema, string $filePath): void
    {
        $directory = dirname($filePath);
        if (!$this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $yamlContent = $this->convertSchemaToYaml($schema);
        $this->filesystem->put($filePath, $yamlContent);
    }

    /**
     * Convert a ModelSchema to YAML string
     */
    public function convertSchemaToYaml(ModelSchema $schema): string
    {
        $data = $schema->toArray();
        
        if (!function_exists('yaml_emit')) {
            // Fallback to simple YAML generation if yaml extension not available
            return $this->generateSimpleYaml($data);
        }

        return yaml_emit($data, YAML_UTF8_ENCODING);
    }

    /**
     * Simple YAML generation fallback
     */
    protected function generateSimpleYaml(array $data): string
    {
        $modelName = $data['name'] ?? 'UnknownModel';
        $yaml = "# Model Schema: {$modelName}\n";
        $yaml .= "# Generated: " . now()->format('Y-m-d H:i:s') . "\n\n";
        
        foreach ($data as $key => $value) {
            $yaml .= $this->convertValueToYaml($key, $value, 0);
        }
        
        return $yaml;
    }

    /**
     * Convert a value to YAML format recursively
     */
    protected function convertValueToYaml(string|int $key, mixed $value, int $depth): string
    {
        $indent = str_repeat('  ', $depth);
        
        if (is_array($value)) {
            $yaml = "{$indent}{$key}:\n";
            foreach ($value as $subKey => $subValue) {
                $yaml .= $this->convertValueToYaml($subKey, $subValue, $depth + 1);
            }
            return $yaml;
        }
        
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_null($value)) {
            $value = 'null';
        } elseif (is_string($value) && (strpos($value, ':') !== false || strpos($value, '\n') !== false)) {
            $value = "'{$value}'";
        }
        
        return "{$indent}{$key}: {$value}\n";
    }
}
