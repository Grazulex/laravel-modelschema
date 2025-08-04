<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\RuleGenerator;
use Tests\TestCase;

/**
 * @covers \Grazulex\LaravelModelschema\Services\Generation\Generators\RuleGenerator
 */
class RuleGeneratorTest extends TestCase
{
    private RuleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RuleGenerator();
    }

    public function test_get_generator_name(): void
    {
        $this->assertEquals('rule', $this->generator->getGeneratorName());
    }

    public function test_get_available_formats(): void
    {
        $formats = $this->generator->getAvailableFormats();
        $this->assertContains('json', $formats);
        $this->assertContains('yaml', $formats);
    }

    public function test_generate_json_format(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $result = $this->generator->generate($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('rules', $jsonData);

        // Check that unique rules are generated
        $this->assertArrayHasKey('UniqueUserEmailRule', $jsonData['rules']);
        $this->assertArrayHasKey('UniqueUserUsernameRule', $jsonData['rules']);
    }

    public function test_generate_unique_rules(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $options = ['include_unique_rules' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        $this->assertArrayHasKey('UniqueUserEmailRule', $rules);
        $emailRule = $rules['UniqueUserEmailRule'];

        $this->assertEquals('unique', $emailRule['type']);
        $this->assertEquals('email', $emailRule['field']);
        $this->assertEquals('UniqueUserEmailRule', $emailRule['class_name']);
        $this->assertEquals('App\\Rules', $emailRule['namespace']);
        $this->assertContains('Rule', $emailRule['implements']);
    }

    public function test_generate_foreign_key_rules(): void
    {
        $schema = $this->createTestSchemaWithForeignKeys();
        $options = ['include_foreign_key_rules' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        $this->assertArrayHasKey('CategoryExistsRule', $rules);
        $categoryRule = $rules['CategoryExistsRule'];

        $this->assertEquals('exists', $categoryRule['type']);
        $this->assertEquals('category_id', $categoryRule['field']);
        $this->assertEquals('categorys', $categoryRule['related_table']);
    }

    public function test_generate_business_rules(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_business_rules' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        $this->assertArrayHasKey('UserStatusRule', $rules);
        $this->assertArrayHasKey('UserPermissionRule', $rules);

        $statusRule = $rules['UserStatusRule'];
        $this->assertEquals('status', $statusRule['type']);

        $permissionRule = $rules['UserPermissionRule'];
        $this->assertEquals('permission', $permissionRule['type']);
    }

    public function test_generate_complex_rules(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_complex_rules' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        $this->assertArrayHasKey('UserComplexValidationRule', $rules);
        $complexRule = $rules['UserComplexValidationRule'];
        $this->assertEquals('complex', $complexRule['type']);
    }

    public function test_generate_custom_rules(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'custom_rules' => [
                [
                    'name' => 'UserAgeRule',
                    'description' => 'Validate user age requirements',
                    'implements' => ['Rule'],
                    'logic' => 'Custom age validation logic',
                ],
            ],
        ];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        $this->assertArrayHasKey('UserAgeRule', $rules);
        $ageRule = $rules['UserAgeRule'];
        $this->assertEquals('custom', $ageRule['type']);
        $this->assertEquals('Validate user age requirements', $ageRule['description']);
    }

    public function test_generate_with_custom_namespace(): void
    {
        $schema = $this->createTestSchema();
        $options = ['rule_namespace' => 'App\\Custom\\Rules'];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        foreach ($rules as $rule) {
            $this->assertEquals('App\\Custom\\Rules', $rule['namespace']);
        }
    }

    public function test_rule_methods(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $emailRule = $jsonData['rules']['UniqueUserEmailRule'];
        $methods = $emailRule['methods'];

        // Check required Rule interface methods
        $this->assertArrayHasKey('passes', $methods);
        $this->assertArrayHasKey('message', $methods);

        $passesMethod = $methods['passes'];
        $this->assertEquals('bool', $passesMethod['return_type']);
        $this->assertEquals(['string $attribute', '$value'], $passesMethod['parameters']);

        $messageMethod = $methods['message'];
        $this->assertEquals('string', $messageMethod['return_type']);
        $this->assertEquals([], $messageMethod['parameters']);

        // Check unique rule specific methods
        $this->assertArrayHasKey('ignore', $methods);
    }

    public function test_rule_properties(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $emailRule = $jsonData['rules']['UniqueUserEmailRule'];
        $properties = $emailRule['properties'];

        // Check unique rule properties
        $this->assertArrayHasKey('ignoreId', $properties);
        $ignoreIdProperty = $properties['ignoreId'];
        $this->assertEquals('?int', $ignoreIdProperty['type']);
        $this->assertEquals('protected', $ignoreIdProperty['visibility']);
    }

    public function test_rule_imports(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $emailRule = $jsonData['rules']['UniqueUserEmailRule'];
        $imports = $emailRule['imports'];

        $this->assertContains('Illuminate\\Contracts\\Validation\\Rule', $imports);
        $this->assertContains('App\\Models\\User', $imports);
    }

    public function test_disable_rule_types(): void
    {
        $schema = $this->createTestSchemaWithUniqueFields();
        $options = [
            'include_unique_rules' => false,
            'include_foreign_key_rules' => false,
            'include_business_rules' => false,
            'include_complex_rules' => false,
        ];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $rules = $jsonData['rules'];

        // Should be empty when all rule types are disabled
        $this->assertEmpty($rules);
    }

    private function createTestSchema(): ModelSchema
    {
        $fields = [
            'id' => new Field('id', 'bigInteger'),
            'name' => new Field('name', 'string'),
            'created_at' => new Field('created_at', 'timestamp'),
            'updated_at' => new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }

    private function createTestSchemaWithUniqueFields(): ModelSchema
    {
        $fields = [
            'id' => new Field('id', 'bigInteger'),
            'name' => new Field('name', 'string'),
            'email' => new Field('email', 'string'),
            'username' => new Field('username', 'string'),
            'created_at' => new Field('created_at', 'timestamp'),
            'updated_at' => new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }

    private function createTestSchemaWithForeignKeys(): ModelSchema
    {
        $fields = [
            'id' => new Field('id', 'bigInteger'),
            'name' => new Field('name', 'string'),
            'category_id' => new Field('category_id', 'unsignedBigInteger'),
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
