# Auto Validation System

The Laravel ModelSchema package includes a comprehensive AutoValidationService that automatically generates Laravel validation rules based on field types and custom attributes defined in your schema files.

## Overview

The AutoValidationService bridges the gap between schema definitions and Laravel's validation system by:

- **Automatically generating validation rules** from field types
- **Processing custom attributes** from field type plugins
- **Supporting complex field types** (spatial, enum, foreign keys)
- **Generating user-friendly messages** for better UX
- **Providing multiple output formats** for different use cases

## Basic Usage

### Via SchemaService

```php
use Grazulex\LaravelModelschema\Services\SchemaService;

$schemaService = new SchemaService();

// Generate validation rules from YAML file
$rules = $schemaService->generateValidationRulesFromFile('path/to/schema.yml');

// Generate validation rules from YAML content
$rules = $schemaService->generateValidationRulesFromYaml($yamlContent);

// Generate complete validation configuration
$config = $schemaService->generateValidationConfig($schema);
// Returns: ['rules' => [...], 'messages' => [...], 'attributes' => [...]]
```

### Direct Usage

```php
use Grazulex\LaravelModelschema\Services\AutoValidationService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

$pluginManager = new FieldTypePluginManager();
$autoValidator = new AutoValidationService($pluginManager);

// Generate rules for a single field
$field = new Field('email', 'email', false);
$rules = $autoValidator->generateFieldValidationRules($field);
// Result: ['required', 'email']

// Generate rules for entire schema
$rules = $autoValidator->generateValidationRules($schema);
```

## Field Type Mapping

The service automatically maps field types to appropriate Laravel validation rules:

### Basic Types

```php
'string' => ['string']
'email' => ['email']
'integer' => ['integer']
'boolean' => ['boolean']
'date' => ['date']
'uuid' => ['uuid']
'json' => ['json']
```

### Numeric Types

```php
'decimal' => ['numeric']
'float' => ['numeric']
'unsignedBigInteger' => ['integer', 'min:0']
```

### Complex Types

```php
'enum' => ['string', 'in:value1,value2,value3']
'foreignId' => ['integer', 'exists:table,id']
'set' => ['array']
```

### Spatial Types

```php
'point' => ['string'] + custom spatial_format validation
'geometry' => ['string'] + custom spatial_format validation
'polygon' => ['string'] + custom spatial_format validation
```

## Laravel Attribute Integration

The service automatically processes Laravel field attributes:

### String Length

```yaml
fields:
  title:
    type: string
    attributes:
      length: 255
```

Generates: `['required', 'string', 'max:255']`

### Unique Constraints

```yaml
fields:
  email:
    type: email
    attributes:
      unique: true
```

Generates: `['required', 'email', 'unique:{{table}},email']`

### Decimal Precision

```yaml
fields:
  price:
    type: decimal
    attributes:
      precision: 8
      scale: 2
```

Generates: `['required', 'numeric', 'decimal:0,2']`

## Custom Attributes Integration

When using field type plugins with custom attributes, the service automatically generates appropriate validation rules:

### URL Field Plugin

```yaml
fields:
  website:
    type: url  # UrlFieldTypePlugin
```

The plugin's custom attributes (schemes, verify_ssl, etc.) automatically contribute to validation rule generation.

### JSON Schema Plugin

```yaml
fields:
  config:
    type: json_schema  # JsonSchemaFieldTypePlugin
```

Custom attributes like `schema`, `strict_validation` enhance the generated rules.

## Foreign Key Handling

The service intelligently handles foreign key relationships:

### Automatic Table Detection

```yaml
fields:
  user_id:  # Automatically detects "users" table
    type: foreignId
```

Generates: `['required', 'integer', 'exists:users,id']`

### Explicit References

```yaml
fields:
  owner_id:
    type: foreignId
    attributes:
      references:
        table: users
        column: id
```

Generates: `['required', 'integer', 'exists:users,id']`

## Enum Field Support

```yaml
fields:
  status:
    type: enum
    attributes:
      values: [active, inactive, pending]
```

Generates: `['required', 'string', 'in:active,inactive,pending']`

## Output Formats

### Array Format (Default)

```php
$rules = $service->generateValidationRules($schema);
// Result:
[
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'unique:users,email'],
    'age' => ['nullable', 'integer', 'min:0', 'max:150']
]
```

