# Système de Plugins pour Types de Champs Personnalisés

Le système de plugins de Laravel ModelSchema permet aux développeurs de créer et d'enregistrer facilement leurs propres types de champs personnalisés. Cette fonctionnalité étend considérablement les capacités du package en permettant l'ajout de types de champs spécialisés sans modifier le code core.

## Vue d'ensemble

Le système de plugins fournit :

- **FieldTypePlugin** : Classe de base abstraite pour créer des plugins
- **FieldTypePluginManager** : Gestionnaire pour l'enregistrement et la gestion des plugins
- **Auto-découverte** : Chargement automatique des plugins depuis des répertoires
- **Validation** : Validation automatique des plugins avant enregistrement
- **Métadonnées** : Système riche de métadonnées pour les plugins
- **Configuration** : Configuration flexible des plugins via des fichiers ou des APIs

## Architecture

### Classes principales

#### FieldTypePlugin (Classe abstraite)

Classe de base pour tous les plugins de types de champs. Étend `FieldTypeInterface` avec des fonctionnalités supplémentaires :

```php
abstract class FieldTypePlugin implements FieldTypeInterface
{
    // Métadonnées du plugin
    protected string $version = '1.0.0';
    protected string $author = '';
    protected string $description = '';
    protected bool $enabled = true;
    protected array $dependencies = [];
    protected array $config = [];
    
    // Méthodes abstraites à implémenter
    abstract public function getType(): string;
    abstract public function getAliases(): array;
    abstract public function validate(array $config): array;
    // ... autres méthodes FieldTypeInterface
}
```

#### FieldTypePluginManager

Gestionnaire central pour les plugins :

```php
class FieldTypePluginManager
{
    public function registerPlugin(FieldTypeInterface $plugin): void;
    public function unregisterPlugin(string $type): void;
    public function getPlugin(string $type): ?FieldTypeInterface;
    public function hasPlugin(string $type): bool;
    public function discoverPlugins(): array;
    public function loadFromConfig(array $config): void;
}
```

## Création d'un Plugin

### 1. Plugin Simple : UrlFieldType

```php
<?php

namespace LaravelModelSchema\Examples;

use LaravelModelSchema\Support\FieldTypePlugin;

class UrlFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.0.0';
    protected string $author = 'Laravel ModelSchema Team';
    protected string $description = 'Field type for validating and storing URLs';
    
    public function getType(): string
    {
        return 'url';
    }
    
    public function getAliases(): array
    {
        return ['website', 'link', 'uri'];
    }
    
    public function validate(array $config): array
    {
        $errors = [];
        
        if (isset($config['max_length'])) {
            if (!is_int($config['max_length']) || $config['max_length'] < 1) {
                $errors[] = 'max_length must be a positive integer';
            }
        }
        
        if (isset($config['default']) && !filter_var($config['default'], FILTER_VALIDATE_URL)) {
            $errors[] = 'default value must be a valid URL';
        }
        
        return $errors;
    }
    
    public function getCastType(array $config): ?string
    {
        return 'string';
    }
    
    public function getValidationRules(array $config): array
    {
        $rules = ['url'];
        
        if (isset($config['max_length'])) {
            $rules[] = 'max:' . $config['max_length'];
        }
        
        if (isset($config['nullable']) && $config['nullable']) {
            $rules[] = 'nullable';
        } else {
            $rules[] = 'required';
        }
        
        return $rules;
    }
    
    public function getMigrationParameters(array $config): array
    {
        return [
            'length' => $config['max_length'] ?? 255,
            'nullable' => $config['nullable'] ?? false,
            'default' => $config['default'] ?? null,
        ];
    }
    
    public function transformConfig(array $config): array
    {
        if (!isset($config['max_length'])) {
            $config['max_length'] = 255;
        }
        
        return $config;
    }
    
    public function getMigrationCall(string $fieldName, array $config): string
    {
        $length = $config['max_length'] ?? 255;
        $call = "\$table->string('{$fieldName}', {$length})";
        
        if (isset($config['nullable']) && $config['nullable']) {
            $call .= '->nullable()';
        }
        
        if (isset($config['default'])) {
            $call .= "->default('{$config['default']}')";
        }
        
        return $call;
    }
    
    public function getSupportedAttributesList(): array
    {
        return ['nullable', 'default', 'max_length', 'schemes'];
    }
}
```

### 2. Plugin Avancé : JsonSchemaFieldType

