<?php

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Schema\ModelSchema;

it('can create a field with all properties', function () {
    $field = new Field(
        name: 'test_field',
        type: 'string',
        nullable: true,
        unique: false,
        default: 'default_value',
        comment: 'Test comment',
        rules: ['required', 'string'],
        length: 255
    );

    expect($field->name)->toBe('test_field');
    expect($field->type)->toBe('string');
    expect($field->nullable)->toBeTrue();
    expect($field->unique)->toBeFalse();
    expect($field->default)->toBe('default_value');
    expect($field->comment)->toBe('Test comment');
    expect($field->rules)->toBe(['required', 'string']);
    expect($field->length)->toBe(255);
});

it('can create a field with minimal properties', function () {
    $field = new Field(
        name: 'simple_field',
        type: 'integer'
    );

    expect($field->name)->toBe('simple_field');
    expect($field->type)->toBe('integer');
    expect($field->nullable)->toBeFalse(); // default
    expect($field->unique)->toBeFalse(); // default
    expect($field->default)->toBeNull(); // default
    expect($field->comment)->toBeNull(); // default
    expect($field->rules)->toBe([]); // default
});

it('can create field from array', function () {
    $config = [
        'type' => 'string',
        'nullable' => true,
        'unique' => false,
        'length' => 255,
        'rules' => ['required', 'string']
    ];

    $field = Field::fromArray('email', $config);

    expect($field->name)->toBe('email');
    expect($field->type)->toBe('string');
    expect($field->nullable)->toBeTrue();
    expect($field->length)->toBe(255);
    expect($field->rules)->toBe(['required', 'string']);
});

it('can create a relationship with all properties', function () {
    $relationship = new Relationship(
        name: 'user',
        type: 'belongsTo',
        model: 'App\\Models\\User',
        foreignKey: 'user_id',
        localKey: 'id'
    );

    expect($relationship->name)->toBe('user');
    expect($relationship->type)->toBe('belongsTo');
    expect($relationship->model)->toBe('App\\Models\\User');
    expect($relationship->foreignKey)->toBe('user_id');
    expect($relationship->localKey)->toBe('id');
});

it('can create a relationship with minimal properties', function () {
    $relationship = new Relationship(
        name: 'posts',
        type: 'hasMany',
        model: 'App\\Models\\Post'
    );

    expect($relationship->name)->toBe('posts');
    expect($relationship->type)->toBe('hasMany');
    expect($relationship->model)->toBe('App\\Models\\Post');
    expect($relationship->foreignKey)->toBeNull();
    expect($relationship->localKey)->toBeNull();
    expect($relationship->pivotTable)->toBeNull();
});

it('can create belongsToMany relationship with pivot table', function () {
    $relationship = new Relationship(
        name: 'roles',
        type: 'belongsToMany',
        model: 'App\\Models\\Role',
        pivotTable: 'user_roles'
    );

    expect($relationship->name)->toBe('roles');
    expect($relationship->type)->toBe('belongsToMany');
    expect($relationship->pivotTable)->toBe('user_roles');
});

it('can create a model schema with all properties', function () {
    $field1 = new Field('id', 'bigInteger');
    $field2 = new Field('name', 'string');
    $relationship = new Relationship('posts', 'hasMany', 'App\\Models\\Post');

    $schema = new ModelSchema(
        name: 'User',
        table: 'users',
        fields: [$field1, $field2],
        relationships: [$relationship],
        options: ['timestamps' => true, 'softDeletes' => false]
    );

    expect($schema->name)->toBe('User');
    expect($schema->table)->toBe('users');
    expect($schema->fields)->toHaveCount(2);
    expect($schema->relationships)->toHaveCount(1);
    expect($schema->options)->toBe(['timestamps' => true, 'softDeletes' => false]);
});

it('can create a model schema with minimal properties', function () {
    $field = new Field('id', 'bigInteger');

    $schema = new ModelSchema(
        name: 'SimpleModel',
        table: 'simple_models',
        fields: [$field]
    );

    expect($schema->name)->toBe('SimpleModel');
    expect($schema->table)->toBe('simple_models');
    expect($schema->fields)->toHaveCount(1);
    expect($schema->relationships)->toBeEmpty();
    expect($schema->options)->toBeEmpty();
});

it('can create schema from array data', function () {
    $config = [
        'table' => 'posts',
        'fields' => [
            'id' => [
                'type' => 'bigInteger',
                'nullable' => false,
                'unique' => true
            ],
            'title' => [
                'type' => 'string',
                'nullable' => false,
                'length' => 255
            ]
        ],
        'relationships' => [
            'author' => [
                'type' => 'belongsTo',
                'model' => 'App\\Models\\User',
                'foreign_key' => 'user_id'
            ]
        ],
        'options' => [
            'timestamps' => true,
            'softDeletes' => false
        ]
    ];

    $schema = ModelSchema::fromArray('Post', $config);

    expect($schema->name)->toBe('Post');
    expect($schema->table)->toBe('posts');
    expect($schema->fields)->toHaveCount(2);
    expect($schema->relationships)->toHaveCount(1);
    expect($schema->options)->toBe(['timestamps' => true, 'softDeletes' => false]);

    // Check field properties - use collect to find fields
    $idField = collect($schema->fields)->firstWhere('name', 'id');
    expect($idField->name)->toBe('id');
    expect($idField->type)->toBe('bigInteger');
    expect($idField->nullable)->toBeFalse();
    expect($idField->unique)->toBeTrue();

    // Check relationship properties
    $authorRel = collect($schema->relationships)->firstWhere('name', 'author');
    expect($authorRel->name)->toBe('author');
    expect($authorRel->type)->toBe('belongsTo');
    expect($authorRel->model)->toBe('App\\Models\\User');
    expect($authorRel->foreignKey)->toBe('user_id');
});
