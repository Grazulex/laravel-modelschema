# Laravel ModelSchema Stubs

This directory contains template stubs for creating model schema YAML files. These stubs are intended to be used by other packages (like Laravel Arc, TurboMaker, etc.) to generate schema files.

## Available Stubs

### basic.schema.stub
A basic model template with:
- Primary key (id)
- Name field
- Description field  
- Active status
- Timestamps

This is the default stub used by the SchemaService API methods:
- `getDefaultStub()` - Returns raw stub content
- `getProcessedDefaultStub()` - Returns processed stub with replacements
- `getDefaultCompleteYaml()` - Returns complete YAML structure ready for apps

## Generator Stubs

The `generators/` directory contains stubs used by individual generators for creating PHP files:
- Controller stubs
- Model stubs  
- Request stubs
- Resource stubs
- Migration stubs
- Route stubs

## Archived Stubs

Previously available stubs have been moved to `archive/` directory:
- `blog.schema.stub` - Blog/content model template
- `user.schema.stub` - User authentication model template  
- `ecommerce.schema.stub` - Product/e-commerce model template
- `pivot.schema.stub` - Pivot table model template
- `core-structure.schema.stub` - Core structure example

These can be restored if needed but are not actively used by the core system.

## Usage in Other Packages

### Getting Default Stub
```php
use LaravelModelschema\Services\SchemaService;

$schemaService = app(SchemaService::class);

// Get raw stub content
$stubContent = $schemaService->getDefaultStub();

// Get processed stub with your data
$processedContent = $schemaService->getProcessedDefaultStub([
    'MODEL_NAME' => 'Product',
    'TABLE_NAME' => 'products',
]);

// Get complete YAML ready for your app
$completeYaml = $schemaService->getDefaultCompleteYaml([
    'MODEL_NAME' => 'Product',
    'TABLE_NAME' => 'products',
], [
    'your_app' => [
        'specific_config' => 'value'
    ]
]);
```

### Publishing Stubs
Other packages can publish these stubs to make them available:

```php
// In your ServiceProvider
$this->publishes([
    __DIR__.'/../../vendor/grazulex/laravel-modelschema/stubs' => resource_path('stubs/your-package'),
], 'your-package-stubs');
```

### Using Stubs in Commands
```php
// In your Artisan command
protected function getStubPath(string $template): string
{
    return resource_path("stubs/your-package/{$template}.schema.stub");
}

protected function generateFromStub(string $template, array $replacements): string
{
    $stubPath = $this->getStubPath($template);
    $stub = file_get_contents($stubPath);
    
    return str_replace(array_keys($replacements), array_values($replacements), $stub);
}
```

## Stub Variables

All stubs support these replacement variables:

- `{{MODEL_NAME}}` - The model name (e.g., "BlogPost")
- `{{TABLE_NAME}}` - The table name (e.g., "blog_posts")  
- `{{MODEL_CLASS}}` - The full model class (e.g., "App\\Models\\BlogPost")
- `{{SNAKE_NAME}}` - Snake case name (e.g., "blog_post")
- `{{KEBAB_NAME}}` - Kebab case name (e.g., "blog-post")
- `{{CREATED_AT}}` - Timestamp of generation

## Extending Stubs

You can create your own stubs based on these templates:

1. Copy a base stub
2. Add your package-specific fields/relationships
3. Add your custom variables
4. Use in your package's commands

Example custom stub:
```yaml
# Custom Model Schema: {{MODEL_NAME}}
# Generated: {{CREATED_AT}}
# Package: {{PACKAGE_NAME}}

model: {{MODEL_NAME}}
table: {{TABLE_NAME}}

# Core fields (handled by laravel-modelschema)
fields:
  id:
    type: bigInteger
    nullable: false
    primary: true

# Your package-specific extensions
your_package:
  custom_field: "{{CUSTOM_VALUE}}"
  settings:
    auto_generate: true
```

## Integration with SchemaService

The core SchemaService can separate core schema data from your extensions:

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = app(SchemaService::class);

// Parse and separate concerns
$yamlData = yaml_parse($yamlContent);
$coreData = $schemaService->extractCoreSchema($yamlData);
$yourExtensions = $schemaService->extractExtensionData($yamlData);

// Handle core with ModelSchema
$schema = ModelSchema::fromArray($coreData['model'], $coreData);

// Handle your extensions separately
$yourCustomLogic = new YourPackageHandler($yourExtensions);
```
