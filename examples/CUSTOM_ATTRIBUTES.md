# Exemples d'utilisation des Attributs Custom

Ce fichier présente des exemples pratiques d'utilisation du système d'attributs custom dans les plugins de types de champs.

## Configuration d'un champ URL avec attributs custom

```yaml
# Schema YAML utilisant les attributs custom du UrlFieldTypePlugin
core:
  model: Website
  table: websites
  fields:
    homepage:
      type: url
      nullable: false
      max_length: 500
      # Attributs custom du UrlFieldTypePlugin
      schemes: ['https', 'http']
      verify_ssl: true
      allow_query_params: true
      max_redirects: 3
      timeout: 45
      domain_whitelist: 
        - 'example.com'
        - 'trusted.org'
        - 'partner.net'
      domain_blacklist:
        - 'malicious.com'
        - 'spam.net'
```

## Configuration d'un champ JSON Schema avec validation

```yaml
# Schema YAML utilisant les attributs custom du JsonSchemaFieldTypePlugin
core:
  model: ApiConfiguration
  table: api_configurations
  fields:
    settings:
      type: json_schema
      nullable: true
      # Attributs custom du JsonSchemaFieldTypePlugin
      schema:
        type: object
        properties:
          endpoint:
            type: string
            pattern: "^https?://"
          method:
            type: string
            enum: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']
          timeout:
            type: integer
            minimum: 1
            maximum: 300
          headers:
            type: object
            additionalProperties:
              type: string
          authentication:
            type: object
            properties:
              type:
                type: string
                enum: ['bearer', 'basic', 'api_key']
              token:
                type: string
            required: ['type']
        required: ['endpoint', 'method']
      strict_validation: true
      allow_additional_properties: false
      schema_format: 'draft-07'
      validation_mode: 'strict'
      error_format: 'detailed'
      schema_cache_ttl: 7200
      schema_version: '1.0.0'
```

## Exemple d'utilisation dans une application Laravel

### 1. Registration du plugin

```php
// Dans AppServiceProvider.php
public function boot()
{
    $pluginManager = app(FieldTypePluginManager::class);
    
    // Register plugins with custom attributes
    $pluginManager->registerPlugin(new UrlFieldTypePlugin());
    $pluginManager->registerPlugin(new JsonSchemaFieldTypePlugin());
}
```

### 2. Validation des configurations

```php
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;

$plugin = new UrlFieldTypePlugin();

// Configuration valide
$validConfig = [
    'schemes' => ['https'],
    'verify_ssl' => true,
    'timeout' => 30,
    'domain_whitelist' => ['trusted.com']
];

$errors = $plugin->validate($validConfig);
// $errors sera vide

// Configuration invalide
$invalidConfig = [
    'schemes' => 'not-an-array',  // Erreur : doit être un array
    'timeout' => -1,              // Erreur : doit être >= 1
    'verify_ssl' => 'yes'         // Erreur : doit être boolean
];

$errors = $plugin->validate($invalidConfig);
// $errors contiendra les messages d'erreur détaillés
```

### 3. Processing automatique des valeurs par défaut

```php
$plugin = new UrlFieldTypePlugin();

// Configuration partielle
$config = [
    'nullable' => true,
    'schemes' => ['https']  // Seulement certains attributs définis
];

// Application des valeurs par défaut
$processedConfig = $plugin->processCustomAttributes($config);

// Résultat :
// [
//     'nullable' => true,
//     'schemes' => ['https'],
//     'verify_ssl' => true,           // Valeur par défaut appliquée
//     'allow_query_params' => true,   // Valeur par défaut appliquée
//     'max_redirects' => 5,           // Valeur par défaut appliquée
//     'timeout' => 30,                // Valeur par défaut appliquée
//     'domain_whitelist' => [],       // Valeur par défaut appliquée
//     'domain_blacklist' => []        // Valeur par défaut appliquée
// ]
```

