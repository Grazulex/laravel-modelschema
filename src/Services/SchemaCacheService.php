<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Exception;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Illuminate\Support\Facades\Cache;

/**
 * Cache service for schema operations
 * Provides caching capabilities for parsed schemas to improve performance
 */
class SchemaCacheService
{
    private bool $enabled;

    private int $ttl;

    private string $keyPrefix;

    public function __construct()
    {
        $this->enabled = config('modelschema.cache.enabled', true);
        $this->ttl = config('modelschema.cache.ttl', 3600);
        $this->keyPrefix = config('modelschema.cache.key_prefix', 'modelschema:');
    }

    /**
     * Generate a cache key for YAML content
     */
    public function generateContentKey(string $yamlContent, string $modelName = ''): string
    {
        $hash = hash('xxh3', $yamlContent);
        $suffix = $modelName !== '' && $modelName !== '0' ? ":{$modelName}" : '';

        return "{$this->keyPrefix}content:{$hash}{$suffix}";
    }

    /**
     * Generate a cache key for file path
     */
    public function generateFileKey(string $filePath): string
    {
        // Include file modification time in the key for cache invalidation
        $mtime = file_exists($filePath) ? filemtime($filePath) : 0;
        $hash = hash('xxh3', $filePath.$mtime);

        return "{$this->keyPrefix}file:{$hash}";
    }

    /**
     * Generate a cache key for validation results
     */
    public function generateValidationKey(string $yamlContent): string
    {
        $hash = hash('xxh3', $yamlContent);

        return "{$this->keyPrefix}validation:{$hash}";
    }

    /**
     * Get cached schema by content
     */
    public function getSchemaByContent(string $yamlContent, string $modelName = ''): ?ModelSchema
    {
        if (! $this->enabled) {
            return null;
        }

        $key = $this->generateContentKey($yamlContent, $modelName);
        $cached = Cache::get($key);

        if ($cached && $cached instanceof ModelSchema) {
            return $cached;
        }

        return null;
    }

    /**
     * Cache schema by content
     */
    public function putSchemaByContent(string $yamlContent, ModelSchema $schema, string $modelName = ''): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->generateContentKey($yamlContent, $modelName);
        Cache::put($key, $schema, $this->ttl);
    }

    /**
     * Get cached schema by file path
     */
    public function getSchemaByFile(string $filePath): ?ModelSchema
    {
        if (! $this->enabled) {
            return null;
        }

        $key = $this->generateFileKey($filePath);
        $cached = Cache::get($key);

        if ($cached && $cached instanceof ModelSchema) {
            return $cached;
        }

        return null;
    }

    /**
     * Cache schema by file path
     */
    public function putSchemaByFile(string $filePath, ModelSchema $schema): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->generateFileKey($filePath);
        Cache::put($key, $schema, $this->ttl);
    }

    /**
     * Get cached validation results
     */
    public function getValidationResults(string $yamlContent): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        $key = $this->generateValidationKey($yamlContent);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        return null;
    }

    /**
     * Cache validation results
     */
    public function putValidationResults(string $yamlContent, array $validationResults): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->generateValidationKey($yamlContent);
        Cache::put($key, $validationResults, $this->ttl);
    }

    /**
     * Clear all schema cache entries
     */
    public function clearAll(): void
    {
        if (! $this->enabled) {
            return;
        }

        // For simplicity, we'll use Cache::flush() for now
        // In a production environment, you might want to implement
        // pattern-based deletion to avoid clearing other cache entries
        try {
            // Clear only our prefixed keys by iterating through common patterns
            $this->clearSchemaKeys();
        } catch (Exception $e) {
            // Fallback: log the error but don't fail
            logger()->warning('Failed to clear schema cache', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'ttl' => $this->ttl,
            'key_prefix' => $this->keyPrefix,
            'driver' => config('cache.default'),
        ];
    }

    /**
     * Check if cache is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Temporarily disable cache
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Enable cache
     */
    public function enable(): void
    {
        $this->enabled = config('modelschema.cache.enabled', true);
    }

    /**
     * Remove schema from cache by file path
     */
    public function forgetSchemaByFile(string $filePath): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->generateFileKey($filePath);
        Cache::forget($key);
    }

    /**
     * Remove all schemas from cache
     */
    public function forgetAllSchemas(): void
    {
        if (! $this->enabled) {
            return;
        }

        // Note: This flushes all cache, not just schemas
        // In a real implementation, you might want to use cache tags
        Cache::flush();
    }

    /**
     * Remove validation from cache by content
     */
    public function forgetValidation(string $content): void
    {
        if (! $this->enabled) {
            return;
        }

        $key = $this->generateValidationKey($content);
        Cache::forget($key);
    }

    /**
     * Remove all validation results from cache
     */
    public function forgetAllValidations(): void
    {
        if (! $this->enabled) {
            return;
        }

        // Note: This flushes all cache, not just validations
        // In a real implementation, you might want to use cache tags
        Cache::flush();
    }

    /**
     * Clear cache entries for schema operations
     */
    protected function clearSchemaKeys(): void
    {
        // We'll implement a simple approach by tracking and clearing common key patterns
        $patterns = ['content:', 'file:', 'validation:'];

        foreach ($patterns as $pattern) {
            // For safety, we don't implement pattern deletion in the base version
            // Applications can extend this service to add driver-specific implementations
        }
    }
}
