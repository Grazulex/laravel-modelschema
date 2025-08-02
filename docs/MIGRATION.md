# Migration Guide

This guide helps you migrate from previous versions of Laravel ModelSchema to the new fragment-based architecture.

## Overview of Changes

### Major Architectural Changes

#### Before (v1.x)
- Generated complete PHP files directly
- Simple YAML structure without core/extension separation
- Limited to basic model and migration generation
- Tightly coupled with specific use cases

#### After (v2.x)
- Generates insertable JSON/YAML fragments
- Clear core/extension separation in YAML structure
- 6 specialized generators (Model, Migration, Requests, Resources, Factory, Seeder)
- Designed for integration with parent applications

## Schema Structure Changes

### Old Schema Format
```yaml
model: User
table: users
fields:
  name:
    type: string
    nullable: false
  email:
    type: string
    unique: true
relations:
  posts:
    type: hasMany
    model: App\Models\Post
options:
  timestamps: true
```

### New Schema Format
```yaml
core:
  model: User
  table: users
  fields:
    name:
      type: string
      nullable: false
    email:
      type: string
      unique: true
  relations:
    posts:
      type: hasMany
      model: App\Models\Post
  options:
    timestamps: true

# Extensions can be added by parent applications
turbomaker:
  views: ['index', 'create']
  routes: ['web', 'api']
```

**Key Changes:**
1. All core schema data now lives under a `core:` key
2. Extension data can be added at the root level
3. This enables clean separation between core logic and app-specific features

## API Changes

### Old API (v1.x)
```php
use Grazulex\ModelSchema\ModelSchema;

// Parse schema
$schema = ModelSchema::fromYamlFile('user.yaml');

// Generate files
$modelContent = $schema->generateModel();
$migrationContent = $schema->generateMigration();

// Save files directly
file_put_contents('User.php', $modelContent);
file_put_contents('create_users_table.php', $migrationContent);
```

### New API (v2.x)
```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Parse and validate schema
$result = $schemaService->parseAndSeparateSchema($yamlContent);
$errors = $schemaService->validateCoreSchema($yamlContent);

// Generate insertable fragments
$data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);

// Extract fragments for integration
$modelFragment = json_decode($data['generation_data']['model']['json'], true);
$migrationFragment = json_decode($data['generation_data']['migration']['json'], true);

// Parent app integrates fragments into its own templates
```

## Migration Steps

### Step 1: Update Schema Files

Convert your existing YAML schemas to the new format:

#### Before
```yaml
model: Product
table: products
fields:
  name:
    type: string
```

#### After
```yaml
core:
  model: Product
  table: products
  fields:
    name:
      type: string
```

**Migration Script:**
```php
function migrateSchemaFile($oldYamlFile) {
    $content = file_get_contents($oldYamlFile);
    $data = yaml_parse($content);
    
    $newData = ['core' => $data];
    
    $newContent = yaml_emit($newData);
    file_put_contents($oldYamlFile, $newContent);
}
```

### Step 2: Update Code Usage

#### Replace Direct File Generation
```php
// OLD - Direct generation
$schema = ModelSchema::fromYamlFile('user.yaml');
$modelContent = $schema->generateModel();
file_put_contents('User.php', $modelContent);
```

```php
// NEW - Fragment-based integration
$schemaService = new SchemaService();
$yamlContent = file_get_contents('user.yaml');

$data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
$modelFragment = json_decode($data['generation_data']['model']['json'], true);

// Integrate fragment into your own template
$modelContent = view('my-app.model-template', $modelFragment)->render();
file_put_contents('User.php', $modelContent);
```

#### Replace Simple Parsing
```php
// OLD - Direct schema access
$schema = ModelSchema::fromYamlFile('user.yaml');
$fields = $schema->fields();
$relations = $schema->relations();
```

```php
// NEW - Structured data extraction
$schemaService = new SchemaService();
$yamlContent = file_get_contents('user.yaml');

$coreData = $schemaService->extractCoreContentForGeneration($yamlContent);
$fields = $coreData['fields'];
$relations = $coreData['relations'];
```

### Step 3: Update Dependencies

#### Composer Changes
```json
{
  "require": {
    "grazulex/laravel-modelschema": "^2.0",
    "symfony/yaml": "^7.3"
  }
}
```

The new version uses Symfony YAML instead of the PHP YAML extension.

### Step 4: Update Service Integration

If you were using the old ModelSchema in a service provider:

#### Before
```php
// In your service provider
$this->app->singleton(ModelSchemaService::class, function ($app) {
    return new ModelSchemaService();
});
```

#### After
```php
// In your service provider
$this->app->singleton(SchemaService::class, function ($app) {
    return new SchemaService();
});

$this->app->singleton(GenerationService::class, function ($app) {
    return new GenerationService();
});
```

## New Features Available

### 1. Multiple Generators
You now have access to 6 different generators:
- Model
- Migration
- Requests (Store/Update)
- Resources (Single/Collection)
- Factory
- Seeder

### 2. Fragment-Based Architecture
Generate insertable fragments instead of complete files:
```php
$fragments = $generationService->generateAll($schema);
// Returns fragments for all generators
```

### 3. Core/Extension Separation
Cleanly separate core schema logic from application-specific extensions:
```php
$result = $schemaService->parseAndSeparateSchema($yamlContent);
$coreData = $result['core'];
$extensionData = $result['extensions'];
```

### 4. Enhanced Validation
More robust validation with detailed error reporting:
```php
$errors = $schemaService->validateCoreSchema($yamlContent);
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Validation error: {$error}\n";
    }
}
```

### 5. Stub System
Generate complete YAML from stubs with replacements:
```php
$completeYaml = $schemaService->generateCompleteYamlFromStub(
    'user.schema.stub',
    ['MODEL_NAME' => 'User'],
    $extensionData
);
```

## Breaking Changes

### 1. Namespace Changes
- Old: `Grazulex\ModelSchema\*`
- New: `Grazulex\LaravelModelschema\*`

### 2. Class Names
- Old: `ModelSchema`
- New: `SchemaService`, `GenerationService`

### 3. Method Names
- Old: `generateModel()`, `generateMigration()`
- New: `getGenerationDataFromCompleteYaml()`, fragment extraction

### 4. Output Format
- Old: Complete PHP file content
- New: JSON/YAML fragments for integration

### 5. YAML Structure
- Old: Flat structure
- New: `core` section required

## Common Migration Issues

### Issue 1: Missing Core Section
**Error:** `Core section not found in YAML`

**Solution:** Wrap existing schema in `core:` section
```yaml
# Add this wrapper
core:
  # Your existing schema here
  model: User
  table: users
  # ...
```

### Issue 2: Direct File Generation
**Error:** Method `generateModel()` not found

**Solution:** Use fragment-based approach
```php
// Replace direct generation
$modelContent = $schema->generateModel();

// With fragment integration
$data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
$fragment = json_decode($data['generation_data']['model']['json'], true);
```

### Issue 3: YAML Extension Dependency
**Error:** YAML extension not available

**Solution:** The new version uses Symfony YAML (no extension required)
```bash
# No need to install YAML extension
composer require symfony/yaml
```

## Testing Your Migration

### 1. Schema Validation
```php
$errors = $schemaService->validateCoreSchema($yamlContent);
if (empty($errors)) {
    echo "Migration successful!\n";
} else {
    echo "Migration issues found:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
}
```

### 2. Fragment Generation
```php
try {
    $data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
    echo "All generators working: " . count($data['generation_data']) . " fragments generated\n";
} catch (Exception $e) {
    echo "Fragment generation failed: " . $e->getMessage() . "\n";
}
```

### 3. Integration Test
```php
// Test complete workflow
$completeYaml = $schemaService->generateCompleteYamlFromStub(
    'basic.schema.stub',
    ['MODEL_NAME' => 'TestModel'],
    []
);

$data = $schemaService->getGenerationDataFromCompleteYaml($completeYaml);
$modelFragment = json_decode($data['generation_data']['model']['json'], true);

assert($modelFragment['model']['class_name'] === 'TestModel');
echo "Integration test passed!\n";
```

## Getting Help

If you encounter issues during migration:

1. **Check the Examples**: Review the new examples in the `examples/` directory
2. **Validate Your Schema**: Use the validation methods to identify issues
3. **Review the Architecture Guide**: Understand the new fragment-based approach
4. **Test Incrementally**: Migrate one schema at a time
5. **Create Issues**: Report migration problems on GitHub

## Benefits After Migration

1. **Cleaner Architecture**: Better separation of concerns
2. **More Generators**: Access to 6 different generators
3. **Better Integration**: Designed for parent application integration
4. **Enhanced Validation**: More robust error handling
5. **Future-Proof**: Extensible design for new features
