# Système de Plugins pour Types de Champs Personnalisés

Le système de plugins de Laravel ModelSchema permet aux développeurs de créer et d'enregistrer facilement leurs propres types de champs personnalisés en utilisant une **architecture basée sur les traits**. Cette approche moderne étend considérablement les capacités du package en permettant l'ajout de types de champs spécialisés avec une configuration flexible et modulaire.

## Vue d'ensemble

Le système de plugins basé sur les traits fournit :

- **FieldTypePlugin** : Classe de base abstraite utilisant des traits de configuration
- **FieldTypePluginManager** : Gestionnaire pour l'enregistrement et la gestion des plugins
- **Architecture par traits** : Configuration modulaire via des tableaux de traits
- **Auto-découverte** : Chargement automatique des plugins depuis des répertoires
- **Validation avancée** : Validation automatique des plugins et de leurs traits avant enregistrement  
- **Métadonnées enrichies** : Système riche de métadonnées pour les plugins avec support des traits
- **Configuration flexible** : Configuration des traits via des fichiers ou des APIs

## Architecture Basée sur les Traits

### Concepts Clés

L'architecture par traits permet de définir les options et comportements des champs à travers des **objets de configuration** plutôt que des propriétés codées en dur. Cela offre :

1. **Flexibilité** : Les traits peuvent être combinés et réutilisés
2. **Validation dynamique** : Chaque trait peut avoir ses propres règles
3. **Extensibilité** : Nouveaux traits ajoutables sans modification du core
4. **Modularité** : Separation claire des responsabilités

### Classes principales

#### FieldTypePlugin (Classe abstraite avec traits)

Classe de base pour tous les plugins de types de champs. Utilise une approche par traits pour la configuration :

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
    
    // Configuration par traits
    protected array $customAttributes = [];           // Liste des traits supportés
    protected array $customAttributeConfig = [];     // Configuration de chaque trait
    
    // Méthodes abstraites à implémenter
    abstract public function getType(): string;
    abstract public function getAliases(): array;
    abstract public function validate(array $config): array;
    // ... autres méthodes FieldTypeInterface
}
```

#### FieldTypePluginManager (avec support des traits)

Gestionnaire central pour les plugins avec support complet de l'architecture par traits :

```php
class FieldTypePluginManager
{
    public function registerPlugin(FieldTypeInterface $plugin): void;
    public function unregisterPlugin(string $type): void;
    public function getPlugin(string $type): ?FieldTypeInterface;
    public function hasPlugin(string $type): bool;
    public function discoverPlugins(): array;
    public function loadFromConfig(array $config): void;
    
    // Nouveau : Support des traits
    public function validatePluginTraits(FieldTypePlugin $plugin): array;
    public function mergeTraitConfigurations(array $baseConfig, array $traitConfig): array;
}
```

## Configuration par Traits

### Principe de Base

Au lieu de définir des propriétés fixes, les plugins utilisent des **traits de configuration** définis dans des tableaux :

```php
// Ancien système (rigide)
protected array $specificAttributes = ['url_only', 'max_length'];

// Nouveau système par traits (flexible)
protected array $customAttributes = ['schemes', 'verify_ssl', 'timeout'];
protected array $customAttributeConfig = [
    'schemes' => [
        'type' => 'array',
        'default' => ['http', 'https'],
        'enum' => ['http', 'https', 'ftp', 'ftps'],
        'description' => 'Protocoles autorisés'
    ],
    // ... autres traits
];
```

### Types de Traits Disponibles

#### 1. Traits de Validation de Type
```php
'timeout' => [
    'type' => 'integer',        // Trait de type
    'min' => 1,                 // Trait de contrainte minimum
    'max' => 300,               // Trait de contrainte maximum
    'default' => 30             // Trait de valeur par défaut
]
```

#### 2. Traits d'Énumération
```php
'schemes' => [
    'type' => 'array',
    'enum' => ['http', 'https', 'ftp', 'ftps'],  // Trait d'énumération
    'default' => ['http', 'https']
]
```

#### 3. Traits de Validation Personnalisée
```php
'domain_whitelist' => [
    'type' => 'array',
    'validator' => function ($value): array {     // Trait de validation custom
        if (!is_array($value)) return ['must be an array'];
        foreach ($value as $domain) {
            if (!filter_var("http://{$domain}", FILTER_VALIDATE_URL)) {
                return ["Invalid domain: {$domain}"];
            }
        }
        return [];
    }
]
```

#### 4. Traits de Transformation
```php
'max_length' => [
    'type' => 'integer',
    'transform' => function ($value) {           // Trait de transformation
        return max(1, min(255, (int) $value));  // Force entre 1 et 255
    }
]
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
## Création d'un Plugin avec Architecture par Traits

