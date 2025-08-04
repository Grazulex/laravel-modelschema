# New Generators in Laravel ModelSchema v2.0

Laravel ModelSchema v2.0 introduces 4 powerful new generators that extend the package's capabilities into business logic and advanced Laravel patterns. These generators follow the same fragment-based architecture as existing generators, producing insertable JSON/YAML fragments.

## 🆕 New Generators Overview

| Generator | Purpose | Output Fragment | Use Case |
|-----------|---------|-----------------|----------|
| **ObserverGenerator** | Eloquent Observer event handlers | `observers: {class_name, events, methods}` | Model lifecycle hooks, auditing, notifications |
| **ServiceGenerator** | Business logic service classes | `services: {class_name, methods, dependencies}` | Business logic layer, CRUD operations |
| **ActionGenerator** | Single-responsibility action classes | `actions: {crud_actions, business_actions}` | Command pattern, single-purpose operations |
| **RuleGenerator** | Custom validation rule classes | `rules: {business_rules, foreign_key_rules}` | Complex validation logic, business rules |

## 🔍 ObserverGenerator

Generates Eloquent Observer classes with model event handlers for implementing business logic during model lifecycle events.

### Features
- **Complete Event Coverage**: creating, created, updating, updated, deleting, deleted, saving, saved
- **Configurable Events**: Enable/disable specific events based on needs
- **Business Logic Integration**: Generated methods with placeholder code for business logic
- **Model Integration**: Automatic model binding and namespace resolution

### Usage Example
```php
$observerResult = $generationService->generateObservers($schema, [
    'events' => ['creating', 'created', 'updating', 'deleted'],
    'namespace' => 'App\Observers',
    'soft_deletes' => true,
]);
```

### Generated Fragment
```json
{
  "observers": {
    "class_name": "UserObserver",
    "model": "App\\Models\\User",
    "namespace": "App\\Observers",
    "events": {
      "creating": {
        "enabled": true,
        "code": "// Set default values before creating"
      },
      "created": {
        "enabled": true,
        "code": "// Log user creation or send notifications"
      },
      "updating": {
        "enabled": true,
        "code": "// Validate update permissions"
      },
      "deleted": {
        "enabled": true,
        "code": "// Clean up related data"
      }
    },
    "imports": [
      "Illuminate\\Database\\Eloquent\\Model"
    ]
  }
}
```

## ⚙️ ServiceGenerator

Generates business logic service classes that encapsulate CRUD operations and complex business logic, following the Service Layer pattern.

### Features
- **CRUD Operations**: Complete create, read, update, delete methods
- **Repository Pattern**: Optional repository integration
- **Business Methods**: Custom business logic methods
- **Dependency Injection**: Automatic dependency resolution
- **Caching Support**: Built-in caching strategies
- **Validation Integration**: Form request validation

### Usage Example
```php
$serviceResult = $generationService->generateServices($schema, [
    'namespace' => 'App\Services',
    'repository_pattern' => true,
    'business_methods' => true,
    'caching' => true,
]);
```

### Generated Fragment
```json
{
  "services": {
    "class_name": "UserService",
    "model": "App\\Models\\User",
    "namespace": "App\\Services",
    "methods": {
      "create": {
        "parameters": ["array $data"],
        "return_type": "User",
        "validation": true,
        "caching": false
      },
      "update": {
        "parameters": ["User $user", "array $data"],
        "return_type": "User",
        "validation": true,
        "caching": true
      },
      "delete": {
        "parameters": ["User $user"],
        "return_type": "bool",
        "soft_delete": true
      },
      "findActive": {
        "parameters": [],
        "return_type": "Collection",
        "business_logic": true,
        "caching": true
      }
    },
    "dependencies": [
      "UserRepository",
      "ValidationService",
      "CacheManager"
    ],
    "imports": [
      "Illuminate\\Support\\Collection",
      "App\\Models\\User",
      "App\\Repositories\\UserRepository"
    ]
  }
}
```