```php
<?php

namespace LaravelModelSchema\Examples;

use LaravelModelSchema\Support\FieldTypePlugin;

class JsonSchemaFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.1.0';
    protected string $description = 'Field type for storing and validating JSON against a schema';
    protected array $dependencies = ['json'];
    
    public function getType(): string
    {
        return 'json_schema';
    }
    
    public function getAliases(): array
    {
        return ['structured_json', 'validated_json', 'schema_json'];
    }
    
    public function validate(array $config): array
    {
        $errors = [];
        
        // Schema is required
        if (!isset($config['schema'])) {
            $errors[] = 'schema configuration is required for json_schema fields';
        } elseif (!is_array($config['schema'])) {
            $errors[] = 'schema must be a valid array/object';
        } else {
            $schemaErrors = $this->validateJsonSchema($config['schema']);
            $errors = array_merge($errors, $schemaErrors);
        }
        
        return $errors;
    }
    
    public function getValidationRules(array $config): array
    {
        $rules = ['json'];
        
        if (isset($config['schema'])) {
            $rules[] = function ($attribute, $value, $fail) use ($config) {
                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fail("The {$attribute} must be valid JSON.");
                    return;
                }
                
                $errors = $this->validateValueAgainstSchema($decoded, $config['schema']);
                if (!empty($errors)) {
                    $fail("The {$attribute} does not match the required schema: " . implode(', ', $errors));
                }
            };
        }
        
        return $rules;
    }
    
    // Méthodes de validation de schéma...
    protected function validateJsonSchema(array $schema): array { /* ... */ }
    protected function validateValueAgainstSchema(mixed $value, array $schema): array { /* ... */ }
}
```

## Enregistrement de Plugins

### 1. Enregistrement Manuel

```php
use LaravelModelSchema\Support\FieldTypePluginManager;
use LaravelModelSchema\Examples\UrlFieldTypePlugin;

$manager = new FieldTypePluginManager();
$plugin = new UrlFieldTypePlugin();

$manager->registerPlugin($plugin);
```

### 2. Enregistrement via Configuration

```php
$config = [
    'plugins' => [
        [
            'class' => UrlFieldTypePlugin::class,
            'config' => [
                'enabled' => true,
                'custom_setting' => 'value'
            ]
        ]
    ]
];

$manager->loadFromConfig($config);
```

### 3. Auto-découverte

```php
$config = [
    'auto_discovery' => true,
    'plugin_directories' => [
        '/path/to/plugins',
        '/another/plugin/directory'
    ]
];

$manager->loadFromConfig($config);
$discoveredPlugins = $manager->discoverPlugins();
```

## Gestion des Plugins

### Métadonnées des Plugins

```php
// Obtenir les métadonnées d'un plugin
$metadata = $manager->getPluginMetadata('url');

/*
Retourne :
[
    'name' => 'url',
    'version' => '1.0.0',
    'author' => 'Laravel ModelSchema Team',
    'description' => 'Field type for validating and storing URLs',
    'enabled' => true,
    'dependencies' => [],
    'aliases' => ['website', 'link', 'uri'],
    'supported_databases' => ['mysql', 'postgresql', 'sqlite'],
    'attributes' => ['nullable', 'default', 'max_length', 'schemes']
]
*/
```

### Activation/Désactivation

```php
// Désactiver un plugin
$manager->disablePlugin('url');

// Réactiver un plugin
$manager->enablePlugin('url');

// Obtenir seulement les plugins activés
$enabledPlugins = $manager->getEnabledPlugins();
```

### Gestion des Dépendances

Le système valide automatiquement les dépendances :

```php
// Ce plugin nécessite le type 'json'
class JsonSchemaFieldTypePlugin extends FieldTypePlugin
{
    protected array $dependencies = ['json'];
    
    // Si 'json' n'est pas disponible, l'enregistrement échouera
}
```

## Configuration des Plugins

### Schema de Configuration

```php
class UrlFieldTypePlugin extends FieldTypePlugin
{
    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'max_length' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 2048,
                    'default' => 255
                ],
                'schemes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['http', 'https', 'ftp', 'ftps', 'file']
                    ],
                    'default' => ['http', 'https']
                ]
            ]
        ];
    }
}
```

### Configuration Avancée

```php
// Configuration du plugin
$plugin->setConfig([
    'max_length' => 500,
    'schemes' => ['https'],
    'custom_validation' => true
]);

// Obtenir une valeur de configuration
$maxLength = $plugin->getConfigValue('max_length', 255);

// Obtenir toute la configuration
$config = $plugin->getConfig();
```

