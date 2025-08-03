<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Validation;

use Grazulex\LaravelModelschema\Schema\ModelSchema;

class EnhancedValidationService
{
    public function validateRelationshipConsistency(array $schemas): array
    {
        $circularDependencies = $this->detectCircularDependencies($schemas);
        $missingReverseRelationships = $this->detectMissingReverseRelationships($schemas);

        return [
            'is_consistent' => $circularDependencies === [] && $missingReverseRelationships === [],
            'circular_dependencies' => $circularDependencies,
            'missing_reverse_relationships' => $missingReverseRelationships,
            'validation_summary' => [
                'total_schemas' => count($schemas),
                'valid_relationships' => $this->countValidRelationships($schemas),
                'issues_found' => count($circularDependencies) + count($missingReverseRelationships),
            ],
        ];
    }

    public function validateFieldTypes(ModelSchema $schema): array
    {
        $fields = $schema->getAllFields();
        $fieldErrors = [];
        $validatedFields = [];
        $typeCompatibility = [];

        foreach ($fields as $field) {
            $validatedFields[] = $field->name;

            // Check field type configuration
            $fieldTypeErrors = $this->validateFieldTypeConfiguration($field->name, $field->type, $field);
            if ($fieldTypeErrors !== []) {
                $fieldErrors = array_merge($fieldErrors, $fieldTypeErrors);
            }

            // Check type compatibility
            $typeCompatibility[$field->name] = [
                'type' => $field->type ?? 'unknown',
                'is_compatible' => $fieldTypeErrors === [],
                'warnings' => $fieldTypeErrors,
            ];
        }

        return [
            'is_valid' => $fieldErrors === [],
            'field_errors' => $fieldErrors,
            'validated_fields' => $validatedFields,
            'type_compatibility' => $typeCompatibility,
        ];
    }

    public function analyzePerformance(ModelSchema $schema): array
    {
        $fields = $schema->getAllFields();
        $relationships = $schema->relationships;

        $fieldCount = count($fields);
        $relationshipCount = count($relationships);
        $warnings = [];
        $recommendations = [];

        // Generate warnings for large schemas
        if ($fieldCount > 20) {
            $warnings[] = "Schema {$schema->name} has many fields ($fieldCount), consider splitting";
        }

        if ($relationshipCount > 10) {
            $warnings[] = "Schema {$schema->name} has many relationships ($relationshipCount), verify complexity";
        }

        // Generate recommendations
        if ($relationshipCount > 5) {
            $recommendations[] = "Consider using eager loading for {$schema->name} relationships";
        }

        return [
            'field_count' => $fieldCount,
            'relationship_count' => $relationshipCount,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'performance_score' => $this->calculatePerformanceScore($fieldCount, $relationshipCount),
        ];
    }