### 1. Plugin Moderne : UrlFieldTypePlugin

```php
<?php

namespace Grazulex\LaravelModelschema\Examples;

use Grazulex\LaravelModelschema\Support\FieldTypePlugin;

class UrlFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.0.0';
    protected string $author = 'Laravel ModelSchema Team';
    protected string $description = 'Field type for validating and storing URLs with trait-based configuration';
    
    public function __construct()
    {
        // Définir les traits supportés par ce plugin
        $this->customAttributes = [
            'schemes',
            'verify_ssl', 
            'allow_query_params',
            'max_redirects',
            'timeout',
            'domain_whitelist',
            'domain_blacklist'
        ];
        
        // Configuration de chaque trait avec validation et comportement
        $this->customAttributeConfig = [
            'schemes' => [
                'type' => 'array',
                'required' => false,
                'default' => ['http', 'https'],
                'enum' => ['http', 'https', 'ftp', 'ftps', 'file'],
                'description' => 'Protocoles URL autorisés pour la validation'
            ],
            'verify_ssl' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Activer la vérification du certificat SSL'
            ],
            'allow_query_params' => [
                'type' => 'boolean',
                'required' => false, 
                'default' => true,
                'description' => 'Autoriser les paramètres de requête dans l\'URL'
            ],
            'max_redirects' => [
                'type' => 'integer',
                'required' => false,
                'min' => 0,
                'max' => 10,
                'default' => 3,
                'description' => 'Nombre maximum de redirections autorisées'
            ],
            'timeout' => [
                'type' => 'integer',
                'required' => false,
                'min' => 1,
                'max' => 300,
                'default' => 30,
                'transform' => function ($value) {
                    // Trait de transformation : force la valeur dans la plage valide
                    return max(1, min(300, (int) $value));
                },
                'description' => 'Timeout de connexion en secondes'
            ],
            'domain_whitelist' => [
                'type' => 'array',
                'required' => false,
                'validator' => function ($value): array {
                    // Trait de validation personnalisée pour liste de domaines
                    if (!is_array($value)) {
                        return ['domain_whitelist must be an array'];
                    }
                    foreach ($value as $domain) {
                        if (!is_string($domain) || !filter_var("http://{$domain}", FILTER_VALIDATE_URL)) {
                            return ["Invalid domain in whitelist: {$domain}"];
                        }
                    }
                    return [];
                },
                'description' => 'Liste blanche des domaines autorisés'
            ],
            'domain_blacklist' => [
                'type' => 'array',
                'required' => false,
                'validator' => function ($value): array {
                    // Même trait de validation que whitelist mais pour blacklist
                    if (!is_array($value)) {
                        return ['domain_blacklist must be an array'];
                    }
                    foreach ($value as $domain) {
                        if (!is_string($domain) || !filter_var("http://{$domain}", FILTER_VALIDATE_URL)) {
                            return ["Invalid domain in blacklist: {$domain}"];
                        }
                    }
                    return [];
                },
                'description' => 'Liste noire des domaines interdits'
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
    
    public function validate(array $config): array
    {
        $errors = [];
        
        // Validation de base du champ URL
        if (isset($config['default']) && !filter_var($config['default'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Default value must be a valid URL';
        }
        
        // Les traits personnalisés sont validés automatiquement par le système
        
        return $errors;
    }
    
    public function getCastType(array $config): ?string
    {
        return 'string';
    }
    
    public function getValidationRules(array $config): array
    {
        $rules = ['url'];
        
        // Utiliser les traits pour construire les règles de validation
        if (isset($config['schemes'])) {
            $schemes = implode(',', $config['schemes']);
            $rules[] = "url:schemes:{$schemes}";
        }
        
        return $rules;
    }
    
    public function getMigrationParameters(array $config): array
    {
        return [
            'type' => 'string',
            'length' => $config['max_length'] ?? 2048
        ];
    }
    
    public function transformConfig(array $config): array
    {
        // Les transformations des traits sont appliquées automatiquement
        return $this->processCustomAttributes($config);
    }
    
    public function getMigrationCall(array $config): string
    {
        $length = $config['max_length'] ?? 2048;
        return "string('{$config['name']}', {$length})";
    }
}
```
    
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

## ✨ Système d'Attributs Custom

### Vue d'ensemble

Le système d'attributs custom permet aux plugins de définir des attributs spécifiques au-delà des attributs Laravel standards. Ces attributs peuvent avoir leur propre validation, valeurs par défaut et logique de transformation.