### String Format for Laravel Requests

```php
$rulesString = $service->generateValidationRulesForRequest($schema, 'string');
// Result: "['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email']"
```

## Validation Messages

Generate user-friendly validation messages:

```php
$messages = $service->generateValidationMessages($schema);
// Result:
[
    'name.required' => 'The Name field is required.',
    'email.email' => 'The Email must be a valid email address.',
    'age.integer' => 'The Age must be an integer.'
]
```

## Complete Laravel Request Example

```php
// Generate complete validation configuration
$config = $schemaService->generateValidationConfig($schema);

// Use in Laravel FormRequest
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'age' => 'nullable|integer|min:0|max:150',
            'is_active' => 'required|boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The Name field is required.',
            'email.email' => 'The Email must be a valid email address.',
            'age.integer' => 'The Age must be an integer.'
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Full Name',
            'email' => 'Email Address',
            'age' => 'Age'
        ];
    }
}
```

## Custom Validation Rules

For complex field types, the service can generate custom validation rule references:

```php
// For spatial fields
$customRules = $service->generateCustomValidationRules($field);
// Might include: ['spatial_format', 'json_schema:schema_name']
```

## Integration with Plugin System

The AutoValidationService seamlessly integrates with the field type plugin system:

1. **Plugin Registration**: Custom field types are automatically detected
2. **Custom Attributes**: Plugin-specific attributes contribute to validation rules
3. **Type-specific Logic**: Each plugin can influence rule generation
4. **Extensibility**: New plugins automatically work with validation generation

## Best Practices

### 1. Leverage Field Types

Use appropriate field types in your schemas to get automatic validation:

```yaml
# Good: Uses specific types
fields:
  email: { type: email }
  age: { type: integer }
  website: { type: url }

# Less optimal: Generic types require manual validation
fields:
  email: { type: string }  # Misses email validation
  age: { type: string }    # Misses numeric validation
```

### 2. Use Attributes for Constraints

```yaml
fields:
  password:
    type: string
    attributes:
      length: 255      # Generates max:255
      min_length: 8    # For custom validation
```

### 3. Combine with Custom Rules

```php
// Generated rules as base
$baseRules = $service->generateValidationRules($schema);

// Add custom business logic
$customRules = [
    'password' => array_merge($baseRules['password'], ['confirmed']),
    'terms_accepted' => ['accepted']
];
```

### 4. Cache Generated Rules

For performance in production:

```php
$cacheKey = 'validation_rules_' . $schema->name;
$rules = Cache::remember($cacheKey, 3600, function() use ($service, $schema) {
    return $service->generateValidationRules($schema);
});
```

## Advanced Features

### Custom Rule Generation

Extend the service for application-specific validation:

```php
class CustomAutoValidationService extends AutoValidationService
{
    protected function generateCustomValidationRules(Field $field): array
    {
        $rules = parent::generateCustomValidationRules($field);
        
        // Add application-specific rules
        if ($field->type === 'business_email') {
            $rules[] = 'not_regex:/gmail|yahoo|hotmail/';
        }
        
        return $rules;
    }
}
```

### Dynamic Table References

For multi-tenant applications:

```php
$rules = $service->generateValidationRules($schema);

// Replace table placeholders with tenant-specific tables
foreach ($rules as $field => $fieldRules) {
    $rules[$field] = array_map(function($rule) {
        return str_replace('{{table}}', 'tenant_' . auth()->user()->tenant_id . '_table', $rule);
    }, $fieldRules);
}
```

## Error Handling

The service gracefully handles various edge cases:

- **Unknown field types**: Fallback to 'string' validation
- **Missing attributes**: Use sensible defaults
- **Invalid configurations**: Log warnings and continue
- **Plugin errors**: Isolate failures to specific plugins

## Performance Considerations

- **Plugin Loading**: Plugins are loaded on-demand
- **Rule Caching**: Consider caching generated rules in production
- **Batch Processing**: Process multiple schemas efficiently
- **Memory Usage**: Rules are generated without loading full schema into memory

The AutoValidationService provides a powerful foundation for automatically generating Laravel validation rules from schema definitions, reducing boilerplate and ensuring consistency between your schema and validation logic.
