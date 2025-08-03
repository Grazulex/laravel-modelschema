# Architecture Guide

This guide explains the architecture of Laravel ModelSchema and how it's designed to integrate with parent applications like TurboMaker and Arc.

## Core Principles

### 1. Separation of Concerns
- **Core Schema Logic**: Handled by ModelSchema package
- **Application Generation**: Handled by parent applications
- **Fragment Production**: Clean insertable data structures

### 2. Fragment-Based Architecture
Instead of generating complete files, ModelSchema produces **insertable fragments** that parent applications can integrate into their own templates.

### 3. Core/Extension Separation
The package uses a "core" structure in YAML to clearly separate core schema data from application-specific extensions.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    Parent Application                       │
│                 (TurboMaker, Arc, etc.)                     │
├─────────────────────────────────────────────────────────────┤
│ • Generates complete YAML from stubs                       │
│ • Validates schemas using ModelSchema                      │
│ • Extracts generation fragments                            │
│ • Integrates fragments into app-specific templates         │
│ • Produces final PHP files                                 │
└─────────────────────────────────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│               Laravel ModelSchema Package                   │
├─────────────────────────────────────────────────────────────┤
│ SchemaService (Core API)                                   │
│ ├─ parseAndSeparateSchema()                                │
│ ├─ validateCoreSchema()                                    │
│ ├─ extractCoreContentForGeneration()                       │
│ ├─ generateCompleteYamlFromStub()                          │
│ └─ getGenerationDataFromCompleteYaml()                     │
│                                                             │
│ GenerationService (Fragment Coordinator)                   │
│ ├─ generateAll()                                           │
│ ├─ generateSingle()                                        │
│ └─ getAvailableGenerators()                               │
│                                                             │
│ 8 Specialized Generators                                   │
│ ├─ ModelGenerator                                          │
│ ├─ MigrationGenerator                                      │
│ ├─ RequestGenerator                                        │
│ ├─ ResourceGenerator                                       │
│ ├─ FactoryGenerator                                        │
│ ├─ SeederGenerator                                         │
│ ├─ ControllerGenerator                                     │
│ ├─ TestGenerator                                           │
│ └─ PolicyGenerator                                         │
│                                                             │
│ Field Type Plugin System                                   │
│ ├─ FieldTypePluginManager                                  │
│ ├─ FieldTypePlugin (Base Class)                           │
│ ├─ Custom Attributes System ✨                           │
│ └─ Custom Plugin Discovery                                 │
└─────────────────────────────────────────────────────────────┘
```

## Service Layer

### SchemaService
The main API service providing schema parsing, validation, and integration methods.

**Key Methods:**
- `parseAndSeparateSchema()` - Separates core schema from extensions
- `validateCoreSchema()` - Validates only the core section
- `extractCoreContentForGeneration()` - Extracts structured data for generators
- `generateCompleteYamlFromStub()` - Generates complete YAML from stub + extensions
- `getGenerationDataFromCompleteYaml()` - Extracts all generation fragments

### GenerationService
Coordinates all generators to produce insertable fragments.

**Key Methods:**
- `generateAll()` - Generates fragments for all generators
- `generateSingle()` - Generates fragment for specific generator
- `getAvailableGenerators()` - Lists available generators

## Generator System

Each generator produces insertable fragments in both JSON and YAML formats:

### Generator Interface
```php
interface GeneratorInterface
{
    public function getGeneratorName(): string;
    public function getAvailableFormats(): array;
    public function generate(ModelSchema $schema, array $options = []): array;
}
```

### AbstractGenerator
Base class providing common functionality:
- Stub loading and processing
- Format validation
- Metadata generation
- Error handling

### Specialized Generators

1. **ModelGenerator** - Eloquent model fragments
2. **MigrationGenerator** - Database migration fragments  
3. **RequestGenerator** - Form request validation fragments
4. **ResourceGenerator** - API resource transformation fragments
5. **FactoryGenerator** - Model factory fragments
6. **SeederGenerator** - Database seeder fragments
7. **ControllerGenerator** - API and web controller fragments
8. **TestGenerator** - Feature and unit test fragments  
9. **PolicyGenerator** - Authorization policy fragments

## Schema Structure

### Core Schema Format
```yaml
core:
  model: User
  table: users
  fields:
    name:
      type: string
      nullable: false
      rules: ['required', 'string', 'max:255']
    email:
      type: string
      unique: true
      rules: ['required', 'email', 'unique:users']
  relations:
    posts:
      type: hasMany
      model: App\Models\Post
  options:
    timestamps: true
    soft_deletes: false
