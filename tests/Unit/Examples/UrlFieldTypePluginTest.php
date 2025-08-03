<?php

declare(strict_types=1);

namespace Tests\Unit\Examples;

use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Tests\TestCase;

class UrlFieldTypePluginTest extends TestCase
{
    protected UrlFieldTypePlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = new UrlFieldTypePlugin();
    }

    /** @test */
    public function it_has_correct_basic_properties(): void
    {
        $this->assertEquals('url', $this->plugin->getType());
        $this->assertEquals(['website', 'link', 'uri'], $this->plugin->getAliases());
        $this->assertEquals('string', $this->plugin->getCastType([]));
    }

    /** @test */
    public function it_has_correct_metadata(): void
    {
        $metadata = $this->plugin->getMetadata();

        $this->assertEquals('url', $metadata['name']);
        $this->assertEquals('1.0.0', $metadata['version']);
        $this->assertEquals('Laravel ModelSchema Team', $metadata['author']);
        $this->assertStringContainsString('URL', $metadata['description']);
        $this->assertEquals(['website', 'link', 'uri'], $metadata['aliases']);
        $this->assertContains('nullable', $metadata['attributes']);
        $this->assertContains('max_length', $metadata['attributes']);
        $this->assertContains('schemes', $metadata['attributes']);
    }

    /** @test */
    public function it_validates_configuration_correctly(): void
    {
        // Valid configuration
        $validConfig = [
            'max_length' => 255,
            'schemes' => ['http', 'https'],
            'default' => 'https://example.com',
        ];

        $errors = $this->plugin->validate($validConfig);
        $this->assertEmpty($errors);

        // Invalid max_length
        $invalidConfig = [
            'max_length' => -1,
        ];

        $errors = $this->plugin->validate($invalidConfig);
        $this->assertContains('max_length must be a positive integer', $errors);

        // Invalid default URL
        $invalidConfig = [
            'default' => 'not-a-url',
        ];

        $errors = $this->plugin->validate($invalidConfig);
        $this->assertContains('default value must be a valid URL', $errors);

        // Invalid schemes
        $invalidConfig = [
            'schemes' => 'not-an-array',
        ];

        $errors = $this->plugin->validate($invalidConfig);
        $this->assertStringContainsString("Custom attribute 'schemes' must be of type array", $errors[0]);

        $invalidConfig = [
            'schemes' => ['invalid-scheme'],
        ];

        $errors = $this->plugin->validate($invalidConfig);
        $this->assertStringContainsString("Custom attribute 'schemes' contains invalid value 'invalid-scheme'", $errors[0]);
    }

    /** @test */
    public function it_transforms_configuration_correctly(): void
    {
        $config = [];

        $transformed = $this->plugin->transformConfig($config);

        $this->assertEquals(255, $transformed['max_length']);
        $this->assertEquals(['http', 'https'], $transformed['schemes']);

        // Test string schemes conversion
        $config = [
            'schemes' => 'https',
        ];

        $transformed = $this->plugin->transformConfig($config);
        $this->assertEquals(['https'], $transformed['schemes']);
    }

    /** @test */
    public function it_generates_correct_validation_rules(): void
    {
        $config = [
            'max_length' => 500,
            'nullable' => true,
        ];

        $rules = $this->plugin->getValidationRules($config);

        $this->assertContains('url', $rules);
        $this->assertContains('max:500', $rules);
        $this->assertContains('nullable', $rules);

        // Test without nullable
        $config = ['max_length' => 255];

        $rules = $this->plugin->getValidationRules($config);

        $this->assertContains('required', $rules);
        $this->assertNotContains('nullable', $rules);
    }

    /** @test */
    public function it_generates_migration_parameters_correctly(): void
    {
        $config = [
            'max_length' => 500,
            'nullable' => true,
            'default' => 'https://example.com',
        ];

        $params = $this->plugin->getMigrationParameters($config);

        $this->assertEquals(500, $params['length']);
        $this->assertTrue($params['nullable']);
        $this->assertEquals('https://example.com', $params['default']);

        // Test defaults
        $config = [];
        $params = $this->plugin->getMigrationParameters($config);

        $this->assertEquals(255, $params['length']);
        $this->assertArrayNotHasKey('nullable', $params);
    }

    /** @test */
    public function it_generates_migration_call_correctly(): void
    {
        $config = [
            'max_length' => 500,
            'nullable' => true,
            'default' => 'https://example.com',
        ];

        $call = $this->plugin->getMigrationCall('website_url', $config);

        $expected = "\$table->string('website_url', 500)->nullable()->default('https://example.com')";
        $this->assertEquals($expected, $call);

        // Test minimal config
        $config = [];
        $call = $this->plugin->getMigrationCall('url', $config);

        $expected = "\$table->string('url', 255)";
        $this->assertEquals($expected, $call);
    }

    /** @test */
    public function it_supports_correct_attributes(): void
    {
        $this->assertTrue($this->plugin->supportsAttribute('nullable'));
        $this->assertTrue($this->plugin->supportsAttribute('default'));
        $this->assertTrue($this->plugin->supportsAttribute('max_length'));
        $this->assertTrue($this->plugin->supportsAttribute('schemes'));
        $this->assertFalse($this->plugin->supportsAttribute('unsupported'));
    }

    /** @test */
    public function it_supports_correct_databases(): void
    {
        $databases = $this->plugin->getSupportedDatabases();

        $this->assertContains('mysql', $databases);
        $this->assertContains('postgresql', $databases);
        $this->assertContains('sqlite', $databases);
    }

    /** @test */
    public function it_transforms_url_values_correctly(): void
    {
        $this->assertNull($this->plugin->transformValue(null));
        $this->assertNull($this->plugin->transformValue(''));

        $this->assertEquals('https://example.com', $this->plugin->transformValue('https://example.com'));
        $this->assertEquals('https://example.com', $this->plugin->transformValue('example.com'));
    }

    /** @test */
    public function it_validates_url_schemes_correctly(): void
    {
        $config = ['schemes' => ['https']];

        $this->assertTrue($this->plugin->validateUrl('https://example.com', $config));
        $this->assertFalse($this->plugin->validateUrl('http://example.com', $config));
        $this->assertFalse($this->plugin->validateUrl('invalid-url', $config));
    }

    /** @test */
    public function it_extracts_domain_correctly(): void
    {
        $this->assertEquals('example.com', $this->plugin->extractDomain('https://example.com/path'));
        $this->assertEquals('subdomain.example.com', $this->plugin->extractDomain('http://subdomain.example.com'));
        $this->assertNull($this->plugin->extractDomain('invalid-url'));
    }

    /** @test */
    public function it_has_valid_config_schema(): void
    {
        $schema = $this->plugin->getConfigSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('max_length', $schema['properties']);
        $this->assertArrayHasKey('schemes', $schema['properties']);
    }

    /** @test */
    public function it_can_be_enabled_and_disabled(): void
    {
        $this->assertTrue($this->plugin->isEnabled());

        $this->plugin->setEnabled(false);
        $this->assertFalse($this->plugin->isEnabled());

        $this->plugin->setEnabled(true);
        $this->assertTrue($this->plugin->isEnabled());
    }

    /** @test */
    public function it_can_serialize_to_array(): void
    {
        $this->plugin->setConfig(['test' => 'value']);

        $array = $this->plugin->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertArrayHasKey('config', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('aliases', $array);

        $this->assertEquals('url', $array['type']);
        $this->assertEquals(['test' => 'value'], $array['config']);
    }
}
