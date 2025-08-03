# Custom Field Types Validation with Trait-Based Architecture

The Laravel ModelSchema package includes a robust validation system for custom field types, now extended with a **trait-based architecture** that enables validation of complex and modular configurations.

## New: Trait-Based Validation System

### Overview
The trait-based architecture revolutionizes validation by enabling:

1. **Modular validation**: Each trait can have its own validation rules
2. **Cross-trait validation**: Traits can interact for contextual validation  
3. **Dynamic validation**: Rules adapt based on trait configuration
4. **Layered validation**: Type, constraints, business logic, and custom validation

### Trait-Based Validation Configuration
```php
// Dans un plugin FieldTypePlugin
$this->customAttributeConfig = [
    'timeout' => [
        'type' => 'integer',           // Type validation
        'min' => 1,                    // Minimum constraint
        'max' => 300,                  // Maximum constraint
        'required' => false,           // Required
        'validator' => function($value): array {  // Custom validation
            if ($value > 60 && !extension_loaded('curl')) {
                return ['Timeout > 60s requires curl extension'];
            }
            return [];
        }
    ],
    'schemes' => [
        'type' => 'array',
        'enum' => ['http', 'https', 'ftp'],    // Enumeration validation
        'validator' => function($schemes): array {
            $errors = [];
            if (in_array('ftp', $schemes) && !in_array('ftps', $schemes)) {
                $errors[] = 'FTP should be paired with FTPS for security';
            }
            return $errors;
        }
    ]
];
```

### Plugin Example with Trait-Based Validation
```php
class AdvancedUrlFieldTypePlugin extends FieldTypePlugin
{
    public function validate(array $config): array
    {
        $errors = [];
        
        // Cross-trait validation
        if (($config['verify_ssl'] ?? true) && in_array('http', $config['schemes'] ?? [])) {
            $errors[] = 'SSL verification cannot be enabled with HTTP scheme';
        }
        
        // Conditional validation based on traits
        if (($config['virus_scan'] ?? false) && !in_array($config['storage_disk'] ?? 'local', ['local', 's3'])) {
            $errors[] = 'Virus scanning requires local or S3 storage';
        }
        
        return $errors;
    }
    
    // Individual trait validations are automatic
}
```

## Supported Custom Field Types (Legacy + Traits)

### Enumeration Fields

#### Enum
```yaml
fields:
  status:
    type: enum
    values: ['active', 'inactive', 'pending']
    default: 'active'
```

**Automatic validations:**
- Required presence of `values` array
- Values must be strings or numbers
- No duplicate values
- Default value must be in the values array
- Warning if less than 2 values (boolean suggestion)
- Warning if more than 100 values (lookup table suggestion)

#### Set / Multi-Select
```yaml
fields:
  permissions:
    type: set
    values: ['read', 'write', 'delete', 'admin']
    default: ['read', 'write']
```

**Automatic validations:**
- Inherits all enum validations
- Maximum 64 values (MySQL SET limitation)
- Default value can be an array or comma-separated string
- Validation that all default values are in the values array

### Geometric Fields

#### Point
```yaml
fields:
  location:
    type: point
    srid: 4326
    dimension: 2
    coordinate_system: geographic
```

**Validations automatiques :**
- SRID doit être un nombre positif
- Dimension doit être 2, 3, ou 4
- Système de coordonnées doit être 'cartesian' ou 'geographic'
- Avertissement pour des SRID non-standards (différents de 4326 ou 3857)

#### Geometry
```yaml
fields:
  shape:
    type: geometry
    srid: 3857
```

**Validations automatiques :**
- Mêmes validations que Point
- Support pour tous types de géométries

#### Polygon
```yaml
fields:
  boundary:
    type: polygon
    srid: 4326
    min_points: 3
    max_points: 1000
```

**Validations automatiques :**
- Mêmes validations que Point
- `min_points` doit être >= 3
- `max_points` doit être >= `min_points`

## Validation des Attributs de Champs

### Attributs de Longueur
```yaml
fields:
  name:
    type: string
    length: 255
```

**Validations :**
- Longueur doit être un nombre positif
- Avertissement si longueur > 65535 (suggestion TEXT)
- Vérification de compatibilité avec le type de champ

### Attributs de Précision
```yaml
fields:
  price:
    type: decimal
    precision: 10
    scale: 2
```

**Validations :**
- Précision entre 1 et 65
- Échelle entre 0 et 30
- Échelle <= précision
- Vérification de compatibilité avec le type de champ

### Attributs Non Signés
```yaml
fields:
  count:
    type: integer
    unsigned: true
```

