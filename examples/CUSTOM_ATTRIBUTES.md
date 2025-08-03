# Custom Attributes Examples with Trait-Based Architecture

This file presents practical examples of using the **trait-based** custom attributes system in Laravel ModelSchema field type plugins.

## Trait-Based Architecture: Overview

The new system uses a **configuration traits** approach that enables:

1. **Modularity**: Each attribute is defined as a reusable trait
2. **Flexibility**: Traits can be combined and configured dynamically  
3. **Advanced validation**: Each trait has its own validation rules
4. **Transformation**: Traits can transform values automatically
5. **Documentation**: Each trait is self-documented

### Attribute Trait Structure

```php
// In a FieldTypePlugin plugin
$this->customAttributeConfig = [
    'trait_name' => [
        'type' => 'string|int|boolean|array',    // Trait data type
        'required' => true|false,                // Required or optional trait
        'default' => $defaultValue,              // Trait default value
        'min' => $minimum,                       // Minimum constraint (numeric)
        'max' => $maximum,                       // Maximum constraint (numeric)
        'enum' => [$allowedValues],              // Allowed values for this trait
        'validator' => $validator,               // Custom validation function
        'transform' => $transformer,             // Transformation function
        'description' => 'Trait description'    // Trait documentation
    ]
];
```

## URL Field Configuration with Custom Attributes

```yaml
# YAML Schema using UrlFieldTypePlugin custom attributes
core:
  model: Website
  table: websites
  fields:
    homepage:
      type: url
      nullable: false
      max_length: 500
      # UrlFieldTypePlugin custom attributes
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

## JSON Schema Field Configuration with Validation

```yaml
# YAML Schema using JsonSchemaFieldTypePlugin custom attributes
core:
  model: ApiConfiguration
  table: api_configurations
  fields:
    settings:
      type: json_schema
      nullable: true
      # JsonSchemaFieldTypePlugin custom attributes
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

## Usage Example in a Laravel Application

### 1. Plugin Registration

```php
// In AppServiceProvider.php
public function boot()
{
    $pluginManager = app(FieldTypePluginManager::class);
    
    // Register plugins with custom attributes
    $pluginManager->registerPlugin(new UrlFieldTypePlugin());
    $pluginManager->registerPlugin(new JsonSchemaFieldTypePlugin());
}
```

### 2. Configuration Validation

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

### 4. Integration with Generation System

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// YAML with custom attributes
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

// Parse and validation (includes custom attributes)
$result = $schemaService->parseAndSeparateSchema($yamlContent);
$errors = $schemaService->validateCoreSchema($yamlContent);

// Fragment generation (custom attributes are taken into account)
$generationData = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);
```

## Advanced Use Cases

### 1. Plugin with Conditional Validation

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

### 2. Plugin with Data Transformation

```php
class EncryptedFieldTypePlugin extends FieldTypePlugin
{
    public function processCustomAttributes(array $fieldConfig): array
    {
        $config = parent::processCustomAttributes($fieldConfig);
        
        // Automatic transformation
        if (isset($config['encryption_key']) && $config['encryption_key'] === 'auto') {
            $config['encryption_key'] = $this->generateEncryptionKey();
        }
        
        return $config;
    }
}
```

### 3. Usage with Relations

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
      # Custom attributes can also be used in relation configurations
```

## Debugging and Testing

### 1. Step-by-Step Validation

```php
$plugin = new UrlFieldTypePlugin();

// Debug: see all supported attributes
$allAttributes = $plugin->getSupportedAttributesList();
// ['nullable', 'default', 'max_length', 'schemes', 'verify_ssl', ...]

// Debug: see only custom attributes
$customAttributes = $plugin->getCustomAttributes();
// ['schemes', 'verify_ssl', 'allow_query_params', ...]

// Debug: validation of a specific attribute
$errors = $plugin->validateCustomAttribute('timeout', 'invalid');
// ["Custom attribute 'timeout' must be of type integer"]
```

### 2. Unit Testing

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

This custom attributes system offers maximum flexibility for creating sophisticated field types while maintaining the robustness and ease of use of the Laravel ModelSchema package.
