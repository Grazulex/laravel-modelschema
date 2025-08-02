# Release Notes

## Version 2.0.0 - Fragment-Based Architecture (August 2025)

### 🚀 Major Architectural Overhaul

This is a **major release** that completely redesigns Laravel ModelSchema with a fragment-based architecture optimized for integration with parent applications like TurboMaker and Arc.

### ⚠️ Breaking Changes

#### 1. New YAML Structure
- **Before**: Flat YAML structure
- **After**: Core/extension separation with `core:` section

```yaml
# v1.x (OLD)
model: User
table: users
fields:
  name:
    type: string

# v2.x (NEW)
core:
  model: User
  table: users
  fields:
    name:
      type: string
```

#### 2. API Changes
- **Removed**: Direct file generation methods
- **Added**: Fragment-based generation API
- **New**: `SchemaService` and `GenerationService` classes

#### 3. Namespace Changes
- **Old**: `Grazulex\ModelSchema\*`
- **New**: `Grazulex\LaravelModelschema\*`

### 🎯 New Features

#### 1. Fragment-Based Generation
Generate insertable JSON/YAML fragments instead of complete files:
```php
$data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
$modelFragment = json_decode($data['generation_data']['model']['json'], true);
```

#### 2. Six Specialized Generators
- **ModelGenerator** - Eloquent model fragments
- **MigrationGenerator** - Database migration fragments
- **RequestGenerator** - Form request validation fragments
- **ResourceGenerator** - API resource transformation fragments
- **FactoryGenerator** - Model factory fragments
- **SeederGenerator** - Database seeder fragments

#### 3. Core/Extension Separation
Clean separation between core schema logic and application-specific extensions:
```yaml
core:
  model: User
  table: users
  fields:
    name:
      type: string

# Extensions added by parent applications
turbomaker:
  views: ['index', 'create']
  routes: ['web', 'api']

arc:
  permissions: ['view', 'create', 'edit']
```

#### 4. Complete Integration API
New `SchemaService` provides comprehensive API for parent applications:
- `parseAndSeparateSchema()` - Parse and separate core from extensions
- `validateCoreSchema()` - Validate only core schema
- `extractCoreContentForGeneration()` - Extract structured data
- `generateCompleteYamlFromStub()` - Generate complete YAML from stub
- `getGenerationDataFromCompleteYaml()` - Extract all generation fragments

#### 5. Enhanced Validation
- Strict core schema validation
- Detailed error reporting
- Support for extension validation
- Field type and relationship validation

#### 6. Stub System
- **Schema stubs**: Base templates for different use cases
- **Generator stubs**: Fragment templates for each generator
- **Replacement system**: Dynamic content generation from stubs

### 🔧 Technical Improvements

#### 1. Symfony YAML Integration
- **Removed**: PHP YAML extension dependency
- **Added**: Symfony YAML component for better reliability

#### 2. Performance Optimizations
- Lazy loading of generators
- Efficient memory management
- Stateless fragment generation
- Caching-friendly design

#### 3. Extensibility
- Plugin system for custom field types
- Extensible generator architecture
- Custom validation rules support

### 📚 Documentation Overhaul

#### New Documentation
- **Complete README** with current architecture
- **Architecture Guide** (`docs/ARCHITECTURE.md`)
- **Migration Guide** (`docs/MIGRATION.md`)
- **Fragment Documentation** (`examples/FRAGMENTS.md`)

#### Examples
- **Integration Examples** (`examples/IntegrationExample.php`)
- **API Examples** (`examples/SchemaServiceApiExample.php`)
- **Extension APIs** (`examples/ApiExtensions.php`)
- **Custom Field Type** (`examples/UrlFieldType.php`)

### 🧪 Testing

#### Comprehensive Test Suite
- **151 tests** with **743 assertions**
- **100% API coverage** for all public methods
- **Integration tests** simulating parent application usage
- **Performance tests** ensuring scalability

#### Test Categories
- **Unit tests**: Individual service and generator testing
- **Integration tests**: Complete workflow validation
- **Feature tests**: Laravel integration testing
- **Edge case testing**: Error handling and validation

### 📦 Dependencies

#### Added
- `symfony/yaml: ^7.3` - YAML parsing and generation

#### Removed
- PHP YAML extension dependency

#### Updated
- Laravel 12.x compatibility
- PHP 8.3+ requirement

### 🔧 Migration Guide

For detailed migration instructions, see [`docs/MIGRATION.md`](docs/MIGRATION.md).

#### Quick Migration Steps

1. **Update schema files** - Add `core:` wrapper
2. **Update code usage** - Replace direct generation with fragments
3. **Update dependencies** - Install Symfony YAML
4. **Test integration** - Validate with new API