**Validations :**
- Vérification de compatibilité avec les types numériques

## Usage API

### Via SchemaService

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Multiple schemas validation
$schemas = [
    (object) [
        'name' => 'User',
        'fields' => [
            (object) [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'inactive'],
                'default' => 'active'
            ]
        ]
    ]
];

$result = $schemaService->validateCustomFieldTypes($schemas);

if ($result['is_valid']) {
    echo "Validation successful!";
} else {
    foreach ($result['errors'] as $error) {
        echo "Erreur : $error\n";
    }
}
```

### Via Fichiers YAML

```php
// Single file validation
$result = $schemaService->validateCustomFieldTypesFromFile('schema.yaml');

// Multiple files validation
$result = $schemaService->validateCustomFieldTypesFromFiles([
    'user.schema.yaml',
    'product.schema.yaml'
]);
```

## Structure de Réponse

La validation retourne un tableau structuré :

```php
[
    'is_valid' => true,              // Statut global
    'errors' => [],                  // Erreurs de validation
    'warnings' => [],                // Avertissements
    'custom_type_stats' => [         // Usage statistics
        'enum' => 2,
        'set' => 1,
        'point' => 3
    ],
    'available_custom_types' => [...], // Types disponibles
    'validation_summary' => [          // Résumé de la validation
        'total_fields_validated' => 15,
        'custom_fields_found' => 6,
        'unique_custom_types' => 3,
        'errors_found' => 0,
        'warnings_found' => 2
    ]
]
```

## Types d'Erreurs

### Erreurs de Configuration
- Champs enum sans tableau `values`
- Valeurs dupliquées dans enum/set
- Valeurs par défaut invalides
- SRID négatif ou invalide
- Dimensions invalides pour les champs géométriques

### Erreurs d'Attributs
- Longueur négative ou nulle
- Précision/échelle hors limites
- Échelle supérieure à la précision
- Attributs incompatibles avec le type de champ

### Erreurs de Types
- Types de champs personnalisés inexistants
- Types ni built-in ni personnalisés

## Avertissements

### Avertissements de Performance
- Champs enum avec trop de valeurs (> 100)
- Champs set avec plus de 64 valeurs
- Longueurs de chaînes très importantes

### Avertissements de Configuration
- SRID non-standards pour les champs géométriques
- Champs enum avec moins de 2 valeurs
- Attributs inutiles pour certains types

## Integration with Logging

All validations are automatically logged:

```php
// Les métriques incluent :
// - Number of validated schemas
// - Number of processed fields
// - Types personnalisés utilisés
// - Temps de validation
// - Erreurs et avertissements détectés
```

## Extension

Le système peut être étendu pour supporter de nouveaux types personnalisés :

1. Ajouter le type à `getAvailableCustomFieldTypes()`
2. Implémenter la validation dans `validateCustomFieldTypeConfiguration()`
3. Ajouter des tests spécifiques

## Exemple Complet

```php
<?php

require_once 'vendor/autoload.php';

use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

$schemas = [
    (object) [
        'name' => 'User',
        'fields' => [
            (object) [
                'name' => 'status',
                'type' => 'enum',
                'values' => ['active', 'inactive', 'pending'],
                'default' => 'active'
            ],
            (object) [
                'name' => 'permissions',
                'type' => 'set',
                'values' => ['read', 'write', 'delete', 'admin'],
                'default' => ['read']
            ]
        ]
    ],
    (object) [
        'name' => 'Location',
        'fields' => [
            (object) [
                'name' => 'coordinates',
                'type' => 'point',
                'srid' => 4326,
                'dimension' => 2
            ],
            (object) [
                'name' => 'area',
                'type' => 'polygon',
                'srid' => 4326,
                'min_points' => 3
            ]
        ]
    ]
];

$result = $schemaService->validateCustomFieldTypes($schemas);

echo "Validation : " . ($result['is_valid'] ? "✅ Réussie" : "❌ Échouée") . "\n";
echo "Champs validés : " . $result['validation_summary']['total_fields_validated'] . "\n";
echo "Types personnalisés : " . $result['validation_summary']['unique_custom_types'] . "\n";

if (!empty($result['errors'])) {
    echo "\nErreurs :\n";
    foreach ($result['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (!empty($result['warnings'])) {
    echo "\nAvertissements :\n";
    foreach ($result['warnings'] as $warning) {
        echo "  - $warning\n";
    }
}
```

This functionality ensures robustness and reliability of schema definitions by detecting configuration errors before code generation.