### Définition des Attributs Custom

#### Structure de Base

```php
class UrlFieldTypePlugin extends FieldTypePlugin
{
    public function __construct()
    {
        // Définir les attributs custom supportés
        $this->customAttributes = [
            'schemes',
            'verify_ssl',
            'allow_query_params',
            'max_redirects',
            'timeout',
            'domain_whitelist',
            'domain_blacklist'
        ];

        // Configuration détaillée pour chaque attribut
        $this->customAttributeConfig = [
            'schemes' => [
                'type' => 'array',
                'required' => false,
                'default' => ['http', 'https'],
                'enum' => ['http', 'https', 'ftp', 'ftps', 'file'],
                'description' => 'Schémas d\'URL autorisés pour la validation'
            ],
            'verify_ssl' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Active la vérification des certificats SSL'
            ],
            'timeout' => [
                'type' => 'integer',
                'required' => false,
                'default' => 30,
                'min' => 1,
                'max' => 300,
                'description' => 'Timeout de connexion en secondes'
            ]
        ];
    }
}
```

### Types de Validation Disponibles

#### 1. Validation de Type
```php
'type' => 'string|int|integer|float|double|bool|boolean|array|object|null|numeric'
```
Valide le type de données de l'attribut.

#### 2. Validation Requis
```php
'required' => true|false
```
Détermine si l'attribut doit être fourni.

#### 3. Valeurs par Défaut
```php
'default' => 'any_value'
```
Appliquée automatiquement si l'attribut n'est pas fourni.

#### 4. Contraintes Numériques
```php
'min' => 1,        // Valeur minimum
'max' => 100       // Valeur maximum
```
Pour les types numériques uniquement.

#### 5. Validation Enum
```php
'enum' => ['value1', 'value2', 'value3']
```
Restreint les valeurs à un ensemble spécifique. Fonctionne avec :
- **Valeurs scalaires** : Validation directe
- **Arrays** : Validation de chaque élément du tableau

#### 6. Validateurs Custom
```php
'validator' => function ($value, $attribute) {
    $errors = [];
    
    if (!$this->customValidationLogic($value)) {
        $errors[] = "Validation custom échouée pour {$attribute}";
    }
    
    return $errors; // Retourner array d'erreurs (vide = valide)
}
```

### Exemple Complet : JsonSchemaFieldTypePlugin

```php
class JsonSchemaFieldTypePlugin extends FieldTypePlugin
{
    public function __construct()
    {
        $this->customAttributes = [
            'schema',
            'strict_validation',
            'allow_additional_properties',
            'schema_format',
            'validation_mode',
            'error_format',
            'schema_cache_ttl',
            'schema_version'
        ];

        $this->customAttributeConfig = [
            'schema' => [
                'type' => 'array',
                'required' => true,
                'description' => 'Définition du JSON Schema pour validation',
                'validator' => function ($value) {
                    return $this->validateJsonSchema($value);
                }
            ],
            'strict_validation' => [
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Active le mode de validation stricte'
            ],
            'schema_format' => [
                'type' => 'string',
                'required' => false,
                'default' => 'draft-07',
                'enum' => ['draft-04', 'draft-06', 'draft-07', 'draft-2019-09', 'draft-2020-12'],
                'description' => 'Version de la spécification JSON Schema'
            ],
            'validation_mode' => [
                'type' => 'string',
                'required' => false,
                'default' => 'strict',
                'enum' => ['strict', 'loose', 'type_only'],
                'description' => 'Mode de validation des données JSON'
            ],
            'schema_cache_ttl' => [
                'type' => 'integer',
                'required' => false,
                'default' => 3600,
                'min' => 0,
                'max' => 86400,
                'description' => 'TTL du cache de schéma en secondes (0 = pas de cache)'
            ]
        ];
    }
}
```

### Utilisation dans les Schémas YAML

```yaml
core:
  model: Website
  table: websites
  fields:
    homepage:
      type: url
      nullable: false
      # Attributs Laravel standards
      max_length: 500
      # Attributs custom du UrlFieldTypePlugin
      schemes: ['https', 'http']
      verify_ssl: true
      timeout: 45
      domain_whitelist: ['example.com', 'trusted.org']
      domain_blacklist: ['malicious.com']
      
    api_config:
      type: json_schema
      nullable: true
      # Attributs custom du JsonSchemaFieldTypePlugin
      schema:
        type: object
        properties:
          endpoint:
            type: string
          method:
            type: string
            enum: ['GET', 'POST', 'PUT', 'DELETE']
          timeout:
            type: integer
            minimum: 1
            maximum: 300
        required: ['endpoint', 'method']
      strict_validation: true
      schema_format: 'draft-07'
      validation_mode: 'strict'
      schema_cache_ttl: 7200
```

