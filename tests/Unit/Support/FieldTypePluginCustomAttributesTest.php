<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;

describe('FieldType Plugin Custom Attributes', function () {
    it('supports custom attributes in UrlFieldTypePlugin', function () {
        $plugin = new UrlFieldTypePlugin();

        $customAttributes = $plugin->getCustomAttributes();

        expect($customAttributes)->toContain('schemes');
        expect($customAttributes)->toContain('verify_ssl');
        expect($customAttributes)->toContain('allow_query_params');
        expect($customAttributes)->toContain('max_redirects');
        expect($customAttributes)->toContain('timeout');
        expect($customAttributes)->toContain('domain_whitelist');
        expect($customAttributes)->toContain('domain_blacklist');
    });

    it('validates custom attributes correctly in UrlFieldTypePlugin', function () {
        $plugin = new UrlFieldTypePlugin();

        // Valid configuration
        $validConfig = [
            'schemes' => ['https', 'http'],
            'verify_ssl' => true,
            'allow_query_params' => false,
            'max_redirects' => 3,
            'timeout' => 30,
            'domain_whitelist' => ['example.com', 'trusted.org'],
            'domain_blacklist' => ['malicious.com'],
        ];

        $errors = $plugin->validate($validConfig);
        expect($errors)->toBeEmpty();
    });

    it('rejects invalid custom attribute values in UrlFieldTypePlugin', function () {
        $plugin = new UrlFieldTypePlugin();

        // Invalid configuration
        $invalidConfig = [
            'schemes' => 'not-an-array', // Should be array
            'verify_ssl' => 'yes', // Should be boolean
            'max_redirects' => -1, // Should be positive
            'timeout' => 'invalid', // Should be integer
            'domain_whitelist' => 'single-domain', // Should be array
        ];

        $errors = $plugin->validate($invalidConfig);
        expect($errors)->not->toBeEmpty();
        expect(count($errors))->toBeGreaterThan(0);
    });

    it('supports custom attributes in JsonSchemaFieldTypePlugin', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        $customAttributes = $plugin->getCustomAttributes();

        expect($customAttributes)->toContain('schema');
        expect($customAttributes)->toContain('strict_validation');
        expect($customAttributes)->toContain('allow_additional_properties');
        expect($customAttributes)->toContain('schema_format');
        expect($customAttributes)->toContain('validation_mode');
        expect($customAttributes)->toContain('error_format');
        expect($customAttributes)->toContain('schema_cache_ttl');
        expect($customAttributes)->toContain('schema_version');
    });

    it('validates required custom attributes in JsonSchemaFieldTypePlugin', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        // Missing required 'schema' attribute
        $configWithoutSchema = [
            'strict_validation' => true,
        ];

        $errors = $plugin->validate($configWithoutSchema);
        expect($errors)->not->toBeEmpty();
        expect(implode(' ', $errors))->toContain('schema');
    });

    it('validates custom attribute types correctly', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        // Valid types
        $validConfig = [
            'schema' => ['type' => 'object', 'properties' => []],
            'strict_validation' => true,
            'allow_additional_properties' => false,
            'schema_format' => 'draft-07',
            'validation_mode' => 'strict',
            'error_format' => 'detailed',
            'schema_cache_ttl' => 3600,
            'schema_version' => '1.0.0',
        ];

        $errors = $plugin->validate($validConfig);
        expect($errors)->toBeEmpty();
    });

    it('validates custom attribute enum values', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        // Invalid enum values
        $invalidConfig = [
            'schema' => ['type' => 'object'],
            'schema_format' => 'invalid-draft', // Not in enum
            'validation_mode' => 'invalid-mode', // Not in enum
            'error_format' => 'invalid-format', // Not in enum
        ];

        $errors = $plugin->validate($invalidConfig);
        expect($errors)->not->toBeEmpty();
    });

    it('validates custom attribute min/max constraints', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        // Values outside min/max range
        $invalidConfig = [
            'schema' => ['type' => 'object'],
            'schema_cache_ttl' => -1, // Below min (0)
        ];

        $errors = $plugin->validate($invalidConfig);
        expect($errors)->not->toBeEmpty();

        // Value above max
        $invalidConfig2 = [
            'schema' => ['type' => 'object'],
            'schema_cache_ttl' => 100000, // Above max (86400)
        ];

        $errors2 = $plugin->validate($invalidConfig2);
        expect($errors2)->not->toBeEmpty();
    });

    it('merges standard and custom attributes in getSupportedAttributesList', function () {
        $plugin = new UrlFieldTypePlugin();

        $supportedAttributes = $plugin->getSupportedAttributesList();

        // Should contain standard Laravel attributes
        expect($supportedAttributes)->toContain('nullable');
        expect($supportedAttributes)->toContain('default');
        expect($supportedAttributes)->toContain('max_length');

        // Should contain custom attributes
        expect($supportedAttributes)->toContain('schemes');
        expect($supportedAttributes)->toContain('verify_ssl');
        expect($supportedAttributes)->toContain('timeout');
    });

    it('processes custom attributes with default values', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        $config = [
            'schema' => ['type' => 'object'],
            'nullable' => true,
        ];

        $processedConfig = $plugin->processCustomAttributes($config);

        // Should have applied default values for custom attributes
        expect($processedConfig['strict_validation'])->toBe(true);
        expect($processedConfig['allow_additional_properties'])->toBe(false);
        expect($processedConfig['schema_format'])->toBe('draft-07');
        expect($processedConfig['validation_mode'])->toBe('strict');
        expect($processedConfig['error_format'])->toBe('detailed');
        expect($processedConfig['schema_cache_ttl'])->toBe(3600);

        // Original config should be preserved
        expect($processedConfig['schema'])->toBe(['type' => 'object']);
        expect($processedConfig['nullable'])->toBe(true);
    });

    it('validates custom attributes using custom validator functions', function () {
        $plugin = new JsonSchemaFieldTypePlugin();

        // Invalid schema structure (should trigger custom validator)
        $invalidConfig = [
            'schema' => ['invalid' => 'schema'], // Invalid JSON Schema
        ];

        $errors = $plugin->validate($invalidConfig);
        expect($errors)->not->toBeEmpty();
    });
});
