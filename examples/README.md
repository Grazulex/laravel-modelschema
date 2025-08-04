# Laravel ModelSchema - Examples and Documentation

This directory contains a complete collection of practical examples for Laravel ModelSchema, focusing on the new **trait-based architecture** and advanced plugin system.

## 🆕 Trait-Based Architecture - New Features

Laravel ModelSchema has evolved to a modern architecture based on **configuration traits** that enables modular and flexible customization of field types.

### Key Changes
- **Options passed as traits**: Modular configuration via trait objects
- **Extensible plugins**: Plugin system based on `FieldTypePlugin`
- **Advanced validation**: Trait-based validation with constraints and business logic
- **Declarative configuration**: Behavior definition via configuration arrays

## 📁 Examples Structure

### Core Examples (Main Usage)
- **[IntegrationExample.php](IntegrationExample.php)** - Complete integration workflow with traits
- **[SchemaServiceApiExample.php](SchemaServiceApiExample.php)** - SchemaService API with trait support
- **[TraitBasedPluginExample.php](TraitBasedPluginExample.php)** - Advanced trait-based plugin examples
- **[NewGeneratorsExample.php](NewGeneratorsExample.php)** - ✨ **New Generators Demo (v2.0)** - Observer, Service, Action, Rule generators

### Plugin System Examples (Plugin System)
- **[UrlFieldType.php](UrlFieldType.php)** - Legacy URL plugin (historical reference)
- **See `src/Examples/UrlFieldTypePlugin.php`** - Modern URL plugin with traits

### Configuration Examples (Configuration)
- **[CUSTOM_ATTRIBUTES.md](CUSTOM_ATTRIBUTES.md)** - Custom attributes guide with traits
- **[FRAGMENTS.md](FRAGMENTS.md)** - Structure of generated fragments

### Specialized Examples (Specialized Examples)
- **[ApiExtensions.php](ApiExtensions.php)** - Advanced API extensions
- **[AutoValidationExample.php](AutoValidationExample.php)** - Automatic validation
- **[CustomFieldTypesValidationExample.php](CustomFieldTypesValidationExample.php)** - Custom validation
- **[LoggingExample.php](LoggingExample.php)** - Logging system
- **[SecurityUsageExamples.php](SecurityUsageExamples.php)** - Security and validation
- **[YamlOptimizationExamples.php](YamlOptimizationExamples.php)** - YAML optimization
- **[SchemaOptimizationUsage.php](SchemaOptimizationUsage.php)** - Schema optimization

## 🎯 Examples by Use Case

### 1. Getting Started with Traits
```bash
# Start by understanding the trait-based architecture
php examples/TraitBasedPluginExample.php

# See complete integration with traits
php examples/IntegrationExample.php
```

### 2. Creating a Plugin with Traits
Refer to `TraitBasedPluginExample.php` for complete examples of plugins using the trait-based architecture:

```php
// Trait configuration in a plugin
$this->customAttributeConfig = [
    'timeout' => [
        'type' => 'integer',
        'min' => 1,
        'max' => 300,
        'default' => 30,
        'validator' => function($value): array {
            // Custom validation logic
            return [];
        }
    ]
];
```

### 3. Using the SchemaService API with Traits
```bash
# Complete API with trait examples
php examples/SchemaServiceApiExample.php
```

### 4. Advanced Field Configuration
Refer to `CUSTOM_ATTRIBUTES.md` for trait configuration examples:

```yaml
# YAML with configuration traits
core:
  model: User
  fields:
    homepage:
      type: url
      # Configuration traits
      schemes: ["https", "http"]
      verify_ssl: true
      timeout: 45
      domain_whitelist: ["trusted.com"]
```

## 🧩 Available Trait Types

### Validation Traits
- **Type traits**: `string`, `integer`, `boolean`, `array`
- **Constraint traits**: `min`, `max`, `required`, `enum`
- **Custom validator traits**: Custom validation via callbacks

### Transformation Traits
- **Value transformation**: Automatic value modification
- **Default value traits**: Smart default value application
- **Format traits**: Automatic data formatting

### Behavior Traits
- **Storage traits**: Storage configuration (disk, path, encryption)
- **Processing traits**: Automatic processing (compression, thumbnails)
- **Security traits**: Security validation (virus scan, SSL verification)

## 📖 Complete Documentation

### Main Guides
- **[README.md](../README.md)** - General introduction with trait-based architecture
- **[docs/FIELD_TYPE_PLUGINS.md](../docs/FIELD_TYPE_PLUGINS.md)** - Complete guide to trait-based plugins
- **[docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md)** - Trait-enhanced architecture
- **[docs/CUSTOM_FIELD_TYPES_VALIDATION.md](../docs/CUSTOM_FIELD_TYPES_VALIDATION.md)** - Trait-based validation

### Modern Plugin Examples
1. **UrlFieldTypePlugin** - URL plugin with security and validation traits
2. **FileUploadFieldTypePlugin** - File upload with storage and security traits
3. **GeographicCoordinatesFieldTypePlugin** - Geographic coordinates with validation traits
4. **JsonSchemaFieldTypePlugin** - JSON Schema validation with configurable traits

## 🚀 Quick Start

### 1. Installation and Configuration
```bash
composer require grazulex/laravel-modelschema
```

### 2. Trait-Based Plugin Registration
```php
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

$manager = new FieldTypePluginManager();
$manager->registerPlugin(new UrlFieldTypePlugin());
$manager->registerPlugin(new FileUploadFieldTypePlugin());
```

### 3. Usage in YAML Schema
```yaml
core:
  model: Website
  table: websites
  fields:
    logo:
      type: file_upload
      # Trait-based configuration
      allowed_extensions: ["jpg", "png"]
      max_file_size: "2MB"
      auto_optimize: true
      generate_thumbnails:
        small: "150x150"
        medium: "300x300"
```

### 4. Fragment Generation
```php
$schemaService = new SchemaService();
$generationData = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);

// Fragments now include trait processing
$modelFragment = json_decode($generationData['generation_data']['model']['json'], true);
```

## 🔄 Migration from Legacy System

If you are using the legacy field types system, refer to:
- **[docs/MIGRATION.md](../docs/MIGRATION.md)** - Migration guide
- **[UrlFieldType.php](UrlFieldType.php)** - Legacy example for reference

### Trait-Based Architecture Benefits
✅ **Flexibility**: Modular and reusable configuration  
✅ **Advanced validation**: Trait-based validation with business logic  
✅ **Extensibility**: New traits can be added without core modification  
✅ **Maintainability**: Clear separation of responsibilities  
✅ **Performance**: Optimized configuration processing  

## 🤝 Contributing

To contribute new examples or improve existing ones:

1. Create examples using the trait-based architecture
2. Document new traits and their usage
3. Add tests to validate examples
4. Follow established naming conventions

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/Grazulex/laravel-modelschema/issues)
- **Discussions**: [GitHub Discussions](https://github.com/Grazulex/laravel-modelschema/discussions)
- **Documentation**: [Wiki](https://github.com/Grazulex/laravel-modelschema/wiki)

---

**🎯 The trait-based architecture of Laravel ModelSchema offers a modern and flexible approach for schema generation. Explore the examples to discover all the possibilities!**