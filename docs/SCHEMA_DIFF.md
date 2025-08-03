# Schema Diff - Comparaison et Analyse des Schémas

Le SchemaDiffService fournit des fonctionnalités avancées pour comparer des schémas, analyser l'impact des changements, et détecter les modifications incompatibles. Cette fonctionnalité est essentielle pour gérer l'évolution des schémas de base de données de manière sûre et contrôlée.

## Table des matières

- [Vue d'ensemble](#vue-densemble)
- [Installation et Configuration](#installation-et-configuration)
- [Utilisation de base](#utilisation-de-base)
- [Fonctionnalités avancées](#fonctionnalités-avancées)
- [Types de changements](#types-de-changements)
- [Analyse d'impact](#analyse-dimpact)
- [Génération de rapports](#génération-de-rapports)
- [Intégration avec les migrations](#intégration-avec-les-migrations)
- [Exemples pratiques](#exemples-pratiques)
- [Bonnes pratiques](#bonnes-pratiques)

## Vue d'ensemble

Le SchemaDiffService permet de :

- **Comparer deux schémas** et identifier toutes les différences
- **Détecter les changements incompatibles** qui pourraient casser l'application
- **Analyser l'impact des migrations** sur les données existantes
- **Évaluer les changements de validation** et leurs conséquences
- **Générer des rapports détaillés** pour la documentation
- **Comparer des schémas depuis des fichiers** YAML ou JSON

## Installation et Configuration

Le SchemaDiffService est automatiquement disponible via le SchemaService principal :

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();
// Le SchemaDiffService est automatiquement injecté
```

## Utilisation de base

### Comparaison de schémas

```php
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Field;

// Créer le schéma original
$originalSchema = new ModelSchema(
    name: 'User',
    table: 'users',
    fields: [
        'id' => new Field('id', 'bigInteger', false, false, true),
        'name' => new Field('name', 'string', false),
        'email' => new Field('email', 'email', false, true),
    ]
);

// Créer le schéma modifié
$modifiedSchema = new ModelSchema(
    name: 'User',
    table: 'users',
    fields: [
        'id' => new Field('id', 'bigInteger', false, false, true),
        'name' => new Field('name', 'string', false),
        'email' => new Field('email', 'email', false, true),
        'age' => new Field('age', 'integer', true), // Nouveau champ
    ]
);

// Comparer les schémas
$diff = $schemaService->compareSchemas($originalSchema, $modifiedSchema);

// Analyser les résultats
echo "Compatibilité : " . $diff['summary']['compatibility'];
echo "Champs ajoutés : " . $diff['summary']['fields']['added'];
echo "Changements incompatibles : " . count($diff['breaking_changes']);
```

### Comparaison depuis des fichiers

```php
// Comparer depuis des fichiers YAML
$diff = $schemaService->compareSchemasFromYaml(
    'path/to/old-schema.yaml',
    'path/to/new-schema.yaml'
);

// Comparer depuis des fichiers JSON
$diff = $schemaService->compareSchemasFromFiles(
    'path/to/old-schema.json',
    'path/to/new-schema.json'
);
```

## Fonctionnalités avancées

### Vérification rapide des changements incompatibles

```php
// Vérifier s'il y a des changements incompatibles
$hasBreakingChanges = $schemaService->hasBreakingChanges($oldSchema, $newSchema);

if ($hasBreakingChanges) {
    echo "⚠️ Attention : Des changements incompatibles ont été détectés !";
}
```

### Analyse d'impact des migrations

```php
// Obtenir l'impact des migrations
$migrationImpact = $schemaService->getMigrationImpact($oldSchema, $newSchema);

echo "Migration requise : " . ($migrationImpact['requires_migration'] ? 'Oui' : 'Non');
echo "Risque de perte de données : " . $migrationImpact['data_loss_risk'];
echo "Complexité : " . $migrationImpact['complexity'];

// Opérations de migration requises
foreach ($migrationImpact['migration_operations'] as $operation) {
    echo "- {$operation['type']}: {$operation['description']}";
}
```

### Analyse des changements de validation

```php
// Obtenir les changements de validation
$validationChanges = $schemaService->getValidationChanges($oldSchema, $newSchema);

if ($validationChanges['rules_changed']) {
    echo "Les règles de validation ont changé !";
    
    // Nouvelles règles
    foreach ($validationChanges['added_validation'] as $field => $rules) {
        echo "Nouvelles règles pour {$field}: " . implode(', ', $rules);
    }
    
    // Règles supprimées
    foreach ($validationChanges['removed_validation'] as $field => $rules) {
        echo "Règles supprimées pour {$field}: " . implode(', ', $rules);
    }
}
```

## Types de changements

### Changements de champs

#### Champs ajoutés
```php
// Les champs ajoutés sont généralement compatibles
$diff['field_changes']['added'] = [
    'age' => [
        'type' => 'integer',
        'nullable' => true,
        'breaking' => false
    ]
];
```

#### Champs supprimés
```php
// Les champs supprimés sont toujours incompatibles
$diff['field_changes']['removed'] = [
    'old_field' => [
        'type' => 'string',
        'breaking' => true,
        'data_loss_risk' => 'high'
    ]
];
```

#### Champs modifiés
```php
// Exemple de changement de type incompatible
$diff['field_changes']['modified'] = [
    'age' => [
        'type' => [
            'old' => 'string',
            'new' => 'integer',
            'breaking' => true
        ],
        'length' => [
            'old' => 255,
            'new' => null,
            'breaking' => false
        ]
    ]
];
```

### Changements de relations

```php
// Relations ajoutées, supprimées ou modifiées
$diff['relationship_changes'] = [
    'added' => [
        'posts' => [
            'type' => 'hasMany',
            'model' => 'Post'
        ]
    ],
    'removed' => [
        'old_relation' => [
            'type' => 'belongsTo',
            'model' => 'OldModel'
        ]
    ],
    'modified' => [
        'profile' => [
            'type' => [
                'old' => 'hasOne',
                'new' => 'belongsTo',
                'breaking' => true
            ]
        ]
    ]
];
```

## Analyse d'impact

### Niveaux de compatibilité

- **`fully_compatible`** : Aucun changement incompatible
- **`partially_compatible`** : Changements mineurs avec risques limités
- **`incompatible`** : Changements incompatibles détectés

### Niveaux de risque de perte de données

- **`none`** : Aucun risque
- **`low`** : Risque minimal (ex: augmentation de longueur)
- **`medium`** : Risque modéré (ex: réduction de longueur)
- **`high`** : Risque élevé (ex: suppression de champ)

### Niveaux d'impact

- **`low`** : Changements cosmétiques ou d'optimisation
- **`medium`** : Changements fonctionnels sans impact majeur
- **`high`** : Changements structurels importants

## Génération de rapports

### Rapport complet

```php
// Générer un rapport Markdown complet
$report = $schemaService->generateSchemaDiffReport($oldSchema, $newSchema);

// Sauvegarder le rapport
file_put_contents('schema_diff_report.md', $report);
```

### Exemple de rapport généré

```markdown
# Schema Diff Report

**Schema:** User  
**Table:** users  
**Comparison Date:** 2024-01-15 10:30:00  

## Summary

- **Compatibility:** incompatible
- **Impact Level:** high
- **Migration Required:** Yes
- **Data Loss Risk:** medium

## Field Changes

### Fields Added (2)
- `age` (integer, nullable)
- `phone` (string, nullable)

### Fields Removed (1)
- `old_field` (string) ⚠️ **Data Loss Risk**

### Fields Modified (1)
- `name`: length 255 → 300 ✅ Compatible

## Breaking Changes

⚠️ **Field Removed:** old_field
- Impact: high
- Data will be permanently lost

## Migration Impact

- **Operations Required:** 3
- **Estimated Complexity:** medium
- **Recommended Actions:**
  - Backup data before migration
  - Test on staging environment
  - Consider data migration script
```

## Intégration avec les migrations

### Génération automatique de migrations

```php
// Analyser l'impact avant de créer une migration
$migrationImpact = $schemaService->getMigrationImpact($oldSchema, $newSchema);

if ($migrationImpact['data_loss_risk'] === 'high') {
    echo "⚠️ ATTENTION : Cette migration entraînera une perte de données !";
    echo "Opérations à risque :";
    
    foreach ($migrationImpact['migration_operations'] as $operation) {
        if ($operation['risk_level'] === 'high') {
            echo "- {$operation['description']}";
        }
    }
    
    $confirm = readline("Continuer ? (yes/no): ");
    if ($confirm !== 'yes') {
        exit("Migration annulée.");
    }
}
```

### Script de pré-migration

```php
// Créer un script de validation avant migration
$diff = $schemaService->compareSchemas($currentSchema, $targetSchema);

// Vérifier la compatibilité
if ($diff['summary']['compatibility'] === 'incompatible') {
    // Créer un script de sauvegarde
    $backupScript = generateBackupScript($diff['breaking_changes']);
    file_put_contents('pre_migration_backup.sql', $backupScript);
    
    echo "Script de sauvegarde créé : pre_migration_backup.sql";
}
```

## Exemples pratiques

### Cas d'usage 1 : Validation avant déploiement

```php
function validateSchemaChanges($oldSchemaFile, $newSchemaFile) {
    $schemaService = new SchemaService();
    
    // Comparer les schémas
    $diff = $schemaService->compareSchemasFromYaml($oldSchemaFile, $newSchemaFile);
    
    // Vérifier la compatibilité
    if ($diff['summary']['compatibility'] === 'incompatible') {
        echo "❌ Déploiement bloqué : changements incompatibles détectés\n";
        
        foreach ($diff['breaking_changes'] as $change) {
            echo "- {$change['description']}\n";
        }
        
        return false;
    }
    
    // Avertir sur les changements à risque moyen
    if ($diff['migration_impact']['data_loss_risk'] === 'medium') {
        echo "⚠️ Attention : risque modéré de perte de données\n";
        
        // Générer un rapport
        $report = $schemaService->generateSchemaDiffReport($oldSchema, $newSchema);
        file_put_contents('deployment_risk_report.md', $report);
        
        return 'review_required';
    }
    
    echo "✅ Déploiement approuvé : changements compatibles\n";
    return true;
}
```

### Cas d'usage 2 : Analyse de l'évolution du schéma

```php
function analyzeSchemaEvolution($schemaVersions) {
    $schemaService = new SchemaService();
    $evolutionReport = [];
    
    for ($i = 1; $i < count($schemaVersions); $i++) {
        $oldSchema = loadSchema($schemaVersions[$i-1]);
        $newSchema = loadSchema($schemaVersions[$i]);
        
        $diff = $schemaService->compareSchemas($oldSchema, $newSchema);
        
        $evolutionReport[] = [
            'version' => $schemaVersions[$i],
            'compatibility' => $diff['summary']['compatibility'],
            'fields_added' => $diff['summary']['fields']['added'],
            'fields_removed' => $diff['summary']['fields']['removed'],
            'breaking_changes' => count($diff['breaking_changes']),
            'migration_required' => $diff['migration_impact']['requires_migration']
        ];
    }
    
    return $evolutionReport;
}
```

### Cas d'usage 3 : Test de régression de schéma

```php
class SchemaRegressionTest extends TestCase
{
    public function test_schema_backward_compatibility()
    {
        $schemaService = new SchemaService();
        
        $productionSchema = loadProductionSchema();
        $developmentSchema = loadDevelopmentSchema();
        
        $diff = $schemaService->compareSchemas($productionSchema, $developmentSchema);
        
        // Aucun changement incompatible autorisé
        $this->assertEmpty($diff['breaking_changes'], 
            'Breaking changes detected: ' . json_encode($diff['breaking_changes']));
        
        // Risque de perte de données limité
        $this->assertNotEquals('high', $diff['migration_impact']['data_loss_risk'],
            'High data loss risk detected');
    }
}
```

## Bonnes pratiques

### 1. Intégration CI/CD

```yaml
# .github/workflows/schema-validation.yml
name: Schema Validation
on: [pull_request]

jobs:
  schema-diff:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout code
        uses: actions/checkout@v2
        
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.1
          
      - name: Install dependencies
        run: composer install
        
      - name: Compare schemas
        run: |
          php scripts/compare-schemas.php \
            --old=schemas/production.yaml \
            --new=schemas/staging.yaml \
            --output=schema-diff.md
            
      - name: Comment PR
        uses: actions/github-script@v6
        with:
          script: |
            const fs = require('fs');
            const report = fs.readFileSync('schema-diff.md', 'utf8');
            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: report
            });
```

### 2. Validation des changements

```php
// Toujours valider avant les changements critiques
function beforeSchemaChange($oldSchema, $newSchema) {
    $schemaService = new SchemaService();
    
    // 1. Vérifier la compatibilité
    $hasBreaking = $schemaService->hasBreakingChanges($oldSchema, $newSchema);
    if ($hasBreaking) {
        throw new SchemaException('Breaking changes detected');
    }
    
    // 2. Analyser l'impact des migrations
    $impact = $schemaService->getMigrationImpact($oldSchema, $newSchema);
    if ($impact['data_loss_risk'] === 'high') {
        throw new SchemaException('High data loss risk');
    }
    
    // 3. Vérifier les changements de validation
    $validation = $schemaService->getValidationChanges($oldSchema, $newSchema);
    if ($validation['rules_changed']) {
        logValidationChanges($validation);
    }
}
```

### 3. Documentation automatique

```php
// Générer automatiquement la documentation des changements
function generateChangeLog($versions) {
    $schemaService = new SchemaService();
    $changelog = "# Schema Changelog\n\n";
    
    foreach ($versions as $version) {
        $diff = $schemaService->compareSchemasFromFiles(
            "schemas/{$version['old']}.yaml",
            "schemas/{$version['new']}.yaml"
        );
        
        $changelog .= "## Version {$version['new']}\n\n";
        $changelog .= "**Date:** {$version['date']}\n";
        $changelog .= "**Compatibility:** {$diff['summary']['compatibility']}\n\n";
        
        if (!empty($diff['field_changes']['added'])) {
            $changelog .= "### Added Fields\n";
            foreach ($diff['field_changes']['added'] as $field => $details) {
                $changelog .= "- `{$field}` ({$details['type']})\n";
            }
            $changelog .= "\n";
        }
        
        if (!empty($diff['breaking_changes'])) {
            $changelog .= "### ⚠️ Breaking Changes\n";
            foreach ($diff['breaking_changes'] as $change) {
                $changelog .= "- {$change['description']}\n";
            }
            $changelog .= "\n";
        }
    }
    
    return $changelog;
}
```

Le SchemaDiffService offre une solution complète pour gérer l'évolution des schémas de manière sûre et contrôlée. Il permet de prévenir les erreurs, d'analyser l'impact des changements, et de maintenir la compatibilité entre les versions.
