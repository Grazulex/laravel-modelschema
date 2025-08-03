<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RequestGenerator;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Test the enhanced RequestGenerator functionality
 */
class RequestGeneratorEnhancedTest extends TestCase
{
    private RequestGenerator $generator;

    private ModelSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new RequestGenerator();

        // Create a test schema with various field types and relationships
        $fields = [
            'name' => new Field(
                name: 'name',
                type: 'string',
                nullable: false,
                validation: ['required' => true, 'max' => 255]
            ),
            'email' => new Field(
                name: 'email',
                type: 'email',
                nullable: false,
                unique: true,
                validation: ['required' => true, 'unique' => true]
            ),
            'age' => new Field(
                name: 'age',
                type: 'integer',
                nullable: true,
                validation: ['min' => 18, 'max' => 120]
            ),
            'status' => new Field(
                name: 'status',
                type: 'enum',
                nullable: true,
                attributes: ['options' => ['active', 'inactive', 'pending']]
            ),
        ];

        $relationships = [
            'roles' => new Relationship('roles', 'belongsToMany', 'Role'),
        ];

        $this->schema = new ModelSchema('User', 'users', $fields, $relationships);
    }

    public function test_enhanced_json_generation_includes_all_features(): void
    {
        $options = [
            'enhanced' => true,
            'enable_authorization' => true,
            'enable_custom_messages' => true,
            'requests_namespace' => 'App\\Http\\Requests\\User',
        ];

        $result = $this->generator->generate($this->schema, $options);
        $data = json_decode($result['json'], true);

        $this->assertArrayHasKey('requests', $data);
        $requests = $data['requests'];

        // Check structure
        $this->assertArrayHasKey('store', $requests);
        $this->assertArrayHasKey('update', $requests);

        // Check store request
        $storeRequest = $requests['store'];
        $this->assertEquals('StoreUserRequest', $storeRequest['name']);
        $this->assertEquals('App\\Http\\Requests\\User', $storeRequest['namespace']);
        $this->assertArrayHasKey('validation_rules', $storeRequest);
        $this->assertArrayHasKey('messages', $storeRequest);
        $this->assertArrayHasKey('authorization', $storeRequest);
        $this->assertArrayHasKey('custom_methods', $storeRequest);
        $this->assertArrayHasKey('relationships_validation', $storeRequest);
        $this->assertArrayHasKey('conditional_rules', $storeRequest);
    }

    public function test_enhanced_features_are_populated(): void
    {
        $options = ['enhanced' => true, 'enable_authorization' => true];

        $result = $this->generator->generate($this->schema, $options);
        $data = json_decode($result['json'], true);

        $storeRequest = $data['requests']['store'];

        // Check authorization
        $this->assertArrayHasKey('enabled', $storeRequest['authorization']);
        $this->assertTrue($storeRequest['authorization']['enabled']);
        $this->assertArrayHasKey('logic', $storeRequest['authorization']);

        // Check custom methods
        $this->assertIsArray($storeRequest['custom_methods']);

        // Check relationship validation - check for the generated key
        $relationshipRules = $storeRequest['relationships_validation'];
        $this->assertIsArray($relationshipRules);
        // For belongsToMany relationship, should have roles.* or similar
        $hasRoleValidation = false;
        foreach (array_keys($relationshipRules) as $key) {
            if (str_contains($key, 'roles')) {
                $hasRoleValidation = true;
                break;
            }
        }
        $this->assertTrue($hasRoleValidation, 'Should have role validation rules');

        // Check conditional rules
        $this->assertArrayHasKey('status', $storeRequest['conditional_rules']);
    }

    public function test_standard_mode_uses_traditional_structure(): void
    {
        $options = ['enhanced' => false];

        $result = $this->generator->generate($this->schema, $options);
        $data = json_decode($result['json'], true);

        $requests = $data['requests'];

        // Should use traditional structure
        $this->assertArrayHasKey('store_request', $requests);
        $this->assertArrayHasKey('update_request', $requests);
        $this->assertArrayNotHasKey('store', $requests);
        $this->assertArrayNotHasKey('update', $requests);

        // Should NOT have enhanced features
        $storeRequest = $requests['store_request'];
        $this->assertArrayNotHasKey('authorization', $storeRequest);
        $this->assertArrayNotHasKey('custom_methods', $storeRequest);
    }

    public function test_disabled_authorization_returns_simple_true(): void
    {
        $options = [
            'enhanced' => true,
            'enable_authorization' => false,
        ];

        $result = $this->generator->generate($this->schema, $options);
        $data = json_decode($result['json'], true);

        $authorization = $data['requests']['store']['authorization'];

        $this->assertFalse($authorization['enabled']);
        $this->assertEquals(['return true;'], $authorization['logic']);
    }

    public function test_yaml_generation_works(): void
    {
        $options = ['enhanced' => true];

        $result = $this->generator->generate($this->schema, $options);
        $data = Yaml::parse($result['yaml']);

        $this->assertArrayHasKey('requests', $data);
        $this->assertArrayHasKey('store', $data['requests']);
        $this->assertArrayHasKey('update', $data['requests']);
    }
}
