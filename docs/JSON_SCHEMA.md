# JSON Schema and Line Numbers

Two tools help catch mistakes in YAML schema files before they reach generation:

- a **JSON Schema export** of the YAML schema format, for autocompletion and validation in the
  editor or in CI;
- **line numbers in validation errors**, so each error points at the offending key.

## JSON Schema export

`JsonSchemaService` builds a [JSON Schema](https://json-schema.org/) (draft-07) that describes the
YAML schema files. It covers both the `core` structure (other root keys stay free for extension
data) and the flat structure.

```php
use Grazulex\LaravelModelschema\Services\JsonSchemaService;

$service = new JsonSchemaService();

$schema = $service->generate();          // array
$json = $service->toJson();              // pretty-printed JSON string
$service->exportToFile(base_path('modelschema.schema.json'));
```

The same export is available from `SchemaService`:

```php
$schemaService->exportJsonSchema($options);                     // array
$schemaService->exportJsonSchemaToFile($path, $options);        // returns $path
```

### What is described

| Key | Rules |
|-----|-------|
| `model` | string, valid PHP class name |
| `name` | string (used when `model` is missing) |
| `table` | string |
| `version` | `"major"`, `"major.minor"` or `"major.minor.patch"`, or a number |
| `fields` | required mapping with at least one field |
| `fields.*.type` | one of the field types and aliases registered in the `FieldTypeRegistry` |
| `fields.*.nullable` / `unique` / `index` | boolean |
| `fields.*.length` / `precision` / `scale` | integer |
| `fields.*.rules` / `validation` | list of strings |
| `fields.*.values` | list (enum and set types) |
| `fields.*.comment` | string |
| `relationships` / `relations` | mapping of relationships |
| `relationships.*.type` | one of the 11 supported relationship types |
| `relationships.*.model` | string |
| `options.timestamps` / `soft_deletes` | boolean |
| `options.namespace` / `table` | string |

Field and relationship definitions accept additional keys, so field type specific attributes
(`unsigned`, `format`, `size`, plugin attributes...) remain valid. Every property carries a
description shown by editors on hover.

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | `string` | none | Value of `$id`. |
| `title` | `string` | `Laravel ModelSchema` | Value of `title`. |
| `strict` | `bool` | `false` | Reject unknown keys inside the `core` section (catches typos such as `feilds`). The flat format is never strict, because its root also holds extension data. |
| `field_types` | `string[]` | `[]` | Extra field types to accept in addition to the registry. |
| `allow_unknown_field_types` | `bool` | `false` | Suggest the known field types for autocompletion without rejecting other values. |

The field types come from the `FieldTypeRegistry` at export time: custom field types discovered
from `modelschema.custom_field_types_path` are included when the export runs inside the
application. Regenerate the file after adding a field type.

### Editor setup

With the YAML language server (VS Code YAML extension, Neovim, Zed...), reference the file at the
top of a schema:

```yaml
# yaml-language-server: $schema=../modelschema.schema.json
core:
  model: Post
  fields:
    title:
      type: string
```

or map it to every schema file in `.vscode/settings.json`:

```json
{
    "yaml.schemas": {
        "./modelschema.schema.json": ["resources/schemas/*.yaml", "*.schema.yaml"]
    }
}
```

In PhpStorm, add it under *Settings → Languages & Frameworks → Schemas and DTDs → JSON Schema
Mappings*.

### CI

Export the file in a script and validate the schema files with any JSON Schema validator, or use
the line-number validation below directly in PHP:

```php
// scripts/export-json-schema.php
require __DIR__.'/../vendor/autoload.php';

(new Grazulex\LaravelModelschema\Services\JsonSchemaService())
    ->exportToFile(__DIR__.'/../modelschema.schema.json', ['strict' => true]);
```

## Line numbers in validation errors

`SchemaService::validateYamlWithLineNumbers()` validates YAML content (the `core` structure or the
flat structure) and returns each error with the line of the offending key:

```php
$errors = $schemaService->validateYamlWithLineNumbers($yamlContent);
// or
$errors = $schemaService->validateYamlFileWithLineNumbers('resources/schemas/post.yaml');

// [
//     [
//         'message' => "Unknown field type 'strng' in field 'title'",
//         'line' => 6,
//         'path' => 'core.fields.title.type',
//         'formatted' => "Line 6: Unknown field type 'strng' in field 'title'",
//     ],
// ]
```

| Key | Description |
|-----|-------------|
| `message` | The error message. Schema errors use the same messages as `validateFromCompleteAppYaml()`. |
| `line` | 1-based line of the offending key, or of its closest declared parent (for example when the key sits in an inline `{ ... }` mapping). `null` when it cannot be located. |
| `path` | Dotted path of the offending key, e.g. `core.fields.title.type`. `null` for errors about the whole document. |
| `formatted` | `Line N: message`, or the message alone when the line is unknown. |

An empty array means the schema is valid. The following are reported with their line:

- YAML syntax errors (line given by the YAML parser);
- an invalid `version`;
- structural errors that would prevent building the schema: a field or relationship that is not a
  mapping, or a known attribute with the wrong type (`length: "255"`, `nullable: "yes"`...);
- unknown field types and relationship types;
- a schema without fields;
- security issues found in keys and values (`default: "exec('ls')"`), the model name and the
  namespace.

`validateYamlFileWithLineNumbers()` throws a `SchemaException` when the file does not exist.

The existing `validateSchema()`, `validateCoreSchema()` and `validateFromCompleteAppYaml()`
methods are unchanged and still return plain message strings.

### Locating keys yourself

The `YamlLineLocator` support class exposes the key-to-line mapping:

```php
use Grazulex\LaravelModelschema\Support\YamlLineLocator;

$locator = YamlLineLocator::fromString($yamlContent);

$locator->getLine('core.fields.title.type');         // 6
$locator->getLine(['core', 'fields', 'first.name']); // segments, for keys containing a dot
$locator->getLine('core.fields.title.rules.1');      // sequence items use their index
$locator->getLine('core.fields.missing', false);     // null: exact match only
$locator->has('core.relations');                     // bool
$locator->all();                                     // ['core' => 1, 'core.model' => 2, ...]
```

It works on block-style YAML. Keys declared inside inline flow collections (`{ ... }`, `[ ... ]`)
are not indexed; lookups then return the line of the closest declared parent.
