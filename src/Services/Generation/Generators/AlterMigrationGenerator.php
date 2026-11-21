<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\SchemaDiffService;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Generator for incremental ("alter") Laravel migrations.
 *
 * Compares two versions of a schema through the SchemaDiffService and produces
 * the Schema::table() operations (add, drop, change, rename, indexes, foreign keys)
 * needed to move a database from the old version to the new one, with a reversible down().
 *
 * Supported options:
 *  - renames:    array<string, string> map of old column name => new column name
 *  - class_name: override the generated class name
 */
class AlterMigrationGenerator
{
    /**
     * Diff attributes that change the column definition itself (require ->change())
     */
    private const COLUMN_ATTRIBUTES = ['type', 'nullable', 'default', 'length', 'precision', 'scale', 'comment', 'values'];

    /**
     * Order in which operation types are executed in up()
     */
    private const OPERATION_ORDER = [
        'drop_foreign',
        'drop_unique',
        'drop_index',
        'rename_column',
        'drop_column',
        'drop_timestamps',
        'drop_soft_deletes',
        'modify_column',
        'add_column',
        'add_timestamps',
        'add_soft_deletes',
        'add_unique',
        'add_index',
        'add_foreign',
    ];

    private const INDENT = '            ';

    private SchemaDiffService $diffService;

    public function __construct(?SchemaDiffService $diffService = null)
    {
        $this->diffService = $diffService ?? new SchemaDiffService();
    }

    public function getGeneratorName(): string
    {
        return 'alter_migration';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml', 'php'];
    }

    /**
     * Generate the alter migration fragments for the given schema versions
     *
     * @return array{metadata: array, json: string, yaml: string, php: string}
     */
    public function generate(ModelSchema $oldSchema, ModelSchema $newSchema, array $options = []): array
    {
        $data = $this->buildMigrationData($oldSchema, $newSchema, $options);

        $result = [
            'metadata' => [
                'generator' => $this->getGeneratorName(),
                'model_name' => $newSchema->name,
                'table_name' => $newSchema->table,
                'old_table_name' => $oldSchema->table,
                'generated_at' => now()->toISOString(),
                'options' => $options,
            ],
        ];

        foreach ($this->getAvailableFormats() as $format) {
            $result[$format] = $this->generateFormat($data, $format);
        }

        return $result;
    }

    /**
     * Build the structured alter migration data (operations, code, risk analysis)
     */
    public function buildMigrationData(ModelSchema $oldSchema, ModelSchema $newSchema, array $options = []): array
    {
        $diff = $this->diffService->compareSchemas($oldSchema, $newSchema);
        $warnings = [];

        $operations = $this->buildOperations($oldSchema, $newSchema, $diff, $options, $warnings);
        $tableRenamed = $oldSchema->table !== $newSchema->table;

        $up = array_map(fn (array $operation): string => $operation['up'], $operations);
        $down = array_reverse(array_map(fn (array $operation): string => $operation['down'], $operations));

        return [
            'table' => $newSchema->table,
            'old_table' => $oldSchema->table,
            'table_renamed' => $tableRenamed,
            'class_name' => $options['class_name'] ?? $this->getMigrationClassName($newSchema->table),
            'migration_name' => 'alter_'.$newSchema->table.'_table',
            'requires_migration' => $operations !== [] || $tableRenamed,
            'data_loss_risk' => $diff['migration_impact']['data_loss_risk'] ?? 'none',
            'compatibility' => $diff['summary']['compatibility'] ?? 'fully_compatible',
            'breaking_changes' => $diff['breaking_changes'] ?? [],
            'operations' => array_map(function (array $operation): array {
                unset($operation['up'], $operation['down']);

                return $operation;
            }, $operations),
            'up' => $up,
            'down' => $down,
            'warnings' => $warnings,
        ];
    }

