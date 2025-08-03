# Stub API for External Applications

This document describes the API methods available for external applications to obtain and use default stubs from Laravel ModelSchema.

## Overview

Laravel ModelSchema provides a clean API for external packages to get default schema stubs without needing to manage their own template files. This ensures consistency and reduces duplication across the Laravel ecosystem.

## Available API Methods

### 1. `getDefaultStub()`

Returns the raw content of the default schema stub.

```php
use LaravelModelschema\Services\SchemaService;

$schemaService = app(SchemaService::class);
$stubContent = $schemaService->getDefaultStub();
```

**Returns:** Raw stub content with placeholders like `{{MODEL_NAME}}`, `{{TABLE_NAME}}`

**Use case:** When you need the raw template to apply your own processing logic.

### 2. `getProcessedDefaultStub(array $replacements = [])`

Returns processed stub content with placeholders replaced by your values.

```php
$processedContent = $schemaService->getProcessedDefaultStub([
    'MODEL_NAME' => 'Product',
    'TABLE_NAME' => 'products',
    'NAMESPACE' => 'App\\Models\\Catalog',
]);
```

**Parameters:**
- `MODEL_NAME` - The name of the model class
- `TABLE_NAME` - The database table name
- `NAMESPACE` - The model namespace
- `CREATED_AT` - Creation timestamp (defaults to current time)

**Returns:** Core YAML schema ready for use

**Use case:** When you need a basic YAML schema that your app can use directly or extend.

### 3. `getDefaultCompleteYaml(array $replacements = [], array $extensionData = [])`

Returns a complete YAML structure that includes both core schema and your app-specific data.

```php
$completeYaml = $schemaService->getDefaultCompleteYaml([
    'MODEL_NAME' => 'Product',
    'TABLE_NAME' => 'products',
], [
    'laravel_arc' => [
        'views' => ['index', 'show', 'create', 'edit'],
        'routes' => ['resource'],
    ],
    'turbo_maker' => [
        'api_endpoints' => true,
        'validation' => 'strict',
    ]
]);
```

**Parameters:**
- `$replacements` - Same as `getProcessedDefaultStub()`
- `$extensionData` - Your app-specific configuration data

**Returns:** Complete YAML with core data wrapped in proper structure plus your extensions

**Use case:** When you need a complete YAML file that your app can save and use immediately.

## Example Integration

### Laravel Arc Integration

```php
// In your Laravel Arc command
class MakeArcModelCommand extends Command
{
    public function handle(SchemaService $schemaService)
    {
        $yaml = $schemaService->getDefaultCompleteYaml([
            'MODEL_NAME' => $this->argument('name'),
            'TABLE_NAME' => Str::snake(Str::pluralStudly($this->argument('name'))),
        ], [
            'laravel_arc' => [
                'views' => $this->option('views') ? ['index', 'show', 'create', 'edit'] : [],
                'routes' => $this->option('routes') ? ['resource'] : [],
                'policies' => $this->option('policies') ?? false,
            ]
        ]);
        
        // Save the YAML file
        file_put_contents(
            resource_path("schemas/{$this->argument('name')}.yaml"),
            $yaml
        );
    }
}
```

### TurboMaker Integration

```php
// In your TurboMaker service
class TurboMakerSchemaGenerator
{
    public function createSchema(string $modelName, array $config): string
    {
        return $this->schemaService->getDefaultCompleteYaml([
            'MODEL_NAME' => $modelName,
            'TABLE_NAME' => Str::snake(Str::pluralStudly($modelName)),
        ], [
            'turbo_maker' => [
                'api_endpoints' => $config['api'] ?? true,
                'validation' => $config['validation'] ?? 'standard',
                'relationships' => $config['relationships'] ?? [],
            ]
        ]);
    }
}
```

## Benefits

1. **Consistency** - All apps use the same base schema structure
2. **Maintenance** - Updates to the base stub are automatically available to all apps
3. **Simplicity** - No need to manage stub files in each package
4. **Flexibility** - Apps can extend with their own data while keeping core compatibility

## Default Stub Content

The default stub includes:
- Primary key field (`id`)
- Name field (`string`, required)
- Description field (`text`, nullable)
- Active status field (`boolean`, default true)
- Timestamps (`created_at`, `updated_at`)

This provides a solid foundation that most applications can build upon.

## Backward Compatibility

This API maintains full backward compatibility with existing methods:
- `getAvailableStubs()` - Lists all available stubs
- `getStubContent(string $stubName)` - Gets specific stub content
- `processStubForCore(string $stubName, array $replacements)` - Processes any stub

The new default methods are convenience wrappers that specifically target the `basic.schema.stub` which is the most commonly used template.
