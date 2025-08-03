<?php

declare(strict_types=1);

namespace Tests\Unit\Examples;

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use ReflectionClass;
use Tests\TestCase;

class JsonSchemaFieldTypePluginTest extends TestCase
{
    protected JsonSchemaFieldTypePlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = new JsonSchemaFieldTypePlugin();
    }

    /** @test */
    public function it_has_correct_basic_properties(): void
    {
        $this->assertEquals('json_schema', $this->plugin->getType());
        $this->assertEquals(['structured_json', 'validated_json', 'schema_json'], $this->plugin->getAliases());
        $this->assertEquals('array', $this->plugin->getCastType([]));
        $this->assertEquals(['json'], $this->plugin->getDependencies());
    }

    /** @test */
    public function it_validates_schema_requirement(): void
    {
        // Missing schema
        $config = [];
        $errors = $this->plugin->validate($config);
        $this->assertStringContainsString('Missing required attributes: schema', $errors[0]);

        // Invalid schema type
        $config = ['schema' => 'not-an-array'];
        $errors = $this->plugin->validate($config);
        $this->assertStringContainsString("Custom attribute 'schema' must be of type array", $errors[0]);

        // Valid schema
        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ];
        $errors = $this->plugin->validate($config);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_validates_schema_structure(): void
    {
        // Schema without type
        $config = [
            'schema' => [
                'properties' => [],
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertContains('schema must have a type property', $errors);

        // Invalid schema type
        $config = [
            'schema' => [
                'type' => 'invalid-type',
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertStringContainsString('schema type must be one of:', $errors[0]);
    }

    /** @test */
    public function it_validates_object_schema_properties(): void
    {
        // Invalid properties type
        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => 'not-an-array',
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertContains('schema properties must be an array', $errors);

        // Invalid required type
        $config = [
            'schema' => [
                'type' => 'object',
                'required' => 'not-an-array',
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertContains('schema required must be an array', $errors);
    }

    /** @test */
    public function it_validates_array_schema_items(): void
    {
        $config = [
            'schema' => [
                'type' => 'array',
                'items' => 'not-an-array',
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertContains('schema items must be an array for array type', $errors);
    }

    /** @test */
    public function it_validates_default_value_against_schema(): void
    {
        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name'],
            ],
            'default' => [
                'name' => 'John',
                'age' => 25,
            ],
        ];

        $errors = $this->plugin->validate($config);
        $this->assertEmpty($errors);

        // Invalid default value
        $config['default'] = [
            'age' => 'not-a-number', // Should be integer
        ];

        $errors = $this->plugin->validate($config);
        $this->assertStringContainsString('default value does not match schema', $errors[0]);
    }

    /** @test */
    public function it_transforms_configuration_correctly(): void
    {
        $config = [
            'schema' => [
                'type' => 'string',
            ],
        ];

        $transformed = $this->plugin->transformConfig($config);

        $this->assertTrue($transformed['strict_validation']);

        // Test JSON string schema conversion
        $config = [
            'schema' => '{"type": "string"}',
        ];

        $transformed = $this->plugin->transformConfig($config);
        $this->assertIsArray($transformed['schema']);
        $this->assertEquals('string', $transformed['schema']['type']);
    }

    /** @test */
    public function it_generates_validation_rules_correctly(): void
    {
        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            'nullable' => true,
        ];

        $rules = $this->plugin->getValidationRules($config);

        $this->assertContains('json', $rules);
        $this->assertContains('nullable', $rules);

        // Should contain custom validation closure
        $hasCustomValidation = false;
        foreach ($rules as $rule) {
            if (is_callable($rule)) {
                $hasCustomValidation = true;
                break;
            }
        }
        $this->assertTrue($hasCustomValidation);
    }

    /** @test */
    public function it_generates_migration_parameters_correctly(): void
    {
        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
            'nullable' => true,
            'default' => ['name' => 'Default'],
        ];

        $params = $this->plugin->getMigrationParameters($config);

        $this->assertTrue($params['nullable']);
        $this->assertStringContainsString('name', $params['default']);
        $this->assertStringContainsString('JSON Schema:', $params['comment']);
    }

    /** @test */
    public function it_generates_migration_call_correctly(): void
    {
        $config = [
            'schema' => [
                'type' => 'string',
            ],
            'nullable' => true,
            'default' => 'test',
        ];

        $call = $this->plugin->getMigrationCall('data', $config);

        $this->assertStringContainsString("\$table->json('data')", $call);
        $this->assertStringContainsString('->nullable()', $call);
        $this->assertStringContainsString("->default('test')", $call);
        $this->assertStringContainsString('->comment(', $call);
    }

    /** @test */
    public function it_validates_values_against_string_schema(): void
    {
        $schema = ['type' => 'string'];

        $errors = $this->plugin->validate(['schema' => $schema]);
        $this->assertEmpty($errors);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validateValueAgainstSchema');
        $method->setAccessible(true);

        $errors = $method->invoke($this->plugin, 'valid string', $schema);
        $this->assertEmpty($errors);

        $errors = $method->invoke($this->plugin, 123, $schema);
        $this->assertContains('Value must be a string', $errors);
    }

    /** @test */
    public function it_validates_values_against_object_schema(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validateValueAgainstSchema');
        $method->setAccessible(true);

        // Valid object
        $errors = $method->invoke($this->plugin, ['name' => 'John', 'age' => 25], $schema);
        $this->assertEmpty($errors);

        // Missing required property
        $errors = $method->invoke($this->plugin, ['age' => 25], $schema);
        $this->assertStringContainsString("Required property 'name' is missing", $errors[0]);

        // Invalid property type
        $errors = $method->invoke($this->plugin, ['name' => 123], $schema);
        $this->assertStringContainsString("Property 'name': Value must be a string", $errors[0]);
    }

    /** @test */
    public function it_validates_values_against_array_schema(): void
    {
        $schema = [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ];

        $reflection = new ReflectionClass($this->plugin);
        $method = $reflection->getMethod('validateValueAgainstSchema');
        $method->setAccessible(true);

        // Valid array
        $errors = $method->invoke($this->plugin, ['a', 'b', 'c'], $schema);
        $this->assertEmpty($errors);

        // Invalid item type
        $errors = $method->invoke($this->plugin, ['a', 123, 'c'], $schema);
        $this->assertStringContainsString('Item [1]: Value must be a string', $errors[0]);
    }

    /** @test */
    public function it_supports_correct_databases(): void
    {
        $databases = $this->plugin->getSupportedDatabases();

        $this->assertContains('mysql', $databases);
        $this->assertContains('postgresql', $databases);
        $this->assertNotContains('sqlite', $databases); // JSON Schema might not be fully supported in SQLite
    }

    /** @test */
    public function it_supports_correct_attributes(): void
    {
        $this->assertTrue($this->plugin->supportsAttribute('nullable'));
        $this->assertTrue($this->plugin->supportsAttribute('default'));
        $this->assertTrue($this->plugin->supportsAttribute('schema'));
        $this->assertTrue($this->plugin->supportsAttribute('strict_validation'));
    }

    /** @test */
    public function it_has_example_schemas(): void
    {
        $examples = $this->plugin->getExampleSchemas();

        $this->assertIsArray($examples);
        $this->assertArrayHasKey('user_profile', $examples);
        $this->assertArrayHasKey('product_attributes', $examples);

        // Validate user_profile schema structure
        $userProfile = $examples['user_profile'];
        $this->assertEquals('object', $userProfile['type']);
        $this->assertArrayHasKey('properties', $userProfile);
        $this->assertArrayHasKey('required', $userProfile);
        $this->assertContains('name', $userProfile['required']);
        $this->assertContains('email', $userProfile['required']);
    }

    /** @test */
    public function it_has_valid_config_schema(): void
    {
        $schema = $this->plugin->getConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('enabled', $schema['properties']);
        $this->assertArrayHasKey('version', $schema['properties']);
    }
}
