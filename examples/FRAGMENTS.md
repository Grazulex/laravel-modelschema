# Fragment Examples

This directory contains examples of the insertable fragments that Laravel ModelSchema generates. Parent applications can integrate these fragments into their own generation workflows.

## Fragment Structure

Each generator produces fragments in both JSON and YAML formats with the following structure:

```json
{
  "generator_name": {
    "key1": "value1",
    "key2": "value2",
    ...
  }
}
```

The fragment is designed to be insertable into parent application templates or merged with other data.

## Available Fragments

### Model Fragment
```json
{
  "model": {
    "class_name": "User",
    "table": "users",
    "namespace": "App\\Models",
    "fillable": ["name", "email"],
    "casts": {
      "email_verified_at": "timestamp"
    },
    "hidden": ["password"],
    "relations": {
      "posts": {
        "type": "hasMany",
        "model": "App\\Models\\Post"
      }
    },
    "options": {
      "timestamps": true,
      "soft_deletes": false
    }
  }
}
```

### Migration Fragment
```json
{
  "migration": {
    "table": "users",
    "class_name": "CreateUsersTable",
    "fields": [
      {
        "name": "name",
        "type": "string",
        "nullable": false,
        "unique": false
      },
      {
        "name": "email",
        "type": "string",
        "nullable": false,
        "unique": true
      }
    ],
    "indexes": [
      {
        "fields": ["email"],
        "type": "unique"
      }
    ],
    "foreign_keys": [],
    "options": {
      "timestamps": true,
      "soft_deletes": false
    }
  }
}
```

### Request Fragment
```json
{
  "requests": {
    "store": {
      "class_name": "StoreUserRequest",
      "rules": {
        "name": ["required", "string", "max:255"],
        "email": ["required", "email", "unique:users"]
      },
      "messages": {},
      "attributes": {}
    },
    "update": {
      "class_name": "UpdateUserRequest",
      "rules": {
        "name": ["sometimes", "string", "max:255"],
        "email": ["sometimes", "email", "unique:users,email,{id}"]
      },
      "messages": {},
      "attributes": {}
    }
  }
}
```

### Resource Fragment
```json
{
  "resources": {
    "single": {
      "class_name": "UserResource",
      "fields": {
        "id": "id",
        "name": "name",
        "email": "email",
        "created_at": "created_at",
        "updated_at": "updated_at"
      },
      "relations": {
        "posts": "PostResource"
      }
    },
    "collection": {
      "class_name": "UserCollection",
      "resource_class": "UserResource"
    }
  }
}
```

### Factory Fragment
```json
{
  "factory": {
    "class_name": "UserFactory",
    "model": "App\\Models\\User",
    "fields": {
      "name": "fake()->name()",
      "email": "fake()->unique()->safeEmail()",
      "email_verified_at": "now()",
      "password": "Hash::make('password')"
    },
    "states": {
      "unverified": {
        "email_verified_at": null
      }
    }
  }
}
```

### Seeder Fragment
```json
{
  "seeder": {
    "class_name": "UserSeeder",
    "model": "App\\Models\\User",
    "factory": "App\\Database\\Factories\\UserFactory",
    "count": 10,
    "data": [
      {
        "name": "Admin User",
        "email": "admin@example.com"
      }
    ]
  }
}
```

## Usage in Parent Applications

Parent applications receive these fragments and integrate them into their own templates:

```php
// Get generation data from ModelSchema
$data = $schemaService->getGenerationDataFromCompleteYaml($yamlContent);

// Extract model fragment
$modelFragment = json_decode($data['generation_data']['model']['json'], true);

// Use in parent app template
$modelClass = $modelFragment['model']['class_name'];
$fillable = $modelFragment['model']['fillable'];
$casts = $modelFragment['model']['casts'];

// Generate parent app's model file
$modelContent = view('parent-app.model-template', [
    'class_name' => $modelClass,
    'fillable' => $fillable,
    'casts' => $casts,
    'relations' => $modelFragment['model']['relations']
])->render();

file_put_contents(
    app_path("Models/{$modelClass}.php"),
    $modelContent
);
```

## YAML Format

All fragments are also available in YAML format:

```yaml
model:
  class_name: User
  table: users
  namespace: App\Models
  fillable:
    - name
    - email
  casts:
    email_verified_at: timestamp
  relations:
    posts:
      type: hasMany
      model: App\Models\Post
  options:
    timestamps: true
    soft_deletes: false
```

## Benefits of Fragment Architecture

1. **Clean Separation**: Core schema logic separated from app-specific generation
2. **Flexibility**: Parent apps control final file structure and content
3. **Consistency**: Standardized fragment format across all generators
4. **Extensibility**: Easy to add new generators or modify existing ones
5. **Integration**: Simple JSON/YAML format for easy parsing and manipulation
