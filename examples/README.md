# Laravel ModelSchema - Exemples et Documentation

Ce répertoire contient une collection complète d'exemples pratiques pour Laravel ModelSchema, mettant l'accent sur la nouvelle **architecture par traits** et le système de plugins avancé.

## 🆕 Architecture par Traits - Nouveautés

Laravel ModelSchema a évolué vers une architecture moderne basée sur les **traits de configuration** qui permet une personnalisation modulaire et flexible des types de champs.

### Changements Clés
- **Options passées en traits** : Configuration modulaire via des objets de trait
- **Plugins extensibles** : Système de plugins basé sur `FieldTypePlugin`
- **Validation avancée** : Validation par trait avec contraintes et logique métier
- **Configuration déclarative** : Définition des comportements via des tableaux de configuration

## 📁 Structure des Exemples

### Core Examples (Usage Principal)
- **[IntegrationExample.php](IntegrationExample.php)** - Workflow d'intégration complet avec traits
- **[SchemaServiceApiExample.php](SchemaServiceApiExample.php)** - API SchemaService avec support des traits
- **[TraitBasedPluginExample.php](TraitBasedPluginExample.php)** - Exemples avancés de plugins par traits

### Plugin System Examples (Système de Plugins)
- **[UrlFieldType.php](UrlFieldType.php)** - Plugin URL legacy (référence historique)
- **Voir `src/Examples/UrlFieldTypePlugin.php`** - Plugin URL moderne avec traits

### Configuration Examples (Configuration)
- **[CUSTOM_ATTRIBUTES.md](CUSTOM_ATTRIBUTES.md)** - Guide des attributs personnalisés par traits
- **[FRAGMENTS.md](FRAGMENTS.md)** - Structure des fragments générés

### Specialized Examples (Exemples Spécialisés)
- **[ApiExtensions.php](ApiExtensions.php)** - Extensions API avancées
- **[AutoValidationExample.php](AutoValidationExample.php)** - Validation automatique
- **[CustomFieldTypesValidationExample.php](CustomFieldTypesValidationExample.php)** - Validation personnalisée
- **[LoggingExample.php](LoggingExample.php)** - Système de logging
- **[SecurityUsageExamples.php](SecurityUsageExamples.php)** - Sécurité et validation
- **[YamlOptimizationExamples.php](YamlOptimizationExamples.php)** - Optimisation YAML
- **[SchemaOptimizationUsage.php](SchemaOptimizationUsage.php)** - Optimisation de schémas

## 🎯 Exemples par Cas d'Usage

### 1. Débuter avec les Traits
```bash
# Commencer par comprendre l'architecture par traits
php examples/TraitBasedPluginExample.php

# Voir l'intégration complète avec traits
php examples/IntegrationExample.php
```

### 2. Créer un Plugin avec Traits
Consultez `TraitBasedPluginExample.php` pour des exemples complets de plugins utilisant l'architecture par traits :

```php
// Configuration de traits dans un plugin
$this->customAttributeConfig = [
    'timeout' => [
        'type' => 'integer',
        'min' => 1,
        'max' => 300,
        'default' => 30,
        'validator' => function($value): array {
            // Logique de validation personnalisée
            return [];
        }
    ]
];
```

### 3. Utiliser l'API SchemaService avec Traits
```bash
# API complète avec exemples de traits
php examples/SchemaServiceApiExample.php
```

### 4. Configuration Avancée de Champs
Consultez `CUSTOM_ATTRIBUTES.md` pour des exemples de configuration de traits :

```yaml
# YAML avec traits de configuration
core:
  model: User
  fields:
    homepage:
      type: url
      # Traits de configuration
      schemes: ["https", "http"]
      verify_ssl: true
      timeout: 45
      domain_whitelist: ["trusted.com"]
```

## 🧩 Types de Traits Disponibles

### Traits de Validation
- **Type traits** : `string`, `integer`, `boolean`, `array`
- **Constraint traits** : `min`, `max`, `required`, `enum`
- **Custom validator traits** : Validation personnalisée via callbacks

