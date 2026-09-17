# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed
- Source code refreshed with the current Rector rule set (closure parameter types, `in_array` for repeated strict comparisons, previous exception chaining on `SchemaException`, removal of redundant property defaults and `@var` tags); no behavioural change
- GitHub Actions updated to `actions/checkout@v5` and `softprops/action-gh-release@v2`

### Fixed
- CHANGELOG history corrected: the entry previously labelled `2.0.0` describes the first published release and is now `1.0.0`; a `1.1.0` entry was added and the stale roadmap section removed

## [1.2.0] - 2026-09-17

### Added
- Laravel 13 support (`illuminate/support` and `illuminate/contracts` `^12.0|^13.0`)
- CI test matrix now covers PHP 8.3 / 8.4 and Laravel 12 / 13 (Testbench 10 / 11), with `prefer-lowest` and `prefer-stable`
- Pint check added to the Code Quality workflow

### Changed
- PHP 8.3 remains the minimum supported version; PHP 8.3 and 8.4 are now both tested in CI
- Dev dependencies updated: Pest `^3.8|^4.0`, Pest Laravel plugin `^3.2|^4.0`, Orchestra Testbench `^10.0|^11.0`
- Test classes converted from `@test` / `@covers` doc-comment annotations to PHPUnit attributes (`#[Test]`, `#[CoversClass]`); doc-comment metadata is no longer supported by PHPUnit 12 / Pest 4 and those tests were silently skipped
- Rector configuration updated for Rector 2.x (`strictBooleans` prepared set removed)
- Examples reformatted with the current Pint ruleset

### Fixed
- Removed redundant null-coalescing on non-nullable schema properties and a redundant empty-array check flagged by PHPStan 2 / Larastan 3

### Removed
- Laravel 11 support (end of life)

## [1.1.0] - 2025-08-04

### Changed
- Package requirements widened to `illuminate/support` and `illuminate/contracts` `^11.0|^12.0` (Laravel 11 and 12), Orchestra Testbench `^9.0|^10.0`

## [1.0.0] - 2025-08-04

### 🚀 Initial Release - Fragment-Based Architecture

First published release. Compared to the unpublished prototype (`Grazulex\ModelSchema`), this is a complete architectural rewrite focused on fragment-based generation for parent application integration.

### Added
- **Fragment-Based Generation System**
  - 8 specialized generators (Model, Migration, Requests, Resources, Factory, Seeder, Controllers, Tests, Policies)
  - JSON and YAML output formats for all generators
  - Insertable fragments for parent application integration

- **Enhanced Request Generator with Custom Form Requests Support** ⭐ NEW
  - **Configurable Authorization Logic**: Different authorization rules for store/update/custom actions
  - **Custom Validation Messages**: Field-specific validation messages based on field types and rules
  - **Relationship Validation**: Automatic validation rules for belongsTo, belongsToMany, hasMany relationships
  - **Conditional Validation Rules**: Dynamic validation based on field dependencies (e.g., enum values)
  - **Custom Methods Generation**: prepareForValidation(), custom validation methods, and action-specific logic
  - **Multi-Request Support**: Generate custom request types (bulk operations, publish actions, etc.)
  - **Enhanced/Traditional Modes**: Backward compatibility with traditional structure
  - **Complete Test Coverage**: 5 comprehensive tests validating all enhanced features

- **Code Quality Improvements**
  - **PHPStan Level 9 Compliance**: Fixed all static analysis errors for production-ready code
  - **GeometryFieldType Constructor**: Added proper initialization and config handling
  - **Type Safety**: Resolved undefined property access and logical comparisons

- **Core/Extension Separation**
  - New YAML structure with `core:` section
  - Clean separation between core schema and application extensions
  - Support for multiple extension sections (turbomaker, arc, etc.)

- **Complete Integration API**
  - `SchemaService` class with comprehensive parsing and validation methods
  - `GenerationService` class for coordinating fragment generation
  - `parseAndSeparateSchema()` method for core/extension separation
  - `validateCoreSchema()` method for core-only validation
  - `extractCoreContentForGeneration()` method for structured data extraction
  - `generateCompleteYamlFromStub()` method for stub processing
  - `getGenerationDataFromCompleteYaml()` method for fragment extraction

- **Enhanced Validation System**
  - Strict core schema validation with detailed error reporting
  - Field type validation with support for custom types
  - Relationship validation with proper model checking
  - Extension data validation support

- **Stub System**
  - Schema stubs for base templates (basic, blog, user, etc.)
  - Generator stubs for fragment templates
  - Dynamic replacement system for stub processing

- **Comprehensive Documentation**
  - Complete README rewrite with current architecture
  - Architecture guide (`docs/ARCHITECTURE.md`)
  - Migration guide (`docs/MIGRATION.md`)
  - Fragment documentation (`examples/FRAGMENTS.md`)
  - Integration examples (`examples/IntegrationExample.php`)
  - API examples (`examples/SchemaServiceApiExample.php`)
  - Extension API examples (`examples/ApiExtensions.php`)

- **Test Suite Expansion**
  - 151 tests with 743 assertions
  - Complete API coverage for all public methods
  - Integration tests simulating parent application usage
  - Performance and edge case testing

### Changed
- **Breaking**: YAML structure now requires `core:` section for core schema data
- **Breaking**: API completely redesigned around fragment generation
- **Breaking**: Namespace changed from `Grazulex\ModelSchema` to `Grazulex\LaravelModelschema`
- **Breaking**: Removed direct PHP file generation in favor of fragment approach
- **Breaking**: Replaced PHP YAML extension with Symfony YAML component

### Removed
- **Breaking**: Direct file generation methods (`generateModel()`, `generateMigration()`, etc.)
- **Breaking**: Legacy `ModelSchema` class and flat YAML structure
- **Breaking**: PHP YAML extension dependency

### Dependencies
- **Added**: `symfony/yaml: ^7.3` for reliable YAML processing
- **Removed**: PHP YAML extension requirement
- **Updated**: Laravel 12.x compatibility, PHP 8.3+ requirement

### Migration
- See `docs/MIGRATION.md` for detailed migration instructions
- Automatic core wrapping for backward compatibility during transition
- Examples provided for all common migration scenarios

## Unpublished prototype (superseded)

The pre-release prototype was never tagged or published. Its structure is superseded by 1.0.0.

### Legacy Features
- Flat YAML structure without core/extension separation
- Direct PHP file generation
- `ModelSchema::fromYamlFile()` usage pattern
- PHP YAML extension dependency

---

## Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

## Support

- 📖 [Documentation](https://github.com/Grazulex/laravel-modelschema/wiki)
- 🐛 [Issue Tracker](https://github.com/Grazulex/laravel-modelschema/issues)
- 💬 [Discussions](https://github.com/Grazulex/laravel-modelschema/discussions)

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.
