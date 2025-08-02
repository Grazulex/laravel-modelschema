# Enhanced Features Guide

This guide covers the enhanced features added to Laravel ModelSchema v2.0, including the new **ControllerGenerator**, enhanced **ResourceGenerator**, and **EnhancedValidationService**.

## New Features Overview

### 1. Enhanced ResourceGenerator

The ResourceGenerator has been completely rewritten to support advanced API resource generation with:

- **Nested Relationships**: Automatic handling of complex relationship structures
- **Conditional Fields**: Smart field loading based on data availability
- **Partial Resources**: Multiple resource variants for different contexts
- **Collection Resources**: Pagination, filtering, and sorting support
- **Performance Optimization**: Efficient relationship loading strategies

### 2. ControllerGenerator

A new generator that creates comprehensive API and Web controllers with:

- **API Controllers**: RESTful API endpoints with proper resource responses
- **Web Controllers**: Traditional web controllers with view rendering
- **Route Configuration**: Complete route definitions with middleware
- **Validation Integration**: Automatic request validation rules
- **Policy Support**: Authorization policy integration
- **Soft Delete Handling**: Complete support for soft-deletable models

### 3. EnhancedValidationService

Advanced validation system that provides:

- **Relationship Validation**: Circular dependency detection and consistency checks
- **Field Type Validation**: Comprehensive field type compatibility checking
- **Performance Analysis**: Model complexity analysis with recommendations
- **Comprehensive Reporting**: Detailed validation reports with actionable insights

## Enhanced ResourceGenerator

### Basic Usage

```php
use Grazulex\LaravelModelschema\Services\Generation\Generators\ResourceGenerator;

$generator = new ResourceGenerator();
$result = $generator->generate($schema);
```

### Generated Structure

The enhanced ResourceGenerator creates a comprehensive resource structure:

```json
{
  "resources": {
    "main_resource": {
      "name": "UserResource",
      "namespace": "App\\Http\\Resources",
      "fields": {
        "id": {"type": "integer"},
        "name": {"type": "string"},
        "email": {"type": "string"}
      },
      "relationships": {
        "posts": {
          "type": "hasMany",
          "resource": "PostResource",
          "load_condition": "whenLoaded",
          "with_count": true
        }
      },
      "conditional_fields": {
        "email_verified_at": {
          "condition": "when_not_null",
          "format": "datetime"
        }
      }
    },
    "collection_resource": {
      "name": "UserCollection",
      "pagination": {
        "enabled": true,
        "per_page": 15,
        "meta_fields": ["total", "per_page", "current_page"]
      },
      "filtering": {
        "enabled": true,
        "filterable_fields": ["name", "email", "role"]
      },
      "sorting": {
        "enabled": true,
        "sortable_fields": ["name", "created_at", "updated_at"]
      }
    },
    "partial_resources": {
      "basic": {
        "name": "UserBasicResource",
        "fields": ["id", "name", "email"]
      },
      "summary": {
        "name": "UserSummaryResource", 
        "fields": ["id", "name"]
      },
      "detailed": {
        "name": "UserDetailedResource",
        "include_all_fields": true,
        "include_all_relationships": true
      }
    }
  }
}
```

### Configuration Options

```php
$options = [
    'namespace' => 'App\\Http\\Resources\\V2',
    'enable_filtering' => true,
    'enable_sorting' => true,
    'pagination_per_page' => 25,
    'include_timestamps' => true,
    'nested_relationships' => true,
    'partial_resources' => true
];

$result = $generator->generate($schema, $options);
```

## ControllerGenerator

### Basic Usage

```php
use Grazulex\LaravelModelschema\Services\Generation\Generators\ControllerGenerator;

$generator = new ControllerGenerator();
$result = $generator->generate($schema);
```

### Generated Structure

The ControllerGenerator creates both API and Web controllers:

