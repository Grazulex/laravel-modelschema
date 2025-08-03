<?php

declare(strict_types=1);

/**
 * LEGACY EXAMPLE - Traditional Field Type Implementation
 *
 * ⚠️  This example shows the legacy approach for creating custom field types.
 * ✅  For new implementations, use the Plugin System instead:
 *     - See: src/Examples/UrlFieldTypePlugin.php
 *     - Documentation: docs/FIELD_TYPE_PLUGINS.md
 *
 * This file demonstrates the traditional way developers could create field types
 * by extending AbstractFieldType directly. While this still works, the new
 * Plugin System provides better features:
 *
 * - Auto-discovery
 * - Dependency management
 * - Configuration validation
 * - Metadata handling
 * - Better integration
 *
 * To use this legacy approach:
 * 1. Copy this file to your app/FieldTypes directory
 * 2. Adjust the namespace to App\FieldTypes
 * 3. Register manually with FieldTypeRegistry
 */

namespace App\FieldTypes;

use Grazulex\LaravelModelschema\FieldTypes\AbstractFieldType;

/**
 * LEGACY: URL field type implementation
 * Example of a specialized string field with URL validation
 *
 * 🔄 For modern plugin approach, see: src/Examples/UrlFieldTypePlugin.php
 */
final class UrlFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'schemes', // allowed schemes: http, https, ftp, etc.
        'verify_ssl',
        'allow_query_params',
    ];

    public function getType(): string
    {
        return 'url';
    }

    public function getAliases(): array
    {
        return ['website', 'link'];
    }

    public function getMigrationMethod(): string
    {
        return 'string';
    }

    public function getCastType(array $config = []): ?string
    {
        return 'string';
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        // Remove generic string validation and add URL validation
        $rules = array_filter($rules, fn ($rule) => $rule !== 'string');
        $rules[] = 'url';

        // Add scheme validation if specified
        if (isset($config['schemes']) && is_array($config['schemes'])) {
            $schemes = implode(',', $config['schemes']);
            $rules[] = "url:schemes={$schemes}";
        }

        // Set reasonable max length for URLs if not specified
        if (! isset($config['length'])) {
            $rules[] = 'max:2048';
        }

        return $rules;
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Set default length for URL fields
        if (isset($config['length'])) {
            $params[] = $config['length'];
        } else {
            $params[] = 2048; // Default URL length
        }

        return $params;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate schemes
        if (isset($config['schemes'])) {
            if (! is_array($config['schemes'])) {
                $errors[] = 'URL schemes must be an array';
            } else {
                $validSchemes = ['http', 'https', 'ftp', 'ftps', 'sftp'];
                foreach ($config['schemes'] as $scheme) {
                    if (! in_array($scheme, $validSchemes)) {
                        $errors[] = "Invalid URL scheme: {$scheme}. Valid schemes: ".implode(', ', $validSchemes);
                    }
                }
            }
        }

        // Additional validation for URL fields
        if (isset($config['length']) && $config['length'] < 50) {
            $errors[] = 'URL field length should be at least 50 characters';
        }

        return $errors;
    }

    public function transformConfig(array $config): array
    {
        // Set default length for URL fields
        if (! isset($config['length'])) {
            $config['length'] = 2048;
        }

        // Set default schemes if not specified
        if (! isset($config['schemes'])) {
            $config['schemes'] = ['http', 'https'];
        }

        // URLs might be indexed for searching
        if (! isset($config['index'])) {
            $config['index'] = false; // Usually too long to index efficiently
        }

        return $config;
    }
}
