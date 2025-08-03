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
     * Custom attributes supported by this plugin
     * These are in addition to standard Laravel field attributes
     */
    protected array $customAttributes = [];

    /**
     * Custom attribute configurations with validation rules and defaults
     */
    protected array $customAttributeConfig = [];

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
        return array_merge(['nullable', 'default'], $this->customAttributes);
    }

    /**
     * Get custom attributes supported by this plugin
     */
    public function getCustomAttributes(): array
    {
        return $this->customAttributes;
    }

    /**
     * Set custom attributes for this plugin
     */
    public function setCustomAttributes(array $attributes): void
    {
        $this->customAttributes = $attributes;
    }

    /**
     * Add a custom attribute to this plugin
     */
    public function addCustomAttribute(string $attribute, array $config = []): void
    {
        $this->customAttributes[] = $attribute;
        if ($config !== []) {
            $this->customAttributeConfig[$attribute] = $config;
        }
    }

    /**
     * Get custom attribute configuration
     */
    public function getCustomAttributeConfig(string $attribute): array
    {
        return $this->customAttributeConfig[$attribute] ?? [];
    }

    /**
     * Set custom attribute configuration
     */
    public function setCustomAttributeConfig(string $attribute, array $config): void
    {
        $this->customAttributeConfig[$attribute] = $config;
    }

    /**
     * Validate custom attribute value
     */
    public function validateCustomAttribute(string $attribute, $value): array
    {
        $errors = [];
        $config = $this->getCustomAttributeConfig($attribute);

        if ($config === []) {
            return $errors;
        }

        // Type validation (must pass before other validations)
        if (isset($config['type']) && ! $this->validateAttributeType($value, $config['type'])) {
            $errors[] = "Custom attribute '{$attribute}' must be of type {$config['type']}";

            // Return early if type validation fails to prevent cascading errors
            return $errors;
        }

        // Required validation
        if (isset($config['required']) && $config['required'] && ($value === null || $value === '')) {
            $errors[] = "Custom attribute '{$attribute}' is required";
        }

        // Min/Max validation for numeric values
        if (is_numeric($value)) {
            if (isset($config['min']) && $value < $config['min']) {
                $errors[] = "Custom attribute '{$attribute}' must be at least {$config['min']}";
            }
            if (isset($config['max']) && $value > $config['max']) {
                $errors[] = "Custom attribute '{$attribute}' must be at most {$config['max']}";
            }
        }

        // Enum validation
        if (isset($config['enum']) && is_array($config['enum'])) {
            if (is_array($value)) {
                // For array values, check each element against enum
                foreach ($value as $element) {
                    if (! in_array($element, $config['enum'], true)) {
                        $enumValues = implode(', ', $config['enum']);
                        $errors[] = "Custom attribute '{$attribute}' contains invalid value '{$element}'. Must be one of: {$enumValues}";
                    }
                }
            } elseif (! in_array($value, $config['enum'], true)) {
                // For scalar values, check directly
                $enumValues = implode(', ', $config['enum']);
                $errors[] = "Custom attribute '{$attribute}' must be one of: {$enumValues}";
            }
        }

        // Custom validation callback
        if (isset($config['validator']) && is_callable($config['validator'])) {
            $customErrors = call_user_func($config['validator'], $value, $attribute);
            if (is_array($customErrors)) {
                $errors = array_merge($errors, $customErrors);
            }
        }

        return $errors;
    }

    /**
     * Process custom attributes for field configuration
     */
    public function processCustomAttributes(array $fieldConfig): array
    {
        $processedConfig = $fieldConfig;

        foreach ($this->getCustomAttributes() as $attribute) {
            $attributeConfig = $this->getCustomAttributeConfig($attribute);

            if (isset($fieldConfig[$attribute])) {
                $value = $fieldConfig[$attribute];

                // Apply default transformation if specified
                if (isset($attributeConfig['transform']) && is_callable($attributeConfig['transform'])) {
                    $processedConfig[$attribute] = call_user_func($attributeConfig['transform'], $value);
                }
            } elseif (isset($attributeConfig['default'])) {
                // Apply default value if attribute is not provided
                $processedConfig[$attribute] = $attributeConfig['default'];
            }
        }

        return $processedConfig;
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
     * Get missing required custom attributes
     */
    public function getMissingRequiredCustomAttributes(array $config): array
    {
        $missing = [];

        foreach ($this->customAttributeConfig as $attribute => $attrConfig) {
            if (($attrConfig['required'] ?? false) && ! isset($config[$attribute])) {
                $missing[] = $attribute;
            }
        }

        return $missing;
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

    /**
     * Validate attribute type
     */
    protected function validateAttributeType($value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => $value === null,
            'numeric' => is_numeric($value),
            default => true, // Unknown type, assume valid
        };
    }
}
