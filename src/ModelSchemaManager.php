<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema;

use Grazulex\LaravelModelschema\Schema\ModelSchema;

/**
 * Main facade for Laravel ModelSchema package
 */
final class ModelSchemaManager
{
    /**
     * Parse a YAML file and return a ModelSchema
     */
    public static function fromYamlFile(string $filePath): ModelSchema
    {
        return ModelSchema::fromYamlFile($filePath);
    }

    /**
     * Parse YAML content and return a ModelSchema
     */
    public static function fromYaml(string $yamlContent, string $name = 'UnknownModel'): ModelSchema
    {
        return ModelSchema::fromYaml($yamlContent, $name);
    }

    /**
     * Create a ModelSchema from array configuration
     */
    public static function fromArray(string $name, array $config): ModelSchema
    {
        return ModelSchema::fromArray($name, $config);
    }

    /**
     * Validate a schema configuration
     * 
     * @return array<string>
     */
    public static function validate(ModelSchema $schema): array
    {
        $errors = [];

        // Check for required fields
        if ($schema->fields === []) {
            $errors[] = 'Schema must have at least one field';
        }

        // Validate field types
        foreach ($schema->fields as $field) {
            if (! self::isValidFieldType($field->type)) {
                $errors[] = "Invalid field type '{$field->type}' for field '{$field->name}'";
            }
        }

        // Validate relationships
        foreach ($schema->relationships as $relationship) {
            if (! self::isValidRelationshipType($relationship->type)) {
                $errors[] = "Invalid relationship type '{$relationship->type}' for relationship '{$relationship->name}'";
            }

            if (empty($relationship->model) && $relationship->type !== 'morphTo') {
                $errors[] = "Relationship '{$relationship->name}' must have a model";
            }
        }

        return $errors;
    }

    /**
     * Get supported field types
     * 
     * @return array<string>
     */
    public static function getSupportedFieldTypes(): array
    {
        return [
            'string',
            'text',
            'longText',
            'mediumText',
            'integer',
            'bigInteger',
            'tinyInteger',
            'smallInteger',
            'mediumInteger',
            'unsignedBigInteger',
            'unsignedInteger',
            'unsignedTinyInteger',
            'unsignedSmallInteger',
            'unsignedMediumInteger',
            'decimal',
            'float',
            'double',
            'boolean',
            'date',
            'datetime',
            'timestamp',
            'time',
            'year',
            'json',
            'uuid',
            'email',
            'url',
            'binary',
            'enum',
            'set',
        ];
    }

    /**
     * Get supported relationship types
     * 
     * @return array<string>
     */
    public static function getSupportedRelationshipTypes(): array
    {
        return [
            'belongsTo',
            'hasOne',
            'hasMany',
            'belongsToMany',
            'morphTo',
            'morphOne',
            'morphMany',
            'hasManyThrough',
            'hasOneThrough',
        ];
    }

    /**
     * Create a basic schema template
     * 
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public static function createTemplate(string $modelName, array $fields = []): array
    {
        $defaultFields = $fields !== [] ? $fields : [
            'id' => [
                'type' => 'bigInteger',
                'nullable' => false,
                'unique' => true,
                'comment' => 'Primary key',
            ],
            'name' => [
                'type' => 'string',
                'nullable' => false,
                'length' => 255,
                'comment' => 'Name field',
            ],
        ];

        return [
            'model' => $modelName,
            'table' => \Illuminate\Support\Str::snake(\Illuminate\Support\Str::pluralStudly($modelName)),
            'fields' => $defaultFields,
            'relationships' => [],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => false,
                'namespace' => 'App\\Models',
            ],
            'metadata' => [
                'version' => '1.0',
                'description' => "Schema for {$modelName} model",
                'created_at' => date('Y-m-d'),
            ],
        ];
    }

    /**
     * Check if field type is valid
     */
    private static function isValidFieldType(string $type): bool
    {
        return in_array($type, self::getSupportedFieldTypes());
    }

    /**
     * Check if relationship type is valid
     */
    private static function isValidRelationshipType(string $type): bool
    {
        return in_array($type, self::getSupportedRelationshipTypes());
    }
}