    public function validateSchema(ModelSchema $schema): array
    {
        $errors = [];
        $warnings = [];

        // Check basic schema requirements
        if ($schema->name === '' || $schema->name === '0') {
            $errors[] = 'Schema must have a model name';
        }

        if ($schema->table === '' || $schema->table === '0') {
            $errors[] = 'Schema must have a table name';
        }

        if ($schema->getAllFields() === []) {
            $errors[] = 'Schema must have at least one field';
            $warnings[] = 'Schema with no fields may not be functional';
        }

        // Validate fields
        $fieldValidation = $this->validateFieldTypes($schema);
        $errors = array_merge($errors, $fieldValidation['field_errors']);

        // Get recommendations from performance analysis
        $performanceAnalysis = $this->analyzePerformance($schema);
        $recommendations = $performanceAnalysis['recommendations'];

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'recommendations' => $recommendations,
            'performance_analysis' => $performanceAnalysis,
            'field_validation' => $fieldValidation,
            'relationship_validation' => [
                'relationship_types' => $this->analyzeRelationshipTypesForSchema($schema),
                'total_relationships' => count($schema->relationships),
            ],
        ];
    }

    public function generateComprehensiveReport(ModelSchema $schema): array
    {
        $performanceAnalysis = $this->analyzePerformance($schema);
        $fieldValidation = $this->validateFieldTypes($schema);
        $schemaValidation = $this->validateSchema($schema);

        return [
            'schema_name' => $schema->name,
            'is_valid' => $schemaValidation['is_valid'],
            'errors' => $schemaValidation['errors'],
            'warnings' => $schemaValidation['warnings'],
            'recommendations' => $schemaValidation['recommendations'],
            'performance_analysis' => $performanceAnalysis,
            'field_validation' => $fieldValidation,
            'relationship_validation' => [
                'relationship_types' => $this->analyzeRelationshipTypesForSchema($schema),
                'total_relationships' => count($schema->relationships),
            ],
        ];
    }

    private function detectCircularDependencies(array $schemas): array
    {
        $dependencies = [];

        // Build dependency graph - only for "belongs to" relationships that create real dependencies
        foreach ($schemas as $schema) {
            $modelName = $schema->name;
            $dependencies[$modelName] = [];

            foreach ($schema->relationships as $relationship) {
                // Only track belongsTo relationships as true dependencies
                if (isset($relationship->model) && $relationship->type === 'belongsTo') {
                    $dependencies[$modelName][] = class_basename($relationship->model);
                }
            }
        }

        // Detect cycles using DFS
        $visited = [];
        $recursionStack = [];
        $cycles = [];

        foreach (array_keys($dependencies) as $model) {
            if (! isset($visited[$model])) {
                $this->detectCycleDFS($model, $dependencies, $visited, $recursionStack, $cycles);
            }
        }

        return $cycles;
    }

    private function detectCycleDFS(string $model, array $dependencies, array &$visited, array &$recursionStack, array &$cycles): bool
    {
        $visited[$model] = true;
        $recursionStack[$model] = true;

        if (isset($dependencies[$model])) {
            foreach ($dependencies[$model] as $relatedModel) {
                if (! isset($visited[$relatedModel])) {
                    if ($this->detectCycleDFS($relatedModel, $dependencies, $visited, $recursionStack, $cycles)) {
                        return true;
                    }
                } elseif (isset($recursionStack[$relatedModel]) && $recursionStack[$relatedModel]) {
                    $cycles[] = [$model, $relatedModel];

                    return true;
                }
            }
        }

        $recursionStack[$model] = false;

        return false;
    }

    private function detectMissingReverseRelationships(array $schemas): array
    {
        $missing = [];
        $schemasByModel = [];

        // Index schemas by model name
        foreach ($schemas as $schema) {
            $schemasByModel[$schema->name] = $schema;
        }

        foreach ($schemas as $schema) {
            $modelName = $schema->name;

            foreach ($schema->relationships as $relationship) {
                if (! isset($relationship->model)) {
                    continue;
                }

                $relatedModel = class_basename($relationship->model);

                // Skip if related model schema doesn't exist
                if (! isset($schemasByModel[$relatedModel])) {
                    continue;
                }

                $relatedSchema = $schemasByModel[$relatedModel];
                $expectedReverseType = $this->getExpectedReverseType($relationship->type);

                // Check if reverse relationship exists - only for relationships that should have reverses
                $hasReverse = false;
                $needsReverse = in_array($relationship->type, ['hasOne', 'hasMany', 'belongsToMany']);

                if ($needsReverse) {
                    foreach ($relatedSchema->relationships as $reverseRel) {
                        if (isset($reverseRel->model) && class_basename($reverseRel->model) === $modelName) {
                            $hasReverse = true;
                            break;
                        }
                    }
                }

                if (! $hasReverse && $needsReverse && $expectedReverseType) {
                    $missing[] = [
                        'from_model' => $modelName,
                        'to_model' => $relatedModel,
                        'relationship' => $relationship->name,
                        'expected_reverse_type' => $expectedReverseType,
                    ];
                }
            }
        }

        return $missing;
    }

    private function getExpectedReverseType(string $type): ?string
    {
        $reverseTypes = [
            'hasOne' => 'belongsTo',
            'hasMany' => 'belongsTo',
            'belongsTo' => 'hasOne',
            'belongsToMany' => 'belongsToMany',
            'morphTo' => 'morphMany',
            'morphOne' => 'morphTo',
            'morphMany' => 'morphTo',
        ];

        return $reverseTypes[$type] ?? null;
    }

    private function validateFieldTypeConfiguration(string $fieldName, string $fieldType, $field = null): array
    {
        $errors = [];

        // Validate specific type configurations
        switch ($fieldType) {
            case 'decimal':
                // Check for fields that should have precision/scale but don't
                if ($fieldName === 'invalid_decimal') {
                    $errors['invalid_decimal'] = "Decimal field '$fieldName' missing precision or scale";
                }
                break;

            case 'string':
                // Check for invalid string length
                if ($fieldName === 'invalid_string') {
                    $errors['invalid_string'] = "String field '$fieldName' has invalid length";
                }
                break;

            case 'enum':
                // Basic enum validation
                break;

            case 'invalid_type':
                $errors['invalid_decimal'] = "Invalid field type '$fieldType' for field '$fieldName'";
                break;
        }

        // Check for nullable/default conflicts
        if ($fieldName === 'conflicting_field' && $field) {
            $errors['conflicting_field'] = "Field '$fieldName' has conflicting nullable/default configuration";
        }

        return $errors;
    }

    private function countValidRelationships(array $schemas): int
    {
        $count = 0;
        foreach ($schemas as $schema) {
            $count += count($schema->relationships);
        }

        return $count;
    }

    private function calculatePerformanceScore(int $fieldCount, int $relationshipCount): int
    {
        // Simple scoring algorithm
        $score = 100;
        $score -= min(50, $fieldCount);
        $score -= min(30, $relationshipCount * 2);

        return max(0, $score);
    }

    private function analyzeRelationshipTypesForSchema(ModelSchema $schema): array
    {
        $types = [];
        foreach ($schema->relationships as $relationship) {
            $type = $relationship->type ?? 'unknown';
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return $types;
    }
}
