<?php

declare(strict_types=1);

namespace Tests\Feature;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use Tests\TestCase;

class RelationshipValidationIntegrationTest extends TestCase
{
    private EnhancedValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new EnhancedValidationService();
    }

    public function test_comprehensive_relationship_validation_with_real_world_scenario(): void
    {
        // Create a realistic blog scenario with multiple models and relationships
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'email'],
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'Post',
                ],
                'profile' => [
                    'type' => 'hasOne',
                    'model' => 'Profile',
                ],
            ],
        ]);

        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'longText'],
                'user_id' => ['type' => 'foreignId'],
            ],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User',
                    'foreignKey' => 'user_id',
                ],
                'comments' => [
                    'type' => 'hasMany',
                    'model' => 'Comment',
                ],
                'tags' => [
                    'type' => 'belongsToMany',
                    'model' => 'Tag',
                    'pivot' => 'post_tag',
                ],
                // This should trigger a validation error
                'nonexistent' => [
                    'type' => 'belongsTo',
                    'model' => 'NonExistentModel',
                ],
            ],
        ]);

        $commentSchema = ModelSchema::fromArray('Comment', [
            'table' => 'comments',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'content' => ['type' => 'text'],
                'commentable_id' => ['type' => 'unsignedBigInteger'],
                'commentable_type' => ['type' => 'string'],
            ],
            'relationships' => [
                'commentable' => [
                    'type' => 'morphTo',
                    // morphTo relationships don't specify a model - should be valid
                ],
            ],
        ]);

        $profileSchema = ModelSchema::fromArray('Profile', [
            'table' => 'profiles',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'bio' => ['type' => 'text'],
                'user_id' => ['type' => 'foreignId'],
            ],
            'relationships' => [
                'user' => [
                    'type' => 'belongsTo',
                    'model' => 'User',
                ],
            ],
        ]);

        $tagSchema = ModelSchema::fromArray('Tag', [
            'table' => 'tags',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'belongsToMany',
                    'model' => 'Post',
                    'pivot' => 'post_tag',
                ],
            ],
        ]);

        $schemas = [$userSchema, $postSchema, $commentSchema, $profileSchema, $tagSchema];

        // Test the comprehensive validation
        $result = $this->validationService->validateRelationshipConsistency($schemas);

        // Should not be consistent due to the NonExistentModel reference
        $this->assertFalse($result['is_consistent']);

        // Check that the target model validation detected the error
        $this->assertFalse($result['target_model_validation']['is_valid']);
        $this->assertNotEmpty($result['target_model_validation']['errors']);

        // Verify the specific error about NonExistentModel
        $errorFound = false;
        foreach ($result['target_model_validation']['errors'] as $error) {
            if (str_contains($error, 'NonExistentModel')) {
                $errorFound = true;
                break;
            }
        }
        $this->assertTrue($errorFound, 'Should detect NonExistentModel reference');

        // Verify that valid relationships are not flagged as errors
        $this->assertContains('User', $result['target_model_validation']['available_models']);
        $this->assertContains('Post', $result['target_model_validation']['available_models']);
        $this->assertContains('Comment', $result['target_model_validation']['available_models']);

        // Check validation summary
        $this->assertEquals(5, $result['validation_summary']['total_schemas']);
        $this->assertGreaterThan(0, $result['validation_summary']['issues_found']);
    }

    public function test_validates_complex_relationship_chains(): void
    {
        // Test a complex scenario with organization -> departments -> users -> posts
        $organizationSchema = ModelSchema::fromArray('Organization', [
            'table' => 'organizations',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'departments' => [
                    'type' => 'hasMany',
                    'model' => 'Department',
                ],
            ],
        ]);

        $departmentSchema = ModelSchema::fromArray('Department', [
            'table' => 'departments',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'organization' => [
                    'type' => 'belongsTo',
                    'model' => 'Organization',
                ],
                'users' => [
                    'type' => 'hasMany',
                    'model' => 'User',
                ],
            ],
        ]);

        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['id' => ['type' => 'bigInteger']],
            'relationships' => [
                'department' => [
                    'type' => 'belongsTo',
                    'model' => 'Department',
                ],
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
            ],
        ]);

        $schemas = [$organizationSchema, $departmentSchema, $userSchema, $postSchema];
        $result = $this->validationService->validateRelationshipConsistency($schemas);

        // This should be valid - all models exist and relationships are properly defined
        $this->assertTrue($result['is_consistent']);
        $this->assertTrue($result['target_model_validation']['is_valid']);
        $this->assertEmpty($result['target_model_validation']['errors']);
    }

    public function test_handles_self_referencing_relationships(): void
    {
        $categorySchema = ModelSchema::fromArray('Category', [
            'table' => 'categories',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'parent_id' => ['type' => 'foreignId', 'nullable' => true],
            ],
            'relationships' => [
                'parent' => [
                    'type' => 'belongsTo',
                    'model' => 'Category',
                    'foreignKey' => 'parent_id',
                ],
                'children' => [
                    'type' => 'hasMany',
                    'model' => 'Category',
                    'foreignKey' => 'parent_id',
                ],
            ],
        ]);

        $schemas = [$categorySchema];
        $result = $this->validationService->validateRelationshipConsistency($schemas);

        // Self-referencing relationships should be valid
        $this->assertTrue($result['is_consistent']);
        $this->assertTrue($result['target_model_validation']['is_valid']);
        $this->assertEmpty($result['target_model_validation']['errors']);
    }
}
