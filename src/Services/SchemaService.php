<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Exception;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Core schema service for YAML management
 * This service handles the basic operations that other packages can extend
 */
class SchemaService
{
    protected SchemaCacheService $cache;

    protected LoggingService $logger;

    protected EnhancedValidationService $enhancedValidator;

    protected AutoValidationService $autoValidator;

    public function __construct(
        protected Filesystem $filesystem = new Filesystem(),
        ?SchemaCacheService $cache = null,
        ?LoggingService $logger = null,
        ?EnhancedValidationService $enhancedValidator = null,
        ?AutoValidationService $autoValidator = null
    ) {
        $this->cache = $cache ?? new SchemaCacheService();
        $this->logger = $logger ?? new LoggingService();
        $this->enhancedValidator = $enhancedValidator ?? new EnhancedValidationService();
        $this->autoValidator = $autoValidator ?? new AutoValidationService(new FieldTypePluginManager());
    }

    /**
     * Parse a YAML file into a ModelSchema
     * Core functionality that other packages can rely on
     */
    public function parseYamlFile(string $filePath): ModelSchema
    {
        $this->logger->logOperationStart('parseYamlFile', ['file' => $filePath]);

        if (! $this->filesystem->exists($filePath)) {
            $this->logger->logError("File not found: {$filePath}");
            throw SchemaException::fileNotFound($filePath);
        }

        try {
            // Try to get from cache first
            $startTime = microtime(true);
            $cached = $this->cache->getSchemaByFile($filePath);
            $cacheTime = microtime(true) - $startTime;

            if ($cached instanceof ModelSchema) {
                $this->logger->logCache('hit', $filePath, true, $cacheTime);
                $this->logger->logOperationEnd('parseYamlFile', [
                    'source' => 'cache',
                    'cache_time_ms' => round($cacheTime * 1000, 2),
                ]);

                return $cached;
            }

            $this->logger->logCache('miss', $filePath, false, $cacheTime);

            // Parse and cache the result
            $parseStart = microtime(true);
            $schema = ModelSchema::fromYamlFile($filePath);
            $parseTime = microtime(true) - $parseStart;

            $this->logger->logYamlParsing($filePath, true, [
                'parse_time_ms' => round($parseTime * 1000, 2),
                'field_count' => count($schema->getAllFields()),
                'relationship_count' => count($schema->getRelationships()),
                'file_size' => $this->filesystem->size($filePath),
            ]);

            // Cache the result
            $cacheStoreStart = microtime(true);
            $this->cache->putSchemaByFile($filePath, $schema);
            $cacheStoreTime = microtime(true) - $cacheStoreStart;

            $this->logger->logCache('store', $filePath, false, $cacheStoreTime);

            // Check performance thresholds
            $parseTimeMs = $parseTime * 1000;
            $threshold = config('modelschema.logging.performance_thresholds.yaml_parsing_ms', 1000);
            if ($parseTimeMs > $threshold) {
                $this->logger->logWarning(
                    'YAML parsing exceeded threshold',
                    [
                        'file' => $filePath,
                        'parse_time_ms' => round($parseTimeMs, 2),
                        'threshold_ms' => $threshold,
                    ],
                    'Consider optimizing the YAML structure or enabling caching'
                );
            }

            $this->logger->logOperationEnd('parseYamlFile', [
                'source' => 'parse',
                'parse_time_ms' => round($parseTime * 1000, 2),
                'cache_store_time_ms' => round($cacheStoreTime * 1000, 2),
                'field_count' => count($schema->getAllFields()),
                'relationship_count' => count($schema->getRelationships()),
            ]);

            return $schema;

        } catch (Exception $e) {
            $this->logger->logError(
                "Failed to parse YAML file: {$filePath}",
                $e,
                ['file' => $filePath]
            );
            throw $e;
        }
    }

    /**
     * Parse YAML content into a ModelSchema
     * Core functionality for parsing YAML strings
     */
    public function parseYamlContent(string $yamlContent, string $modelName = 'UnknownModel'): ModelSchema
    {
        // Try to get from cache first
        $cached = $this->cache->getSchemaByContent($yamlContent, $modelName);
        if ($cached instanceof ModelSchema) {
            return $cached;
        }

        // Parse and cache the result
        $schema = ModelSchema::fromYaml($yamlContent, $modelName);
        $this->cache->putSchemaByContent($yamlContent, $schema, $modelName);

        return $schema;
    }