### Traits de Transformation
- **Value transformation** : Modification automatique des valeurs
- **Default value traits** : Application de valeurs par défaut intelligentes
- **Format traits** : Formatage automatique des données

### Traits de Comportement
- **Storage traits** : Configuration de stockage (disk, path, encryption)
- **Processing traits** : Traitement automatique (compression, thumbnails)
- **Security traits** : Validation de sécurité (virus scan, SSL verification)

## 📖 Documentation Complète

### Guides Principaux
- **[README.md](../README.md)** - Introduction générale avec architecture par traits
- **[docs/FIELD_TYPE_PLUGINS.md](../docs/FIELD_TYPE_PLUGINS.md)** - Guide complet des plugins par traits
- **[docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md)** - Architecture trait-enhanced
- **[docs/CUSTOM_FIELD_TYPES_VALIDATION.md](../docs/CUSTOM_FIELD_TYPES_VALIDATION.md)** - Validation par traits

### Exemples de Plugins Modernes
1. **UrlFieldTypePlugin** - Plugin URL avec traits de sécurité et validation
2. **FileUploadFieldTypePlugin** - Upload de fichiers avec traits de stockage et sécurité
3. **GeographicCoordinatesFieldTypePlugin** - Coordonnées géographiques avec traits de validation
4. **JsonSchemaFieldTypePlugin** - Validation JSON Schema avec traits configurables

## 🚀 Démarrage Rapide

### 1. Installation et Configuration
```bash
composer require grazulex/laravel-modelschema
```

### 2. Enregistrement de Plugins par Traits
```php
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

$manager = new FieldTypePluginManager();
$manager->registerPlugin(new UrlFieldTypePlugin());
$manager->registerPlugin(new FileUploadFieldTypePlugin());
```

### 3. Utilisation dans un Schema YAML
```yaml
core:
  model: Website
  table: websites
  fields:
    logo:
      type: file_upload
      # Configuration par traits
      allowed_extensions: ["jpg", "png"]
      max_file_size: "2MB"
      auto_optimize: true
      generate_thumbnails:
        small: "150x150"
        medium: "300x300"
```

### 4. Génération de Fragments
```php
$schemaService = new SchemaService();
$generationData = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);

// Les fragments incluent maintenant le traitement des traits
$modelFragment = json_decode($generationData['generation_data']['model']['json'], true);
```

## 🔄 Migration depuis l'Ancien Système

Si vous utilisez l'ancien système de field types, consultez :
- **[docs/MIGRATION.md](../docs/MIGRATION.md)** - Guide de migration
- **[UrlFieldType.php](UrlFieldType.php)** - Exemple legacy pour référence

### Avantages de l'Architecture par Traits
✅ **Flexibilité** : Configuration modulaire et réutilisable  
✅ **Validation avancée** : Validation par trait avec logique métier  
✅ **Extensibilité** : Nouveaux traits ajoutables sans modification du core  
✅ **Maintenabilité** : Séparation claire des responsabilités  
✅ **Performance** : Traitement optimisé des configurations  

## 🤝 Contribuer

Pour contribuer de nouveaux exemples ou améliorer les existants :

1. Créez des exemples utilisant l'architecture par traits
2. Documentez les nouveaux traits et leur usage
3. Ajoutez des tests pour valider les exemples
4. Suivez les conventions de nommage établies

## 📞 Support

- **Issues** : [GitHub Issues](https://github.com/Grazulex/laravel-modelschema/issues)
- **Discussions** : [GitHub Discussions](https://github.com/Grazulex/laravel-modelschema/discussions)
- **Documentation** : [Wiki](https://github.com/Grazulex/laravel-modelschema/wiki)

---

**🎯 L'architecture par traits de Laravel ModelSchema offre une approche moderne et flexible pour la génération de schémas. Explorez les exemples pour découvrir toutes les possibilités !**