<?php

use Grazulex\LaravelModelschema\ModelSchemaManager;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\Relationship;

// Tests pour ModelSchemaManager
it('can create schema from array using manager', function () {
    $config = [
        'table' => 'users',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'nullable' => false],
            'name' => ['type' => 'string', 'length' => 255]
        ],
        'relationships' => [
            'posts' => ['type' => 'hasMany', 'model' => 'Post']
        ],
        'options' => ['timestamps' => true]
    ];
    
    $schema = ModelSchemaManager::fromArray('User', $config);
    
    expect($schema)->toBeInstanceOf(ModelSchema::class);
    expect($schema->name)->toBe('User');
    expect($schema->table)->toBe('users');
    expect($schema->fields)->toHaveCount(2);
    expect($schema->relationships)->toHaveCount(1);
});

it('validates schema correctly', function () {
    // Valid schema
    $validSchema = new ModelSchema(
        name: 'User',
        table: 'users',
        fields: [
            new Field('id', 'bigInteger'),
            new Field('name', 'string')
        ],
        relationships: [
            new Relationship('posts', 'hasMany', 'Post')
        ]
    );
    
    $errors = ModelSchemaManager::validate($validSchema);
    expect($errors)->toBeEmpty();
});

it('detects validation errors for empty fields', function () {
    $invalidSchema = new ModelSchema(
        name: 'EmptyModel',
        table: 'empty_models',
        fields: [], // No fields
        relationships: []
    );
    
    $errors = ModelSchemaManager::validate($invalidSchema);
    expect($errors)->toContain('Schema must have at least one field');
});

it('detects validation errors for invalid field types', function () {
    $invalidSchema = new ModelSchema(
        name: 'InvalidModel',
        table: 'invalid_models',
        fields: [
            new Field('id', 'bigInteger'),
            new Field('bad_field', 'invalidType') // Invalid type
        ]
    );
    
    $errors = ModelSchemaManager::validate($invalidSchema);
    expect($errors)->toContain("Invalid field type 'invalidType' for field 'bad_field'");
});

it('detects validation errors for invalid relationship types', function () {
    $invalidSchema = new ModelSchema(
        name: 'InvalidModel',
        table: 'invalid_models',
        fields: [
            new Field('id', 'bigInteger')
        ],
        relationships: [
            new Relationship('bad_rel', 'invalidRelType', 'Model') // Invalid type
        ]
    );
    
    $errors = ModelSchemaManager::validate($invalidSchema);
    expect($errors)->toContain("Invalid relationship type 'invalidRelType' for relationship 'bad_rel'");
});

it('detects validation errors for relationships without model', function () {
    $invalidSchema = new ModelSchema(
        name: 'InvalidModel',
        table: 'invalid_models',
        fields: [
            new Field('id', 'bigInteger')
        ],
        relationships: [
            new Relationship('bad_rel', 'hasMany', '') // Empty model
        ]
    );
    
    $errors = ModelSchemaManager::validate($invalidSchema);
    expect($errors)->toContain("Relationship 'bad_rel' must have a model");
});

it('allows morphTo relationships without model', function () {
    $validSchema = new ModelSchema(
        name: 'ValidModel',
        table: 'valid_models',
        fields: [
            new Field('id', 'bigInteger')
        ],
        relationships: [
            new Relationship('commentable', 'morphTo', '') // morphTo can have empty model
        ]
    );
    
    $errors = ModelSchemaManager::validate($validSchema);
    expect($errors)->toBeEmpty();
});

it('returns correct supported field types', function () {
    $fieldTypes = ModelSchemaManager::getSupportedFieldTypes();
    
    expect($fieldTypes)->toBeArray();
    expect($fieldTypes)->toContain('string');
    expect($fieldTypes)->toContain('integer');
    expect($fieldTypes)->toContain('bigInteger');
    expect($fieldTypes)->toContain('boolean');
    expect($fieldTypes)->toContain('json');
    expect($fieldTypes)->toContain('uuid');
    expect($fieldTypes)->toContain('email');
    expect($fieldTypes)->toContain('decimal');
    expect($fieldTypes)->toContain('float');
    expect($fieldTypes)->toContain('date');
    expect($fieldTypes)->toContain('timestamp');
});