### 4. Intégration avec le système de génération

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// YAML avec attributs custom
$yamlContent = '
core:
  model: Website
  table: websites
  fields:
    homepage:
      type: url
      schemes: ["https"]
      verify_ssl: true
      timeout: 45
';

// Parse et validation (inclut les attributs custom)
$result = $schemaService->parseAndSeparateSchema($yamlContent);
$errors = $schemaService->validateCoreSchema($yamlContent);

// Génération des fragments (les attributs custom sont pris en compte)
$generationData = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
```

## Cas d'usage avancés

### 1. Plugin avec validation conditionnelle

```php
class DatabaseConnectionFieldTypePlugin extends FieldTypePlugin
{
    public function __construct()
    {
        $this->customAttributes = ['driver', 'host', 'port', 'ssl_mode'];
        
        $this->customAttributeConfig = [
            'driver' => [
                'type' => 'string',
                'required' => true,
                'enum' => ['mysql', 'postgresql', 'sqlite', 'sqlserver']
            ],
            'ssl_mode' => [
                'type' => 'string',
                'required' => false,
                'enum' => ['disable', 'allow', 'prefer', 'require'],
                'validator' => function ($value, $attribute) {
                    // SSL seulement pour PostgreSQL et MySQL
                    $driver = $this->getCurrentDriver();
                    if (in_array($driver, ['sqlite']) && $value !== 'disable') {
                        return ["SSL mode not applicable for {$driver} driver"];
                    }
                    return [];
                }
            ]
        ];
    }
}
```

### 2. Plugin avec transformation de données

```php
class EncryptedFieldTypePlugin extends FieldTypePlugin
{
    public function processCustomAttributes(array $fieldConfig): array
    {
        $config = parent::processCustomAttributes($fieldConfig);
        
        // Transformation automatique
        if (isset($config['encryption_key']) && $config['encryption_key'] === 'auto') {
            $config['encryption_key'] = $this->generateEncryptionKey();
        }
        
        return $config;
    }
}
```

### 3. Utilisation avec les Relations

```yaml
core:
  model: User
  table: users
  fields:
    profile_picture:
      type: url
      schemes: ['https']
      verify_ssl: true
      domain_whitelist: ['cdn.myapp.com', 'images.myapp.com']
      
  relationships:
    posts:
      type: has_many
      model: Post
      # Les attributs custom peuvent aussi être utilisés dans les configurations de relations
```

## Debugging et Tests

### 1. Validation pas à pas

```php
$plugin = new UrlFieldTypePlugin();

// Debug : voir tous les attributs supportés
$allAttributes = $plugin->getSupportedAttributesList();
// ['nullable', 'default', 'max_length', 'schemes', 'verify_ssl', ...]

// Debug : voir seulement les attributs custom
$customAttributes = $plugin->getCustomAttributes();
// ['schemes', 'verify_ssl', 'allow_query_params', ...]

// Debug : validation d'un attribut spécifique
$errors = $plugin->validateCustomAttribute('timeout', 'invalid');
// ["Custom attribute 'timeout' must be of type integer"]
```

### 2. Tests unitaires

```php
class MyPluginTest extends TestCase
{
    public function test_custom_attributes_applied_correctly()
    {
        $plugin = new MyCustomPlugin();
        
        $config = ['my_attribute' => 'value'];
        $processed = $plugin->processCustomAttributes($config);
        
        $this->assertEquals('expected_value', $processed['my_attribute']);
        $this->assertArrayHasKey('default_attribute', $processed);
    }
    
    public function test_validation_errors_for_invalid_config()
    {
        $plugin = new MyCustomPlugin();
        
        $invalidConfig = ['my_attribute' => 'invalid_value'];
        $errors = $plugin->validate($invalidConfig);
        
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('my_attribute', $errors[0]);
    }
}
```

Ce système d'attributs custom offre une flexibilité maximale pour créer des types de champs sophistiqués tout en maintenant la robustesse et la facilité d'utilisation du package Laravel ModelSchema.
