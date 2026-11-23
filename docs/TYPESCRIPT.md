# TypeScript Types

The `TypeScriptGenerator` turns the fields and relationships of a schema into a TypeScript
`interface` (or `type` alias). The generated type describes the JSON shape of the serialized
Eloquent model, so it can be used for Inertia page props, Vue or React components and API clients.

## Usage

```php
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

$schema = ModelSchema::fromYamlFile('schemas/post.schema.yml');

$result = (new GenerationService())->generateTypeScript($schema);

// Ready-to-save TypeScript file
file_put_contents(resource_path('js/types/Post.ts'), $result['ts']);

// Insertable fragments for parent applications
$fragment = json_decode($result['json'], true)['typescript'];
```

For this schema:

```yaml
core:
  model: Post
  table: posts
  fields:
    title:
      type: string
      comment: Public title
    body:
      type: text
      nullable: true
    status:
      type: enum
      attributes:
        values: [draft, published]
    published_at:
      type: datetime
      nullable: true
  relations:
    author:
      type: belongsTo
      model: App\Models\User
    comments:
      type: hasMany
      model: App\Models\Comment
```

`$result['ts']` contains:

```ts
import type { Comment } from './Comment';
import type { User } from './User';

export interface Post {
    id: number;
    /** Public title */
    title: string;
    body: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    /** Foreign key for author relationship */
    author_id: number | null;
    created_at: string | null;
    updated_at: string | null;
    /** belongsTo relationship */
    author?: User | null;
    /** hasMany relationship */
    comments?: Comment[];
}
```

The generator is also reachable through the generic entry points of the `GenerationService`:

```php
$generationService->generate($schema, 'typescript');
$generationService->generateMultiple($schema, ['model', 'typescript'], [
    'typescript' => ['style' => 'type'],   // options of one generator
]);
$generationService->generateAll($schema, ['model' => true, 'typescript' => true]);
$generationService->getGenerator('typescript');
```

It can be used directly as well:

```php
use Grazulex\LaravelModelschema\Services\Generation\Generators\TypeScriptGenerator;

$generator = new TypeScriptGenerator();

$result = $generator->generate($schema);        // metadata + json + yaml + ts
$data = $generator->buildTypeData($schema);     // raw array
$type = $generator->resolveFieldType($field);   // TypeScript type of one field
```

The generator is opt-in: `generateAll()` only runs it when the `typescript` option is `true`.
`getAvailableGenerators()`, `getAvailableGeneratorNames()`, `getGeneratorInstances()` and
`getGeneratorInfo()` keep listing the 13 PHP fragment generators only.

## Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `style` | `string` | `'interface'` | `'interface'` renders `export interface X { ... }`, `'type'` renders `export type X = { ... };`. Any other value throws an `InvalidArgumentException`. |
| `type_name` | `string` | model name | Name of the generated type. Self-referencing relationships follow it. |
| `relations` | `bool` | `true` | Add the relationships as optional properties. |
| `imports` | `bool` | `true` | Emit `import type` statements for the related types. |
| `import_path` | `string` | `'./'` | Directory of the related type files, e.g. `'@/types/'`. |
| `implicit_columns` | `bool` | `true` | Add the columns the schema implies without declaring them: `id`, `created_at` / `updated_at` (when timestamps are enabled) and `deleted_at` (when soft deletes are enabled). |
| `exclude` | `string[]` | `[]` | Property names to leave out, e.g. `['password', 'remember_token']`. |
| `type_map` | `array<string, string>` | `[]` | Overrides the TypeScript type of a field type, e.g. `['decimal' => 'number', 'date' => 'Date']`. |

## Result

`generate()` returns `metadata`, `json`, `yaml` and `ts`. The JSON and YAML fragments use the
`typescript` key:

```json
{
  "typescript": {
    "name": "Post",
    "style": "interface",
    "file_name": "Post.ts",
    "model_class": "App\\Models\\Post",
    "properties": [
      {"name": "id", "type": "number", "optional": false, "nullable": false, "source": "field", "comment": null},
      {"name": "body", "type": "string | null", "optional": false, "nullable": true, "source": "field", "comment": null},
      {"name": "comments", "type": "Comment[]", "optional": true, "nullable": false, "source": "relation", "comment": "hasMany relationship"}
    ],
    "imports": [
      {"name": "Comment", "path": "./Comment"}
    ],
    "code": "import type { Comment } from './Comment';\n\nexport interface Post {\n ..."
  }
}
```

`source` is `field` for columns and `relation` for relationships. `code` is the same source as the
`ts` output.

## Type mapping

Field types are resolved through the `FieldTypeRegistry`, so aliases (`varchar`, `bigint`, `bool`,
`jsonb`, `fk`, ...) map like their base type.

| Field type | TypeScript type |
|------------|-----------------|
| `string`, `text`, `mediumText`, `longText`, `email`, `uuid`, `binary` | `string` |
| `integer`, `bigInteger`, `tinyInteger`, `smallInteger`, `mediumInteger`, `unsignedBigInteger`, `foreignId` | `number` |
| `float`, `double` | `number` |
| `decimal` | `string` |
| `boolean` | `boolean` |
| `date`, `datetime`, `time`, `timestamp` | `string` |
| `json` | `Record<string, unknown> \| unknown[]` |
| `enum` | Union of the `values` (`'draft' \| 'published'`), `string` without values |
| `set` | Array of the `values` union (`('web' \| 'mail')[]`), `string[]` without values |
| `morphs` | Two properties: `{name}_id: number` and `{name}_type: string` |
| `geometry`, `polygon` | `string` |
| `point`, unregistered types | `unknown` |

Nullable fields get `| null`.

Notes:

- `decimal` maps to `string` because Laravel serializes decimal columns as strings to keep their
  precision. Use `'type_map' => ['decimal' => 'number']` when your API returns numbers.
- Dates map to `string` because they are serialized as ISO 8601 strings.
- Enum and set values are read from the `values` attribute of the field (`options` is accepted
  too).

The type of a field is resolved in this order:

1. the `type_map` option (by the type written in the schema, then by its base type);
2. the `getTypeScriptType()` method of the field type, when it has one;
3. the `values` of an `enum` or `set` field;
4. the built-in mapping above;
5. the Eloquent cast of the field type (`getCastType()`);
6. `unknown`.

### Custom field types

Custom field types and plugins work without any change: their cast type is mapped
(`integer` → `number`, `boolean` → `boolean`, `array` → `Record<string, unknown> | unknown[]`,
`string` → `string`, ...). To control the result, add an optional `getTypeScriptType()` method to
the field type:

```php
final class MoneyFieldType extends AbstractFieldType
{
    // ...

    public function getTypeScriptType(array $config): string
    {
        return '{ amount: number; currency: string }';
    }
}
```

The method receives the field configuration (including its custom attributes). It is not part of
`FieldTypeInterface`, so existing field types do not have to implement it.

## Relationships

Relationships are optional properties (`name?:`) because Eloquent only serializes them when they
are loaded. They use the snake_case name Eloquent gives them in arrays and JSON
(`featuredImage` becomes `featured_image`).

| Relationship | TypeScript type |
|--------------|-----------------|
| `belongsTo`, `hasOne`, `morphOne`, `hasOneThrough` | `Related \| null` |
| `hasMany`, `belongsToMany`, `morphMany`, `hasManyThrough`, `morphToMany`, `morphedByMany` | `Related[]` |
| `morphTo`, other types | `unknown` |

The related type is named after the class name of the related model (`App\Models\User` becomes
`User`) and imported from `{import_path}{Name}`. A relationship to the model itself is not imported.
The foreign key of a `belongsTo` relationship is added as `number | null` when the schema does not
declare it.

## Notes and limitations

- The type describes the model as Eloquent serializes it by default. Hidden attributes, appended
  accessors and API Resources that reshape the payload are not known to the schema: use `exclude`
  or extend the generated type by hand.
- The implied `id` is typed as `number`. Declare the `id` field in the schema (for example as
  `uuid`) to change it.
- Pivot data of `belongsToMany` relationships is not described.
- Property names that are not valid identifiers are quoted.
