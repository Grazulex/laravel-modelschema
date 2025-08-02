<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Contracts\GeneratorInterface;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Schema\ModelSchema;

/**
 * Abstract base class for all generators
 * Provides common functionality and enforces structure
 */
abstract class AbstractGenerator implements GeneratorInterface
{
    protected string $stubsPath;

    public function __construct()
    {
        $this->stubsPath = __DIR__.'/../../../stubs/generators';
    }

    /**
     * Generate content for a specific format
     */
    abstract protected function generateFormat(ModelSchema $schema, string $format, array $options): string;

    /**
     * Generate files based on the schema
     */
    public function generate(ModelSchema $schema, array $options = []): array
    {
        $result = [
            'metadata' => [
                'generator' => $this->getGeneratorName(),
                'model_name' => $schema->name,
                'table_name' => $schema->table,
                'generated_at' => now()->toISOString(),
                'options' => $options,
            ],
        ];

        // Generate each available format
        foreach ($this->getAvailableFormats() as $format) {
            $result[$format] = $this->generateFormat($schema, $format, $options);
        }

        return $result;
    }

    /**
     * Get the stub content for this generator
     */
    public function getStubContent(string $outputFormat = 'php'): string
    {
        $stubFile = $this->getStubPath($outputFormat);

        if (! file_exists($stubFile)) {
            throw new SchemaException("Stub file not found: {$stubFile}");
        }

        return file_get_contents($stubFile);
    }

    /**
     * Get the full path to stub file
     */
    protected function getStubPath(string $format): string
    {
        $generatorName = mb_strtolower($this->getGeneratorName());

        return $this->stubsPath."/{$generatorName}.{$format}.stub";
    }

    /**
     * Process stub content with replacements
     */
    protected function processStub(string $stubContent, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $stubContent = str_replace($placeholder, $value, $stubContent);
        }

        return $stubContent;
    }

    /**
     * Get common replacements for all generators
     */
    protected function getCommonReplacements(ModelSchema $schema, array $options = []): array
    {
        return [
            '{{MODEL_NAME}}' => $schema->name,
            '{{MODEL_NAME_LOWER}}' => mb_strtolower($schema->name),
            '{{MODEL_NAME_SNAKE}}' => \Illuminate\Support\Str::snake($schema->name),
            '{{MODEL_NAME_KEBAB}}' => \Illuminate\Support\Str::kebab($schema->name),
            '{{MODEL_NAME_PLURAL}}' => \Illuminate\Support\Str::plural($schema->name),
            '{{MODEL_NAME_PLURAL_LOWER}}' => mb_strtolower(\Illuminate\Support\Str::plural($schema->name)),
            '{{TABLE_NAME}}' => $schema->table,
            '{{NAMESPACE}}' => $schema->getModelNamespace(),
            '{{GENERATED_AT}}' => now()->format('Y-m-d H:i:s'),
            '{{FILLABLE_FIELDS}}' => $this->formatFieldsList(array_keys($schema->getFillableFields())),
            '{{CASTS}}' => $this->formatCasts($schema->getCastableFields()),
            '{{HAS_TIMESTAMPS}}' => $schema->hasTimestamps() ? 'true' : 'false',
            '{{HAS_SOFT_DELETES}}' => $schema->hasSoftDeletes() ? 'true' : 'false',
        ];
    }

    /**
     * Format fields list for PHP array
     */
    protected function formatFieldsList(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        $formatted = array_map(fn ($field): string => "'{$field}'", $fields);

        return implode(",\n        ", $formatted);
    }

    /**
     * Format casts array for PHP
     */
    protected function formatCasts(array $casts): string
    {
        if ($casts === []) {
            return '';
        }

        $formatted = [];
        foreach ($casts as $field => $cast) {
            $formatted[] = "'{$field}' => '{$cast}'";
        }

        return implode(",\n        ", $formatted);
    }

    /**
     * Format relationships for PHP
     */
    protected function formatRelationships(ModelSchema $schema): string
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            $method = $this->generateRelationshipMethod($relationship);
            $relationships[] = $method;
        }

        return implode("\n\n", $relationships);
    }

    /**
     * Generate relationship method code
     */
    protected function generateRelationshipMethod($relationship): string
    {
        $methodName = $relationship->name;
        $relationType = $relationship->type;
        $relatedModel = $relationship->model ?? 'RelatedModel';

        $method = "    /**\n";
        $method .= "     * {$relationType} relationship\n";
        $method .= "     */\n";
        $method .= "    public function {$methodName}()\n";
        $method .= "    {\n";
        $method .= "        return \$this->{$relationType}({$relatedModel}::class";

        // Add additional parameters based on relationship type
        if (isset($relationship->foreignKey)) {
            $method .= ", '{$relationship->foreignKey}'";
        }

        if (isset($relationship->localKey)) {
            $method .= ", '{$relationship->localKey}'";
        }

        $method .= ");\n";

        return $method.'    }';
    }

    /**
     * Convert array to JSON format
     */
    protected function toJsonFormat(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
