<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Contracts;

use Grazulex\LaravelModelschema\Schema\ModelSchema;

/**
 * Contract for all file generators
 * Each generator must implement this interface to ensure consistency
 */
interface GeneratorInterface
{
    /**
     * Generate files based on the schema
     * Returns array with 'php', 'json', and 'metadata' keys
     */
    public function generate(ModelSchema $schema, array $options = []): array;

    /**
     * Get the stub content for this generator
     */
    public function getStubContent(string $outputFormat = 'php'): string;

    /**
     * Get available output formats for this generator
     */
    public function getAvailableFormats(): array;

    /**
     * Get the name/type of this generator
     */
    public function getGeneratorName(): string;
}
