# Schema Diff Service - Rapport d'exemple

Ce rapport a été généré automatiquement par le SchemaDiffService pour démontrer les capacités d'analyse des changements de schéma.

## Utilisation

Le SchemaDiffService peut être utilisé de plusieurs façons :

### 1. Comparaison directe de schémas

```php
use Grazulex\LaravelModelschema\Services\SchemaDiffService;
use Grazulex\LaravelModelschema\Services\LoggingService;

$logger = new LoggingService();
$diffService = new SchemaDiffService($logger);

$diff = $diffService->compareSchemas($oldSchema, $newSchema);
```

### 2. Via SchemaService (recommandé)

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Comparer deux schémas
$diff = $schemaService->compareSchemas($oldSchema, $newSchema);

// Vérifier les changements incompatibles
$hasBreaking = $schemaService->hasBreakingChanges($oldSchema, $newSchema);

// Analyser l'impact des migrations
$migrationImpact = $schemaService->getMigrationImpact($oldSchema, $newSchema);

// Analyser les changements de validation
$validationChanges = $schemaService->getValidationChanges($oldSchema, $newSchema);

// Générer un rapport complet
$report = $schemaService->generateSchemaDiffReport($oldSchema, $newSchema);
```

### 3. Comparaison depuis des fichiers

```php
// Depuis des fichiers YAML
$diff = $schemaService->compareSchemasFromYaml('old.yaml', 'new.yaml');

// Depuis des fichiers JSON
$diff = $schemaService->compareSchemasFromFiles('old.json', 'new.json');
```

## Tests

Pour voir le SchemaDiffService en action, exécutez les tests :

```bash
vendor/bin/pest tests/Unit/Services/SchemaDiffServiceTest.php --verbose
```

Cela exécutera 18 tests qui démontrent toutes les fonctionnalités :

- ✓ Comparaison de schémas sans changements
- ✓ Détection de champs ajoutés
- ✓ Détection de champs supprimés comme changements incompatibles
- ✓ Détection de changements de type de champ
- ✓ Détection de changements de nullabilité
- ✓ Détection de changements de longueur
- ✓ Transitions de type compatibles
- ✓ Changements de nom de table
- ✓ Changements de relations
- ✓ Analyse d'impact des migrations
- ✓ Analyse d'impact de validation
- ✓ Génération de rapports
- ✓ Changements de précision et d'échelle
- ✓ Ajouts de contraintes uniques
- ✓ Changements d'attributs
- ✓ Catégorisation par niveau d'impact
- ✓ Changements de type de relation
- ✓ Identification des niveaux de risque de perte de données

## Exemple de structure de diff

```php
[
    'summary' => [
        'compatibility' => 'incompatible|partially_compatible|fully_compatible',
        'impact_level' => 'low|medium|high',
        'fields' => [
            'added' => 1,
            'removed' => 1,
            'modified' => 2
        ],
        'relationships' => [
            'added' => 0,
            'removed' => 0,
            'modified' => 1
        ]
    ],
    'field_changes' => [
        'added' => [...],
        'removed' => [...],
        'modified' => [...]
    ],
    'relationship_changes' => [
        'added' => [...],
        'removed' => [...],
        'modified' => [...]
    ],
    'breaking_changes' => [
        [
            'type' => 'field_removed',
            'field_name' => 'age',
            'description' => 'Field removed: age',
            'impact' => 'high',
            'change_type' => 'field_removal'
        ]
    ],
    'migration_impact' => [
        'requires_migration' => true,
        'data_loss_risk' => 'high|medium|low|none',
        'complexity' => 'high|medium|low',
        'migration_operations' => [...]
    ],
    'validation_impact' => [
        'rules_changed' => true,
        'added_validation' => [...],
        'removed_validation' => [...],
        'modified_validation' => [...]
    ]
]
```

## Documentation complète

Pour plus d'informations, consultez la documentation complète dans `docs/SCHEMA_DIFF.md`.
