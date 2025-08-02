<?php

declare(strict_types=1);

/**
 * Implementation of the missing API methods from the todo list
 *
 * These methods provide validation, introspection, and element extraction
 * functionality as requested in the todo list.
 */

namespace Grazulex\LaravelModelschema\Services;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class SchemaApiExtensions
{
    private SchemaService $schemaService;

    private GenerationService $generationService;

    public function __construct(
        ?SchemaService $schemaService = null,
        ?GenerationService $generationService = null
    ) {
        $this->schemaService = $schemaService ?? new SchemaService();
        $this->generationService = $generationService ?? new GenerationService();
    }

    /**
     * Validate YAML and return result in JSON/PHP format
     *
     * @param  string  $yamlContent  The YAML content to validate
     * @param  string  $format  'json' or 'php' (default: 'php')
     * @return string|array Validation result in requested format
     */
    public function validateYamlAndReturnResult(string $yamlContent, string $format = 'php')
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'core_data' => null,
            'extensions' => [],
            'validation_timestamp' => now()->toISOString(),
        ];

        try {
            // Parse and separate schema
            $separated = $this->schemaService->parseAndSeparateSchema($yamlContent);
            $result['core_data'] = $separated['core'];
            $result['extensions'] = array_keys($separated['extensions']);

            // Validate core schema
            $errors = $this->schemaService->validateCoreSchema($yamlContent);
            if (! empty($errors)) {
                $result['valid'] = false;
                $result['errors'] = $errors;
            }

            // Add warnings for common issues
            $warnings = $this->analyzeSchemaForWarnings($separated['core']);
            if (! empty($warnings)) {
                $result['warnings'] = $warnings;
            }

        } catch (Exception $e) {
            $result['valid'] = false;
            $result['errors'] = ['YAML parsing error: '.$e->getMessage()];
        }

        return $format === 'json' ? json_encode($result, JSON_PRETTY_PRINT) : $result;
    }

    /**
     * List all elements of a YAML and return result in JSON/PHP format
     *
     * @param  string  $yamlContent  The YAML content to analyze
     * @param  string  $format  'json' or 'php' (default: 'php')
     * @return string|array List of all elements in requested format
     */
    public function listYamlElements(string $yamlContent, string $format = 'php')
    {
        $result = [
            'schema_info' => [
                'has_core' => false,
                'model_name' => null,
                'table_name' => null,
            ],
            'core_elements' => [
                'fields' => [],
                'relations' => [],
                'options' => [],
            ],
            'extensions' => [],
            'generators_available' => [],
            'analysis_timestamp' => now()->toISOString(),
        ];

        try {
            $separated = $this->schemaService->parseAndSeparateSchema($yamlContent);

            if (isset($separated['core'])) {
                $result['schema_info']['has_core'] = true;
                $coreData = $separated['core'];

                // Basic schema info
                $result['schema_info']['model_name'] = $coreData['model'] ?? null;
                $result['schema_info']['table_name'] = $coreData['table'] ?? null;

                // List fields with details
                if (isset($coreData['fields'])) {
                    foreach ($coreData['fields'] as $fieldName => $fieldConfig) {
                        $result['core_elements']['fields'][] = [
                            'name' => $fieldName,
                            'type' => $fieldConfig['type'] ?? 'unknown',
                            'nullable' => $fieldConfig['nullable'] ?? true,
                            'unique' => $fieldConfig['unique'] ?? false,
                            'has_rules' => isset($fieldConfig['rules']),
                            'has_default' => isset($fieldConfig['default']),
                        ];
                    }
                }

                // List relations with details
                if (isset($coreData['relations'])) {
                    foreach ($coreData['relations'] as $relationName => $relationConfig) {
                        $result['core_elements']['relations'][] = [
                            'name' => $relationName,
                            'type' => $relationConfig['type'] ?? 'unknown',
                            'model' => $relationConfig['model'] ?? null,
                            'foreign_key' => $relationConfig['foreign_key'] ?? null,
                            'pivot_table' => $relationConfig['pivot_table'] ?? null,
                        ];
                    }
                }

                // List options
                if (isset($coreData['options'])) {
                    $result['core_elements']['options'] = $coreData['options'];
                }
            }

            // List extensions
            foreach ($separated['extensions'] as $extensionName => $extensionData) {
                $result['extensions'][] = [
                    'name' => $extensionName,
                    'keys' => array_keys($extensionData),
                    'has_data' => ! empty($extensionData),
                ];
            }

            // List available generators
            $result['generators_available'] = $this->generationService->getAvailableGenerators();

        } catch (Exception $e) {
            $result['error'] = 'Error analyzing YAML: '.$e->getMessage();
        }

        return $format === 'json' ? json_encode($result, JSON_PRETTY_PRINT) : $result;
    }

    /**
     * Return a specific element (model, migration, resource, etc.) in its final format
     *
     * @param  string  $yamlContent  The YAML content
     * @param  string  $elementType  The type of element to extract (model, migration, requests, resources, factory, seeder)
     * @param  string  $format  'json' or 'yaml' (default: 'json')
     * @return string|array The element in its final format
     *
     * @throws InvalidArgumentException If element type is not supported
     */
    public function getElementInFinalFormat(string $yamlContent, string $elementType, string $format = 'json')
    {
        $supportedElements = ['model', 'migration', 'requests', 'resources', 'factory', 'seeder'];

        if (! in_array($elementType, $supportedElements)) {
            throw new InvalidArgumentException(
                "Unsupported element type: {$elementType}. Supported types: ".implode(', ', $supportedElements)
            );
        }

        try {
            // Get all generation data
            $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($yamlContent);

            if (! isset($generationData['generation_data'][$elementType])) {
                throw new InvalidArgumentException("Element type '{$elementType}' not found in generation data");
            }

            $elementData = $generationData['generation_data'][$elementType];

            // Return in requested format
            if ($format === 'yaml') {
                return $elementData['yaml'];
            }
            // Return JSON format with additional metadata
            $jsonData = json_decode($elementData['json'], true);

            // Add metadata about the generation
            return [
                'element_type' => $elementType,
                'generated_at' => now()->toISOString(),
                'format' => $format,
                'data' => $jsonData,
                'metadata' => [
                    'generator_used' => $elementType,
                    'source_yaml_valid' => empty($this->schemaService->validateCoreSchema($yamlContent)),
                    'available_formats' => ['json', 'yaml'],
                ],
            ];

        } catch (Exception $e) {
            throw new RuntimeException("Error extracting element '{$elementType}': ".$e->getMessage());
        }
    }

    /**
     * Get validation summary for a YAML schema
     *
     * @param  string  $yamlContent  The YAML content to validate
     * @return array Comprehensive validation summary
     */
    public function getValidationSummary(string $yamlContent): array
    {
        $summary = [
            'overall_status' => 'unknown',
            'core_validation' => [
                'valid' => false,
                'errors' => [],
                'required_fields_present' => [],
                'optional_fields_present' => [],
            ],
            'structure_analysis' => [
                'has_core_section' => false,
                'has_extensions' => false,
                'extension_count' => 0,
                'field_count' => 0,
                'relation_count' => 0,
            ],
            'generation_readiness' => [
                'can_generate_model' => false,
                'can_generate_migration' => false,
                'can_generate_all' => false,
                'generators_available' => [],
            ],
            'recommendations' => [],
        ];

        try {
            // Parse and analyze structure
            $separated = $this->schemaService->parseAndSeparateSchema($yamlContent);

            $summary['structure_analysis']['has_core_section'] = isset($separated['core']);
            $summary['structure_analysis']['has_extensions'] = ! empty($separated['extensions']);
            $summary['structure_analysis']['extension_count'] = count($separated['extensions']);

            if (isset($separated['core'])) {
                $coreData = $separated['core'];
                $summary['structure_analysis']['field_count'] = count($coreData['fields'] ?? []);
                $summary['structure_analysis']['relation_count'] = count($coreData['relations'] ?? []);

                // Check required fields
                $requiredFields = ['model', 'table', 'fields'];
                foreach ($requiredFields as $field) {
                    if (isset($coreData[$field])) {
                        $summary['core_validation']['required_fields_present'][] = $field;
                    }
                }

                // Check optional fields
                $optionalFields = ['relations', 'options'];
                foreach ($optionalFields as $field) {
                    if (isset($coreData[$field])) {
                        $summary['core_validation']['optional_fields_present'][] = $field;
                    }
                }
            }

            // Validate core
            $errors = $this->schemaService->validateCoreSchema($yamlContent);
            $summary['core_validation']['valid'] = empty($errors);
            $summary['core_validation']['errors'] = $errors;

            // Check generation readiness
            if ($summary['core_validation']['valid']) {
                try {
                    $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($yamlContent);
                    $summary['generation_readiness']['generators_available'] = array_keys($generationData['generation_data']);
                    $summary['generation_readiness']['can_generate_model'] = isset($generationData['generation_data']['model']);
                    $summary['generation_readiness']['can_generate_migration'] = isset($generationData['generation_data']['migration']);
                    $summary['generation_readiness']['can_generate_all'] = count($generationData['generation_data']) >= 6;
                } catch (Exception $e) {
                    $summary['recommendations'][] = 'Generation test failed: '.$e->getMessage();
                }
            }

            // Set overall status
            if ($summary['core_validation']['valid'] && $summary['generation_readiness']['can_generate_all']) {
                $summary['overall_status'] = 'excellent';
            } elseif ($summary['core_validation']['valid']) {
                $summary['overall_status'] = 'good';
            } elseif ($summary['structure_analysis']['has_core_section']) {
                $summary['overall_status'] = 'needs_fixes';
            } else {
                $summary['overall_status'] = 'invalid';
            }

            // Add recommendations
            if (! $summary['structure_analysis']['has_core_section']) {
                $summary['recommendations'][] = 'Add a "core" section to your YAML schema';
            }

            if ($summary['structure_analysis']['field_count'] === 0) {
                $summary['recommendations'][] = 'Define at least one field in your schema';
            }

            if (! empty($errors)) {
                $summary['recommendations'][] = 'Fix validation errors: '.implode(', ', $errors);
            }

        } catch (Exception $e) {
            $summary['overall_status'] = 'error';
            $summary['error'] = $e->getMessage();
        }

        return $summary;
    }

    /**
     * Analyze schema for potential warnings
     *
     * @param  array  $coreData  Core schema data
     * @return array List of warnings
     */
    private function analyzeSchemaForWarnings(array $coreData): array
    {
        $warnings = [];

        // Check for missing timestamps
        if (! isset($coreData['options']['timestamps']) || ! $coreData['options']['timestamps']) {
            $warnings[] = 'Timestamps are disabled - consider enabling for better data tracking';
        }

        // Check for fields without validation rules
        if (isset($coreData['fields'])) {
            $fieldsWithoutRules = [];
            foreach ($coreData['fields'] as $fieldName => $fieldConfig) {
                if (! isset($fieldConfig['rules']) || empty($fieldConfig['rules'])) {
                    $fieldsWithoutRules[] = $fieldName;
                }
            }
            if (! empty($fieldsWithoutRules)) {
                $warnings[] = 'Fields without validation rules: '.implode(', ', $fieldsWithoutRules);
            }
        }

        // Check for relations without proper models
        if (isset($coreData['relations'])) {
            foreach ($coreData['relations'] as $relationName => $relationConfig) {
                if (! isset($relationConfig['model']) || empty($relationConfig['model'])) {
                    $warnings[] = "Relation '{$relationName}' missing target model";
                }
            }
        }

        return $warnings;
    }
}