    /**
     * Validate a ModelSchema against core rules
     * Returns array of validation errors (empty if valid)
     */
    public function validateSchema(ModelSchema $schema): array
    {
        $this->logger->logOperationStart('validateSchema', [
            'schema_name' => $schema->name,
            'field_count' => count($schema->getAllFields()),
            'relationship_count' => count($schema->getRelationships()),
        ]);

        $startTime = microtime(true);
        $errors = [];

        // Core validations that all packages should respect
        if ($schema->fields === []) {
            $errors[] = "Schema '{$schema->name}' must have at least one field";
        }

        // Validate field types exist in registry
        foreach ($schema->fields as $field) {
            if (! $this->isValidFieldType($field->type)) {
                $errors[] = "Unknown field type '{$field->type}' in field '{$field->name}'";
            }
        }

        // Validate relationship types
        foreach ($schema->relationships as $relationship) {
            if (! $this->isValidRelationshipType($relationship->type)) {
                $errors[] = "Unknown relationship type '{$relationship->type}' in relationship '{$relationship->name}'";
            }
        }

        $validationTime = microtime(true) - $startTime;
        $success = $errors === [];

        // Log validation results
        $this->logger->logValidation('schema', $success, $errors, [], [
            'field_count' => count($schema->getAllFields()),
            'relationship_count' => count($schema->getRelationships()),
            'validation_time_ms' => round($validationTime * 1000, 2),
        ]);

        // Check performance threshold
        $validationTimeMs = $validationTime * 1000;
        $threshold = config('modelschema.logging.performance_thresholds.validation_ms', 2000);
        if ($validationTimeMs > $threshold) {
            $this->logger->logWarning(
                'Schema validation exceeded threshold',
                [
                    'schema_name' => $schema->name,
                    'validation_time_ms' => round($validationTimeMs, 2),
                    'threshold_ms' => $threshold,
                ],
                'Consider optimizing validation rules or schema complexity'
            );
        }

        $this->logger->logOperationEnd('validateSchema', [
            'success' => $success,
            'error_count' => count($errors),
            'validation_time_ms' => round($validationTime * 1000, 2),
        ]);

        return $errors;
    }

    /**
     * Get the core schema structure (fields that this package manages)
     * Other packages can use this to know what's handled by the core
     */
    public function getCoreSchemaKeys(): array
    {
        return [
            'model',      // Model name
            'table',      // Table name
            'fields',     // Field definitions
            'relationships', // Relationship definitions (also 'relations')
            'options',    // Core options (timestamps, soft_deletes)
            'metadata',   // Metadata information
        ];
    }

    /**
     * Extract core schema data from full YAML data
     * Supports both old format (flat) and new format (with 'core' key)
     */
    public function extractCoreSchemaData(array $yamlData): array
    {
        // New format: check if data is wrapped in 'core' key
        if (isset($yamlData['core']) && is_array($yamlData['core'])) {
            return $yamlData['core'];
        }

        // Old format: extract core keys from flat structure
        $coreKeys = $this->getCoreSchemaKeys();
        $coreSchema = [];

        foreach ($coreKeys as $key) {
            if (isset($yamlData[$key])) {
                $coreSchema[$key] = $yamlData[$key];
            }
        }

        // Handle 'relations' alias for 'relationships'
        if (isset($yamlData['relations']) && ! isset($coreSchema['relationships'])) {
            $coreSchema['relationships'] = $yamlData['relations'];
        }

        return $coreSchema;
    }

    /**
     * Extract extension data (data not handled by core)
     * Supports both old format (flat) and new format (with 'core' key)
     * Other packages can use this to get their custom data
     */
    public function extractExtensionData(array $yamlData): array
    {
        // New format: everything except 'core' key is extension data
        if (isset($yamlData['core']) && is_array($yamlData['core'])) {
            $extensionData = $yamlData;
            unset($extensionData['core']);

            return $extensionData;
        }

        // Old format: exclude core keys from flat structure
        $coreKeys = $this->getCoreSchemaKeys();
        $coreKeys[] = 'relations'; // Also exclude the alias

        $extensionData = [];

        foreach ($yamlData as $key => $value) {
            if (! in_array($key, $coreKeys, true)) {
                $extensionData[$key] = $value;
            }
        }

        return $extensionData;
    }