## Utilisation avec SchemaService

Une fois les plugins enregistrés, ils deviennent automatiquement disponibles dans les schémas YAML :

```yaml
models:
  User:
    fields:
      website:
        type: url
        max_length: 500
        schemes: [https]
        nullable: true
      
      profile_data:
        type: json_schema
        schema:
          type: object
          properties:
            name:
              type: string
            preferences:
              type: object
          required: [name]
```

## Exemples d'Utilisation

### Plugin Currency

```php
class CurrencyFieldTypePlugin extends FieldTypePlugin
{
    public function getType(): string
    {
        return 'currency';
    }
    
    public function getAliases(): array
    {
        return ['money', 'price', 'amount'];
    }
    
    public function validate(array $config): array
    {
        $errors = [];
        
        if (isset($config['currency']) && !in_array($config['currency'], ['USD', 'EUR', 'GBP'], true)) {
            $errors[] = 'Unsupported currency';
        }
        
        return $errors;
    }
    
    public function getValidationRules(array $config): array
    {
        return ['numeric', 'min:0'];
    }
    
    public function getMigrationCall(string $fieldName, array $config): string
    {
        $precision = $config['precision'] ?? 8;
        $scale = $config['scale'] ?? 2;
        
        return "\$table->decimal('{$fieldName}', {$precision}, {$scale})";
    }
}
```

### Plugin Enum avec Validation

```php
class ValidatedEnumFieldTypePlugin extends FieldTypePlugin
{
    public function getType(): string
    {
        return 'validated_enum';
    }
    
    public function validate(array $config): array
    {
        $errors = [];
        
        if (!isset($config['values']) || !is_array($config['values'])) {
            $errors[] = 'values array is required';
        }
        
        if (isset($config['validation_rules'])) {
            foreach ($config['validation_rules'] as $rule) {
                if (!is_string($rule)) {
                    $errors[] = 'validation_rules must be an array of strings';
                }
            }
        }
        
        return $errors;
    }
    
    public function getValidationRules(array $config): array
    {
        $rules = [];
        
        if (isset($config['values'])) {
            $rules[] = 'in:' . implode(',', $config['values']);
        }
        
        if (isset($config['validation_rules'])) {
            $rules = array_merge($rules, $config['validation_rules']);
        }
        
        return $rules;
    }
}
```

## Tests

Le système de plugins inclut des tests complets :

```php
// Test d'enregistrement
public function test_plugin_registration(): void
{
    $manager = new FieldTypePluginManager();
    $plugin = new UrlFieldTypePlugin();
    
    $manager->registerPlugin($plugin);
    
    $this->assertTrue($manager->hasPlugin('url'));
    $this->assertTrue(FieldTypeRegistry::has('url'));
}

// Test de validation
public function test_plugin_validation(): void
{
    $plugin = new UrlFieldTypePlugin();
    
    $errors = $plugin->validate(['max_length' => -1]);
    $this->assertContains('max_length must be a positive integer', $errors);
}
```

## Bonnes Pratiques

### 1. Validation Rigoureuse

```php
public function validate(array $config): array
{
    $errors = [];
    
    // Valider tous les paramètres
    // Fournir des messages d'erreur clairs
    // Vérifier les types et les plages
    
    return $errors;
}
```

### 2. Configuration par Défaut

```php
public function transformConfig(array $config): array
{
    // Définir des valeurs par défaut sensées
    if (!isset($config['max_length'])) {
        $config['max_length'] = 255;
    }
    
    return $config;
}
```

### 3. Documentation des Métadonnées

```php
protected string $description = 'Description claire du plugin et de son utilisation';
protected string $author = 'Nom de l\'auteur ou de l\'équipe';
protected string $version = '1.0.0'; // Versioning sémantique
```

### 4. Support Multi-Base de Données

```php
public function getSupportedDatabases(): array
{
    // Retourner seulement les bases supportées
    return ['mysql', 'postgresql'];
}

public function getMigrationCall(string $fieldName, array $config): string
{
    // Adapter selon la base de données si nécessaire
    $database = config('database.default');
    
    if ($database === 'postgresql') {
        return "\$table->jsonb('{$fieldName}')";
    }
    
    return "\$table->json('{$fieldName}')";
}
```

## Conclusion

Le système de plugins de Laravel ModelSchema offre une extensibilité puissante et flexible pour créer des types de champs personnalisés. Il permet aux développeurs d'étendre les capacités du package tout en maintenant la cohérence et la qualité du code grâce à la validation automatique et aux tests intégrés.
