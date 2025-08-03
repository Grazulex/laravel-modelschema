<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Support;

use Grazulex\LaravelModelschema\Contracts\FieldTypeInterface;

/**
 * Abstract base class for field type plugins
 *
 * Provides structure and utilities for creating custom field type plugins
 * that can be dynamically registered with the FieldTypeRegistry.
 */
abstract class FieldTypePlugin implements FieldTypeInterface
{
    /**
     * Plugin metadata
     */
    protected array $metadata = [];

    /**
     * Plugin version
     */
    protected string $version = '1.0.0';

    /**
     * Plugin author
     */
    protected string $author = '';

    /**
     * Plugin description
     */
    protected string $description = '';

    /**
     * Whether the plugin is enabled
     */
    protected bool $enabled = true;

    /**
     * Plugin dependencies (other field types required)
     */
    protected array $dependencies = [];

    /**
     * Plugin configuration
     */
    protected array $config = [];

    /**
     * Create plugin from array data
     */
    public static function fromArray(array $data): static
    {
        $className = static::class;
        /** @var static $plugin */
        $plugin = new $className();

        if (isset($data['config'])) {
            $plugin->setConfig($data['config']);
        }

        if (isset($data['metadata'])) {
            $plugin->setMetadata($data['metadata']);
        }

        return $plugin;
    }

    /**
     * Get plugin metadata
     */
    public function getMetadata(): array
    {
        return array_merge([
            'name' => $this->getType(),
            'version' => $this->version,
            'author' => $this->author,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'dependencies' => $this->dependencies,
            'aliases' => $this->getAliases(),
            'supported_databases' => $this->getSupportedDatabases(),
            'attributes' => $this->getSupportedAttributesList(),
        ], $this->metadata);
    }

    /**
     * Set plugin metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
    }

    /**
     * Get plugin version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set plugin version
     */
    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * Get plugin author
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * Set plugin author
     */
    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    /**
     * Get plugin description
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Set plugin description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Check if plugin is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable/disable plugin
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Get plugin dependencies
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * Set plugin dependencies
     */
    public function setDependencies(array $dependencies): void
    {
        $this->dependencies = $dependencies;
    }

    /**
     * Get plugin configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set plugin configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Get specific config value
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set specific config value
     */
    public function setConfigValue(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Get supported databases (override in plugin)
     */
    public function getSupportedDatabases(): array
    {
        return ['mysql', 'postgresql', 'sqlite'];
    }

    /**
     * Get supported attributes (override in plugin)
     * Note: This differs from the interface supportsAttribute() method
     */
    public function getSupportedAttributesList(): array
    {
        return ['nullable', 'default'];
    }

    /**
     * Initialize plugin (called after registration)
     */
    public function initialize(): void
    {
        // Override in plugin if needed
    }

    /**
     * Cleanup plugin (called on unregistration)
     */
    public function cleanup(): void
    {
        // Override in plugin if needed
    }

    /**
     * Validate plugin before registration
     */
    public function validatePlugin(): array
    {
        $errors = [];

        if (in_array($this->getType(), ['', '0'], true)) {
            $errors[] = 'Plugin must define a type';
        }

        if ($this->description === '' || $this->description === '0') {
            $errors[] = 'Plugin should have a description';
        }

        // Validate method implementations
        $requiredMethods = [
            'getType',
            'getAliases',
            'validate',
            'supportsAttribute',
            'getCastType',
            'getValidationRules',
            'getMigrationParameters',
            'transformConfig',
            'getMigrationCall',
        ];

        foreach ($requiredMethods as $method) {
            if (! method_exists($this, $method)) {
                $errors[] = "Plugin must implement method: {$method}";
            }
        }

        return $errors;
    }

    /**
     * Check if plugin supports given database
     */
    public function supportsDatabase(string $database): bool
    {
        return in_array($database, $this->getSupportedDatabases(), true);
    }

    /**
     * Check if plugin supports given attribute
     * Note: This implements the interface supportsAttribute() method
     */
    public function supportsAttribute(string $attribute): bool
    {
        return in_array($attribute, $this->getSupportedAttributesList(), true);
    }

    /**
     * Check if plugin supports given attribute (alias for compatibility)
     */
    public function checkAttributeSupport(string $attribute): bool
    {
        return in_array($attribute, $this->getSupportedAttributesList(), true);
    }

    /**
     * Get plugin configuration schema for validation
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
            ],
        ];
    }

    /**
     * Convert plugin to array for serialization
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->getMetadata(),
            'config' => $this->getConfig(),
            'type' => $this->getType(),
            'aliases' => $this->getAliases(),
        ];
    }
}