    /**
     * Check if a field type is valid in the registry
     */
    public function isValidFieldType(string $type): bool
    {
        return \Grazulex\LaravelModelschema\Support\FieldTypeRegistry::has($type);
    }

    /**
     * Check if a relationship type is valid
     */
    public function isValidRelationshipType(string $type): bool
    {
        $validTypes = [
            'belongsTo',
            'hasOne',
            'hasMany',
            'belongsToMany',
            'hasOneThrough',
            'hasManyThrough',
            'morphTo',
            'morphOne',
            'morphMany',
            'morphToMany',
            'morphedByMany',
        ];

        return in_array($type, $validTypes, true);
    }

    /**
     * Get all supported field types from the registry
     */
    public function getSupportedFieldTypes(): array
    {
        return array_keys(\Grazulex\LaravelModelschema\Support\FieldTypeRegistry::all());
    }

    /**
     * Get all supported relationship types
     */
    public function getSupportedRelationshipTypes(): array
    {
        return [
            'belongsTo' => 'Belongs to a single related model',
            'hasOne' => 'Has one related model',
            'hasMany' => 'Has many related models',
            'belongsToMany' => 'Many-to-many relationship',
            'hasOneThrough' => 'Has one through intermediate model',
            'hasManyThrough' => 'Has many through intermediate model',
            'morphTo' => 'Polymorphic belongs to',
            'morphOne' => 'Polymorphic has one',
            'morphMany' => 'Polymorphic has many',
            'morphToMany' => 'Polymorphic many-to-many',
            'morphedByMany' => 'Inverse polymorphic many-to-many',
        ];
    }

    /**
     * Save a ModelSchema to a YAML file
     * Core functionality for writing schemas
     */
    public function saveSchemaToYaml(ModelSchema $schema, string $filePath): void
    {
        $directory = dirname($filePath);
        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        $yamlContent = $this->convertSchemaToYaml($schema);
        $this->filesystem->put($filePath, $yamlContent);
    }

    /**
     * Convert a ModelSchema to YAML string
     */
    public function convertSchemaToYaml(ModelSchema $schema): string
    {
        $data = $schema->toArray();

        return Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    // ==============================================
    // NEW API METHODS FOR PACKAGE INTEGRATION
    // ==============================================

    /**
     * Parse and separate core schema from complete YAML
     * This allows other packages to handle the complete YAML while we only process our part
     */
    public function parseAndSeparateSchema(string $yamlContent): array
    {
        try {
            $fullData = Yaml::parse($yamlContent);
        } catch (Exception $e) {
            throw new SchemaException('Invalid YAML content: '.$e->getMessage());
        }

        // Extract core data and create core schema
        $coreData = $this->extractCoreSchemaData($fullData);

        // Create ModelSchema from core data only
        $modelName = $coreData['model'] ?? $coreData['name'] ?? 'UnknownModel';
        $coreSchema = ModelSchema::fromArray($modelName, $coreData);

        // Extract extension data for other packages
        $extensionData = $this->extractExtensionData($fullData);

        return [
            'core_schema' => $coreSchema,
            'core_data' => $coreData,
            'extension_data' => $extensionData,
            'full_data' => $fullData,
        ];
    }

    /**
     * Validate only the core part of a complete YAML schema
     * Other packages can validate the complete YAML but only get our validation results
     */
    public function validateCoreSchema(string $yamlContent): array
    {
        $separated = $this->parseAndSeparateSchema($yamlContent);

        return $this->validateSchema($separated['core_schema']);
    }

    /**
     * Extract core content from complete YAML for file generation
     * Returns structured data needed for generating PHP files
     */
    public function extractCoreContentForGeneration(string $yamlContent): array
    {
        $separated = $this->parseAndSeparateSchema($yamlContent);
        $coreSchema = $separated['core_schema'];

        return [
            'schema' => $coreSchema,
            'model_name' => $coreSchema->name,
            'table_name' => $coreSchema->table,
            'fields' => $coreSchema->fields,
            'relationships' => $coreSchema->relationships,
            'all_fields' => $coreSchema->getAllFields(),
            'fillable_fields' => array_keys($coreSchema->getFillableFields()),
            'casts' => $coreSchema->getCastableFields(),
            'validation_rules' => $coreSchema->getValidationRules(),
            'has_timestamps' => $coreSchema->hasTimestamps(),
            'has_soft_deletes' => $coreSchema->hasSoftDeletes(),
            'model_namespace' => $coreSchema->getModelNamespace(),
            'model_class' => $coreSchema->getModelClass(),
        ];
    }

    /**
     * Get all available stubs/templates for file generation
     */
    public function getAvailableStubs(): array
    {
        $stubsPath = __DIR__.'/../../stubs';
        $stubs = [];

        if (! is_dir($stubsPath)) {
            return $stubs;
        }

        $files = scandir($stubsPath);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'stub') {
                $stubName = pathinfo($file, PATHINFO_FILENAME);
                $stubs[$stubName] = [
                    'name' => $stubName,
                    'file' => $file,
                    'path' => $stubsPath.'/'.$file,
                    'description' => $this->getStubDescription($stubsPath.'/'.$file),
                ];
            }
        }

