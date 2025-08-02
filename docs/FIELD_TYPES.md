# Field Types System

Laravel ModelSchema provides a comprehensive field type system that allows developers to define model schemas with built-in validation, casting, and migration logic. The system is extensible, allowing custom field types to be added.

## Built-in Field Types

The package includes the following field types inspired by Laravel migrations and TurboMaker:

### Basic Types
- `string` (aliases: `varchar`, `char`) - Variable length string
- `text` (aliases: `longtext`, `mediumtext`) - Text field for longer content
- `longText` (alias: `longtext`) - Large text storage
- `mediumText` (alias: `mediumtext`) - Medium text storage

### Numeric Types
- `integer` (alias: `int`) - Standard integer
- `bigInteger` (aliases: `bigint`, `long`) - Large integer
- `tinyInteger` (alias: `tinyint`) - Small integer (0-255)
- `smallInteger` (alias: `smallint`) - Small integer
- `mediumInteger` (alias: `mediumint`) - Medium integer
- `unsignedBigInteger` (aliases: `unsigned_big_integer`, `unsigned_bigint`) - Unsigned large integer
- `decimal` (aliases: `numeric`, `money`) - Decimal number with precision
- `float` (alias: `real`) - Floating point number
- `double` (alias: `double_precision`) - Double precision floating point

### Date/Time Types
- `date` - Date only (Y-m-d)
- `datetime` - Date and time
- `time` - Time only
- `timestamp` - Timestamp with timezone

### Specialized Types
- `boolean` (alias: `bool`) - True/false value
- `json` (alias: `jsonb`) - JSON data storage
- `uuid` (alias: `guid`) - Universal unique identifier
- `binary` (alias: `blob`) - Binary data
- `email` (alias: `email_address`) - Email address with validation
- `foreignId` (aliases: `foreign_id`, `fk`) - Foreign key reference
- `morphs` (alias: `polymorphic`) - Polymorphic relation fields

## Usage in YAML Schemas

### Basic Field Definition
```yaml
core:
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
