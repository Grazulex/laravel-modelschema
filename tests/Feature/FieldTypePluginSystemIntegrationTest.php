<?php

declare(strict_types=1);

namespace Tests\Feature;

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Tests\TestCase;

class FieldTypePluginSystemIntegrationTest extends TestCase
{
    protected FieldTypePluginManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear registry for clean tests
        FieldTypeRegistry::clear();
        FieldTypeRegistry::initialize();

        $this->manager = new FieldTypePluginManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up
        FieldTypeRegistry::clear();
    }

    /** @test */
    public function it_can_register_and_use_custom_plugins_in_complete_workflow(): void
    {
        // Register custom plugins
        $urlPlugin = new UrlFieldTypePlugin();
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();

        $this->manager->registerPlugin($urlPlugin);
        $this->manager->registerPlugin($jsonSchemaPlugin);

        // Verify plugins are registered
        $this->assertTrue($this->manager->hasPlugin('url'));
        $this->assertTrue($this->manager->hasPlugin('json_schema'));

        // Verify plugins are available in FieldTypeRegistry
        $this->assertTrue(FieldTypeRegistry::has('url'));
        $this->assertTrue(FieldTypeRegistry::has('json_schema'));
        $this->assertTrue(FieldTypeRegistry::has('website')); // alias
        $this->assertTrue(FieldTypeRegistry::has('structured_json')); // alias

        // Test getting field type instances
        $urlFieldType = FieldTypeRegistry::get('url');
        $jsonSchemaFieldType = FieldTypeRegistry::get('json_schema');

        $this->assertInstanceOf(UrlFieldTypePlugin::class, $urlFieldType);
        $this->assertInstanceOf(JsonSchemaFieldTypePlugin::class, $jsonSchemaFieldType);

        // Test field type functionality
        $this->assertEquals('url', $urlFieldType->getType());
        $this->assertEquals('json_schema', $jsonSchemaFieldType->getType());
    }

    /** @test */
    public function it_can_validate_plugin_configurations(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $this->manager->registerPlugin($urlPlugin);

        $urlFieldType = FieldTypeRegistry::get('url');

        // Test valid configuration
        $validConfig = [
            'max_length' => 500,
            'schemes' => ['https'],
            'default' => 'https://example.com',
        ];

        $errors = $urlFieldType->validate($validConfig);
        $this->assertEmpty($errors);

        // Test invalid configuration
        $invalidConfig = [
            'max_length' => -1,
            'default' => 'not-a-url',
        ];

        $errors = $urlFieldType->validate($invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('max_length must be a positive integer', $errors);
        $this->assertContains('default value must be a valid URL', $errors);
    }

    /** @test */
    public function it_can_generate_validation_rules_for_plugins(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $this->manager->registerPlugin($urlPlugin);

        $urlFieldType = FieldTypeRegistry::get('url');

        $config = [
            'max_length' => 500,
            'nullable' => true,
        ];

        $rules = $urlFieldType->getValidationRules($config);

        $this->assertContains('url', $rules);
        $this->assertContains('max:500', $rules);
        $this->assertContains('nullable', $rules);
    }

    /** @test */
    public function it_can_generate_migration_parameters_for_plugins(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $this->manager->registerPlugin($urlPlugin);

        $urlFieldType = FieldTypeRegistry::get('url');

        $config = [
            'max_length' => 500,
            'nullable' => true,
            'default' => 'https://example.com',
        ];

        $params = $urlFieldType->getMigrationParameters($config);

        $this->assertEquals(500, $params['length']);
        $this->assertTrue($params['nullable']);
        $this->assertEquals('https://example.com', $params['default']);
    }

    /** @test */
    public function it_can_generate_migration_calls_for_plugins(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $this->manager->registerPlugin($urlPlugin);

        $urlFieldType = FieldTypeRegistry::get('url');

        $config = [
            'max_length' => 500,
            'nullable' => true,
            'default' => 'https://example.com',
        ];

        $call = $urlFieldType->getMigrationCall('website_url', $config);

        $expected = "\$table->string('website_url', 500)->nullable()->default('https://example.com')";
        $this->assertEquals($expected, $call);
    }

    /** @test */
    public function it_can_handle_complex_json_schema_plugin(): void
    {
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();
        $this->manager->registerPlugin($jsonSchemaPlugin);

        $jsonSchemaFieldType = FieldTypeRegistry::get('json_schema');

        $config = [
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                    'preferences' => [
                        'type' => 'object',
                        'properties' => [
                            'theme' => ['type' => 'string'],
                            'notifications' => ['type' => 'boolean'],
                        ],
                    ],
                ],
                'required' => ['name'],
            ],
            'nullable' => true,
        ];

        // Test validation
        $errors = $jsonSchemaFieldType->validate($config);
        $this->assertEmpty($errors);

        // Test migration call generation
        $call = $jsonSchemaFieldType->getMigrationCall('user_data', $config);
        $this->assertStringContainsString("\$table->json('user_data')", $call);
        $this->assertStringContainsString('->nullable()', $call);
        $this->assertStringContainsString('->comment(', $call);

        // Test validation rules
        $rules = $jsonSchemaFieldType->getValidationRules($config);
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
    public function it_can_manage_plugin_metadata_comprehensively(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();

        $this->manager->registerPlugin($urlPlugin);
        $this->manager->registerPlugin($jsonSchemaPlugin);

        // Test individual plugin metadata
        $urlMetadata = $this->manager->getPluginMetadata('url');
        $this->assertEquals('url', $urlMetadata['name']);
        $this->assertEquals('1.0.0', $urlMetadata['version']);
        $this->assertEquals('Laravel ModelSchema Team', $urlMetadata['author']);
        $this->assertContains('website', $urlMetadata['aliases']);

        $jsonMetadata = $this->manager->getPluginMetadata('json_schema');
        $this->assertEquals('json_schema', $jsonMetadata['name']);
        $this->assertEquals('1.1.0', $jsonMetadata['version']);
        $this->assertEquals(['json'], $jsonMetadata['dependencies']);

        // Test all plugin metadata
        $allMetadata = $this->manager->getAllPluginMetadata();
        $this->assertArrayHasKey('url', $allMetadata);
        $this->assertArrayHasKey('json_schema', $allMetadata);
        $this->assertCount(2, $allMetadata);
    }

    /** @test */
    public function it_can_load_plugins_from_configuration(): void
    {
        $config = [
            'plugins' => [
                [
                    'class' => UrlFieldTypePlugin::class,
                    'config' => [
                        'enabled' => true,
                        'default_max_length' => 500,
                    ],
                ],
                [
                    'class' => JsonSchemaFieldTypePlugin::class,
                    'config' => [
                        'enabled' => true,
                        'strict_validation' => true,
                    ],
                ],
            ],
        ];

        $this->manager->loadFromConfig($config);

        // Verify both plugins are loaded
        $this->assertTrue($this->manager->hasPlugin('url'));
        $this->assertTrue($this->manager->hasPlugin('json_schema'));

        // Verify configurations are applied
        $urlPlugin = $this->manager->getPlugin('url');
        $jsonSchemaPlugin = $this->manager->getPlugin('json_schema');

        $this->assertInstanceOf(UrlFieldTypePlugin::class, $urlPlugin);
        $this->assertInstanceOf(JsonSchemaFieldTypePlugin::class, $jsonSchemaPlugin);

        if ($urlPlugin instanceof UrlFieldTypePlugin) {
            $this->assertEquals(500, $urlPlugin->getConfigValue('default_max_length'));
        }

        if ($jsonSchemaPlugin instanceof JsonSchemaFieldTypePlugin) {
            $this->assertTrue($jsonSchemaPlugin->getConfigValue('strict_validation'));
        }
    }

    /** @test */
    public function it_can_enable_and_disable_plugins(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();

        $this->manager->registerPlugin($urlPlugin);
        $this->manager->registerPlugin($jsonSchemaPlugin);

        // Both plugins should be enabled by default
        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertCount(2, $enabledPlugins);
        $this->assertArrayHasKey('url', $enabledPlugins);
        $this->assertArrayHasKey('json_schema', $enabledPlugins);

        // Disable one plugin
        $this->manager->disablePlugin('url');

        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertCount(1, $enabledPlugins);
        $this->assertArrayNotHasKey('url', $enabledPlugins);
        $this->assertArrayHasKey('json_schema', $enabledPlugins);

        // Re-enable plugin
        $this->manager->enablePlugin('url');

        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertCount(2, $enabledPlugins);
        $this->assertArrayHasKey('url', $enabledPlugins);
    }

    /** @test */
    public function it_validates_plugin_dependencies(): void
    {
        // JsonSchemaFieldTypePlugin depends on 'json' which is built-in
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();

        // Should register successfully because 'json' is available
        $this->manager->registerPlugin($jsonSchemaPlugin);
        $this->assertTrue($this->manager->hasPlugin('json_schema'));

        // Create a plugin with non-existent dependency
        $dependentPlugin = new class extends UrlFieldTypePlugin
        {
            protected array $dependencies = ['non_existent_type'];

            public function getType(): string
            {
                return 'dependent_url';
            }
        };

        $this->expectException(\Grazulex\LaravelModelschema\Exceptions\SchemaException::class);
        $this->expectExceptionMessage("requires field type 'non_existent_type'");

        $this->manager->registerPlugin($dependentPlugin);
    }

    /** @test */
    public function it_can_transform_plugin_configurations(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $this->manager->registerPlugin($urlPlugin);

        $urlFieldType = FieldTypeRegistry::get('url');

        // Test configuration transformation
        $config = ['schemes' => 'https']; // String instead of array

        $transformed = $urlFieldType->transformConfig($config);

        // Should set defaults and normalize data
        $this->assertEquals(255, $transformed['max_length']);
        $this->assertEquals(['https'], $transformed['schemes']); // Converted to array
    }

    /** @test */
    public function it_provides_comprehensive_plugin_system_functionality(): void
    {
        // This test demonstrates the complete plugin system workflow

        // 1. Register multiple plugins
        $urlPlugin = new UrlFieldTypePlugin();
        $jsonSchemaPlugin = new JsonSchemaFieldTypePlugin();

        $this->manager->registerPlugin($urlPlugin);
        $this->manager->registerPlugin($jsonSchemaPlugin);

        // 2. Verify they're available in the field type registry
        $allTypes = FieldTypeRegistry::all();
        $this->assertContains('url', $allTypes);
        $this->assertContains('json_schema', $allTypes);
        $this->assertContains('website', $allTypes); // alias
        $this->assertContains('structured_json', $allTypes); // alias

        // 3. Test that plugins work like built-in field types
        $builtInTypes = ['string', 'integer', 'json'];
        foreach ($builtInTypes as $type) {
            $this->assertTrue(FieldTypeRegistry::has($type));
            $fieldType = FieldTypeRegistry::get($type);
            $this->assertNotNull($fieldType);
        }

        // 4. Test plugin-specific functionality
        $urlFieldType = FieldTypeRegistry::get('url');
        $this->assertInstanceOf(UrlFieldTypePlugin::class, $urlFieldType);

        // Test URL-specific methods
        if ($urlFieldType instanceof UrlFieldTypePlugin) {
            $this->assertEquals('example.com', $urlFieldType->extractDomain('https://example.com/path'));
            $this->assertTrue($urlFieldType->validateUrl('https://test.com', ['schemes' => ['https']]));
        }

        // 5. Test comprehensive metadata
        $metadata = $this->manager->getAllPluginMetadata();
        $this->assertArrayHasKey('url', $metadata);
        $this->assertArrayHasKey('json_schema', $metadata);

        foreach ($metadata as $pluginMetadata) {
            $this->assertArrayHasKey('name', $pluginMetadata);
            $this->assertArrayHasKey('version', $pluginMetadata);
            $this->assertArrayHasKey('author', $pluginMetadata);
            $this->assertArrayHasKey('description', $pluginMetadata);
            $this->assertArrayHasKey('aliases', $pluginMetadata);
            $this->assertArrayHasKey('supported_databases', $pluginMetadata);
            $this->assertArrayHasKey('attributes', $pluginMetadata);
        }

        // 6. Test plugin management
        $plugins = $this->manager->getPlugins();
        $this->assertCount(2, $plugins);

        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertCount(2, $enabledPlugins);

        // This integration test confirms the entire plugin system is working
        $this->addToAssertionCount(1);
    }
}