```json
{
  "controllers": {
    "api_controller": {
      "name": "UserApiController",
      "namespace": "App\\Http\\Controllers\\Api",
      "model": "App\\Models\\User",
      "methods": {
        "index": {
          "return_type": "collection",
          "response_resource": "UserCollection"
        },
        "store": {
          "request_class": "StoreUserRequest",
          "return_type": "resource",
          "response_resource": "UserResource"
        },
        "show": {
          "return_type": "resource",
          "response_resource": "UserResource"
        },
        "update": {
          "request_class": "UpdateUserRequest",
          "return_type": "resource",
          "response_resource": "UserResource"
        },
        "destroy": {
          "return_type": "boolean"
        }
      },
      "validation": {
        "store": {
          "name": ["required", "string", "max:255"],
          "email": ["required", "email", "unique:users"]
        },
        "update": {
          "name": ["string", "max:255"],
          "email": ["email", "unique:users,email,{id}"]
        }
      },
      "relationships": {
        "posts": {
          "type": "hasMany",
          "load_count": true
        }
      }
    },
    "web_controller": {
      "name": "UserController",
      "namespace": "App\\Http\\Controllers",
      "methods": {
        "index": {"view": "users.index"},
        "create": {"view": "users.create"},
        "store": {"redirect": "users.show"},
        "show": {"view": "users.show"},
        "edit": {"view": "users.edit"},
        "update": {"redirect": "users.show"},
        "destroy": {"redirect": "users.index"}
      }
    },
    "resource_routes": {
      "api_routes": {
        "prefix": "api",
        "name": "api.user",
        "controller": "UserApiController",
        "middleware": ["api", "auth:sanctum"]
      },
      "web_routes": {
        "name": "user",
        "controller": "UserController",
        "middleware": ["web", "auth"]
      }
    },
    "middleware": {
      "global": {
        "api": ["api"],
        "web": ["web"]
      },
      "authentication": {
        "api": "auth:sanctum",
        "web": "auth"
      },
      "authorization": {
        "policy": "UserPolicy"
      }
    },
    "policies": {
      "name": "UserPolicy",
      "namespace": "App\\Policies",
      "methods": ["viewAny", "view", "create", "update", "delete", "restore", "forceDelete"]
    }
  }
}
```

### Soft Delete Support

For models with soft deletes, additional methods are generated:

```json
{
  "methods": {
    "restore": {
      "return_type": "resource",
      "response_resource": "UserResource"
    },
    "forceDestroy": {
      "return_type": "boolean"
    }
  }
}
```

### Configuration Options

```php
$options = [
    'api_controller_namespace' => 'App\\Http\\Controllers\\Api\\V1',
    'web_controller_namespace' => 'App\\Http\\Controllers\\Web',
    'enable_policies' => true,
    'route_prefix' => 'admin',
    'middleware_groups' => ['api', 'auth:sanctum'],
    'enable_soft_delete_routes' => true
];

$result = $generator->generate($schema, $options);
```

## EnhancedValidationService

### Basic Usage

```php
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;

$service = new EnhancedValidationService();
$result = $service->validateSchema($schema);
```

### Validation Results

The service provides comprehensive validation results:

```json
{
  "is_valid": true,
  "errors": [],
  "warnings": [
    "Model has many fields (25), consider splitting into related models"
  ],
  "recommendations": [
    "Add indexes for frequently queried fields",
    "Consider caching for complex relationships"
  ],
  "field_validation": {
    "is_valid": true,
    "validated_fields": ["id", "name", "email", "created_at", "updated_at"],
    "field_errors": {},
    "type_compatibility": {
      "string_fields": ["name", "email"],
      "timestamp_fields": ["created_at", "updated_at"],
      "integer_fields": ["id"]
    }
  },
  "relationship_validation": {
    "is_valid": true,
    "relationship_types": {
      "posts": "hasMany",
      "profile": "hasOne"
    },
    "relationship_errors": {}
  },
  "performance_analysis": {
    "field_count": 5,
    "relationship_count": 2,
    "warnings": [],
    "recommendations": [
      "Consider eager loading for hasMany relationships",
      "Add database indexes for foreign keys"
    ]
  }
}
```

### Relationship Consistency Validation

```php
$schemas = [$userSchema, $postSchema, $commentSchema];
$result = $service->validateRelationshipConsistency($schemas);
```

Results include:

```json
{
  "is_consistent": true,
  "circular_dependencies": [],
  "missing_reverse_relationships": [],
  "inconsistent_foreign_keys": []
}
```

### Performance Analysis

```php
$result = $service->analyzePerformance($schema);
```

Provides detailed performance insights:

```json
{
  "field_count": 25,
  "relationship_count": 8,
  "warnings": [
    "High field count may impact query performance",
    "Many relationships may cause N+1 query issues"
  ],
  "recommendations": [
    "Consider model splitting",
    "Implement eager loading strategies",
    "Add selective field loading"
  ]
}
```

## Integration with GenerationService

