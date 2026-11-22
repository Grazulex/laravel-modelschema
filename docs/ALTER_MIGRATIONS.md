# Incremental (Alter) Migrations

The `MigrationGenerator` describes a table as a `Schema::create` migration. When a schema evolves, the
`AlterMigrationGenerator` compares the old and the new version of the schema (through the
`SchemaDiffService`) and produces the incremental `Schema::table` migration that moves an existing
database from one version to the other, including a reversible `down()`.

## Usage

```php
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

$oldSchema = ModelSchema::fromYamlFile('schemas/v1/user.schema.yml');
$newSchema = ModelSchema::fromYamlFile('schemas/v2/user.schema.yml');

$result = (new GenerationService())->generateAlterMigration($oldSchema, $newSchema, [
    'renames' => ['full_name' => 'name'], // optional: old column => new column
]);

// Ready-to-save Laravel migration file
file_put_contents(
    database_path('migrations/'.date('Y_m_d_His').'_alter_users_table.php'),
    $result['php']
);

// Insertable fragments for parent applications
$fragment = json_decode($result['json'], true)['alter_migration'];
```

The generator can also be used directly:

```php
use Grazulex\LaravelModelschema\Services\Generation\Generators\AlterMigrationGenerator;

$generator = new AlterMigrationGenerator();

$result = $generator->generate($oldSchema, $newSchema);           // metadata + json + yaml + php
$data = $generator->buildMigrationData($oldSchema, $newSchema);  // raw array
```

### Options

| Option | Type | Description |
|--------|------|-------------|
| `renames` | `array<string, string>` | Columns to rename, `old_name => new_name`. Without it, a renamed column is seen as a dropped column plus an added column. A rename is only applied when the old name exists only in the old schema and the new name only in the new schema; otherwise it is ignored and a warning is added. |
| `class_name` | `string` | Overrides the class name (default: `Alter{Table}Table`). |

## Result

`generate()` returns `metadata`, `json`, `yaml` and `php`. The JSON and YAML fragments use the
`alter_migration` key:

```json
{
  "alter_migration": {
    "table": "users",
    "old_table": "users",
    "table_renamed": false,
    "class_name": "AlterUsersTable",
    "migration_name": "alter_users_table",
    "requires_migration": true,
    "data_loss_risk": "high",
    "compatibility": "incompatible",
    "breaking_changes": [...],
    "operations": [
      {"type": "drop_unique", "column": "email"},
      {"type": "drop_column", "column": "email", "definition": {...}},
      {"type": "add_column", "column": "age", "definition": {...}}
    ],
    "up": [
      "$table->dropUnique(['email']);",
      "$table->dropColumn('email');",
      "$table->integer('age')->nullable();"
    ],
    "down": [
      "$table->dropColumn('age');",
      "$table->string('email');",
      "$table->unique('email');"
    ],
    "warnings": ["Dropping column 'email' permanently deletes its data."]
  }
}
```

`data_loss_risk`, `compatibility` and `breaking_changes` come straight from the `SchemaDiffService`.

## Supported operations

| Operation | Source of the change | `up()` | `down()` |
|-----------|----------------------|--------|----------|
| `add_column` | Field added | `$table->type('col')...` | `dropColumn` |
| `drop_column` | Field removed | `dropColumn` | Re-creates the old definition |
| `modify_column` | Type, nullable, default, length, precision, scale, comment or enum values changed | `...->change()` | Old definition with `->change()` |
| `rename_column` | `renames` option | `renameColumn` | `renameColumn` back |
| `add_unique` / `drop_unique` | `unique` flag | `unique` / `dropUnique` | Inverse |
| `add_index` / `drop_index` | `index` flag | `index` / `dropIndex` | Inverse |
| `add_foreign` / `drop_foreign` | `belongsTo` relationship added, removed or changed | `foreign(...)->references(...)->on(...)` / `dropForeign` | Inverse |
| `add_timestamps` / `drop_timestamps` | `timestamps` option | `timestamps` / `dropTimestamps` | Inverse |
| `add_soft_deletes` / `drop_soft_deletes` | `soft_deletes` option | `softDeletes` / `dropSoftDeletes` | Inverse |
| Table rename | `table` changed | `Schema::rename(old, new)` after the column changes | `Schema::rename(new, old)` before reverting the column changes |

Operations run in this order in `up()`, and in the exact reverse order in `down()`:

1. drop foreign keys
2. drop unique constraints and indexes (under the original column name)
3. rename columns
4. drop columns
5. drop timestamps / soft deletes
6. modify columns
7. add columns
8. add timestamps / soft deletes
9. add unique constraints and indexes
10. add foreign keys

Indexes are always emitted as separate operations, never inline on the column, so that
`->change()` does not try to recreate an existing index, and so that columns can be dropped
on databases that refuse to drop an indexed column.

Column changes are applied to the original table name before the table is renamed, so the
index names Laravel computes still match the ones created by the original migration.

## Warnings

The `warnings` list flags changes that need attention before migrating:

- a column is dropped (its data is lost);
- a `NOT NULL` column without a default is added (fails on tables that already contain rows);
- a column type changes (data may be lost or truncated);
- a nullable column becomes `NOT NULL` (existing `NULL` values must be filled first);
- a column length is reduced;
- a `renames` entry is invalid and was ignored.

## Notes and limitations

- `->change()` requires Laravel 11 or later, where the full column definition (nullable, default,
  comment) is re-applied. The generator always renders the complete definition.
- Column renames cannot be detected automatically. Use the `renames` option.
- Changes to `rules`, `validation` or custom `attributes` (except enum `values`) do not affect the
  database and do not produce operations.
- Index names use Laravel's naming convention (`{table}_{column}_{type}`). Indexes created with
  custom names must be adjusted by hand.
- Review the generated migration before running it, especially when `data_loss_risk` is not `none`.
- The generator does not look at the schema `version`: the migration always goes from the first
  schema to the second one. When your schemas are versioned, use `$newSchema->isNewerThan($oldSchema)`
  to check the order before generating. See [Schema Versioning](SCHEMA_VERSIONING.md).