### Intégration avec la Validation

Les attributs custom sont automatiquement intégrés dans le processus de validation :

```php
public function validate(array $config): array
{
    $errors = [];

    // Validation des attributs Laravel standards
    // ...

    // Validation automatique des attributs custom
    foreach ($this->getCustomAttributes() as $attribute) {
        if (isset($config[$attribute])) {
            $customErrors = $this->validateCustomAttribute($attribute, $config[$attribute]);
            $errors = array_merge($errors, $customErrors);
        }
    }

    // Vérification des attributs requis manquants
    $missingRequired = $this->getMissingRequiredCustomAttributes($config);
    if (!empty($missingRequired)) {
        $errors[] = 'Attributs requis manquants : ' . implode(', ', $missingRequired);
    }

    return $errors;
}
```

### Transformation et Processing

Les attributs custom peuvent être transformés automatiquement :

```php
// Application automatique des valeurs par défaut
$processedConfig = $plugin->processCustomAttributes($config);

// Les valeurs par défaut sont appliquées pour les attributs non fournis
// Exemple : si 'timeout' n'est pas fourni, il sera défini à 30 (sa valeur par défaut)
```

### Ordre de Validation

1. **Validation de type** (prioritaire, arrête si échec)
2. **Validation requis**
3. **Validation min/max** (pour les numériques)
4. **Validation enum**
5. **Validateurs custom**

### Méthodes API Disponibles

```php
// Récupérer les attributs custom supportés
$customAttributes = $plugin->getCustomAttributes();

// Valider un attribut custom spécifique
$errors = $plugin->validateCustomAttribute('timeout', 45);

// Traiter les attributs custom (appliquer défauts, etc.)
$processedConfig = $plugin->processCustomAttributes($fieldConfig);

// Récupérer les attributs requis manquants
$missing = $plugin->getMissingRequiredCustomAttributes($config);

// Fusion avec attributs Laravel standards
$allAttributes = $plugin->getSupportedAttributesList();
// Retourne : ['nullable', 'default', 'max_length', 'schemes', 'verify_ssl', ...]
```

### Bonnes Pratiques

#### 1. Messages d'Erreur Clairs
```php
$errors[] = "Custom attribute 'timeout' must be between 1 and 300 seconds";
```

#### 2. Documentation Complète
```php
'description' => 'Timeout de connexion en secondes. Min: 1, Max: 300, Défaut: 30'
```

#### 3. Validation Early-Return
La validation de type échoue en premier et arrête les autres validations pour éviter les erreurs en cascade.

#### 4. Performance
- Utilisez la validation built-in (type, enum, min/max) quand possible
- Les validateurs custom doivent être optimisés
- Mise en cache des validations coûteuses

#### 5. Rétrocompatibilité
- Ne changez jamais la signification d'un attribut
- Ajoutez de nouveaux attributs avec des valeurs par défaut sensées
- Utilisez le versioning sémantique

### Tests des Attributs Custom

```php
public function test_custom_attributes_validation()
{
    $plugin = new UrlFieldTypePlugin();
    
    // Test avec configuration valide
    $validConfig = [
        'schemes' => ['https', 'http'],
        'verify_ssl' => true,
        'timeout' => 30
    ];
    
    $errors = $plugin->validate($validConfig);
    $this->assertEmpty($errors);
    
    // Test avec configuration invalide
    $invalidConfig = [
        'schemes' => 'not-an-array',  // Doit être array
        'timeout' => -1               // Hors limites min
    ];
    
    $errors = $plugin->validate($invalidConfig);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString("must be of type array", $errors[0]);
    $this->assertStringContainsString("must be at least 1", $errors[1]);
}

public function test_default_values_applied()
{
    $plugin = new UrlFieldTypePlugin();
    
    $config = ['nullable' => true]; // Pas d'attributs custom
    $processed = $plugin->processCustomAttributes($config);
    
    // Les valeurs par défaut doivent être appliquées
    $this->assertEquals(['http', 'https'], $processed['schemes']);
    $this->assertTrue($processed['verify_ssl']);
    $this->assertEquals(30, $processed['timeout']);
}
```

Le système d'attributs custom offre une flexibilité maximale pour créer des types de champs sophistiqués tout en maintenant la robustesse et la facilité d'utilisation.
