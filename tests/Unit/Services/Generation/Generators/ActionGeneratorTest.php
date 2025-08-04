<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ActionGenerator;
use Tests\TestCase;

/**
 * @covers \Grazulex\LaravelModelschema\Services\Generation\Generators\ActionGenerator
 */
class ActionGeneratorTest extends TestCase
{
    private ActionGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ActionGenerator();
    }

    public function test_get_generator_name(): void
    {
        $this->assertEquals('action', $this->generator->getGeneratorName());
    }

    public function test_get_available_formats(): void
    {
        $formats = $this->generator->getAvailableFormats();
        $this->assertContains('json', $formats);
        $this->assertContains('yaml', $formats);
    }

    public function test_generate_json_format(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->generator->generate($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('actions', $jsonData);

        // Check CRUD actions are generated
        $this->assertArrayHasKey('CreateUserAction', $jsonData['actions']);
        $this->assertArrayHasKey('UpdateUserAction', $jsonData['actions']);
        $this->assertArrayHasKey('DeleteUserAction', $jsonData['actions']);
    }

    public function test_generate_with_custom_namespace(): void
    {
        $schema = $this->createTestSchema();
        $options = ['action_namespace' => 'App\\Custom\\Actions'];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $createAction = $jsonData['actions']['CreateUserAction'];
        $this->assertEquals('App\\Custom\\Actions', $createAction['namespace']);
    }

    public function test_generate_crud_actions(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_crud_actions' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $actions = $jsonData['actions'];

        $this->assertArrayHasKey('CreateUserAction', $actions);
        $this->assertArrayHasKey('UpdateUserAction', $actions);
        $this->assertArrayHasKey('DeleteUserAction', $actions);

        // Check action details
        $createAction = $actions['CreateUserAction'];
        $this->assertEquals('create', $createAction['type']);
        $this->assertEquals('CreateUserAction', $createAction['class_name']);
        $this->assertEquals('App\\Models\\User', $createAction['model_class']);
        $this->assertContains('ShouldQueue', $createAction['implements']);
    }

    public function test_generate_business_actions(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_business_actions' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $actions = $jsonData['actions'];

        $this->assertArrayHasKey('UserBulkUpdateAction', $actions);
        $this->assertArrayHasKey('UserExportAction', $actions);
        $this->assertArrayHasKey('UserImportAction', $actions);

        // Check business action details
        $bulkAction = $actions['UserBulkUpdateAction'];
        $this->assertEquals('bulk_update', $bulkAction['type']);

        $exportAction = $actions['UserExportAction'];
        $this->assertEquals('export', $exportAction['type']);
    }

    public function test_generate_with_status_field(): void
    {
        $schema = $this->createTestSchemaWithStatus();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $actions = $jsonData['actions'];

        $this->assertArrayHasKey('ActivateUserAction', $actions);
        $this->assertArrayHasKey('DeactivateUserAction', $actions);
    }

    public function test_generate_with_custom_actions(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'custom_actions' => [
                [
                    'name' => 'UserCustomAction',
                    'description' => 'Custom action for special processing',
                    'implements' => ['ShouldQueue'],
                ],
            ],
        ];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $actions = $jsonData['actions'];

        $this->assertArrayHasKey('UserCustomAction', $actions);
        $customAction = $actions['UserCustomAction'];
        $this->assertEquals('custom', $customAction['type']);
        $this->assertEquals('Custom action for special processing', $customAction['description']);
    }

    public function test_action_method_generation(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $createAction = $jsonData['actions']['CreateUserAction'];
        $methods = $createAction['methods'];

        // Check execute method
        $this->assertArrayHasKey('execute', $methods);
        $executeMethod = $methods['execute'];
        $this->assertEquals('Execute the create User action.', $executeMethod['description']);
        $this->assertEquals(['array $data'], $executeMethod['parameters']);

        // Check handle method for queued actions
        $this->assertArrayHasKey('handle', $methods);
    }

    public function test_action_properties(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $createAction = $jsonData['actions']['CreateUserAction'];
        $properties = $createAction['properties'];

        // Check queue properties
        $this->assertArrayHasKey('queue', $properties);
        $this->assertArrayHasKey('timeout', $properties);

        $this->assertEquals('string', $properties['queue']['type']);
        $this->assertEquals('int', $properties['timeout']['type']);
    }

    public function test_generate_without_crud_actions(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_crud_actions' => false];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $actions = $jsonData['actions'];

        $this->assertArrayNotHasKey('CreateUserAction', $actions);
        $this->assertArrayNotHasKey('UpdateUserAction', $actions);
        $this->assertArrayNotHasKey('DeleteUserAction', $actions);
    }

    private function createTestSchema(): ModelSchema
    {
        $fields = [
            new Field('id', 'bigInteger'),
            new Field('name', 'string'),
            new Field('email', 'string'),
            new Field('created_at', 'timestamp'),
            new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }

    private function createTestSchemaWithStatus(): ModelSchema
    {
        $fields = [
            'id' => new Field('id', 'bigInteger'),
            'name' => new Field('name', 'string'),
            'email' => new Field('email', 'string'),
            'status' => new Field('status', 'string'),
            'created_at' => new Field('created_at', 'timestamp'),
            'updated_at' => new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }
}
