# Laravel ModelSchema

<img src="new_logo.png" alt="Laravel ModelSchema" width="200">

A foundational Laravel package for schema-driven development. Parse YAML schemas, generate insertable fragments for models, migrations, requests, resources, factories, seeders, controllers, tests, policies, observers, services, actions, and validation rules. Built to power Laravel TurboMaker, Arc, and other schema-based packages.

[![Latest Version](https://img.shi### Field Types & Extensions  
- **📋 [Field Types Guid### Core Documentation
- **🏗️ [Architecture Guide](docs/ARCHITECTURE.md)** - Understanding the package structure and design
- **✨ [New Generators Guide](docs/NEW_GENERATORS.md)** - Observer, Service, Action, Rule generators (v2.0)
- **📈 [Migration Guide](docs/MIGRATION.md)** - Upgrading from previous versions
- **📊 [Fragment Examples](examples/FRAGMENTS.md)** - Understanding generated fragmentsocs/FIELD_TYPES.md)** - Complete field types reference
- **🔌 [Field Type Plugins](docs/FIELD_TYPE_PLUGINS.md)** - Creating custom field type plugins
- **✨ [Custom Attributes Examples](examples/CUSTOM_ATTRIBUTES.md)** - Practical examples of custom attributes usage
- **✅ [Custom Field Validation](docs/CUSTOM_FIELD_TYPES_VALIDATION.md)** - Validating custom field types.io/packagist/v/grazulex/laravel-modelschema.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-modelschema)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-modelschema.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-modelschema)
[![License](https://img.shields.io/github/license/grazulex/laravel-modelschema.svg?style=flat-square)](https://github.com/Grazulex/laravel-modelschema/blob/main/LICENSE.md)
[![PHP Version](https://img.shields.io/packagist/php-v/grazulex/laravel-modelschema.svg?style=flat-square)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-ff2d20?style=flat-square&logo=laravel)](https://laravel.com/)
[![Tests](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-modelschema/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Grazulex/laravel-modelschema/actions)
[![Code Style](https://img.shields.io/badge/code%20style-pint-000000?style=flat-square&logo=laravel)](https://github.com/laravel/pint)

## Overview

Laravel ModelSchema provides **schema parsing, validation, and fragment generation** for Laravel applications. Instead of generating complete files, it produces **insertable JSON/YAML fragments** that parent applications can integrate into their own generation workflows.

**🎯 Core Purpose**: Enable schema-driven development with clean separation between core schema logic and application-specific generation.

### 🚀 Key Features

- **🔍 Schema Parsing & Validation** - Parse YAML schemas with core/extension separation
- **🧩 Fragment Generation** - Generate insertable JSON/YAML fragments for Laravel artifacts  
- **🏗️ Clean Architecture** - Separate core schema responsibilities from app-specific generation
- **🔄 Multi-Generator Support** - Models, Migrations, Requests, Resources, Factories, Seeders, Controllers, Tests, Policies, Observers, Services, Actions, Rules
- **📈 Schema Analysis** - Advanced schema comparison, optimization, and performance analysis
- **🔌 Plugin System** - Extensible field type plugins for custom functionality
- **📊 Integration API** - Complete workflow for external packages (TurboMaker, Arc, etc.)
- **✨ Extensible Design** - Custom field types, generators, and validation rules

## � Installation

```bash
composer require grazulex/laravel-modelschema
```

## 🏗️ Architecture

### Core Services

- **`SchemaService`** - Main API for parsing, validation, and core/extension separation
- **`GenerationService`** - Coordinates all generators to produce insertable fragments
- **`YamlOptimizationService`** - Advanced YAML parsing with lazy loading, streaming, and intelligent caching
- **`SchemaDiffService`** - Advanced schema comparison and difference analysis
- **`SchemaOptimizationService`** - Performance analysis and optimization recommendations
- **13 Specialized Generators** - Model, Migration, Request, Resource, Factory, Seeder, Controller, Test, Policy, Observer, Service, Action, Rule
- **`FieldTypePluginManager`** - Manages extensible field type plugins for custom functionality

### Schema Structure

The package uses a **"core" structure** to clearly separate core schema data from application extensions:

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
    soft_deletes: false

# Extensions added by parent applications
turbomaker:
  views: ['index', 'create', 'edit']
  routes: ['api', 'web']

arc:
  permissions: ['view', 'create', 'edit', 'delete']
```

## 🚀 Quick Start

### 1. Basic Schema Parsing

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Parse and separate core from extensions
$result = $schemaService->parseAndSeparateSchema($yamlContent);
// Returns: ['core' => [...], 'extensions' => [...]]

// Validate only the core schema
$errors = $schemaService->validateCoreSchema($yamlContent);

// Extract structured data for generation
$data = $schemaService->extractCoreContentForGeneration($yamlContent);
```

### 2. Complete Integration Workflow

```php
// 1. Generate complete YAML from stub + app data
$completeYaml = $schemaService->generateCompleteYamlFromStub(
    'user.schema.stub',
    ['MODEL_NAME' => 'User', 'TABLE_NAME' => 'users'],
    $appExtensionData
);

// 2. Validate the complete YAML (focuses on core section)
$errors = $schemaService->validateFromCompleteAppYaml($completeYaml);

// 3. Extract all generation data as insertable fragments
$generationData = $schemaService->getGenerationDataFromCompleteYaml($completeYaml);

// 4. Use fragments in your application
$modelFragment = json_decode($generationData['generation_data']['model']['json'], true);
$migrationFragment = json_decode($generationData['generation_data']['migration']['json'], true);
```

### 3. Fragment-Based Generation

```php
use Grazulex\LaravelModelschema\Services\GenerationService;

$generationService = new GenerationService();

// Generate all fragments for a schema
$fragments = $generationService->generateAll($schema);

// Result structure:
// [
//   'model' => ['json' => '{"model": {...}}', 'yaml' => 'model: {...}'],
//   'migration' => ['json' => '{"migration": {...}}', 'yaml' => 'migration: {...}'],
//   'requests' => ['json' => '{"requests": {...}}', 'yaml' => 'requests: {...}'],
//   'resources' => ['json' => '{"resources": {...}}', 'yaml' => 'resources: {...}'],
//   'factory' => ['json' => '{"factory": {...}}', 'yaml' => 'factory: {...}'],
//   'seeder' => ['json' => '{"seeder": {...}}', 'yaml' => 'seeder: {...}'],
//   'controllers' => ['json' => '{"controllers": {...}}', 'yaml' => 'controllers: {...}'],
//   'tests' => ['json' => '{"tests": {...}}', 'yaml' => 'tests: {...}'],
//   'policies' => ['json' => '{"policies": {...}}', 'yaml' => 'policies: {...}'],
//   'observers' => ['json' => '{"observers": {...}}', 'yaml' => 'observers: {...}'],
//   'services' => ['json' => '{"services": {...}}', 'yaml' => 'services: {...}'],
//   'actions' => ['json' => '{"actions": {...}}', 'yaml' => 'actions: {...}'],
//   'rules' => ['json' => '{"rules": {...}}', 'yaml' => 'rules: {...}']
// ]
```

## 🏗️ Available Generators

Laravel ModelSchema provides 13 specialized generators, each producing insertable JSON/YAML fragments:

### Core Laravel Components
| Generator | Description | Output Fragment |
|-----------|-------------|-----------------|
| **Model** | Eloquent model with relationships, casts, and configurations | `model: {class_name, table, fields, relations, casts, ...}` |
| **Migration** | Database migration with fields, indexes, and foreign keys | `migration: {table, fields, indexes, foreign_keys, ...}` |
| **Request** | Form Request classes for validation (Store/Update) | `requests: {store: {...}, update: {...}}` |
| **Resource** | API Resource classes for data transformation | `resources: {main_resource: {...}, collection_resource: {...}}` |
| **Factory** | Model Factory for testing and seeding | `factory: {class_name, definition, states, ...}` |
| **Seeder** | Database Seeder for data population | `seeder: {class_name, model, count, relationships, ...}` |

### Advanced Components
| Generator | Description | Output Fragment |
|-----------|-------------|-----------------|
| **Controller** | API and Web Controllers with CRUD operations | `controllers: {api_controller: {...}, web_controller: {...}}` |
| **Test** | PHPUnit test classes (Feature and Unit) | `tests: {feature_tests: [...], unit_tests: [...]}` |
| **Policy** | Authorization Policy classes | `policies: {class_name, methods, gates, ...}` |

### Business Logic Components (New in v2.0)
| Generator | Description | Output Fragment |
|-----------|-------------|-----------------|
| **Observer** | Eloquent Observer with model event handlers | `observers: {class_name, events, methods, ...}` |
| **Service** | Business logic Service classes with CRUD operations | `services: {class_name, methods, dependencies, ...}` |
| **Action** | Single-responsibility Action classes | `actions: {crud_actions: [...], business_actions: [...]}` |
| **Rule** | Custom Validation Rule classes | `rules: {business_rules: [...], foreign_key_rules: [...]}` |

### Usage Examples

```php
// Generate specific components
$observerFragment = $generationService->generateObservers($schema);
$serviceFragment = $generationService->generateServices($schema);
$actionFragment = $generationService->generateActions($schema);
$ruleFragment = $generationService->generateRules($schema);

// Generate multiple new components
$fragments = $generationService->generateMultiple($schema, [
    'observers', 'services', 'actions', 'rules'
]);

// Generate everything including new components
$allFragments = $generationService->generateAll($schema, [
    'model' => true,
    'migration' => true,
    'requests' => true,
    'resources' => true,
    'factory' => true,
    'seeder' => true,
    'controllers' => true,
    'tests' => true,
    'policies' => true,
    'observers' => true,    // New
    'services' => true,     // New
    'actions' => true,      // New
    'rules' => true,        // New
]);
```

## 🔧 API Reference

### SchemaService

| Method | Description | Returns |
|--------|-------------|---------|
| `parseAndSeparateSchema()` | Parse YAML and separate core/extensions | `['core' => array, 'extensions' => array]` |
| `validateCoreSchema()` | Validate only core schema section | `array` (errors) |
| `extractCoreContentForGeneration()` | Extract structured core data | `array` |
| `generateCompleteYamlFromStub()` | Generate complete YAML from stub | `string` |
| `getGenerationDataFromCompleteYaml()` | Extract all generation fragments | `array` |

### GenerationService

| Method | Description | Returns |
|--------|-------------|---------|
| `generateAll()` | Generate all fragments for schema | `array` |
| `generateSingle()` | Generate single generator fragment | `array` |
| `getAvailableGenerators()` | List available generators | `array` |

## 🔌 Trait-Based Field Type Plugin System

Laravel ModelSchema features an extensible plugin system using a trait-based architecture for custom field types. This modern approach provides powerful customization through traits and configuration objects.

### Plugin Manager

```php
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

$manager = new FieldTypePluginManager();

// Register a custom plugin
$manager->registerPlugin(new CustomFieldTypePlugin());

// Auto-discover plugins in specific paths  
$manager->discoverPlugins([
    'App\\FieldTypes\\*Plugin',
    'Custom\\Packages\\*FieldTypePlugin'
]);

// Get all registered plugins
$plugins = $manager->getAllPlugins();
```

### Creating Custom Plugins with Traits

The new trait-based approach allows you to define field options through configuration arrays rather than hardcoded properties:

```php
use Grazulex\LaravelModelschema\Support\FieldTypePlugin;

class UrlFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.0.0';
    protected string $author = 'Your Name';
    protected string $description = 'Advanced URL field with validation traits';

    public function __construct()
    {
        // Define custom attributes using trait-based configuration
        $this->customAttributes = [
            'schemes', 'verify_ssl', 'timeout', 'domain_whitelist', 'max_redirects'
        ];
        
        // Configure each attribute with validation traits
        $this->customAttributeConfig = [
            'schemes' => [
                'type' => 'array',
                'default' => ['http', 'https'],
                'enum' => ['http', 'https', 'ftp', 'ftps'],
                'description' => 'Allowed URL schemes for validation'
            ],
            'verify_ssl' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Enable SSL certificate verification'
            ],
            'timeout' => [
                'type' => 'integer',
                'min' => 1,
                'max' => 300,
                'default' => 30,
                'description' => 'Connection timeout in seconds'
            ],
            'domain_whitelist' => [
                'type' => 'array',
                'required' => false,
                'validator' => function ($value): array {
                    // Custom validation trait for domain lists
                    if (!is_array($value)) return ['must be an array'];
                    foreach ($value as $domain) {
                        if (!filter_var("http://{$domain}", FILTER_VALIDATE_URL)) {
                            return ["Invalid domain: {$domain}"];
                        }
                    }
                    return [];
                }
            ]
        ];
    }

    public function getType(): string
    {
        return 'url';
    }
    
    public function getAliases(): array
    {
        return ['website', 'link', 'uri'];
    }
}
```

### Trait-Based Custom Attributes System

The trait-based plugin system supports sophisticated custom attributes through configuration objects:

- **Type validation traits**: `string`, `int`, `boolean`, `array`, etc.
- **Constraint traits**: `min`, `max`, `required`, `enum` values  
- **Default value traits**: Automatically applied if not provided
- **Custom validator traits**: Callback functions for complex validation logic
- **Transformation traits**: Custom value transformation before storage
- **Integration traits**: Seamlessly merged with Laravel's standard attributes

#### Advanced Trait Examples

```php
// Numeric validation traits
'timeout' => [
    'type' => 'integer',
    'min' => 1,
    'max' => 300,
    'default' => 30,
    'transform' => fn($value) => (int) $value // Type transformation trait
],

// Array validation traits with enum constraints
'schemes' => [
    'type' => 'array',
    'enum' => ['http', 'https', 'ftp', 'ftps'],
    'default' => ['http', 'https'],
    'validator' => function($schemes): array {
        // Custom validation trait
        return array_filter($schemes, fn($s) => in_array($s, ['http', 'https']));
    }
],

// Complex custom validator traits
'domain_pattern' => [
    'type' => 'string',
    'validator' => function($pattern): array {
        if (!preg_match('/^\/.*\/[gimxs]*$/', $pattern)) {
            return ['Domain pattern must be a valid regex'];
        }
        return [];
    }
]
```

**📖 See [Field Type Plugins Documentation](docs/FIELD_TYPE_PLUGINS.md) for complete trait-based implementation guide.**

## 📁 Example Schema Files

### Basic User Schema
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
    email_verified_at:
      type: timestamp
      nullable: true
    password:
      type: string
      rules: ['required', 'string', 'min:8']
  options:
    timestamps: true
    soft_deletes: false
```

### Blog Post Schema with Relations
```yaml
core:
  model: Post
  table: posts
  fields:
    title:
      type: string
      rules: ['required', 'string', 'max:255']
    slug:
      type: string
      unique: true
      rules: ['required', 'string', 'unique:posts']
    content:
      type: text
      rules: ['required']
    published_at:
      type: timestamp
      nullable: true
    user_id:
      type: foreignId
      rules: ['required', 'exists:users,id']
  relations:
    user:
      type: belongsTo
      model: App\Models\User
    comments:
      type: hasMany
      model: App\Models\Comment
    tags:
      type: belongsToMany
      model: App\Models\Tag
      pivot_table: post_tags
  options:
    timestamps: true
    soft_deletes: true
```

## � Integration with Parent Applications

This package is designed to be consumed by larger Laravel packages like **TurboMaker** and **Arc**. Here's the typical integration pattern:

### Parent Application Workflow

```php
// 1. Parent app generates complete YAML
$yaml = $schemaService->generateCompleteYamlFromStub('user.schema.stub', [
    'MODEL_NAME' => 'User',
    'TABLE_NAME' => 'users'
], $parentAppData);

// 2. Parent app validates the schema
$errors = $schemaService->validateFromCompleteAppYaml($yaml);
if (!empty($errors)) {
    throw new ValidationException($errors);
}

// 3. Parent app extracts generation fragments
$data = $schemaService->getGenerationDataFromCompleteYaml($yaml);

// 4. Parent app integrates fragments into its own files
$parentAppGenerator->generateModelFile($data['generation_data']['model']['json']);
$parentAppGenerator->generateMigrationFile($data['generation_data']['migration']['json']);
// ... etc for requests, resources, factory, seeder
```

### Fragment Structure

Each generator produces insertable fragments with this structure:

```json
{
  "model": {
    "class_name": "User",
    "table": "users", 
    "fields": [...],
    "relations": [...],
    "casts": {...},
    "options": {...}
  }
}
```

The parent application receives these fragments and inserts them into its own generation templates.

## 🧪 Testing

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run specific test file
./vendor/bin/pest tests/Unit/SchemaServiceTest.php
```

## 🔧 Requirements

- **PHP**: ^8.3
- **Laravel**: ^12.19 (optional, used in service provider)
- **Symfony YAML**: ^7.3 (for YAML parsing)

## 📚 Documentation

### Core Documentation
- **🏗️ [Architecture Guide](docs/ARCHITECTURE.md)** - Understanding the package structure and design
- **� [Migration Guide](docs/MIGRATION.md)** - Upgrading from previous versions
- **📊 [Fragment Examples](examples/FRAGMENTS.md)** - Understanding generated fragments

### Field Types & Extensions  
- **� [Field Types Guide](docs/FIELD_TYPES.md)** - Complete field types reference
- **🔌 [Field Type Plugins](docs/FIELD_TYPE_PLUGINS.md)** - Creating custom field type plugins
- **✅ [Custom Field Validation](docs/CUSTOM_FIELD_TYPES_VALIDATION.md)** - Validating custom field types

### Advanced Features
- **📝 [Logging System](docs/LOGGING.md)** - Comprehensive logging and debugging
- **⚡ [Enhanced Features](docs/enhanced-features.md)** - Advanced capabilities overview
- **� [YAML Optimization](docs/YAML-OPTIMIZATION.md)** - High-performance YAML parsing with intelligent caching and streaming
- **�🔍 [Schema Optimization](docs/SCHEMA_OPTIMIZATION.md)** - Schema analysis and optimization tools
- **🔒 [Security Features](docs/SECURITY.md)** - Comprehensive security validation and protection

### Integration Examples
- **🔗 [Integration Example](examples/IntegrationExample.php)** - Complete integration workflow
- **✨ [New Generators Example](examples/NewGeneratorsExample.php)** - Observer, Service, Action, Rule generators demo
- **🛠️ [Schema Service API](examples/SchemaServiceApiExample.php)** - API usage examples
- **📋 [API Extensions](examples/ApiExtensions.php)** - Extended API implementations
- **🚀 [YAML Optimization Examples](examples/YamlOptimizationExamples.php)** - Performance optimization usage and examples
- **⚡ [Schema Optimization Usage](examples/SchemaOptimizationUsage.php)** - Advanced schema analysis examples
- **🔒 [Security Usage Examples](examples/SecurityUsageExamples.php)** - Security validation and protection examples

## 🤝 Contributing

We welcome contributions! Please see our Contributing Guide for details.

## 🔒 Security

Please review our Security Policy for reporting vulnerabilities.

## 📄 License

Laravel ModelSchema is open-sourced software licensed under the MIT license.

---

Made with ❤️ by Jean-Marc Strauven (https://github.com/Grazulex)