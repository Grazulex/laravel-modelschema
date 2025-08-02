# Field Types System

Le système de Field Types permet aux développeurs de créer des types de champs personnalisés avec leur propre logique de validation, de casting et de migration.

## Types de champs intégrés

Le package inclut les types de champs suivants inspirés de TurboMaker :

### Types de base
- `string` (aliases: `varchar`, `char`)
- `text` (aliases: `longtext`, `mediumtext`) 
- `longText` (alias: `longtext`)
- `mediumText` (alias: `mediumtext`)

### Types numériques
- `integer` (alias: `int`)
- `bigInteger` (aliases: `bigint`, `long`)
- `tinyInteger` (alias: `tinyint`)
- `smallInteger` (alias: `smallint`)
- `mediumInteger` (alias: `mediumint`)
- `unsignedBigInteger` (aliases: `unsigned_big_integer`, `unsigned_bigint`)
- `decimal` (aliases: `numeric`, `money`)
- `float` (alias: `real`)
- `double` (alias: `double_precision`)

### Types de date/heure
- `date`
- `datetime` 
- `time`
- `timestamp`

### Types spécialisés
- `boolean` (alias: `bool`)
- `json` (alias: `jsonb`)
- `uuid` (alias: `guid`)
- `binary` (alias: `blob`)
- `email` (alias: `email_address`)
- `foreignId` (aliases: `foreign_id`, `fk`)
- `morphs` (alias: `polymorphic`)

## Utilisation dans les schémas YAML

```yaml
model: User
table: users
fields:
  id:
    type: uuid
    nullable: false
    unique: true
  
  name:
    type: string
    length: 255
    nullable: false
  
  email:
    type: email
    nullable: false
    unique: true
  
  age:
    type: tinyInteger
    unsigned: true
    nullable: true
  
  balance:
    type: decimal
    precision: 10
    scale: 2
    default: 0.00
  
  profile:
    type: json
    nullable: true
  
  created_at:
    type: timestamp
    use_current: true
```

## Créer des Field Types personnalisés

### 1. Créer votre classe

Créez un fichier dans `app/FieldTypes/` :

```php
<?php

namespace App\FieldTypes;

use Grazulex\LaravelModelschema\FieldTypes\AbstractFieldType;

final class UrlFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'schemes',
        'verify_ssl',
    ];

    public function getType(): string
    {
        return 'url';
    }

    public function getAliases(): array
    {
        return ['website', 'link'];
    }

    public function getMigrationMethod(): string
    {
        return 'string';
    }

    public function getCastType(): ?string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);
        $rules[] = 'url';
        
        if (isset($config['schemes'])) {
            $schemes = implode(',', $config['schemes']);
            $rules[] = "url:schemes={$schemes}";
        }

        return $rules;
    }

    // Autres méthodes...
}
```

### 2. Configuration automatique

Le field type sera automatiquement découvert et enregistré au démarrage de l'application si placé dans le répertoire configuré (`app/FieldTypes/` par défaut).

### 3. Utilisation

```yaml
website:
  type: url
  schemes: ['http', 'https']
  length: 2048
```

## Configuration

Dans `config/modelschema.php` :

```php
'custom_field_types_path' => app_path('FieldTypes'),
'custom_field_types_namespace' => 'App\\FieldTypes',
```

## API du FieldTypeInterface

Chaque field type doit implémenter ces méthodes :

- `getType()`: Retourne l'identifiant du type
- `getAliases()`: Retourne les alias supportés
- `getMigrationMethod()`: Retourne la méthode Laravel pour la migration
- `getCastType()`: Retourne le type de cast Laravel
- `getValidationRules()`: Retourne les règles de validation
- `getMigrationParameters()`: Retourne les paramètres pour la migration
- `supportsAttribute()`: Vérifie si un attribut est supporté
- `validate()`: Valide la configuration du champ
- `transformConfig()`: Transforme la configuration avant utilisation

## Attributs communs

Tous les field types supportent ces attributs :

- `nullable`: Le champ peut être null
- `default`: Valeur par défaut
- `comment`: Commentaire de la colonne
- `index`: Créer un index sur le champ
- `unique`: Contrainte d'unicité

## Exemples d'utilisation avancée

### Field type avec validation personnalisée

```php
public function getValidationRules(array $config = []): array
{
    $rules = parent::getValidationRules($config);
    
    // Validation spécifique
    $rules[] = 'regex:/^[A-Z]{2}[0-9]{4}$/';
    
    return $rules;
}
```

### Field type avec transformation de données

```php
public function transformConfig(array $config): array
{
    // Définir des valeurs par défaut
    if (!isset($config['length'])) {
        $config['length'] = 6;
    }
    
    return $config;
}
```

### Field type avec paramètres de migration complexes

```php
public function getMigrationParameters(array $config): array
{
    $params = [];
    
    if (isset($config['values'])) {
        $params[] = $config['values'];
    }
    
    return $params;
}
```
