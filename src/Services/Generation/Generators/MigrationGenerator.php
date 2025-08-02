<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Database Migrations
 */
class MigrationGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'migration';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml'];
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        return match ($format) {
            'json' => $this->generateJson($schema, $options),
            'yaml' => $this->generateYaml($schema, $options),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    protected function generateJson(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son JSON
        $migrationData = [
            'table' => $schema->table,
            'class_name' => $this->getMigrationClassName($schema->table),
            'fields' => $this->getMigrationFieldsData($schema),
            'indexes' => $this->getMigrationIndexesData($schema),
            'foreign_keys' => $this->getMigrationForeignKeysData($schema),
            'options' => [
                'timestamps' => $schema->hasTimestamps(),
                'soft_deletes' => $schema->hasSoftDeletes(),
            ],
        ];

        // Retourne la structure prête à être insérée : "migration": { ... }
        return $this->toJsonFormat(['migration' => $migrationData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $migrationData = [
            'table' => $schema->table,
            'class_name' => $this->getMigrationClassName($schema->table),
            'fields' => $this->getMigrationFieldsData($schema),
            'indexes' => $this->getMigrationIndexesData($schema),
            'foreign_keys' => $this->getMigrationForeignKeysData($schema),
            'options' => [
                'timestamps' => $schema->hasTimestamps(),
                'soft_deletes' => $schema->hasSoftDeletes(),
            ],
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['migration' => $migrationData], 4, 2);
    }

    protected function getMigrationClassName(string $tableName): string
    {
        return 'Create'.\Illuminate\Support\Str::studly($tableName).'Table';
    }

    protected function formatMigrationFields(ModelSchema $schema): string
    {
        $fields = [];

        foreach ($schema->getAllFields() as $field) {
            $fields[] = $this->generateFieldDefinition($field);
        }

        // Add timestamps if enabled
        if ($schema->hasTimestamps()) {
            $fields[] = '            $table->timestamps();';
        }

        // Add soft deletes if enabled
        if ($schema->hasSoftDeletes()) {
            $fields[] = '            $table->softDeletes();';
        }

        return implode("\n", $fields);
    }

    protected function generateFieldDefinition(Field $field): string
    {
        $fieldType = $this->getMigrationFieldType($field);
        $definition = "            \$table->{$fieldType}('{$field->name}'";

        // Add length for string fields
        if ($field->type === 'string' && isset($field->length)) {
            $definition .= ", {$field->length}";
        }

        $definition .= ')';

        // Add field modifiers
        if ($field->nullable) {
            $definition .= '->nullable()';
        }

        if ($field->unique) {
            $definition .= '->unique()';
        }

        if ($field->index) {
            $definition .= '->index()';
        }

        if ($field->default !== null) {
            $defaultValue = is_string($field->default) ? "'{$field->default}'" : $field->default;
            $definition .= "->default({$defaultValue})";
        }

        if (isset($field->comment)) {
            $definition .= "->comment('{$field->comment}')";
        }

        return $definition.';';
    }

    protected function getMigrationFieldType(Field $field): string
    {
        return match ($field->type) {
            'bigInteger' => 'bigIncrements',
            'integer' => 'integer',
            'string' => 'string',
            'text' => 'text',
            'boolean' => 'boolean',
            'decimal' => 'decimal',
            'float' => 'float',
            'date' => 'date',
            'timestamp' => 'timestamp',
            'json' => 'json',
            'uuid' => 'uuid',
            'email' => 'string', // Email is stored as string
            default => 'string'
        };
    }

    protected function formatMigrationIndexes(ModelSchema $schema): string
    {
        $indexes = [];

        foreach ($schema->getAllFields() as $field) {
            if ($field->index && ! $field->unique) {
                $indexes[] = "            \$table->index('{$field->name}');";
            }
        }

        return implode("\n", $indexes);
    }

    protected function formatMigrationForeignKeys(ModelSchema $schema): string
    {
        $foreignKeys = [];

        foreach ($schema->relationships as $relationship) {
            if ($relationship->type === 'belongsTo') {
                $foreignKey = $relationship->foreignKey ?? $relationship->name.'_id';
                $referencedTable = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($relationship->model));

                $foreignKeys[] = "            \$table->foreign('{$foreignKey}')->references('id')->on('{$referencedTable}');";
            }
        }

        return implode("\n", $foreignKeys);
    }

    protected function getMigrationFieldsData(ModelSchema $schema): array
    {
        $fields = [];

        foreach ($schema->getAllFields() as $field) {
            $fields[] = [
                'name' => $field->name,
                'type' => $field->type,
                'migration_type' => $this->getMigrationFieldType($field),
                'nullable' => $field->nullable,
                'unique' => $field->unique,
                'index' => $field->index,
                'default' => $field->default ?? null,
                'length' => $field->length ?? null,
                'comment' => $field->comment ?? null,
            ];
        }

        return $fields;
    }

    protected function getMigrationIndexesData(ModelSchema $schema): array
    {
        $indexes = [];

        foreach ($schema->getAllFields() as $field) {
            if ($field->index && ! $field->unique) {
                $indexes[] = [
                    'type' => 'index',
                    'columns' => [$field->name],
                    'name' => null,
                ];
            }
        }

        return $indexes;
    }

    protected function getMigrationForeignKeysData(ModelSchema $schema): array
    {
        $foreignKeys = [];

        foreach ($schema->relationships as $relationship) {
            if ($relationship->type === 'belongsTo') {
                $foreignKey = $relationship->foreignKey ?? $relationship->name.'_id';
                $referencedTable = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($relationship->model));

                $foreignKeys[] = [
                    'column' => $foreignKey,
                    'references' => 'id',
                    'on' => $referencedTable,
                    'onDelete' => 'cascade',
                    'onUpdate' => 'cascade',
                ];
            }
        }

        return $foreignKeys;
    }
}