    protected function generateFormat(array $data, string $format): string
    {
        return match ($format) {
            'json' => json_encode(['alter_migration' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'yaml' => Yaml::dump(['alter_migration' => $data], 6, 2),
            'php' => $this->renderPhpMigration($data),
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    protected function getMigrationClassName(string $tableName): string
    {
        return 'Alter'.Str::studly($tableName).'Table';
    }

    /**
     * Render a complete, ready-to-save Laravel migration file
     */
    protected function renderPhpMigration(array $data): string
    {
        $oldTable = $this->export($data['old_table']);
        $newTable = $this->export($data['table']);

        $upBody = [];
        $downBody = [];

        if ($data['up'] !== []) {
            $upBody[] = $this->renderTableClosure($oldTable, $data['up']);
            $downBody[] = $this->renderTableClosure($oldTable, $data['down']);
        }

        if ($data['table_renamed']) {
            $upBody[] = "        Schema::rename({$oldTable}, {$newTable});";
            array_unshift($downBody, "        Schema::rename({$newTable}, {$oldTable});");
        }

        $upCode = $upBody === [] ? '        //' : implode("\n\n", $upBody);
        $downCode = $downBody === [] ? '        //' : implode("\n\n", $downBody);

        return <<<PHP
<?php

declare(strict_types=1);

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
{$upCode}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
{$downCode}
    }
};

PHP;
    }

    /**
     * Build the ordered list of operations
     *
     * @param  array<int, string>  $warnings
     */
    private function buildOperations(ModelSchema $oldSchema, ModelSchema $newSchema, array $diff, array $options, array &$warnings): array
    {
        $oldFields = $oldSchema->getAllFields();
        $newFields = $newSchema->getAllFields();

        $removedNames = array_keys($diff['field_changes']['removed'] ?? []);
        $addedNames = array_keys($diff['field_changes']['added'] ?? []);

        $renames = $this->resolveRenames($options['renames'] ?? [], $removedNames, $addedNames, $warnings);

        $removedNames = array_values(array_diff($removedNames, array_keys($renames)));
        $addedNames = array_values(array_diff($addedNames, array_values($renames)));

        $operations = [];

        // Foreign keys
        $this->collectForeignKeyOperations($oldSchema, $newSchema, $diff, $operations);

        // Renamed columns
        foreach ($renames as $from => $to) {
            $oldField = $oldFields[$from];
            $newField = $newFields[$to];

            $operations[] = [
                'type' => 'rename_column',
                'column' => $to,
                'from' => $from,
                'up' => $this->line("\$table->renameColumn({$this->export($from)}, {$this->export($to)});"),
                'down' => $this->line("\$table->renameColumn({$this->export($to)}, {$this->export($from)});"),
            ];

            $this->collectFieldChangeOperations($oldField, $newField, $operations, $warnings);
        }

        // Removed columns
        foreach ($removedNames as $name) {
            $field = $oldFields[$name];

            $this->collectIndexOperations($field->name, $field, null, $operations);

            $operations[] = [
                'type' => 'drop_column',
                'column' => $name,
                'definition' => $field->toArray(),
                'up' => $this->line("\$table->dropColumn({$this->export($name)});"),
                'down' => $this->line($this->columnDefinition($field).';'),
            ];

            $warnings[] = "Dropping column '{$name}' permanently deletes its data.";
        }

        // Added columns
        foreach ($addedNames as $name) {
            $field = $newFields[$name];

            $operations[] = [
                'type' => 'add_column',
                'column' => $name,
                'definition' => $field->toArray(),
                'up' => $this->line($this->columnDefinition($field).';'),
                'down' => $this->line("\$table->dropColumn({$this->export($name)});"),
            ];

            $this->collectIndexOperations($field->name, null, $field, $operations);

            if (! $field->nullable && $field->default === null) {
                $warnings[] = "Column '{$name}' is added as NOT NULL without a default value; this fails on tables that already contain rows.";
            }
        }

        // Modified columns (any column present in both versions)
        foreach (array_intersect_key($oldFields, $newFields) as $name => $oldField) {
            $this->collectFieldChangeOperations($oldField, $newFields[$name], $operations, $warnings);
        }

        // Timestamps and soft deletes
        $this->collectOptionOperations($oldSchema, $newSchema, $operations);

        return $this->sortOperations($operations);
    }

    /**
     * Keep only the renames that match a removed column and an added column
     *
     * @param  array<int, string>  $removedNames
     * @param  array<int, string>  $addedNames
     * @param  array<int, string>  $warnings
     * @return array<string, string>
     */
    private function resolveRenames(mixed $renames, array $removedNames, array $addedNames, array &$warnings): array
    {
        if (! is_array($renames)) {
            return [];
        }

        $resolved = [];

        foreach ($renames as $from => $to) {
            $from = (string) $from;

            if (! is_string($to) || $to === '') {
                $warnings[] = "Rename of column '{$from}' ignored: the new name must be a non-empty string.";

                continue;
            }

            if (! in_array($from, $removedNames, true) || ! in_array($to, $addedNames, true)) {
                $warnings[] = "Rename '{$from}' => '{$to}' ignored: '{$from}' must only exist in the old schema and '{$to}' only in the new schema.";

                continue;
            }

            if (in_array($to, $resolved, true)) {
                $warnings[] = "Rename '{$from}' => '{$to}' ignored: '{$to}' is already the target of another rename.";

                continue;
            }

            $resolved[$from] = $to;
        }

        return $resolved;
    }

    /**
     * Collect column change and index operations between two versions of the same column
     *
     * @param  array<int, string>  $warnings
     */
    private function collectFieldChangeOperations(Field $oldField, Field $newField, array &$operations, array &$warnings): void
    {
        $changes = $this->compareColumn($oldField, $newField);
        $columnChanges = array_intersect_key($changes, array_flip(self::COLUMN_ATTRIBUTES));

        if ($columnChanges !== []) {
            $previous = $this->withName($oldField, $newField->name);

            $operations[] = [
                'type' => 'modify_column',
                'column' => $newField->name,
                'changes' => $columnChanges,
                'up' => $this->line($this->columnDefinition($newField).'->change();'),
                'down' => $this->line($this->columnDefinition($previous).'->change();'),
            ];

            if (isset($columnChanges['type'])) {
                $warnings[] = "Changing the type of column '{$newField->name}' from '{$oldField->type}' to '{$newField->type}' may lose or truncate data.";
            }

            if (isset($columnChanges['nullable']) && ! $newField->nullable) {
                $warnings[] = "Column '{$newField->name}' becomes NOT NULL; existing NULL values must be filled before migrating.";
            }

            if (isset($columnChanges['length']) && $oldField->length !== null && ($newField->length === null || $newField->length < $oldField->length)) {
                $warnings[] = "Column '{$newField->name}' gets a shorter length; longer values may be truncated.";
            }
        }

        $this->collectIndexOperations($oldField->name, $oldField, $newField, $operations);
    }

    /**
     * Collect unique/index operations for a column.
     * Indexes are dropped under their original column name and added under the new one.
     */
    private function collectIndexOperations(string $oldColumn, ?Field $oldField, ?Field $newField, array &$operations): void
    {
        foreach (['unique' => 'Unique', 'index' => 'Index'] as $kind => $suffix) {
            $had = $oldField instanceof Field && $oldField->{$kind};
            $has = $newField instanceof Field && $newField->{$kind};

            if ($had && ! $has) {
                $column = $this->export($oldColumn);
                $operations[] = [
                    'type' => 'drop_'.$kind,
                    'column' => $oldColumn,
                    'up' => $this->line("\$table->drop{$suffix}([{$column}]);"),
                    'down' => $this->line("\$table->{$kind}({$column});"),
                ];
            }

            if (! $had && $has) {
                $column = $this->export($newField->name);
                $operations[] = [
                    'type' => 'add_'.$kind,
                    'column' => $newField->name,
                    'up' => $this->line("\$table->{$kind}({$column});"),
                    'down' => $this->line("\$table->drop{$suffix}([{$column}]);"),
                ];
            }
        }
    }

    /**
     * Collect foreign key operations from belongsTo relationship changes
     */
    private function collectForeignKeyOperations(ModelSchema $oldSchema, ModelSchema $newSchema, array $diff, array &$operations): void
    {
        $relationshipChanges = $diff['relationship_changes'] ?? [];

        $dropped = array_keys($relationshipChanges['removed'] ?? []);
        $added = array_keys($relationshipChanges['added'] ?? []);

        foreach (array_keys($relationshipChanges['modified'] ?? []) as $name) {
            $dropped[] = $name;
            $added[] = $name;
        }

        foreach ($dropped as $name) {
            $relationship = $oldSchema->relationships[$name] ?? null;
            if ($relationship instanceof Relationship && $relationship->type === 'belongsTo') {
                $operations[] = $this->foreignKeyOperation($relationship, false);
            }
        }

        foreach ($added as $name) {
            $relationship = $newSchema->relationships[$name] ?? null;
            if ($relationship instanceof Relationship && $relationship->type === 'belongsTo') {
                $operations[] = $this->foreignKeyOperation($relationship, true);
            }
        }
    }

    private function foreignKeyOperation(Relationship $relationship, bool $add): array
    {
        $column = $relationship->foreignKey ?? $relationship->name.'_id';
        $references = $relationship->localKey ?? 'id';
        $on = Str::snake(Str::plural(class_basename(str_replace('\\\\', '\\', $relationship->model))));

        $create = $this->line("\$table->foreign({$this->export($column)})->references({$this->export($references)})->on({$this->export($on)});");
        $drop = $this->line("\$table->dropForeign([{$this->export($column)}]);");

        return [
            'type' => $add ? 'add_foreign' : 'drop_foreign',
            'column' => $column,
            'references' => $references,
            'on' => $on,
            'relationship' => $relationship->name,
            'up' => $add ? $create : $drop,
            'down' => $add ? $drop : $create,
        ];
    }

    /**
     * Collect timestamps / soft deletes operations
     */
    private function collectOptionOperations(ModelSchema $oldSchema, ModelSchema $newSchema, array &$operations): void
    {
        $explicitTimestamps = isset($oldSchema->fields['created_at']) || isset($oldSchema->fields['updated_at'])
            || isset($newSchema->fields['created_at']) || isset($newSchema->fields['updated_at']);

        if (! $explicitTimestamps && $oldSchema->hasTimestamps() !== $newSchema->hasTimestamps()) {
            $add = $newSchema->hasTimestamps();
            $operations[] = [
                'type' => $add ? 'add_timestamps' : 'drop_timestamps',
                'column' => null,
                'up' => $this->line($add ? '$table->timestamps();' : '$table->dropTimestamps();'),
                'down' => $this->line($add ? '$table->dropTimestamps();' : '$table->timestamps();'),
            ];
        }

        $explicitSoftDeletes = isset($oldSchema->fields['deleted_at']) || isset($newSchema->fields['deleted_at']);

        if (! $explicitSoftDeletes && $oldSchema->hasSoftDeletes() !== $newSchema->hasSoftDeletes()) {
            $add = $newSchema->hasSoftDeletes();
            $operations[] = [
                'type' => $add ? 'add_soft_deletes' : 'drop_soft_deletes',
                'column' => null,
                'up' => $this->line($add ? '$table->softDeletes();' : '$table->dropSoftDeletes();'),
                'down' => $this->line($add ? '$table->dropSoftDeletes();' : '$table->softDeletes();'),
            ];
        }
    }

    /**
     * Stable sort of the operations by execution order
     */
    private function sortOperations(array $operations): array
    {
        $order = array_flip(self::OPERATION_ORDER);
        $indexed = [];

        foreach (array_values($operations) as $position => $operation) {
            $indexed[] = [$order[$operation['type']] ?? count($order), $position, $operation];
        }

        usort($indexed, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn (array $item): array => $item[2], $indexed);
    }

    /**
     * Compare the database-relevant attributes of two versions of a column
     */
    private function compareColumn(Field $oldField, Field $newField): array
    {
        $changes = [];

        foreach (['type', 'nullable', 'default', 'length', 'precision', 'scale', 'comment', 'unique', 'index'] as $attribute) {
            if ($oldField->{$attribute} !== $newField->{$attribute}) {
                $changes[$attribute] = ['old' => $oldField->{$attribute}, 'new' => $newField->{$attribute}];
            }
        }

        $oldValues = $oldField->attributes['values'] ?? null;
        $newValues = $newField->attributes['values'] ?? null;
        if ($oldValues !== $newValues && in_array($newField->type, ['enum', 'set'], true)) {
            $changes['values'] = ['old' => $oldValues, 'new' => $newValues];
        }

        return $changes;
    }

    /**
     * Build the Blueprint column definition (without trailing semicolon, without indexes)
     */
    private function columnDefinition(Field $field): string
    {
        $name = $this->export($field->name);
        $values = $field->attributes['values'] ?? null;

        $call = match ($field->type) {
            'string', 'email', 'url', 'slug', 'password' => $field->length !== null
                ? "string({$name}, {$field->length})"
                : "string({$name})",
            'char' => $field->length !== null ? "char({$name}, {$field->length})" : "char({$name})",
            'datetime', 'dateTime' => "dateTime({$name})",
            'decimal' => $this->numericCall('decimal', $name, $field->precision, $field->scale),
            'float' => $this->numericCall('float', $name, $field->precision, null),
            'enum', 'set' => is_array($values) && $values !== []
                ? "{$field->type}({$name}, {$this->export(array_values($values))})"
                : "string({$name})",
            'text', 'mediumText', 'longText', 'tinyText',
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'mediumInteger',
            'unsignedInteger', 'unsignedBigInteger', 'unsignedSmallInteger', 'unsignedTinyInteger', 'unsignedMediumInteger',
            'boolean', 'date', 'time', 'timestamp', 'timestampTz', 'year', 'double',
            'json', 'jsonb', 'uuid', 'ulid', 'binary', 'ipAddress', 'macAddress',
            'foreignId', 'foreignUuid', 'morphs', 'nullableMorphs', 'uuidMorphs',
            'geometry', 'point', 'polygon' => "{$field->type}({$name})",
            default => "string({$name})",
        };

        $definition = '$table->'.$call;

        if ($field->nullable) {
            $definition .= '->nullable()';
        }

        if ($field->default !== null) {
            $definition .= "->default({$this->export($field->default)})";
        }

        if ($field->comment !== null) {
            $definition .= "->comment({$this->export($field->comment)})";
        }

        return $definition;
    }

    private function numericCall(string $method, string $name, ?int $precision, ?int $scale): string
    {
        $arguments = [$name];

        if ($precision !== null) {
            $arguments[] = (string) $precision;

            if ($scale !== null) {
                $arguments[] = (string) $scale;
            }
        }

        return $method.'('.implode(', ', $arguments).')';
    }

    private function withName(Field $field, string $name): Field
    {
        if ($field->name === $name) {
            return $field;
        }

        return new Field(
            name: $name,
            type: $field->type,
            nullable: $field->nullable,
            unique: $field->unique,
            index: $field->index,
            default: $field->default,
            length: $field->length,
            precision: $field->precision,
            scale: $field->scale,
            rules: $field->rules,
            validation: $field->validation,
            comment: $field->comment,
            attributes: $field->attributes,
        );
    }

    private function renderTableClosure(string $table, array $lines): string
    {
        $body = array_map(fn (string $line): string => self::INDENT.$line, $lines);

        return "        Schema::table({$table}, function (Blueprint \$table) {\n"
            .implode("\n", $body)
            ."\n        });";
    }

    private function line(string $code): string
    {
        return $code;
    }

    /**
     * Export a PHP value as source code
     */
    private function export(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return var_export($value, true);
        }

        if (is_array($value)) {
            return '['.implode(', ', array_map(fn (mixed $item): string => $this->export($item), $value)).']';
        }

        return var_export(is_scalar($value) ? $value : (string) json_encode($value), true);
    }
}
