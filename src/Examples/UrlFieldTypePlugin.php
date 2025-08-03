<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Examples;

use Grazulex\LaravelModelschema\Support\FieldTypePlugin;

/**
 * Example plugin for URL field type
 *
 * Demonstrates how to create a custom field type plugin
 * with validation, configuration, and database support.
 */
class UrlFieldTypePlugin extends FieldTypePlugin
{
    protected string $version = '1.0.0';

    protected string $author = 'Laravel ModelSchema Team';

    protected string $description = 'Field type for validating and storing URLs';

    /**
     * Get the field type identifier
     */
    public function getType(): string
    {
        return 'url';
    }

    /**
     * Get aliases for this field type
     */
    public function getAliases(): array
    {
        return ['website', 'link', 'uri'];
    }

    /**
     * Validate field configuration
     */
    public function validate(array $config): array
    {
        $errors = [];

        // Validate max_length if provided
        if (isset($config['max_length'])) {
            if (! is_int($config['max_length']) || $config['max_length'] < 1) {
                $errors[] = 'max_length must be a positive integer';
            }

            if ($config['max_length'] > 2048) {
                $errors[] = 'max_length should not exceed 2048 characters for URLs';
            }
        }

        // Validate default value if provided
        if (isset($config['default']) && ! filter_var($config['default'], FILTER_VALIDATE_URL)) {
            $errors[] = 'default value must be a valid URL';
        }

        // Validate schemes if provided
        if (isset($config['schemes'])) {
            if (! is_array($config['schemes'])) {
                $errors[] = 'schemes must be an array';
            } else {
                $validSchemes = ['http', 'https', 'ftp', 'ftps', 'file'];
                foreach ($config['schemes'] as $scheme) {
                    if (! in_array($scheme, $validSchemes, true)) {
                        $errors[] = "Invalid scheme '{$scheme}'. Allowed: ".implode(', ', $validSchemes);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Get cast type for Laravel model
     */
    public function getCastType(array $config): ?string
    {
        return 'string';
    }

    /**
     * Get validation rules for this field type
     */
    public function getValidationRules(array $config): array
    {
        $rules = ['url'];

        // Add max length validation
        if (isset($config['max_length'])) {
            $rules[] = 'max:'.$config['max_length'];
        } else {
            $rules[] = 'max:2048'; // Default URL length limit
        }

        // Add nullable if configured
        $rules[] = isset($config['nullable']) && $config['nullable'] ? 'nullable' : 'required';

        return $rules;
    }

    /**
     * Get migration parameters for this field
     */
    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Default length for URLs
        $length = $config['max_length'] ?? 255;
        $params['length'] = $length;

        // Add nullable parameter
        if (isset($config['nullable']) && $config['nullable']) {
            $params['nullable'] = true;
        }

        // Add default value
        if (isset($config['default'])) {
            $params['default'] = $config['default'];
        }

        return $params;
    }

    /**
     * Transform configuration array
     */
    public function transformConfig(array $config): array
    {
        // Set default max_length if not provided
        if (! isset($config['max_length'])) {
            $config['max_length'] = 255;
        }

        // Set default schemes if not provided
        if (! isset($config['schemes'])) {
            $config['schemes'] = ['http', 'https'];
        }

        // Ensure schemes is an array
        if (! is_array($config['schemes'])) {
            $config['schemes'] = [$config['schemes']];
        }

        return $config;
    }

    /**
     * Get the migration method call with parameters
     */
    public function getMigrationCall(string $fieldName, array $config): string
    {
        $length = $config['max_length'] ?? 255;

        $call = "\$table->string('{$fieldName}', {$length})";

        // Add nullable if configured
        if (isset($config['nullable']) && $config['nullable']) {
            $call .= '->nullable()';
        }

        // Add default value
        if (isset($config['default'])) {
            $call .= "->default('{$config['default']}')";
        }

        return $call;
    }

    /**
     * Get supported databases
     */
    public function getSupportedDatabases(): array
    {
        return ['mysql', 'postgresql', 'sqlite'];
    }

    /**
     * Get supported attributes
     */
    public function getSupportedAttributesList(): array
    {
        return ['nullable', 'default', 'max_length', 'schemes'];
    }

    /**
     * Transform URL value for storage/display
     */
    public function transformValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Ensure the URL has a scheme
        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return 'https://'.$value;
        }

        return $value;
    }

    /**
     * Validate URL against configured schemes
     */
    public function validateUrl(string $url, array $config): bool
    {
        $schemes = $config['schemes'] ?? ['http', 'https'];

        $parsedUrl = parse_url($url);
        if ($parsedUrl === 0 || ($parsedUrl === '' || $parsedUrl === '0') || $parsedUrl === [] || $parsedUrl === false || $parsedUrl === null || ! isset($parsedUrl['scheme'])) {
            return false;
        }

        return in_array($parsedUrl['scheme'], $schemes, true);
    }

    /**
     * Extract domain from URL
     */
    public function extractDomain(string $url): ?string
    {
        $parsedUrl = parse_url($url);

        return $parsedUrl['host'] ?? null;
    }

    /**
     * Plugin-specific configuration schema
     */
    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'enabled' => ['type' => 'boolean'],
                'version' => ['type' => 'string'],
                'author' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'max_length' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 2048,
                    'default' => 255,
                ],
                'schemes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => ['http', 'https', 'ftp', 'ftps', 'file'],
                    ],
                    'default' => ['http', 'https'],
                ],
            ],
        ];
    }
}