### 🎯 Benefits for Parent Applications

#### 1. Clean Separation
- Core schema logic handled by ModelSchema
- Application-specific logic handled by parent app
- No coupling between schema and generation

#### 2. Flexible Integration
- Insertable fragments fit any template system
- JSON/YAML output for maximum compatibility
- Extensible with application-specific data

#### 3. Robust Architecture
- Validated schema structure
- Comprehensive error handling
- Future-proof extensibility

### 🔄 Upgrade Path

#### For Direct Users
1. Update YAML schemas to use `core:` structure
2. Replace direct file generation with fragment integration
3. Update service usage to new API

#### For Package Developers (TurboMaker, Arc)
1. Integrate with new fragment-based API
2. Use `SchemaService` for schema parsing and validation
3. Integrate fragments into existing template systems

### 🚧 Deprecations

#### Removed in v2.0
- Direct PHP file generation
- Old flat YAML structure (automatically migrated)
- Legacy ModelSchema class
- PHP YAML extension requirement

### 🛠️ Future Roadmap

#### Planned Features
- Additional generators (Controllers, Tests, Policies)
- Enhanced validation system
- Visual schema editor
- GraphQL schema generation

#### Extension Points
- Custom generator plugins
- Advanced field type system
- Schema versioning
- Performance monitoring

### 🤝 Community

#### Contributing
- New architecture makes contributions easier
- Clear separation of concerns
- Comprehensive test coverage
- Detailed documentation

#### Support
- Migration assistance available
- Examples for common use cases
- Active issue tracking
- Community feedback integration

---

## Version 1.x.x - Legacy (Deprecated)

Previous versions using flat YAML structure and direct file generation are now deprecated. Please migrate to v2.0.0 for continued support and new features.

### Legacy Features (No Longer Supported)
- Direct PHP file generation
- Flat YAML structure
- `ModelSchema::fromYamlFile()` usage
- PHP YAML extension dependency

For legacy documentation, see [v1.x branch](https://github.com/Grazulex/laravel-modelschema/tree/v1.x).

---

# Scripts de Release (Legacy)

Ce dossier contient des scripts pour gérer les releases du package Laravel ModelSchema.

## Scripts disponibles

### `release.sh`
Créer une nouvelle release et la publier sur GitHub et Packagist.

**Usage :**
```bash
./release.sh <version> [notes_de_release]
```

**Exemples :**
```bash
# Release simple
./release.sh 1.2.0

# Release avec notes
./release.sh 1.2.0 "Ajout des traits pour DTOs et amélioration des performances"

# Release avec notes multilignes
./release.sh 1.2.0 "
- Ajout des traits ValidatesData, ConvertsData, DtoUtilities
- Correction des erreurs PHPStan
- Amélioration de la documentation
"
```

### `check-releases.sh`
Vérifier l'état des releases et des tags.

**Usage :**
```bash
./check-releases.sh
```

## Prérequis

### GitHub CLI
Pour utiliser les scripts, vous devez avoir GitHub CLI installé et configuré :

```bash
# Ubuntu/Debian
sudo apt install gh

# macOS
brew install gh

# Connexion
gh auth login
```

## Workflow de release

1. **Développement** : Faites vos modifications et committez normalement
2. **Vérification** : `./check-releases.sh` pour voir l'état actuel
3. **Release** : `./release.sh X.Y.Z "Description"` quand prêt à publier
4. **Suivi** : Le workflow GitHub Actions s'occupe du reste

## Processus automatique

Quand vous lancez `./release.sh` :

1. ✅ **Vérifications** : Format version, état du repo, permissions
2. 📤 **Push** : Pousse les derniers changements
3. 🚀 **Déclenchement** : Lance le workflow GitHub Actions
4. 🧪 **Tests** : Exécute la suite de tests complète (Pest + PHPStan)
5. 🏷️ **Tag** : Crée et pousse le tag Git (seulement si tests OK)
6. 📦 **Release** : Crée la release GitHub (seulement si tag OK)
7. 🌐 **Packagist** : Mise à jour automatique via webhook

## Versioning

Utilisez le [Semantic Versioning](https://semver.org/) :
- **Major** (X.0.0) : Changements incompatibles
- **Minor** (X.Y.0) : Nouvelles fonctionnalités compatibles
- **Patch** (X.Y.Z) : Corrections de bugs

## Remarques

- Seul `grazulex` peut déclencher des releases (configuré dans le workflow)
- **Les tests doivent passer avant la création de la release** (obligatoire)
- La release est annulée si les tests échouent
- Packagist se met à jour automatiquement grâce au webhook GitHub