        return $stubs;
    }

    /**
     * Get content of a specific stub for file generation
     */
    public function getStubContent(string $stubName): string
    {
        $stubsPath = __DIR__.'/../../stubs';
        $stubFile = $stubsPath.'/'.$stubName;

        if (! str_ends_with($stubFile, '.stub')) {
            $stubFile .= '.schema.stub';
        }

        if (! file_exists($stubFile)) {
            throw new SchemaException("Stub file not found: {$stubName}");
        }

        return file_get_contents($stubFile);
    }

    /**
     * Process stub content with model data and return core YAML
     */
    public function processStubForCore(string $stubName, array $replacements = []): string
    {
        $content = $this->getStubContent($stubName);

        // Default replacements for core functionality
        $defaultReplacements = [
            '{{MODEL_NAME}}' => $replacements['MODEL_NAME'] ?? 'SampleModel',
            '{{TABLE_NAME}}' => $replacements['TABLE_NAME'] ?? 'sample_models',
            '{{CREATED_AT}}' => $replacements['CREATED_AT'] ?? now()->format('Y-m-d H:i:s'),
            '{{NAMESPACE}}' => $replacements['NAMESPACE'] ?? 'App\\Models',
        ];

        $allReplacements = array_merge($defaultReplacements, $replacements);

        foreach ($allReplacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    /**
     * Wrap core schema data in the new "core" structure
     */
    public function wrapInCoreStructure(array $coreData, array $extensionData = []): array
    {
        $wrappedData = [
            'core' => $coreData,
        ];

        // Add extension data at root level
        foreach ($extensionData as $key => $value) {
            if ($key !== 'core') {
                $wrappedData[$key] = $value;
            }
        }

        return $wrappedData;
    }

    /**
     * Generate complete YAML for app from base stub and core schema
     * Cette fonction permet à l'app parent de recevoir un YAML de base
     * et de générer un YAML complet avec toutes les données
     */
    public function generateCompleteYamlFromStub(string $stubName, array $replacements = [], array $extensionData = []): string
    {
        // 1. Process the stub to get base YAML
        $baseYamlContent = $this->processStubForCore($stubName, $replacements);

        // 2. Parse the base YAML to get core data
        $separated = $this->parseAndSeparateSchema($baseYamlContent);
        $coreData = $separated['core_data'];

        // 3. Wrap in new structure and add extension data
        $completeData = $this->wrapInCoreStructure($coreData, $extensionData);

        // 4. Convert to YAML string
        return Yaml::dump($completeData, 4, 2);
    }

    /**
     * Merge core schema data with app-specific extension data
     * Permet à l'app parent de fusionner ses données avec notre core
     */
    public function mergeWithAppData(array $coreData, array $appData): array
    {
        // Wrap core data in structure
        $wrappedCore = $this->wrapInCoreStructure($coreData);

        // Merge with app data (app data has priority for non-core keys)
        $merged = array_merge($wrappedCore, $appData);

        // Ensure core remains intact if app accidentally overwrites it
        $merged['core'] = $coreData;

        return $merged;
    }

    /**
     * Extract and validate only our core part from complete app YAML
     * L'app parent peut envoyer son YAML complet et on valide juste notre partie
     */
    public function validateFromCompleteAppYaml(string $completeYaml): array
    {
        try {
            $fullData = Yaml::parse($completeYaml);
        } catch (Exception $e) {
            return ['Invalid YAML format: '.$e->getMessage()];
        }

        // Extract our core data
        $coreData = $this->extractCoreSchemaData($fullData);

        if ($coreData === []) {
            return ['No core schema data found in YAML'];
        }

        // Create and validate core schema
        $modelName = $coreData['model'] ?? $coreData['name'] ?? 'UnknownModel';
        $coreSchema = ModelSchema::fromArray($modelName, $coreData);

        return $this->validateSchema($coreSchema);
    }

    /**
     * Get structured generation data from complete app YAML
     * L'app parent peut envoyer son YAML et recevoir toutes nos données structurées
     * pour la génération de fichiers
     */
    public function getGenerationDataFromCompleteYaml(string $completeYaml): array
    {
        $separated = $this->parseAndSeparateSchema($completeYaml);
        $coreSchema = $separated['core_schema'];

        // Use GenerationService to get all generation data
        $generationService = new Generation\GenerationService();

        $generationData = [];

        // Get data for each generator type
        foreach (['model', 'migration', 'requests', 'resources', 'factory', 'seeder'] as $type) {
            try {
                $generator = $generationService->getGenerator($type);
                $result = $generator->generate($coreSchema);
                $generationData[$type] = $result;
            } catch (Exception $e) {
                $generationData[$type] = ['error' => $e->getMessage()];
            }
        }

        return [
            'core_schema' => $coreSchema,
            'core_data' => $separated['core_data'],
            'extension_data' => $separated['extension_data'],
            'generation_data' => $generationData,
        ];
    }

    /**
     * Get default stub content for app initialization
     * Provides a basic schema stub that apps can use as starting point
     */
    public function getDefaultStub(array $replacements = []): string
    {
        return $this->getStubContent('basic.schema.stub');
    }

    /**
     * Get processed default stub with replacements for immediate use
     * Apps can use this to get a ready-to-use YAML schema
     */
    public function getProcessedDefaultStub(array $replacements = []): string
    {
        return $this->processStubForCore('basic.schema.stub', $replacements);
    }

    /**
     * Get complete YAML from default stub for app integration
     * Returns full YAML structure that apps can directly use
     */
    public function getDefaultCompleteYaml(array $replacements = [], array $extensionData = []): string
    {
        return $this->generateCompleteYamlFromStub('basic.schema.stub', $replacements, $extensionData);
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Clear schema cache for a specific file
     */
    public function clearSchemaCache(string $filePath): void
    {
        $this->cache->forgetSchemaByFile($filePath);
    }

    /**
     * Clear all schema caches
     */
    public function clearAllSchemaCache(): void
    {
        $this->cache->forgetAllSchemas();
    }

    /**
     * Clear validation cache for specific content
     */
    public function clearValidationCache(string $content): void
    {
        $this->cache->forgetValidation($content);
    }

    /**
     * Clear all validation caches
     */
    public function clearAllValidationCache(): void
    {
        $this->cache->forgetAllValidations();
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        return [
            'enabled' => true,
            'service' => 'SchemaCacheService',
            'driver' => 'Laravel Cache',
            'default_ttl' => 3600,
        ];
    }

    /**
     * Validate custom field types in schemas
     */
    public function validateCustomFieldTypes(array $schemas): array
    {
        $this->logger->logOperationStart('validateCustomFieldTypes', [
            'schema_count' => count($schemas),
            'total_fields' => $this->countFieldsInSchemas($schemas),
        ]);

        $startTime = microtime(true);

        $result = $this->enhancedValidator->validateCustomFieldTypes($schemas);

        $validationTime = microtime(true) - $startTime;

        // Log the validation results
        $this->logger->logValidation('custom_field_types', $result['is_valid'], $result['errors'], $result['warnings'], [
            'schema_count' => count($schemas),
            'total_fields_validated' => $result['validation_summary']['total_fields_validated'],
            'custom_fields_found' => $result['validation_summary']['custom_fields_found'],
            'unique_custom_types' => $result['validation_summary']['unique_custom_types'],
            'custom_types_used' => array_keys($result['custom_type_stats']),
            'validation_time_ms' => round($validationTime * 1000, 2),
        ]);

        // Check performance threshold
        $validationTimeMs = $validationTime * 1000;
        $threshold = config('modelschema.logging.performance_thresholds.validation_ms', 2000);
        if ($validationTimeMs > $threshold) {
            $this->logger->logPerformance(
                'custom_field_types_validation',
                [
                    'execution_time_ms' => $validationTimeMs,
                    'schema_count' => count($schemas),
                    'fields_validated' => $result['validation_summary']['total_fields_validated'],
                    'threshold_ms' => $threshold,
                    'recommendation' => 'Consider optimizing custom field type validation or reducing schema complexity',
                ]
            );
        }

        $this->logger->logOperationEnd('validateCustomFieldTypes', [
            'success' => $result['is_valid'],
            'error_count' => count($result['errors']),
            'warning_count' => count($result['warnings']),
            'custom_fields_found' => $result['validation_summary']['custom_fields_found'],
            'validation_time_ms' => round($validationTime * 1000, 2),
        ]);

        return $result;
    }

    /**
     * Validate custom field types for a single schema file
     */
    public function validateCustomFieldTypesFromFile(string $filePath): array
    {
        $schema = $this->parseYamlFile($filePath);

        return $this->validateCustomFieldTypes([$schema]);
    }

    /**
     * Validate custom field types for multiple schema files
     */
    public function validateCustomFieldTypesFromFiles(array $filePaths): array
    {
        $schemas = [];

        foreach ($filePaths as $filePath) {
            try {
                $schemas[] = $this->parseYamlFile($filePath);
            } catch (Exception $e) {
                $this->logger->logError("Failed to parse schema file: {$filePath}", $e, [
                    'file' => $filePath,
                ]);
                throw $e;
            }
        }

        return $this->validateCustomFieldTypes($schemas);
    }

    /**
     * Generate Laravel validation rules for a schema automatically
     * Based on field types and custom attributes
     */
    public function generateValidationRules(ModelSchema $schema): array
    {
        $this->logger->logOperationStart('generateValidationRules', [
            'model' => $schema->name,
            'table' => $schema->table,
            'field_count' => count($schema->getAllFields()),
        ]);

        try {
            $rules = $this->autoValidator->generateValidationRules($schema);

            $this->logger->logOperationEnd('generateValidationRules', [
                'rules_count' => count($rules),
                'fields_with_rules' => array_keys($rules),
            ]);

            return $rules;
        } catch (Exception $e) {
            $this->logger->logError('Failed to generate validation rules: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate Laravel validation rules from YAML content
     */
    public function generateValidationRulesFromYaml(string $yamlContent, string $modelName = 'UnknownModel'): array
    {
        $schema = $this->parseYamlContent($yamlContent, $modelName);

        return $this->generateValidationRules($schema);
    }

    /**
     * Generate Laravel validation rules from YAML file
     */
    public function generateValidationRulesFromFile(string $filePath): array
    {
        $schema = $this->parseYamlFile($filePath);

        return $this->generateValidationRules($schema);
    }

    /**
     * Generate validation rules in Laravel request format
     */
    public function generateValidationRulesForRequest(ModelSchema $schema, string $format = 'array'): array|string
    {
        return $this->autoValidator->generateValidationRulesForRequest($schema, $format);
    }

    /**
     * Generate user-friendly validation messages
     */
    public function generateValidationMessages(ModelSchema $schema): array
    {
        return $this->autoValidator->generateValidationMessages($schema);
    }

    /**
     * Generate complete validation configuration (rules + messages)
     */
    public function generateValidationConfig(ModelSchema $schema): array
    {
        return [
            'rules' => $this->generateValidationRules($schema),
            'messages' => $this->generateValidationMessages($schema),
            'attributes' => $this->generateAttributeNames($schema),
        ];
    }

    /**
     * Extract description from stub file comments
     */
    protected function getStubDescription(string $stubPath): string
    {
        if (! file_exists($stubPath)) {
            return 'No description available';
        }

        $content = file_get_contents($stubPath);
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if (str_starts_with(mb_trim($line), '#') && ! str_starts_with(mb_trim($line), '# Generated:')) {
                return mb_trim(mb_substr(mb_trim($line), 1));
            }
        }

        return 'Template stub';
    }

    /**
     * Count total fields across schemas
     */
    private function countFieldsInSchemas(array $schemas): int
    {
        $total = 0;
        foreach ($schemas as $schema) {
            if (is_object($schema) && property_exists($schema, 'fields')) {
                $total += count($schema->fields);
            }
        }

        return $total;
    }

    /**
     * Generate human-readable attribute names for validation
     */
    private function generateAttributeNames(ModelSchema $schema): array
    {
        $attributes = [];

        foreach ($schema->getAllFields() as $field) {
            $displayName = ucwords(str_replace('_', ' ', $field->name));
            $attributes[$field->name] = $displayName;
        }

        return $attributes;
    }
}
