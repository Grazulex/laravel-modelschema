<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ResourceGenerator;

describe('Enhanced ResourceGenerator', function () {
    beforeEach(function () {
        $this->generator = new ResourceGenerator();
        $this->schema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'name' => ['type' => 'string', 'nullable' => false],
                'email' => ['type' => 'string', 'unique' => true],
                'avatar' => ['type' => 'string', 'nullable' => true],
                'role' => ['type' => 'string', 'default' => 'user'],
                'email_verified_at' => ['type' => 'timestamp', 'nullable' => true],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Post',
                ],
                'profile' => [
                    'type' => 'hasOne',
                    'model' => 'App\Models\Profile',
                ],
                'roles' => [
                    'type' => 'belongsToMany',
                    'model' => 'App\Models\Role',
                    'pivot_table' => 'user_roles',
                ],
            ],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => false,
            ],
        ]);
    });

    it('can generate enhanced resources data in JSON format as insertable fragment', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('resources');

        $resources = $jsonData['resources'];
        expect($resources)->toHaveKey('main_resource');
        expect($resources)->toHaveKey('collection_resource');
        expect($resources)->toHaveKey('partial_resources');
        expect($resources)->toHaveKey('relationship_resources');

        // Check main resource structure
        $mainResource = $resources['main_resource'];
        expect($mainResource['name'])->toBe('UserResource');
        expect($mainResource['namespace'])->toBe('App\Http\Resources');
        expect($mainResource)->toHaveKey('fields');
        expect($mainResource)->toHaveKey('relationships');
        expect($mainResource)->toHaveKey('conditional_fields');
    });

    it('generates proper field transformations', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $fields = $jsonData['resources']['main_resource']['fields'];

        expect($fields)->toHaveKey('id');
        expect($fields['id']['type'])->toBe('integer');

        expect($fields)->toHaveKey('name');
        expect($fields['name']['type'])->toBe('string');

        expect($fields)->toHaveKey('email');
        expect($fields['email']['type'])->toBe('string');

        // Check conditional fields
        $conditionalFields = $jsonData['resources']['main_resource']['conditional_fields'];
        expect($conditionalFields)->toHaveKey('email_verified_at');
        expect($conditionalFields['email_verified_at']['condition'])->toBe('when_not_null');

        expect($conditionalFields)->toHaveKey('avatar');
        expect($conditionalFields['avatar']['condition'])->toBe('when_not_null');
    });

    it('generates nested relationship resources', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $relationships = $jsonData['resources']['main_resource']['relationships'];

        // hasMany relationship
        expect($relationships)->toHaveKey('posts');
        expect($relationships['posts']['type'])->toBe('hasMany');
        expect($relationships['posts']['resource'])->toBe('PostResource');
        expect($relationships['posts']['load_condition'])->toBe('whenLoaded');
        expect($relationships['posts']['with_count'])->toBe(true);

        // hasOne relationship
        expect($relationships)->toHaveKey('profile');
        expect($relationships['profile']['type'])->toBe('hasOne');
        expect($relationships['profile']['resource'])->toBe('ProfileResource');
        expect($relationships['profile']['load_condition'])->toBe('whenLoaded');

        // belongsToMany relationship
        expect($relationships)->toHaveKey('roles');
        expect($relationships['roles']['type'])->toBe('belongsToMany');
        expect($relationships['roles']['resource'])->toBe('RoleResource');
        expect($relationships['roles']['with_pivot'])->toBe(true);
    });

    it('generates partial resources for different contexts', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $partialResources = $jsonData['resources']['partial_resources'];

        // Basic resource for listings
        expect($partialResources)->toHaveKey('basic');
        $basicResource = $partialResources['basic'];
        expect($basicResource['name'])->toBe('UserBasicResource');
        expect($basicResource['fields'])->toHaveKey('id');
        expect($basicResource['fields'])->toHaveKey('name');
        expect($basicResource['fields'])->toHaveKey('email');
        expect($basicResource['fields'])->not->toHaveKey('email_verified_at');

        // Summary resource for cards
        expect($partialResources)->toHaveKey('summary');
        $summaryResource = $partialResources['summary'];
        expect($summaryResource['name'])->toBe('UserSummaryResource');
        expect($summaryResource['fields'])->toHaveKey('id');
        expect($summaryResource['fields'])->toHaveKey('name');

        // Detailed resource for admin
        expect($partialResources)->toHaveKey('detailed');
        $detailedResource = $partialResources['detailed'];
        expect($detailedResource['name'])->toBe('UserDetailedResource');
        expect($detailedResource['include_all_fields'])->toBe(true);
        expect($detailedResource['include_all_relationships'])->toBe(true);
    });

    it('generates collection resource configuration', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $collectionResource = $jsonData['resources']['collection_resource'];
        expect($collectionResource['name'])->toBe('UserCollection');
        expect($collectionResource['pagination'])->toHaveKey('enabled');
        expect($collectionResource['pagination']['enabled'])->toBe(true);
        expect($collectionResource['pagination'])->toHaveKey('per_page');
        expect($collectionResource['pagination'])->toHaveKey('meta_fields');

        expect($collectionResource['filtering'])->toHaveKey('enabled');
        expect($collectionResource['filtering']['enabled'])->toBe(true);
        expect($collectionResource['filtering'])->toHaveKey('filterable_fields');

        expect($collectionResource['sorting'])->toHaveKey('enabled');
        expect($collectionResource['sorting']['enabled'])->toBe(true);
        expect($collectionResource['sorting'])->toHaveKey('sortable_fields');
    });

    it('handles different field types properly', function () {
        $schemaWithDifferentTypes = ModelSchema::fromArray('Product', [
            'table' => 'products',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
                'published' => ['type' => 'boolean', 'default' => false],
                'tags' => ['type' => 'json'],
                'published_at' => ['type' => 'datetime', 'nullable' => true],
                'description' => ['type' => 'text'],
            ],
        ]);

        $result = $this->generator->generate($schemaWithDifferentTypes);
        $jsonData = json_decode($result['json'], true);

        $fields = $jsonData['resources']['main_resource']['fields'];

        expect($fields['price']['type'])->toBe('float');
        expect($fields['price']['format'])->toBe('decimal');

        expect($fields['published']['type'])->toBe('boolean');

        expect($fields['tags']['type'])->toBe('array');
        expect($fields['tags']['format'])->toBe('json');

        expect($fields['published_at']['type'])->toBe('string');
        expect($fields['published_at']['format'])->toBe('datetime');
    });

    it('can generate with custom options', function () {
        $options = [
            'namespace' => 'App\Http\Resources\V2',
            'enable_filtering' => false,
            'enable_sorting' => false,
            'pagination_per_page' => 50,
            'include_timestamps' => false,
        ];

        $result = $this->generator->generate($this->schema, $options);
        $jsonData = json_decode($result['json'], true);

        $mainResource = $jsonData['resources']['main_resource'];
        expect($mainResource['namespace'])->toBe('App\Http\Resources\V2');

        $collectionResource = $jsonData['resources']['collection_resource'];
        expect($collectionResource['filtering']['enabled'])->toBe(false);
        expect($collectionResource['sorting']['enabled'])->toBe(false);
        expect($collectionResource['pagination']['per_page'])->toBe(50);

        // Should not include timestamps when disabled
        $fields = $mainResource['fields'];
        expect($fields)->not->toHaveKey('created_at');
        expect($fields)->not->toHaveKey('updated_at');
    });

    it('generates proper YAML format', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKey('yaml');

        $yamlContent = $result['yaml'];
        expect($yamlContent)->toContain('resources:');
        expect($yamlContent)->toContain('main_resource:');
        expect($yamlContent)->toContain('UserResource');
        expect($yamlContent)->toContain('relationships:');
        expect($yamlContent)->toContain('posts:');
        expect($yamlContent)->toContain('partial_resources:');
        expect($yamlContent)->toContain('basic:');
    });

    it('generates relationship resources configuration', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $relationshipResources = $jsonData['resources']['relationship_resources'];

        expect($relationshipResources)->toHaveKey('posts');
        $postsResource = $relationshipResources['posts'];
        expect($postsResource['nested_loading'])->toBe(true);
        expect($postsResource['eager_load'])->toBe(false);
        expect($postsResource['with_count'])->toBe(true);

        expect($relationshipResources)->toHaveKey('profile');
        $profileResource = $relationshipResources['profile'];
        expect($profileResource['nested_loading'])->toBe(true);
        expect($profileResource['eager_load'])->toBe(true);

        expect($relationshipResources)->toHaveKey('roles');
        $rolesResource = $relationshipResources['roles'];
        expect($rolesResource['with_pivot'])->toBe(true);
        expect($rolesResource['pivot_fields'])->toBeArray();
    });
});
