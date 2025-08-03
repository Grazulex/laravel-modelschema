<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use Tests\TestCase;

class EnhancedValidationServiceRelationshipTest extends TestCase
{
    private EnhancedValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EnhancedValidationService();
    }

    public function test_validates_target_model_existence(): void
    {
        // Create schemas with relationships
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'Post',
                ],
            ],
        ]);

        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User',
                ],
                'invalid_relation' => [
                    'type' => 'belongsTo',
                    'model' => 'NonExistentModel',
                ],
            ],
        ]);

        $schemas = [$userSchema, $postSchema];
        $result = $this->service->validateRelationshipConsistency($schemas);

        $this->assertFalse($result['is_consistent']);
        $this->assertNotEmpty($result['target_model_validation']['errors']);
        $this->assertStringContainsString('NonExistentModel', $result['target_model_validation']['errors'][0]);
    }

    public function test_validates_relationship_types(): void
    {
        $schema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User',
                ],
                'invalid_type' => [
                    'type' => 'invalidType',
                    'model' => 'User',
                ],
            ],
        ]);

        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
        ]);

        $schemas = [$schema, $userSchema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('Invalid relationship type', $result['errors'][0]);
    }

    public function test_handles_morph_relationships_correctly(): void
    {
        $schema = ModelSchema::fromArray('Comment', [
            'table' => 'comments',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'commentable' => [
                    'type' => 'morphTo',
                    // morphTo relationships don't specify a model
                ],
            ],
        ]);

        $schemas = [$schema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validates_missing_target_model(): void
    {
        $schema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    // Missing model property
                ],
            ],
        ]);

        $schemas = [$schema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertFalse($result['is_valid']);
        $this->assertStringContainsString('missing target model', $result['errors'][0]);
    }

    public function test_normalizes_model_names_correctly(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
        ]);

        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'user_namespace' => [
                    'type' => 'belongsTo',
                    'model' => 'App\\Models\\User',
                ],
                'user_full_namespace' => [
                    'type' => 'belongsTo',
                    'model' => '\\App\\Models\\User',
                ],
            ],
        ]);

        $schemas = [$userSchema, $postSchema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validates_belongs_to_many_relationships(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
        ]);

        $roleSchema = ModelSchema::fromArray('Role', [
            'table' => 'roles',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'users' => [
                    'type' => 'belongsToMany',
                    'model' => 'User',
                    'pivot' => 'role_user',
                ],
            ],
        ]);

        $schemas = [$userSchema, $roleSchema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_includes_common_laravel_models(): void
    {
        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User', // Common Laravel model
                ],
            ],
        ]);

        $schemas = [$postSchema];
        $result = $this->service->validateTargetModels($schemas);

        $this->assertTrue($result['is_valid']);
        $this->assertContains('User', $result['available_models']);
        $this->assertContains('App\\Models\\User', $result['available_models']);
    }
}