## ⚡ ActionGenerator

Generates single-responsibility Action classes following the Command pattern. Each action performs one specific operation, making code more testable and maintainable.

### Features
- **CRUD Actions**: Standard create, update, delete actions
- **Business Actions**: Custom business logic actions
- **Custom Actions**: User-defined actions for specific workflows
- **Parameter Validation**: Type-safe parameter handling
- **Return Types**: Proper return type declarations
- **Dependency Injection**: Automatic service resolution

### Usage Example
```php
$actionResult = $generationService->generateActions($schema, [
    'namespace' => 'App\Actions\User',
    'crud_actions' => true,
    'business_actions' => true,
    'custom_actions' => [
        'SendWelcomeEmail',
        'UpdateLastLogin',
        'SuspendAccount',
    ],
]);
```

### Generated Fragment
```json
{
  "actions": {
    "crud_actions": [
      {
        "class_name": "CreateUserAction",
        "namespace": "App\\Actions\\User",
        "method": "execute",
        "parameters": ["array $data"],
        "return_type": "User",
        "validation": true
      },
      {
        "class_name": "UpdateUserAction",
        "namespace": "App\\Actions\\User",
        "method": "execute",
        "parameters": ["User $user", "array $data"],
        "return_type": "User",
        "validation": true
      }
    ],
    "business_actions": [
      {
        "class_name": "SendWelcomeEmailAction",
        "namespace": "App\\Actions\\User",
        "method": "execute",
        "parameters": ["User $user"],
        "return_type": "void",
        "dependencies": ["MailService"]
      },
      {
        "class_name": "UpdateLastLoginAction",
        "namespace": "App\\Actions\\User",
        "method": "execute",
        "parameters": ["User $user"],
        "return_type": "User",
        "fields": ["last_login_at"]
      }
    ]
  }
}
```

## 📏 RuleGenerator

Generates custom Laravel validation rule classes for complex business validation logic that can't be expressed with standard Laravel rules.

### Features
- **Business Rules**: Complex validation logic for business constraints
- **Foreign Key Rules**: Advanced relationship validation
- **Unique Rules**: Multi-field uniqueness constraints
- **Complex Rules**: Custom validation algorithms
- **Database Integration**: Query-based validation
- **Multi-field Validation**: Cross-field validation logic

### Usage Example
```php
$ruleResult = $generationService->generateRules($schema, [
    'namespace' => 'App\Rules',
    'business_rules' => true,
    'foreign_key_rules' => true,
    'unique_rules' => true,
    'complex_rules' => true,
]);
```

### Generated Fragment
```json
{
  "rules": {
    "business_rules": [
      {
        "class_name": "UniqueEmailRule",
        "namespace": "App\\Rules",
        "field": "email",
        "logic": "Check email uniqueness across multiple tables",
        "message": "This email address is already in use"
      },
      {
        "class_name": "ValidStatusTransitionRule",
        "namespace": "App\\Rules",
        "field": "status",
        "logic": "Validate status transitions based on business rules",
        "dependencies": ["StatusService"]
      }
    ],
    "foreign_key_rules": [
      {
        "class_name": "ExistingRoleRule",
        "namespace": "App\\Rules",
        "field": "role_id",
        "table": "roles",
        "column": "id",
        "message": "The selected role does not exist"
      }
    ],
    "unique_rules": [
      {
        "class_name": "UniqueUserEmailRule",
        "namespace": "App\\Rules",
        "fields": ["email"],
        "table": "users",
        "ignore_field": "id",
        "message": "This email is already taken"
      }
    ]
  }
}
```

## 🔄 Integration Examples

### Generate All New Components
```php
use Grazulex\LaravelModelschema\Services\GenerationService;

$generationService = new GenerationService();

// Generate all new components at once
$fragments = $generationService->generateMultiple($schema, [
    'observers', 'services', 'actions', 'rules'
], [
    'enhanced' => true,
    'namespace_prefix' => 'App',
]);

// Extract individual fragments
$observerData = json_decode($fragments['individual_results']['observers']['json'], true);
$serviceData = json_decode($fragments['individual_results']['services']['json'], true);
$actionData = json_decode($fragments['individual_results']['actions']['json'], true);
$ruleData = json_decode($fragments['individual_results']['rules']['json'], true);
```

