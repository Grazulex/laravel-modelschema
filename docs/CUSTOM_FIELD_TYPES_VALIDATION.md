# Validation des Types de Champs Personnalisés avec Architecture par Traits

Le package Laravel ModelSchema inclut un système de validation robuste pour les types de champs personnalisés, maintenant étendu avec une **architecture par traits** qui permet de valider des configurations complexes et modulaires.

## Nouveau : Système de Validation par Traits

### Vue d'ensemble
L'architecture par traits révolutionne la validation en permettant :

1. **Validation modulaire** : Chaque trait peut avoir ses propres règles de validation
2. **Validation croisée** : Les traits peuvent interagir pour une validation contextuelle  
3. **Validation dynamique** : Les règles s'adaptent selon la configuration des traits
4. **Validation en couches** : Type, contraintes, logique métier, et validation personnalisée

### Configuration de Validation par Traits
```php
// Dans un plugin FieldTypePlugin
$this->customAttributeConfig = [
    'timeout' => [
        'type' => 'integer',           // Validation de type
        'min' => 1,                    // Contrainte minimum
        'max' => 300,                  // Contrainte maximum
        'required' => false,           // Obligation
        'validator' => function($value): array {  // Validation personnalisée
            if ($value > 60 && !extension_loaded('curl')) {
                return ['Timeout > 60s requires curl extension'];
            }
            return [];
        }
    ],
    'schemes' => [
        'type' => 'array',
        'enum' => ['http', 'https', 'ftp'],    // Validation d'énumération
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

### Exemple de Plugin avec Validation par Traits
```php
class AdvancedUrlFieldTypePlugin extends FieldTypePlugin
{
    public function validate(array $config): array
    {
        $errors = [];
        
        // Validation croisée entre traits
        if (($config['verify_ssl'] ?? true) && in_array('http', $config['schemes'] ?? [])) {
            $errors[] = 'SSL verification cannot be enabled with HTTP scheme';
        }
        
        // Validation conditionnelle basée sur les traits
        if (($config['virus_scan'] ?? false) && !in_array($config['storage_disk'] ?? 'local', ['local', 's3'])) {
            $errors[] = 'Virus scanning requires local or S3 storage';
        }
        
        return $errors;
    }
    
    // Les validations individuelles des traits sont automatiques
}
```

## Types de Champs Personnalisés Supportés (Legacy + Traits)

### Champs d'Énumération

#### Enum
```yaml
fields:
  status:
    type: enum
    values: ['active', 'inactive', 'pending']
    default: 'active'
```

**Validations automatiques :**
- Présence obligatoire du tableau `values`
- Valeurs doivent être des chaînes ou des nombres
- Pas de valeurs dupliquées
- Valeur par défaut doit être dans le tableau de valeurs
- Avertissement si moins de 2 valeurs (suggestion boolean)
- Avertissement si plus de 100 valeurs (suggestion table de lookup)

#### Set / Multi-Select
```yaml
fields:
  permissions:
    type: set
    values: ['read', 'write', 'delete', 'admin']
    default: ['read', 'write']
```

**Validations automatiques :**
- Hérite de toutes les validations d'enum
- Maximum 64 valeurs (limitation MySQL SET)
- Valeur par défaut peut être un tableau ou une chaîne séparée par des virgules
- Validation que toutes les valeurs par défaut sont dans le tableau de valeurs

### Champs Géométriques

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

## API d'Utilisation

### Via SchemaService

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Validation de plusieurs schémas
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
    echo "Validation réussie !";
} else {
    foreach ($result['errors'] as $error) {
        echo "Erreur : $error\n";
    }
}
```

### Via Fichiers YAML

```php
// Validation d'un fichier unique
$result = $schemaService->validateCustomFieldTypesFromFile('schema.yaml');

// Validation de plusieurs fichiers
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
    'custom_type_stats' => [         // Statistiques d'utilisation
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

## Intégration avec le Logging

Toutes les validations sont automatiquement loggées :

```php
// Les métriques incluent :
// - Nombre de schémas validés
// - Nombre de champs traités
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

Cette fonctionnalité assure la robustesse et la fiabilité des définitions de schémas en détectant les erreurs de configuration avant la génération de code.