```

### Extension Format
```yaml
# Extensions added by parent applications
turbomaker:
  views: ['index', 'create', 'edit', 'show']
  routes: ['web', 'api']
  controllers: ['UserController', 'ApiUserController']

arc:
  permissions: ['view', 'create', 'edit', 'delete']
  roles: ['admin', 'user']
  middleware: ['auth', 'verified']
```

## Fragment Output

### Fragment Structure
Each generator produces fragments with this pattern:
```json
{
  "generator_name": {
    "key1": "value1",
    "key2": "value2",
    "nested": {
      "data": "structure"
    }
  }
}
```

### Example Model Fragment
```json
{
  "model": {
    "class_name": "User",
    "table": "users",
    "namespace": "App\\Models",
    "fillable": ["name", "email"],
    "casts": {
      "email_verified_at": "timestamp"
    },
    "relations": {
      "posts": {
        "type": "hasMany",
        "model": "App\\Models\\Post"
      }
    },
    "options": {
      "timestamps": true,
      "soft_deletes": false
    }
  }
}
```

## Integration Workflow

### 1. Parent App Preparation
```php
// Parent app defines extension data
$extensionData = [
    'turbomaker' => [
        'views' => ['index', 'create', 'edit'],
        'routes' => ['web', 'api']
    ]
];
```

### 2. Complete YAML Generation
```php
// Generate complete YAML from stub
$completeYaml = $schemaService->generateCompleteYamlFromStub(
    'user.schema.stub',
    ['MODEL_NAME' => 'User', 'TABLE_NAME' => 'users'],
    $extensionData
);
```

### 3. Validation
```php
// Validate the complete YAML (focuses on core)
$errors = $schemaService->validateFromCompleteAppYaml($completeYaml);
if (!empty($errors)) {
    throw new ValidationException($errors);
}
```

### 4. Fragment Extraction
```php
// Extract all generation fragments
$data = $schemaService->getGenerationDataFromCompleteYaml($completeYaml);

// Access individual fragments
$modelFragment = json_decode($data['generation_data']['model']['json'], true);
$migrationFragment = json_decode($data['generation_data']['migration']['json'], true);
```

### 5. Parent App Integration
```php
// Parent app uses fragments in its templates
$parentAppGenerator->generateModelFile($modelFragment);
$parentAppGenerator->generateMigrationFile($migrationFragment);
// ... etc for all generators
```

## Field Type Plugin System

The package features an extensible plugin system for creating custom field types beyond the built-in ones.

### Plugin Architecture

```
FieldTypePluginManager
├─ Plugin Registration
├─ Auto-Discovery
├─ Dependency Management
└─ Metadata Handling

FieldTypePlugin (Abstract Base)
├─ Configuration Validation
├─ Value Transformation  
├─ Migration Generation
└─ Validation Rules
```

### Plugin Manager

The `FieldTypePluginManager` handles the complete lifecycle of field type plugins:

- **Registration**: Manual and automatic plugin registration
- **Discovery**: Auto-discovery using configurable patterns
- **Validation**: Plugin dependency and configuration validation
- **Metadata**: Plugin metadata management and caching

### Creating Plugins

Custom field type plugins extend the `FieldTypePlugin` abstract class:

```php
use Grazulex\LaravelModelschema\Support\FieldTypePlugin;