/**
 * Example usage of the new API methods
 */

// Example usage
if (class_exists('Grazulex\LaravelModelschema\Services\SchemaService')) {
    $api = new SchemaApiExtensions();

    $yamlContent = <<<YAML
core:
  model: User
  table: users
  fields:
    name:
      type: string
      nullable: false
      rules: ['required', 'string', 'max:255']
    email:
      type: string
      unique: true
      rules: ['required', 'email', 'unique:users']
  relations:
    posts:
      type: hasMany
      model: App\Models\Post
  options:
    timestamps: true
    soft_deletes: false

turbomaker:
  views: ['index', 'create']
  routes: ['web', 'api']
YAML;

    // 1. Validate YAML and return result
    echo "=== Validation Result ===\n";
    $validationResult = $api->validateYamlAndReturnResult($yamlContent, 'json');
    echo $validationResult."\n\n";

    // 2. List all elements
    echo "=== YAML Elements ===\n";
    $elements = $api->listYamlElements($yamlContent, 'json');
    echo $elements."\n\n";

    // 3. Get specific element in final format
    echo "=== Model Element ===\n";
    try {
        $modelElement = $api->getElementInFinalFormat($yamlContent, 'model', 'json');
        echo json_encode($modelElement, JSON_PRETTY_PRINT)."\n\n";
    } catch (Exception $e) {
        echo 'Error: '.$e->getMessage()."\n\n";
    }

    // 4. Get validation summary
    echo "=== Validation Summary ===\n";
    $summary = $api->getValidationSummary($yamlContent);
    echo json_encode($summary, JSON_PRETTY_PRINT)."\n";
}