### Complete Application Generation (13 Generators)
```php
// Generate everything including new components
$allFragments = $generationService->generateAll($schema, [
    // Core Laravel Components
    'model' => true,
    'migration' => true,
    'requests' => true,
    'resources' => true,
    'factory' => true,
    'seeder' => true,
    
    // Advanced Components  
    'controllers' => true,
    'tests' => true,
    'policies' => true,
    
    // New Business Logic Components (v2.0)
    'observers' => true,
    'services' => true,
    'actions' => true,
    'rules' => true,
]);
```

### Parent Application Integration
```php
// In your parent application (TurboMaker, Arc, etc.)

// 1. Extract service fragment
$serviceData = json_decode($allFragments['services']['json'], true);

// 2. Generate service file using your template
$serviceContent = view('your-app.service-template', [
    'class_name' => $serviceData['services']['class_name'],
    'namespace' => $serviceData['services']['namespace'],
    'methods' => $serviceData['services']['methods'],
    'dependencies' => $serviceData['services']['dependencies'],
    'model' => $serviceData['services']['model']
])->render();

// 3. Write service file
file_put_contents(
    app_path("Services/{$serviceData['services']['class_name']}.php"),
    $serviceContent
);

// Similar process for observers, actions, and rules...
```

## 🏗️ Architecture Benefits

### Clean Separation of Concerns
- **ModelSchema**: Produces fragment data structure
- **Parent Application**: Controls file generation and templating
- **Developers**: Focus on business logic, not boilerplate

### Consistent Pattern
All new generators follow the same fragment-based pattern:
1. **Input**: ModelSchema + options
2. **Processing**: Generate structured data
3. **Output**: JSON/YAML fragments
4. **Integration**: Parent app uses fragments in templates

### Extensibility
The fragment-based approach makes it easy to:
- Add new generator types
- Customize output for different frameworks
- Integrate with existing toolchains
- Support multiple template engines

## 🚀 Migration Guide

### From v1.x to v2.0
```php
// Before (v1.x) - Only 9 generators
$fragments = $generationService->generateAll($schema, [
    'model' => true,
    'migration' => true,
    'requests' => true,
    'resources' => true,
    'factory' => true,
    'seeder' => true,
    'controllers' => true,
    'tests' => true,
    'policies' => true,
]);

// After (v2.0) - Now 13 generators available
$fragments = $generationService->generateAll($schema, [
    'model' => true,
    'migration' => true,
    'requests' => true,
    'resources' => true,
    'factory' => true,
    'seeder' => true,
    'controllers' => true,
    'tests' => true,
    'policies' => true,
    'observers' => true,    // New
    'services' => true,     // New
    'actions' => true,      // New
    'rules' => true,        // New
]);
```

### Backward Compatibility
- All existing generators remain unchanged
- New generators are opt-in (disabled by default)
- Fragment structure is backward compatible
- API methods maintain same signatures

## 📚 Related Documentation

- **[Architecture Guide](../docs/ARCHITECTURE.md)** - Overall package architecture
- **[Fragment Examples](FRAGMENTS.md)** - Detailed fragment structures
- **[Integration Example](IntegrationExample.php)** - Complete workflow example
- **[New Generators Example](NewGeneratorsExample.php)** - Hands-on demonstration

## ✨ What's Next?

The new generators open up possibilities for:
- **Advanced Laravel Patterns**: Repository, Service, Action patterns
- **Better Code Organization**: Single-responsibility classes
- **Improved Testing**: Smaller, focused classes
- **Business Logic Separation**: Clean architecture principles
- **Custom Workflows**: Action-based task automation

Start exploring these new generators and see how they can improve your Laravel application architecture!