### Enhanced Generation

The GenerationService now coordinates all 7 generators:

```php
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

$service = new GenerationService();
$generators = ['model', 'migration', 'request', 'resource', 'factory', 'seeder', 'controller'];

$result = $service->generateMultiple($schema, $generators);
```

### With Validation

Enable validation during generation:

```php
$options = ['enable_validation' => true];
$result = $service->generateMultiple($schema, $generators, $options);

// Results include validation data
$validation = json_decode($result['json'], true)['validation_results'];
```

### Custom Options per Generator

```php
$options = [
    'resource' => [
        'namespace' => 'App\\Http\\Resources\\V2',
        'enable_filtering' => false
    ],
    'controller' => [
        'api_controller_namespace' => 'App\\Http\\Controllers\\Api\\V2',
        'enable_policies' => false
    ]
];

$result = $service->generateMultiple($schema, $generators, $options);
```

## Advanced Features

### Nested Relationship Loading

Resources automatically handle nested relationships:

```json
{
  "relationships": {
    "posts": {
      "type": "hasMany",
      "resource": "PostResource",
      "nested_loading": true,
      "load_condition": "whenLoaded",
      "with_count": true
    },
    "posts.comments": {
      "type": "nested",
      "resource": "CommentResource",
      "load_condition": "whenLoaded"
    }
  }
}
```

### Conditional Field Loading

Smart conditional loading based on data availability:

```json
{
  "conditional_fields": {
    "email_verified_at": {
      "condition": "when_not_null",
      "format": "datetime"
    },
    "avatar_url": {
      "condition": "when_not_null",
      "transform": "url"
    }
  }
}
```

### Advanced Filtering and Sorting

Collection resources support advanced filtering:

```json
{
  "filtering": {
    "enabled": true,
    "filterable_fields": ["name", "email", "role", "created_at"],
    "search_fields": ["name", "email"],
    "date_range_fields": ["created_at", "updated_at"]
  },
  "sorting": {
    "enabled": true,
    "sortable_fields": ["name", "created_at", "updated_at"],
    "default_sort": "created_at:desc"
  }
}
```

## Best Practices

### 1. Resource Organization

- Use **main_resource** for detailed single-item responses
- Use **collection_resource** for paginated listings
- Use **partial_resources** for different contexts (basic, summary, detailed)
- Leverage **conditional_fields** for optional data

### 2. Controller Structure

- Separate API and Web controllers for different concerns
- Use proper middleware for authentication and authorization
- Implement policies for fine-grained access control
- Handle soft deletes appropriately

### 3. Validation Strategy

- Always validate schemas before generation in production
- Pay attention to performance warnings for large models
- Check relationship consistency across multiple models
- Use recommendations to optimize your data structure

### 4. Performance Optimization

- Enable eager loading for frequently accessed relationships
- Use partial resources to reduce payload size
- Implement proper pagination for large datasets
- Add database indexes based on validation recommendations

## Examples

### Complete E-commerce Product Example

```php
$productSchema = ModelSchema::fromArray('Product', [
    'table' => 'products',
    'fields' => [
        'id' => ['type' => 'bigInteger'],
        'name' => ['type' => 'string', 'rules' => ['required', 'max:255']],
        'slug' => ['type' => 'string', 'unique' => true],
        'description' => ['type' => 'text'],
        'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
        'sale_price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2, 'nullable' => true],
        'stock_quantity' => ['type' => 'integer', 'default' => 0],
        'is_active' => ['type' => 'boolean', 'default' => true],
        'meta_data' => ['type' => 'json', 'nullable' => true],
        'created_at' => ['type' => 'timestamp'],
        'updated_at' => ['type' => 'timestamp']
    ],
    'relationships' => [
        'category' => ['type' => 'belongsTo', 'model' => 'App\\Models\\Category'],
        'reviews' => ['type' => 'hasMany', 'model' => 'App\\Models\\Review'],
        'tags' => ['type' => 'belongsToMany', 'model' => 'App\\Models\\Tag'],
        'images' => ['type' => 'hasMany', 'model' => 'App\\Models\\ProductImage']
    ]
]);

// Generate complete application layer
$generators = ['model', 'migration', 'request', 'resource', 'controller', 'factory', 'seeder'];
$result = $service->generateMultiple($productSchema, $generators, ['enable_validation' => true]);
```

This generates a complete, production-ready structure for a Product model with all necessary components and validation.
