<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Support;

use Grazulex\LaravelModelschema\Contracts\FieldTypeInterface;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Throwable;

/**
 * Manager for field type plugins
 *
 * Handles registration, loading, and management of custom field type plugins.
 */
class FieldTypePluginManager
{
    /**
     * Registered plugins
     */
    protected array $plugins = [];

    /**
     * Plugin directories to scan
     */
    protected array $pluginDirectories = [];

    /**
     * Plugin cache
     */
    protected array $pluginCache = [];

    /**
     * Whether cache is enabled
     */
    protected bool $cacheEnabled = true;

    /**
     * Plugin discovery patterns
     */
    protected array $discoveryPatterns = [
        '*.php',
        '*FieldType.php',
        '*Plugin.php',
    ];

    /**
     * Field type registry class (static)
     */
    protected string $registryClass;

    public function __construct(string $registryClass = FieldTypeRegistry::class)
    {
        $this->registryClass = $registryClass;
    }

    /**
     * Register a plugin
     */
    public function registerPlugin(FieldTypeInterface $plugin): void
    {
        // Validate plugin
        if ($plugin instanceof FieldTypePlugin) {
            $errors = $plugin->validatePlugin();
            if ($errors !== []) {
                throw new SchemaException(
                    'Plugin validation failed: '.implode(', ', $errors)
                );
            }
        }

        $type = $plugin->getType();

        // Check for conflicts
        if (isset($this->plugins[$type])) {
            throw new SchemaException("Plugin with type '{$type}' already registered");
        }

        // Check dependencies
        $this->validateDependencies($plugin);

        // Register plugin
        $this->plugins[$type] = $plugin;

        // Register with field type registry
        call_user_func([$this->registryClass, 'register'], $type, get_class($plugin));

        // Register aliases
        foreach ($plugin->getAliases() as $alias) {
            call_user_func([$this->registryClass, 'registerAlias'], $alias, $type);
        }

        // Initialize plugin
        if ($plugin instanceof FieldTypePlugin) {
            $plugin->initialize();
        }
    }

    /**
     * Unregister a plugin
     */
    public function unregisterPlugin(string $type): void
    {
        if (! isset($this->plugins[$type])) {
            throw new SchemaException("Plugin '{$type}' not found");
        }

        $plugin = $this->plugins[$type];

        // Cleanup plugin
        if ($plugin instanceof FieldTypePlugin) {
            $plugin->cleanup();
        }

        // Remove from registry - we'll need to add unregister methods to FieldTypeRegistry
        // For now, we just track in our plugin manager

        // Remove aliases - same limitation
        foreach ($plugin->getAliases() as $alias) {
            // call_user_func([$this->registryClass, 'unregisterAlias'], $alias);
        }

        // Remove plugin
        unset($this->plugins[$type]);
    }

    /**
     * Get all registered plugins
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Get plugin by type
     */
    public function getPlugin(string $type): ?FieldTypeInterface
    {
        return $this->plugins[$type] ?? null;
    }

    /**
     * Check if plugin exists
     */
    public function hasPlugin(string $type): bool
    {
        return isset($this->plugins[$type]);
    }

    /**
     * Get enabled plugins only
     */
    public function getEnabledPlugins(): array
    {
        return array_filter($this->plugins, function ($plugin): bool {
            return ! ($plugin instanceof FieldTypePlugin) || $plugin->isEnabled();
        });
    }

    /**
     * Enable plugin
     */
    public function enablePlugin(string $type): void
    {
        $plugin = $this->getPlugin($type);
        if ($plugin instanceof FieldTypePlugin) {
            $plugin->setEnabled(true);
        }
    }

    /**
     * Disable plugin
     */
    public function disablePlugin(string $type): void
    {
        $plugin = $this->getPlugin($type);
        if ($plugin instanceof FieldTypePlugin) {
            $plugin->setEnabled(false);
        }
    }