class CustomFieldTypePlugin extends FieldTypePlugin
{
    public function getType(): string
    {
        return 'custom_type';
    }

    public function validateConfig(array $config): array
    {
        // Custom validation logic
        return $errors;
    }

    public function transformValue($value, array $config)
    {
        // Value transformation logic
        return $transformedValue;
    }
}
```

### Integration with Core System

Plugins integrate seamlessly with the existing field type system:

1. **Registration**: Plugins are registered with the `FieldTypeRegistry`
2. **Discovery**: Automatic discovery using namespace patterns
3. **Validation**: Full integration with schema validation
4. **Generation**: Automatic inclusion in all generators

### Custom Attributes System ✨

The plugin system now supports sophisticated custom attributes beyond Laravel's standard field attributes:

#### Key Features
- **Type Validation**: Strict type checking (string, int, boolean, array, etc.)
- **Constraints**: Min/max values, required fields, enum restrictions
- **Default Values**: Automatic application of default values
- **Custom Validators**: Callback functions for complex validation logic
- **Integration**: Seamless merge with Laravel standard attributes

#### Example Implementation
```php
class UrlFieldTypePlugin extends FieldTypePlugin
{
    public function __construct()
    {
        $this->customAttributes = ['schemes', 'verify_ssl', 'timeout'];
        
        $this->customAttributeConfig = [
            'schemes' => [
                'type' => 'array',
                'enum' => ['http', 'https', 'ftp'],
                'default' => ['http', 'https']
            ],
            'timeout' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 300,
                'default' => 30
            ]
        ];
    }
}
```

#### Usage in Schemas
```yaml
fields:
  website:
    type: url
    schemes: ['https']
    verify_ssl: true
    timeout: 45
```

**See [Field Type Plugins Documentation](FIELD_TYPE_PLUGINS.md) for complete implementation guide.**

## Stub System

### Schema Stubs
Base schema templates for different use cases:
- `basic.schema.stub` - Basic model schema
- `blog.schema.stub` - Blog-related schemas
- `user.schema.stub` - User management schemas

### Generator Stubs
Template fragments for each generator:
- `generators/model.json.stub`
- `generators/migration.yaml.stub`
- `generators/requests.json.stub`
- etc.

## Extension Points

### Custom Field Types
```php
class CustomFieldType extends AbstractFieldType
{
    public function getType(): string
    {
        return 'custom';
    }

    public function getMigrationMethod(): string
    {
        return 'string';
    }

    public function getCastType(): ?string
    {
        return 'string';
    }
}
```

### Custom Generators
```php
class CustomGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'custom';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml'];
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        // Implementation
    }
}
```

## Testing Strategy

### Unit Tests
- Individual service methods
- Generator output validation
- Schema parsing and validation
- Error handling

### Integration Tests
- Complete workflow testing
- Fragment integration
- Parent app simulation
- Performance testing

### Test Coverage
- 151 tests with 743 assertions
- Full coverage of all public APIs
- Error scenarios and edge cases
- Cross-generator consistency

## Performance Considerations

### Caching
- Parsed schemas can be cached
- Generated fragments are stateless
- Validation results are cacheable

### Memory Management
- Lazy loading of generators
- Efficient YAML parsing with Symfony YAML
- Minimal object creation

### Scalability
- Stateless design allows horizontal scaling
- Fragment-based architecture reduces memory footprint
- Async processing compatibility

## Security Considerations

### Input Validation
- Strict YAML schema validation
- SQL injection prevention in migration fragments
- XSS prevention in resource fragments

### File System Security
- Stub file validation
- Path traversal prevention
- Temporary file cleanup

### Data Sanitization
- Field name sanitization
- Class name validation
- Namespace validation

## Future Enhancements

### Planned Features
- Additional generators (Controller, Test, etc.)
- Enhanced validation rules
- Custom stub locations
- Plugin system for generators

### Extension Possibilities
- GraphQL schema generation
- OpenAPI specification generation
- Database reverse engineering
- Visual schema editor integration
