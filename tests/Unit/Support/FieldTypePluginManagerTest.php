<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Tests\TestCase;

class FieldTypePluginManagerTest extends TestCase
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
    public function it_can_register_and_retrieve_plugins(): void
    {
        $plugin = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin);

        $this->assertTrue($this->manager->hasPlugin('url'));
        $this->assertSame($plugin, $this->manager->getPlugin('url'));
        $this->assertContains('url', array_keys($this->manager->getPlugins()));
    }

    /** @test */
    public function it_registers_plugin_with_field_type_registry(): void
    {
        $plugin = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin);

        // Plugin should be available in field type registry
        $this->assertTrue(FieldTypeRegistry::has('url'));

        // Aliases should be registered
        $this->assertTrue(FieldTypeRegistry::has('website'));
        $this->assertTrue(FieldTypeRegistry::has('link'));
        $this->assertTrue(FieldTypeRegistry::has('uri'));
    }

    /** @test */
    public function it_validates_plugin_before_registration(): void
    {
        // Create a mock plugin with missing required methods
        $invalidPlugin = new class extends UrlFieldTypePlugin
        {
            public function getType(): string
            {
                return ''; // Invalid empty type
            }
        };

        $this->expectException(\Grazulex\LaravelModelschema\Exceptions\SchemaException::class);
        $this->expectExceptionMessage('Plugin validation failed');

        $this->manager->registerPlugin($invalidPlugin);
    }

    /** @test */
    public function it_prevents_duplicate_plugin_registration(): void
    {
        $plugin1 = new UrlFieldTypePlugin();
        $plugin2 = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin1);

        $this->expectException(\Grazulex\LaravelModelschema\Exceptions\SchemaException::class);
        $this->expectExceptionMessage("Plugin with type 'url' already registered");

        $this->manager->registerPlugin($plugin2);
    }

    /** @test */
    public function it_can_unregister_plugins(): void
    {
        $plugin = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin);
        $this->assertTrue($this->manager->hasPlugin('url'));

        $this->manager->unregisterPlugin('url');
        $this->assertFalse($this->manager->hasPlugin('url'));
        $this->assertNull($this->manager->getPlugin('url'));
    }

    /** @test */
    public function it_tracks_enabled_disabled_plugins(): void
    {
        $plugin = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin);

        // Plugin should be enabled by default
        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertArrayHasKey('url', $enabledPlugins);

        // Disable plugin
        $this->manager->disablePlugin('url');
        $this->assertFalse($plugin->isEnabled());

        $enabledPlugins = $this->manager->getEnabledPlugins();
        $this->assertArrayNotHasKey('url', $enabledPlugins);

        // Re-enable plugin
        $this->manager->enablePlugin('url');
        $this->assertTrue($plugin->isEnabled());
    }

    /** @test */
    public function it_validates_plugin_dependencies(): void
    {
        // JsonSchemaFieldTypePlugin depends on 'json' type
        $plugin = new JsonSchemaFieldTypePlugin();

        // Should work since 'json' is a built-in type
        $this->manager->registerPlugin($plugin);
        $this->assertTrue($this->manager->hasPlugin('json_schema'));

        // Test with missing dependency
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
    public function it_can_get_plugin_metadata(): void
    {
        $plugin = new UrlFieldTypePlugin();

        $this->manager->registerPlugin($plugin);

        $metadata = $this->manager->getPluginMetadata('url');

        $this->assertIsArray($metadata);
        $this->assertEquals('url', $metadata['name']);
        $this->assertEquals('1.0.0', $metadata['version']);
        $this->assertEquals('Laravel ModelSchema Team', $metadata['author']);
        $this->assertStringContainsString('URL', $metadata['description']);
        $this->assertEquals(['website', 'link', 'uri'], $metadata['aliases']);
        $this->assertContains('nullable', $metadata['attributes']);
    }

    /** @test */
    public function it_can_get_all_plugin_metadata(): void
    {
        $urlPlugin = new UrlFieldTypePlugin();
        $jsonPlugin = new JsonSchemaFieldTypePlugin();

        $this->manager->registerPlugin($urlPlugin);
        $this->manager->registerPlugin($jsonPlugin);

        $allMetadata = $this->manager->getAllPluginMetadata();

        $this->assertIsArray($allMetadata);
        $this->assertArrayHasKey('url', $allMetadata);
        $this->assertArrayHasKey('json_schema', $allMetadata);

        $this->assertEquals('1.0.0', $allMetadata['url']['version']);
        $this->assertEquals('1.1.0', $allMetadata['json_schema']['version']);
    }

    /** @test */
    public function it_can_load_from_configuration(): void
    {
        $config = [
            'plugins' => [
                [
                    'class' => UrlFieldTypePlugin::class,
                    'config' => [
                        'enabled' => true,
                        'custom_setting' => 'value',
                    ],
                ],
            ],
        ];

        $this->manager->loadFromConfig($config);

        $this->assertTrue($this->manager->hasPlugin('url'));

        $plugin = $this->manager->getPlugin('url');
        $this->assertInstanceOf(UrlFieldTypePlugin::class, $plugin);
        if ($plugin instanceof UrlFieldTypePlugin) {
            $this->assertEquals('value', $plugin->getConfigValue('custom_setting'));
        }
    }

    /** @test */
    public function it_handles_invalid_plugin_configuration(): void
    {
        $config = [
            'plugins' => [
                [
                    // Missing class
                    'config' => ['enabled' => true],
                ],
            ],
        ];

        $this->expectException(\Grazulex\LaravelModelschema\Exceptions\SchemaException::class);
        $this->expectExceptionMessage('Plugin configuration must include class name');

        $this->manager->loadFromConfig($config);
    }

    /** @test */
    public function it_handles_non_existent_plugin_class(): void
    {
        $config = [
            'plugins' => [
                [
                    'class' => 'NonExistentPluginClass',
                ],
            ],
        ];

        $this->expectException(\Grazulex\LaravelModelschema\Exceptions\SchemaException::class);
        $this->expectExceptionMessage('Plugin class \'NonExistentPluginClass\' not found');

        $this->manager->loadFromConfig($config);
    }

    /** @test */
    public function it_can_manage_plugin_cache(): void
    {
        $this->manager->setCacheEnabled(true);
        $this->manager->clearCache();

        // Cache operations should not throw errors
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_can_set_discovery_patterns(): void
    {
        $patterns = ['*Plugin.php', '*FieldType.php'];

        $this->manager->setDiscoveryPatterns($patterns);

        // Should not throw errors
        $this->addToAssertionCount(1);
    }
}