it('returns correct supported relationship types', function () {
    $relationshipTypes = ModelSchemaManager::getSupportedRelationshipTypes();
    
    expect($relationshipTypes)->toBeArray();
    expect($relationshipTypes)->toContain('belongsTo');
    expect($relationshipTypes)->toContain('hasOne');
    expect($relationshipTypes)->toContain('hasMany');
    expect($relationshipTypes)->toContain('belongsToMany');
    expect($relationshipTypes)->toContain('morphTo');
    expect($relationshipTypes)->toContain('morphOne');
    expect($relationshipTypes)->toContain('morphMany');
    expect($relationshipTypes)->toContain('hasManyThrough');
    expect($relationshipTypes)->toContain('hasOneThrough');
});

it('creates basic template with default fields', function () {
    $template = ModelSchemaManager::createTemplate('Product');
    
    expect($template)->toBeArray();
    expect($template['model'])->toBe('Product');
    expect($template['table'])->toBe('products'); // pluralized
    expect($template['fields'])->toHaveKey('id');
    expect($template['fields'])->toHaveKey('name');
    expect($template['relationships'])->toBeArray();
    expect($template['options'])->toHaveKey('timestamps');
    expect($template['metadata'])->toHaveKey('version');
    expect($template['metadata'])->toHaveKey('description');
    expect($template['metadata'])->toHaveKey('created_at');
});

it('creates template with custom fields', function () {
    $customFields = [
        'title' => [
            'type' => 'string',
            'nullable' => false,
            'length' => 255
        ],
        'content' => [
            'type' => 'text',
            'nullable' => true
        ]
    ];
    
    $template = ModelSchemaManager::createTemplate('Article', $customFields);
    
    expect($template['model'])->toBe('Article');
    expect($template['table'])->toBe('articles');
    expect($template['fields'])->toBe($customFields);
    expect($template['fields'])->toHaveKey('title');
    expect($template['fields'])->toHaveKey('content');
    expect($template['fields'])->not->toHaveKey('id'); // custom fields replace defaults
    expect($template['fields'])->not->toHaveKey('name');
});

it('template has correct structure and metadata', function () {
    $template = ModelSchemaManager::createTemplate('TestModel');
    
    // Check main structure
    expect($template)->toHaveKeys(['model', 'table', 'fields', 'relationships', 'options', 'metadata']);
    
    // Check options
    expect($template['options'])->toHaveKeys(['timestamps', 'soft_deletes', 'namespace']);
    expect($template['options']['timestamps'])->toBeTrue();
    expect($template['options']['soft_deletes'])->toBeFalse();
    expect($template['options']['namespace'])->toBe('App\\Models');
    
    // Check metadata
    expect($template['metadata'])->toHaveKeys(['version', 'description', 'created_at']);
    expect($template['metadata']['version'])->toBe('1.0');
    expect($template['metadata']['description'])->toContain('TestModel');
    expect($template['metadata']['created_at'])->toBeString();
});

it('table name is correctly pluralized and snake_cased', function () {
    $templates = [
        'User' => 'users',
        'BlogPost' => 'blog_posts',
        'Category' => 'categories',
        'ProductVariant' => 'product_variants',
        'OrderItem' => 'order_items'
    ];
    
    foreach ($templates as $model => $expectedTable) {
        $template = ModelSchemaManager::createTemplate($model);
        expect($template['table'])->toBe($expectedTable);
    }
});

it('validates multiple errors correctly', function () {
    $invalidSchema = new ModelSchema(
        name: 'MultiErrorModel',
        table: 'multi_error_models',
        fields: [
            new Field('bad_field', 'invalidType'), // Error 1: invalid field type
        ],
        relationships: [
            new Relationship('bad_rel1', 'invalidRelType', 'Model'), // Error 2: invalid rel type
            new Relationship('bad_rel2', 'hasMany', ''), // Error 3: empty model
        ]
    );
    
    $errors = ModelSchemaManager::validate($invalidSchema);
    
    expect($errors)->toHaveCount(3);
    expect($errors)->toContain("Invalid field type 'invalidType' for field 'bad_field'");
    expect($errors)->toContain("Invalid relationship type 'invalidRelType' for relationship 'bad_rel1'");
    expect($errors)->toContain("Relationship 'bad_rel2' must have a model");
});
