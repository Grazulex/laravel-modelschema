<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;

// Dataset pour différents types de champs
dataset('fieldConfigs', [
    'string_field' => [
        'name' => 'title',
        'config' => [
            'type' => 'string',
            'nullable' => false,
            'length' => 255,
            'rules' => ['required', 'string', 'max:255'],
            'comment' => 'Article title',
        ],
    ],
    'integer_field' => [
        'name' => 'count',
        'config' => [
            'type' => 'integer',
            'nullable' => true,
            'default' => 0,
            'rules' => ['integer', 'min:0'],
        ],
    ],
    'boolean_field' => [
        'name' => 'is_active',
        'config' => [
            'type' => 'boolean',
            'nullable' => false,
            'default' => true,
            'validation' => ['boolean'],
        ],
    ],
    'decimal_field' => [
        'name' => 'price',
        'config' => [
            'type' => 'decimal',
            'precision' => 8,
            'scale' => 2,
            'nullable' => false,
            'rules' => ['required', 'numeric', 'min:0'],
        ],
    ],
]);

// Tests pour Field
it('creates field from array with all configurations', function (string $name, array $config) {
    $field = Field::fromArray($name, $config);

    expect($field->name)->toBe($name);
    expect($field->type)->toBe($config['type']);

    if (isset($config['nullable'])) {
        expect($field->nullable)->toBe($config['nullable']);
    }

    if (isset($config['length'])) {
        expect($field->length)->toBe($config['length']);
    }

    if (isset($config['precision'])) {
        expect($field->precision)->toBe($config['precision']);
    }

    if (isset($config['scale'])) {
        expect($field->scale)->toBe($config['scale']);
    }

    if (isset($config['default'])) {
        expect($field->default)->toBe($config['default']);
    }

    if (isset($config['rules'])) {
        expect($field->rules)->toBe($config['rules']);
    }

    if (isset($config['validation'])) {
        expect($field->validation)->toBe($config['validation']);
    }

    if (isset($config['comment'])) {
        expect($field->comment)->toBe($config['comment']);
    }
})->with('fieldConfigs');

it('field has correct basic properties', function () {
    $field = Field::fromArray('email', [
        'type' => 'string',
        'nullable' => false,
        'unique' => true,
        'rules' => ['required', 'email', 'unique:users'],
        'validation' => ['email'],
    ]);

    // Test field properties that exist
    expect($field->nullable)->toBeFalse();
    expect($field->unique)->toBeTrue();
    expect($field->index)->toBeFalse();
    expect($field->default)->toBeNull();
    expect($field->length)->toBeNull();
    expect($field->precision)->toBeNull();
    expect($field->scale)->toBeNull();
});

it('field handles different default values', function () {
    $stringField = Field::fromArray('name', ['type' => 'string', 'default' => 'John']);
    $intField = Field::fromArray('age', ['type' => 'integer', 'default' => 25]);
    $boolField = Field::fromArray('active', ['type' => 'boolean', 'default' => false]);
    $nullField = Field::fromArray('optional', ['type' => 'string', 'default' => null]);

    expect($stringField->default)->toBe('John');
    expect($intField->default)->toBe(25);
    expect($boolField->default)->toBe(false);
    expect($nullField->default)->toBeNull();
});

it('field to array conversion', function () {
    $field = Field::fromArray('title', [
        'type' => 'string',
        'nullable' => false,
        'length' => 255,
        'rules' => ['required', 'string'],
        'comment' => 'Title field',
    ]);

    $array = $field->toArray();

    expect($array)->toBeArray();
    expect($array['name'])->toBe('title');
    expect($array['type'])->toBe('string');
    expect($array['nullable'])->toBe(false);
    expect($array['length'])->toBe(255);
    expect($array['rules'])->toBe(['required', 'string']);
    expect($array['comment'])->toBe('Title field');
});

// Dataset pour les relations
dataset('relationshipConfigs', [
    'belongs_to' => [
        'name' => 'user',
        'config' => [
            'type' => 'belongsTo',
            'model' => 'App\\Models\\User',
            'foreign_key' => 'user_id',
            'local_key' => 'id',
        ],
    ],
    'has_many' => [
        'name' => 'posts',
        'config' => [
            'type' => 'hasMany',
            'model' => 'App\\Models\\Post',
            'foreign_key' => 'user_id',
        ],
    ],
    'belongs_to_many' => [
        'name' => 'roles',
        'config' => [
            'type' => 'belongsToMany',
            'model' => 'App\\Models\\Role',
            'pivot_table' => 'user_roles',
            'pivot_fields' => ['created_at', 'updated_at'],
            'with_timestamps' => true,
        ],
    ],
    'has_one' => [
        'name' => 'profile',
        'config' => [
            'type' => 'hasOne',
            'model' => 'App\\Models\\Profile',
            'foreign_key' => 'user_id',
            'local_key' => 'id',
        ],
    ],
]);

// Tests pour Relationship
it('creates relationship from array with all configurations', function (string $name, array $config) {
    $relationship = Relationship::fromArray($name, $config);

    expect($relationship->name)->toBe($name);
    expect($relationship->type)->toBe($config['type']);
    expect($relationship->model)->toBe($config['model']);

    if (isset($config['foreign_key'])) {
        expect($relationship->foreignKey)->toBe($config['foreign_key']);
    }

    if (isset($config['local_key'])) {
        expect($relationship->localKey)->toBe($config['local_key']);
    }

    if (isset($config['pivot_table'])) {
        expect($relationship->pivotTable)->toBe($config['pivot_table']);
    }

    if (isset($config['pivot_fields'])) {
        expect($relationship->pivotFields)->toBe($config['pivot_fields']);
    }

    if (isset($config['with_timestamps'])) {
        expect($relationship->withTimestamps)->toBe($config['with_timestamps']);
    }
})->with('relationshipConfigs');