    /**
     * Add plugin directory for discovery
     */
    public function addPluginDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            throw new SchemaException("Plugin directory '{$directory}' does not exist");
        }

        $this->pluginDirectories[] = realpath($directory);
    }

    /**
     * Set plugin directories
     */
    public function setPluginDirectories(array $directories): void
    {
        $this->pluginDirectories = [];
        foreach ($directories as $directory) {
            $this->addPluginDirectory($directory);
        }
    }

    /**
     * Discover and load plugins from directories
     */
    public function discoverPlugins(): array
    {
        $discovered = [];

        foreach ($this->pluginDirectories as $directory) {
            $plugins = $this->discoverPluginsInDirectory($directory);
            $discovered = array_merge($discovered, $plugins);
        }

        return $discovered;
    }

    /**
     * Get plugin metadata
     */
    public function getPluginMetadata(string $type): array
    {
        $plugin = $this->getPlugin($type);

        if (! $plugin instanceof FieldTypePlugin) {
            return [];
        }

        return $plugin->getMetadata();
    }

    /**
     * Get all plugin metadata
     */
    public function getAllPluginMetadata(): array
    {
        $metadata = [];

        foreach ($this->plugins as $type => $plugin) {
            if ($plugin instanceof FieldTypePlugin) {
                $metadata[$type] = $plugin->getMetadata();
            }
        }

        return $metadata;
    }

    /**
     * Load plugins from configuration
     */
    public function loadFromConfig(array $config): void
    {
        // Auto-discovery
        if (isset($config['auto_discovery']) && $config['auto_discovery'] && isset($config['plugin_directories'])) {
            $this->setPluginDirectories($config['plugin_directories']);
            $this->discoverPlugins();
        }

        // Explicit plugin registration
        if (isset($config['plugins'])) {
            foreach ($config['plugins'] as $pluginConfig) {
                $this->loadPluginFromConfig($pluginConfig);
            }
        }
    }

    /**
     * Enable/disable plugin cache
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Clear plugin cache
     */
    public function clearCache(): void
    {
        $this->pluginCache = [];
    }

    /**
     * Set discovery patterns
     */
    public function setDiscoveryPatterns(array $patterns): void
    {
        $this->discoveryPatterns = $patterns;
    }

    /**
     * Discover plugins in specific directory
     */
    protected function discoverPluginsInDirectory(string $directory): array
    {
        $discovered = [];

        foreach ($this->discoveryPatterns as $pattern) {
            $files = glob($directory.DIRECTORY_SEPARATOR.$pattern);

            foreach ($files as $file) {
                $plugin = $this->loadPluginFromFile($file);
                if ($plugin instanceof FieldTypeInterface) {
                    $discovered[] = $plugin;
                }
            }
        }

        return $discovered;
    }

    /**
     * Load plugin from file
     */
    protected function loadPluginFromFile(string $file): ?FieldTypeInterface
    {
        // Check cache first
        if ($this->cacheEnabled && isset($this->pluginCache[$file])) {
            $cacheData = $this->pluginCache[$file];
            if (filemtime($file) === $cacheData['mtime']) {
                return $this->createPluginFromCache($cacheData['data']);
            }
        }

        try {
            // Include the file
            require_once $file;

            // Find plugin class
            $classes = $this->findPluginClassesInFile($file);

            foreach ($classes as $className) {
                if (is_subclass_of($className, FieldTypeInterface::class)) {
                    $plugin = new $className();

                    // Cache plugin data
                    if ($this->cacheEnabled) {
                        $this->pluginCache[$file] = [
                            'mtime' => filemtime($file),
                            'data' => $plugin instanceof FieldTypePlugin ? $plugin->toArray() : [],
                        ];
                    }

                    return $plugin;
                }
            }
        } catch (Throwable $e) {
            // Log error but don't stop discovery
            error_log("Failed to load plugin from {$file}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Find plugin classes in file
     */
    protected function findPluginClassesInFile(string $file): array
    {
        $content = file_get_contents($file);
        $classes = [];

        // Extract namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class names
        if (preg_match_all('/class\s+(\w+)(?:\s+extends\s+\w+)?(?:\s+implements\s+[^{]+)?/', $content, $matches)) {
            foreach ($matches[1] as $className) {
                $fullClassName = $namespace !== '' && $namespace !== '0' ? $namespace.'\\'.$className : $className;
                $classes[] = $fullClassName;
            }
        }

        return $classes;
    }

    /**
     * Create plugin from cache data
     */
    protected function createPluginFromCache(array $data): ?FieldTypeInterface
    {
        // This would need to be implemented based on plugin structure
        // For now, we'll just return null to force reload
        return null;
    }

    /**
     * Validate plugin dependencies
     */
    protected function validateDependencies(FieldTypeInterface $plugin): void
    {
        if (! ($plugin instanceof FieldTypePlugin)) {
            return;
        }

        $dependencies = $plugin->getDependencies();

        foreach ($dependencies as $dependency) {
            if (! call_user_func([$this->registryClass, 'has'], $dependency)) {
                throw new SchemaException(
                    "Plugin '{$plugin->getType()}' requires field type '{$dependency}' which is not registered"
                );
            }
        }
    }

    /**
     * Load single plugin from configuration
     */
    protected function loadPluginFromConfig(array $config): void
    {
        if (! isset($config['class'])) {
            throw new SchemaException('Plugin configuration must include class name');
        }

        $className = $config['class'];

        if (! class_exists($className)) {
            throw new SchemaException("Plugin class '{$className}' not found");
        }

        if (! is_subclass_of($className, FieldTypeInterface::class)) {
            throw new SchemaException("Plugin class '{$className}' must implement FieldTypeInterface");
        }

        $plugin = new $className();

        // Configure plugin
        if ($plugin instanceof FieldTypePlugin && isset($config['config'])) {
            $plugin->setConfig($config['config']);
        }

        $this->registerPlugin($plugin);
    }
}
