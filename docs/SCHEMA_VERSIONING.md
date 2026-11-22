# Schema Versioning

A schema can declare its own version with the `version` key. The version is validated and
normalized when the schema is read, and is available on the `ModelSchema` object so that two
revisions of the same schema can be ordered, compared and turned into an incremental migration.

Versioning is optional: a schema without a `version` key behaves exactly as before.

## Declaring a version

With the `core` structure, the version belongs to the `core` section:

```yaml
core:
  model: User
  version: "1.2.0"
  table: users
  fields:
    name:
      type: string
```

With the flat structure, it is a root key:

```yaml
model: User
version: "1.2.0"
table: users
fields:
  name:
    type: string
```

## Accepted formats

A version is made of one to three numeric segments: `major`, `major.minor` or
`major.minor.patch`. It is always normalized to `major.minor.patch`.

| YAML value | Stored version |
|------------|----------------|
| `1` | `1.0.0` |
| `1.2` | `1.2.0` |
| `"1.2"` | `1.2.0` |
| `1.2.3` | `1.2.3` |
| `"1.10"` | `1.10.0` |

Rejected values include prefixes and suffixes (`v1.0.0`, `1.0.0-beta`), more than three segments
(`1.2.3.4`), leading zeros (`01.2.0`), negative numbers, empty strings, booleans and lists.

> **Quote your versions.** YAML reads an unquoted `1.10` as the number `1.1`, which would be
> stored as `1.1.0`. Writing `"1.10"` (or a three-segment version such as `1.10.0`) avoids the
> problem.

An empty value (`version:` or `version: null`) is treated as "no version".

## Validation on read

The version is checked every time a schema is built:

- `ModelSchema::fromArray()`, `ModelSchema::fromYaml()` and `ModelSchema::fromYamlFile()`;
- the `ModelSchema` constructor (`new ModelSchema(name: 'User', table: 'users', version: '1.2')`);
- `SchemaService::parseYamlFile()`, `parseYamlContent()`, `parseAndSeparateSchema()` and the
  methods built on top of them.

An invalid version throws a `Grazulex\LaravelModelschema\Exceptions\SchemaException`:

```php
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Schema\ModelSchema;

try {
    $schema = ModelSchema::fromYamlFile('schemas/user.schema.yml');
} catch (SchemaException $e) {
    // Invalid schema version 'v2': expected 'major', 'major.minor' or 'major.minor.patch' ...
}
```

`SchemaService::validateFromCompleteAppYaml()` returns errors instead of throwing, so an invalid
version is reported as an entry of the returned error list.

To check a value without building a schema:

```php
ModelSchema::isValidVersion('1.2');      // true
ModelSchema::isValidVersion('latest');   // false
ModelSchema::normalizeVersion('1.2');    // '1.2.0'
ModelSchema::normalizeVersion(null);     // null
ModelSchema::normalizeVersion('latest'); // throws SchemaException
```

## Working with versions

```php
$schema = ModelSchema::fromYamlFile('schemas/user.schema.yml');

$schema->version;       // '1.2.0', or null for an unversioned schema
$schema->hasVersion();  // true

$schema->compareVersion($otherSchema); // -1, 0 or 1
$schema->compareVersion('1.5');        // also accepts a version number
$schema->isNewerThan($otherSchema);    // bool
$schema->isOlderThan('2.0.0');         // bool
```

In comparisons, an unversioned schema counts as `0.0.0`, so any versioned schema with a version
above `0.0.0` is newer than an unversioned one.

The version is exported by `toArray()`, `toJson()` and `SchemaService::convertSchemaToYaml()` /
`saveSchemaToYaml()` as a `version` key. Unversioned schemas do not export the key, so their
output is unchanged.

## Version changes in the schema diff

`SchemaDiffService::compareSchemas()` reports a version change in `schema_changes`:

```php
$diff = $schemaService->compareSchemas($oldSchema, $newSchema);

$diff['schema_changes']['version'];
// ['old' => '1.2.0', 'new' => '1.3.0', 'type' => 'upgraded']
```

`type` is `upgraded`, `downgraded`, `added` (the old schema had no version) or `removed` (the new
schema has no version). The entry is absent when both versions are equal. A version change is
informational: it is never a breaking change and does not produce a migration operation by itself.

## Versions and incremental migrations

Versions make the direction of an incremental migration explicit. Before generating an alter
migration, check that the schemas are passed in the right order:

```php
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

$oldSchema = ModelSchema::fromYamlFile('schemas/v1/user.schema.yml'); // version: "1.0.0"
$newSchema = ModelSchema::fromYamlFile('schemas/v2/user.schema.yml'); // version: "1.1.0"

if (! $newSchema->isNewerThan($oldSchema)) {
    throw new RuntimeException('The target schema must have a higher version than the current one.');
}

$result = (new GenerationService())->generateAlterMigration($oldSchema, $newSchema);
```

See [Incremental (Alter) Migrations](ALTER_MIGRATIONS.md).

## Compatibility notes

- `version` is now a core schema key (`SchemaService::getCoreSchemaKeys()`). In the flat
  structure, a root `version` key is therefore read as the schema version and is no longer part
  of the extension data. With the `core` structure, a root `version` key outside `core` is still
  extension data and is not validated; only `core.version` is the schema version.
- `metadata.version` is left untouched. It stays free-form metadata and is not interpreted as the
  schema version.
- Parsed schemas cached by a previous release of the package are ignored and parsed again, so no
  cache clearing is needed when upgrading.