it('relationship has correct basic properties', function () {
    $belongsTo = Relationship::fromArray('user', ['type' => 'belongsTo', 'model' => 'User']);
    $hasMany = Relationship::fromArray('posts', ['type' => 'hasMany', 'model' => 'Post']);
    $belongsToMany = Relationship::fromArray('roles', ['type' => 'belongsToMany', 'model' => 'Role']);
    $hasOne = Relationship::fromArray('profile', ['type' => 'hasOne', 'model' => 'Profile']);

    expect($belongsTo->type)->toBe('belongsTo');
    expect($hasMany->type)->toBe('hasMany');
    expect($belongsToMany->type)->toBe('belongsToMany');
    expect($hasOne->type)->toBe('hasOne');
});

it('relationship to array conversion', function () {
    $relationship = Relationship::fromArray('roles', [
        'type' => 'belongsToMany',
        'model' => 'App\\Models\\Role',
        'pivot_table' => 'user_roles',
        'with_timestamps' => true,
    ]);

    $array = $relationship->toArray();

    expect($array)->toBeArray();
    expect($array['name'])->toBe('roles');
    expect($array['type'])->toBe('belongsToMany');
    expect($array['model'])->toBe('App\\Models\\Role');
    expect($array['pivot_table'])->toBe('user_roles');
    expect($array['with_timestamps'])->toBe(true);
});

// Tests pour ModelSchema
it('creates complete model schema with all features', function () {
    $fields = [
        Field::fromArray('id', ['type' => 'bigInteger', 'nullable' => false]),
        Field::fromArray('name', ['type' => 'string', 'nullable' => false, 'length' => 255]),
        Field::fromArray('email', ['type' => 'string', 'nullable' => false, 'unique' => true]),
        Field::fromArray('active', ['type' => 'boolean', 'default' => true]),
    ];

    $relationships = [
        Relationship::fromArray('posts', ['type' => 'hasMany', 'model' => 'Post']),
        Relationship::fromArray('profile', ['type' => 'hasOne', 'model' => 'Profile']),
    ];

    $schema = new ModelSchema(
        name: 'User',
        table: 'users',
        fields: $fields,
        relationships: $relationships,
        options: ['timestamps' => true, 'softDeletes' => true],
        metadata: ['created_by' => 'schema_generator', 'version' => '1.0']
    );

    expect($schema->name)->toBe('User');
    expect($schema->table)->toBe('users');
    expect($schema->fields)->toHaveCount(4);
    expect($schema->relationships)->toHaveCount(2);
    expect($schema->options)->toBe(['timestamps' => true, 'softDeletes' => true]);
    expect($schema->metadata)->toBe(['created_by' => 'schema_generator', 'version' => '1.0']);
});

it('model schema basic access methods', function () {
    $fields = [
        Field::fromArray('id', ['type' => 'bigInteger']),
        Field::fromArray('name', ['type' => 'string']),
        Field::fromArray('email', ['type' => 'string', 'unique' => true]),
    ];

    $relationships = [
        Relationship::fromArray('posts', ['type' => 'hasMany', 'model' => 'Post']),
        Relationship::fromArray('profile', ['type' => 'hasOne', 'model' => 'Profile']),
    ];

    $schema = new ModelSchema('User', 'users', $fields, $relationships);

    // Test basic properties
    expect($schema->name)->toBe('User');
    expect($schema->table)->toBe('users');
    expect($schema->fields)->toHaveCount(3);
    expect($schema->relationships)->toHaveCount(2);

    // Test field access by name using collect
    $nameField = collect($schema->fields)->firstWhere('name', 'name');
    expect($nameField->name)->toBe('name');
    expect($nameField->type)->toBe('string');

    // Test relationship access by name using collect
    $postsRel = collect($schema->relationships)->firstWhere('name', 'posts');
    expect($postsRel->name)->toBe('posts');
    expect($postsRel->type)->toBe('hasMany');
});

it('model schema basic properties', function () {
    $fields = [
        Field::fromArray('name', ['type' => 'string', 'rules' => ['required', 'string']]),
        Field::fromArray('email', ['type' => 'string', 'rules' => ['required', 'email']]),
        Field::fromArray('age', ['type' => 'integer', 'rules' => ['integer', 'min:0']]),
    ];

    $schema = new ModelSchema('User', 'users', $fields);

    // Test basic schema properties
    expect($schema->name)->toBe('User');
    expect($schema->table)->toBe('users');
    expect($schema->fields)->toHaveCount(3);
    expect($schema->relationships)->toBeEmpty();
    expect($schema->options)->toBeEmpty();
});

it('model schema array conversion', function () {
    $config = [
        'table' => 'users',
        'fields' => [
            'id' => ['type' => 'bigInteger', 'nullable' => false],
            'name' => ['type' => 'string', 'length' => 255, 'rules' => ['required']],
            'email' => ['type' => 'string', 'unique' => true],
        ],
        'relationships' => [
            'posts' => ['type' => 'hasMany', 'model' => 'Post'],
            'profile' => ['type' => 'hasOne', 'model' => 'Profile'],
        ],
        'options' => ['timestamps' => true],
        'metadata' => ['version' => '1.0'],
    ];

    $schema = ModelSchema::fromArray('User', $config);

    expect($schema->name)->toBe('User');
    expect($schema->fields)->toHaveCount(3);
    expect($schema->relationships)->toHaveCount(2);

    // Test basic schema properties
    expect($schema->table)->toBe('users');
    expect($schema->options)->toBe(['timestamps' => true]);
    expect($schema->metadata)->toBe(['version' => '1.0']);
});
