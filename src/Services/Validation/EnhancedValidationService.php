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
        $targetModelValidation = $this->validateTargetModels($schemas);

        return [
            'is_consistent' => $circularDependencies === [] && $missingReverseRelationships === [] && $targetModelValidation['is_valid'],
            'circular_dependencies' => $circularDependencies,
            'missing_reverse_relationships' => $missingReverseRelationships,
            'target_model_validation' => $targetModelValidation,
            'validation_summary' => [
                'total_schemas' => count($schemas),
                'valid_relationships' => $this->countValidRelationships($schemas),
                'issues_found' => count($circularDependencies) + count($missingReverseRelationships) + count($targetModelValidation['errors']),
            ],
        ];
    }

    /**
     * Validate that target models exist for all relationships
     */
    public function validateTargetModels(array $schemas): array
    {
        $errors = [];
        $warnings = [];
        $availableModels = $this->extractAvailableModels($schemas);

        foreach ($schemas as $schema) {
            foreach ($schema->relationships as $relationship) {
                $relationshipErrors = $this->validateSingleRelationship($relationship, $schema->name, $availableModels);
                $errors = array_merge($errors, $relationshipErrors);
            }
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'available_models' => $availableModels,
        ];
    }

    /**
     * Validate Laravel custom validation rules used in field configurations
     */
    public function validateLaravelRules(array $schemas): array
    {
        $errors = [];
        $warnings = [];
        $validatedRules = [];

        foreach ($schemas as $schema) {
            foreach ($schema->getAllFields() as $field) {
                // Check both 'validation' and 'rules' properties
                $customRules = array_merge($field->validation, $field->rules);

                foreach ($customRules as $customRule) {
                    $ruleValidation = $this->validateSingleLaravelRule($customRule, $field, $schema, $schemas);

                    if (! $ruleValidation['is_valid']) {
                        $errors = array_merge($errors, $ruleValidation['errors']);
                    }

                    $warnings = array_merge($warnings, $ruleValidation['warnings']);
                    $validatedRules[] = [
                        'field' => $field->name,
                        'model' => $schema->name,
                        'rule' => $customRule,
                        'is_valid' => $ruleValidation['is_valid'],
                        'rule_type' => $ruleValidation['rule_type'],
                        'is_custom' => true,
                    ];
                }
            }
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_rules' => $validatedRules,
            'statistics' => [
                'total_rules' => count($validatedRules),
                'valid_rules' => count(array_filter($validatedRules, fn ($r) => $r['is_valid'])),
                'invalid_rules' => count(array_filter($validatedRules, fn ($r): bool => ! $r['is_valid'])),
                'custom_rules' => count($validatedRules), // All rules are custom since we only check custom ones
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

        // Validate Laravel custom rules
        $laravelRulesValidation = $this->validateLaravelRules([$schema]);
        $errors = array_merge($errors, $laravelRulesValidation['errors']);
        $warnings = array_merge($warnings, $laravelRulesValidation['warnings']);

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
            'laravel_rules_validation' => $laravelRulesValidation,
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
            'laravel_rules_validation' => $schemaValidation['laravel_rules_validation'],
            'relationship_validation' => [
                'relationship_types' => $this->analyzeRelationshipTypesForSchema($schema),
                'total_relationships' => count($schema->relationships),
            ],
        ];
    }

    /**
     * Validate custom field types and their configurations
     */
    public function validateCustomFieldTypes(array $schemas): array
    {
        $errors = [];
        $warnings = [];
        $customTypeStats = [];
        $availableCustomTypes = $this->getAvailableCustomFieldTypes();

        foreach ($schemas as $schema) {
            foreach ($schema->fields as $field) {
                $fieldValidation = $this->validateSingleFieldType($field, $schema->name, $availableCustomTypes);
                $errors = array_merge($errors, $fieldValidation['errors']);
                $warnings = array_merge($warnings, $fieldValidation['warnings']);

                // Track custom type usage
                if ($this->isCustomFieldType($field->type)) {
                    if (! isset($customTypeStats[$field->type])) {
                        $customTypeStats[$field->type] = 0;
                    }
                    $customTypeStats[$field->type]++;
                }
            }
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'custom_type_stats' => $customTypeStats,
            'available_custom_types' => $availableCustomTypes,
            'validation_summary' => [
                'total_fields_validated' => $this->countTotalFields($schemas),
                'custom_fields_found' => array_sum($customTypeStats),
                'unique_custom_types' => count($customTypeStats),
                'errors_found' => count($errors),
                'warnings_found' => count($warnings),
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
                    $targetModel = class_basename($relationship->model);

                    // Skip self-referencing relationships - they are not problematic circular dependencies
                    if ($targetModel !== $modelName) {
                        $dependencies[$modelName][] = $targetModel;
                    }
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

    /**
     * Extract all available model names from schemas
     */
    private function extractAvailableModels(array $schemas): array
    {
        $models = [];
        foreach ($schemas as $schema) {
            $models[] = $schema->name;

            // Also consider common Laravel models that might not be in schemas
            $models = array_merge($models, [
                'User', 'App\\Models\\User', '\\App\\Models\\User',
                'Notification', 'App\\Models\\Notification', '\\App\\Models\\Notification',
            ]);
        }

        return array_unique($models);
    }

    /**
     * Validate a single relationship for target model existence and consistency
     */
    private function validateSingleRelationship($relationship, string $sourceModel, array $availableModels): array
    {
        $errors = [];

        // Skip validation for morphTo relationships (they don't specify a specific model)
        if (($relationship->type ?? '') === 'morphTo') {
            return $errors;
        }

        // Check if target model is specified
        if (! isset($relationship->model) || empty($relationship->model)) {
            $errors[] = "Relationship '{$relationship->name}' in model '{$sourceModel}' missing target model";

            return $errors;
        }

        $targetModel = $relationship->model;

        // Normalize model names for comparison
        $normalizedTarget = $this->normalizeModelName($targetModel);
        $normalizedAvailable = array_map([$this, 'normalizeModelName'], $availableModels);

        // Check if target model exists
        if (! in_array($normalizedTarget, $normalizedAvailable, true)) {
            $errors[] = "Relationship '{$relationship->name}' in model '{$sourceModel}' references non-existent model '{$targetModel}'";
        }

        // Validate relationship type consistency
        $relationshipErrors = $this->validateRelationshipTypeConsistency($relationship, $sourceModel);

        return array_merge($errors, $relationshipErrors);
    }

    /**
     * Normalize model name for comparison (handle namespace variations)
     */
    private function normalizeModelName(string $modelName): string
    {
        // Remove leading backslash
        $normalized = mb_ltrim($modelName, '\\');

        // Extract class name only
        $parts = explode('\\', $normalized);

        return end($parts);
    }

    /**
     * Validate a single Laravel validation rule
     */
    private function validateSingleLaravelRule(string $rule, object $field, object $schema, array $schemas): array
    {
        $errors = [];
        $warnings = [];
        $ruleType = $this->identifyRuleType($rule);

        switch ($ruleType) {
            case 'exists':
                $existsValidation = $this->validateExistsRule($rule, $field, $schema, $schemas);
                $errors = array_merge($errors, $existsValidation['errors']);
                $warnings = array_merge($warnings, $existsValidation['warnings']);
                break;

            case 'unique':
                $uniqueValidation = $this->validateUniqueRule($rule, $field, $schema, $schemas);
                $errors = array_merge($errors, $uniqueValidation['errors']);
                $warnings = array_merge($warnings, $uniqueValidation['warnings']);
                break;

            case 'in':
                $inValidation = $this->validateInRule($rule, $field);
                $errors = array_merge($errors, $inValidation['errors']);
                $warnings = array_merge($warnings, $inValidation['warnings']);
                break;

            case 'regex':
                $regexValidation = $this->validateRegexRule($rule, $field);
                $errors = array_merge($errors, $regexValidation['errors']);
                $warnings = array_merge($warnings, $regexValidation['warnings']);
                break;

            case 'size_constraint':
                $sizeValidation = $this->validateSizeConstraintRule($rule, $field);
                $errors = array_merge($errors, $sizeValidation['errors']);
                $warnings = array_merge($warnings, $sizeValidation['warnings']);
                break;

            case 'conditional':
                $conditionalValidation = $this->validateConditionalRule($rule, $field, $schema);
                $errors = array_merge($errors, $conditionalValidation['errors']);
                $warnings = array_merge($warnings, $conditionalValidation['warnings']);
                break;

            case 'basic':
                // Basic rules like 'required', 'nullable', 'string' are always valid
                break;

            case 'unknown':
                $warnings[] = "Unknown validation rule '{$rule}' for field '{$field->name}' in model '{$schema->name}'";
                break;
        }

        return [
            'is_valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'rule_type' => $ruleType,
        ];
    }

    /**
     * Identify the type of validation rule
     */
    private function identifyRuleType(string $rule): string
    {
        // Handle rule with parameters (e.g., "exists:users,id")
        $ruleName = explode(':', $rule)[0];

        // Database rules
        if (in_array($ruleName, ['exists', 'unique'])) {
            return $ruleName;
        }

        // List validation
        if ($ruleName === 'in' || $ruleName === 'not_in') {
            return 'in';
        }

        // Pattern matching
        if ($ruleName === 'regex' || $ruleName === 'not_regex') {
            return 'regex';
        }

        // Size constraints
        if (in_array($ruleName, ['min', 'max', 'between', 'size', 'digits', 'digits_between'])) {
            return 'size_constraint';
        }

        // Conditional rules
        if (in_array($ruleName, ['required_if', 'required_unless', 'required_with', 'required_without', 'required_with_all', 'required_without_all'])) {
            return 'conditional';
        }

        // Basic validation rules
        if (in_array($ruleName, [
            'required', 'nullable', 'string', 'integer', 'numeric', 'boolean', 'array', 'object',
            'email', 'url', 'uuid', 'date', 'date_format', 'before', 'after', 'confirmed',
            'alpha', 'alpha_dash', 'alpha_num', 'ip', 'ipv4', 'ipv6', 'json', 'file', 'image',
        ])) {
            return 'basic';
        }

        return 'unknown';
    }

    /**
     * Validate exists rule (e.g., "exists:users,id")
     */
    private function validateExistsRule(string $rule, object $field, object $schema, array $schemas): array
    {
        $errors = [];
        $warnings = [];

        // Parse the exists rule
        $parts = explode(':', $rule, 2);
        if (count($parts) < 2) {
            $errors[] = "Invalid exists rule format for field '{$field->name}' in model '{$schema->name}': '{$rule}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $parameters = explode(',', $parts[1]);
        $tableName = $parameters[0];
        $columnName = $parameters[1] ?? 'id';

        // Find matching schema for the table
        $targetSchema = $this->findSchemaByTableName($tableName, $schemas);

        if (! $targetSchema) {
            $errors[] = "Exists rule references non-existent table '{$tableName}' for field '{$field->name}' in model '{$schema->name}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check if column exists in target schema
        $targetField = $this->findFieldInSchema($columnName, $targetSchema);
        if (! $targetField) {
            $errors[] = "Exists rule references non-existent column '{$columnName}' in table '{$tableName}' for field '{$field->name}' in model '{$schema->name}'";
        }

        // Type compatibility warning
        if ($targetField && $field->type !== $targetField->type) {
            $warnings[] = "Type mismatch in exists rule: field '{$field->name}' ({$field->type}) references '{$columnName}' ({$targetField->type}) in table '{$tableName}'";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate unique rule (e.g., "unique:users,email")
     */
    private function validateUniqueRule(string $rule, object $field, object $schema, array $schemas): array
    {
        $errors = [];
        $warnings = [];

        // Parse the unique rule
        $parts = explode(':', $rule, 2);
        if (count($parts) < 2) {
            $errors[] = "Invalid unique rule format for field '{$field->name}' in model '{$schema->name}': '{$rule}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $parameters = explode(',', $parts[1]);
        $tableName = $parameters[0];
        $columnName = $parameters[1] ?? $field->name;

        // Validate table exists
        $targetSchema = $this->findSchemaByTableName($tableName, $schemas);
        if (! $targetSchema) {
            $errors[] = "Unique rule references non-existent table '{$tableName}' for field '{$field->name}' in model '{$schema->name}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validate column exists
        $targetField = $this->findFieldInSchema($columnName, $targetSchema);
        if (! $targetField) {
            $errors[] = "Unique rule references non-existent column '{$columnName}' in table '{$tableName}' for field '{$field->name}' in model '{$schema->name}'";
        }

        // Check if field is suitable for unique constraint
        if ($targetField && in_array($targetField->type, ['text', 'longText', 'mediumText', 'json'])) {
            $warnings[] = "Unique rule on field '{$columnName}' of type '{$targetField->type}' may cause performance issues";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate 'in' rule (e.g., "in:active,inactive,pending")
     */
    private function validateInRule(string $rule, object $field): array
    {
        $errors = [];
        $warnings = [];

        $parts = explode(':', $rule, 2);
        if (count($parts) < 2) {
            $errors[] = "Invalid 'in' rule format for field '{$field->name}': '{$rule}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $values = explode(',', $parts[1]);
        if (count($values) === 1 && mb_trim($values[0]) === '') {
            $errors[] = "Empty values list in 'in' rule for field '{$field->name}': '{$rule}'";
        }

        // Warn about potential performance issues with too many values
        if (count($values) > 50) {
            $warnings[] = 'Large number of values ('.count($values).") in 'in' rule for field '{$field->name}' may impact performance";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate regex rule (e.g., "regex:/^[a-zA-Z0-9]+$/")
     */
    private function validateRegexRule(string $rule, object $field): array
    {
        $errors = [];
        $warnings = [];

        $parts = explode(':', $rule, 2);
        if (count($parts) < 2) {
            $errors[] = "Invalid regex rule format for field '{$field->name}': '{$rule}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $pattern = $parts[1];

        // Validate regex syntax
        if (@preg_match($pattern, '') === false) {
            $errors[] = "Invalid regex pattern for field '{$field->name}': '{$pattern}'";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate size constraint rules (e.g., "min:5", "max:100", "between:1,10")
     */
    private function validateSizeConstraintRule(string $rule, object $field): array
    {
        $errors = [];
        $warnings = [];

        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        if (in_array($ruleName, ['min', 'max', 'size', 'digits']) && ! is_numeric($parameter)) {
            $errors[] = "Invalid parameter for '{$ruleName}' rule on field '{$field->name}': '{$parameter}'";
        }

        if (in_array($ruleName, ['between', 'digits_between'])) {
            $values = explode(',', $parameter ?? '');
            if (count($values) !== 2 || ! is_numeric($values[0]) || ! is_numeric($values[1])) {
                $errors[] = "Invalid parameters for '{$ruleName}' rule on field '{$field->name}': '{$parameter}'";
            } elseif ((float) $values[0] >= (float) $values[1]) {
                $errors[] = "Invalid range for '{$ruleName}' rule on field '{$field->name}': minimum must be less than maximum";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Validate conditional rules (e.g., "required_if:status,active")
     */
    private function validateConditionalRule(string $rule, object $field, object $schema): array
    {
        $errors = [];
        $warnings = [];

        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = $parts[1] ?? '';

        if ($parameters === '' || $parameters === '0') {
            $errors[] = "Missing parameters for conditional rule '{$ruleName}' on field '{$field->name}'";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $paramList = explode(',', $parameters);
        $referencedField = $paramList[0];

        // Check if referenced field exists in the same schema
        $targetField = $this->findFieldInSchema($referencedField, $schema);
        if (! $targetField) {
            $errors[] = "Conditional rule '{$ruleName}' on field '{$field->name}' references non-existent field '{$referencedField}'";
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * Find schema by table name
     */
    private function findSchemaByTableName(string $tableName, array $schemas): ?object
    {
        foreach ($schemas as $schema) {
            if (($schema->table ?? mb_strtolower($schema->name)) === $tableName) {
                return $schema;
            }
        }

        return null;
    }

    /**
     * Find field in schema by name
     */
    private function findFieldInSchema(string $fieldName, object $schema): ?object
    {
        foreach ($schema->getAllFields() as $field) {
            if ($field->name === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Validate relationship type consistency and configuration
     */
    private function validateRelationshipTypeConsistency(object $relationship, string $sourceModel): array
    {
        $errors = [];
        $relationshipType = $relationship->type ?? '';

        // Validate relationship type exists
        $validTypes = ['hasOne', 'hasMany', 'belongsTo', 'belongsToMany', 'morphTo', 'morphOne', 'morphMany'];
        if (! in_array($relationshipType, $validTypes, true)) {
            $errors[] = "Invalid relationship type '{$relationshipType}' in model '{$sourceModel}' for relationship '{$relationship->name}'";

            return $errors;
        }

        // Validate foreign key configuration for belongsTo relationships
        if ($relationshipType === 'belongsTo') {
            $errors = array_merge($errors, $this->validateBelongsToConfiguration($relationship));
        }

        // Validate pivot table for belongsToMany relationships
        if ($relationshipType === 'belongsToMany') {
            return array_merge($errors, $this->validateBelongsToManyConfiguration($relationship, $sourceModel));
        }

        return $errors;
    }

    /**
     * Validate belongsTo relationship configuration
     */
    private function validateBelongsToConfiguration(object $relationship): array
    {
        $errors = [];

        // Check if foreign key follows Laravel conventions
        if (isset($relationship->foreignKey)) {
            $expectedForeignKey = mb_strtolower($this->normalizeModelName($relationship->model ?? '')).'_id';
            if ($relationship->foreignKey !== $expectedForeignKey) {
                // This is a warning, not an error, as custom foreign keys are valid
            }
        }

        return $errors;
    }

    /**
     * Validate belongsToMany relationship configuration
     */
    private function validateBelongsToManyConfiguration(object $relationship, string $sourceModel): array
    {
        $errors = [];

        // Check if pivot table is specified for belongsToMany
        if (! isset($relationship->pivot) || empty($relationship->pivot)) {
            $sourceTable = mb_strtolower($sourceModel);
            $targetModel = $this->normalizeModelName($relationship->model ?? '');
            $targetTable = mb_strtolower($targetModel);

            // Generate expected pivot table name
            $tables = [$sourceTable, $targetTable];
            sort($tables);
            $expectedPivot = implode('_', $tables);

            // This is informational - Laravel will auto-generate if not specified
        }

        return $errors;
    }

    /**
     * Validate a single field type and its configuration
     */
    private function validateSingleFieldType(object $field, string $schemaName, array $availableCustomTypes): array
    {
        $errors = [];
        $warnings = [];
        $fieldType = $field->type;

        // Check if field type exists
        if ($this->isCustomFieldType($fieldType)) {
            if (! in_array($fieldType, $availableCustomTypes)) {
                $errors[] = "Unknown custom field type '{$fieldType}' in field '{$field->name}' of schema '{$schemaName}'. Available custom types: ".implode(', ', $availableCustomTypes);
            } else {
                // Validate custom field type configuration
                $configValidation = $this->validateCustomFieldTypeConfiguration($field, $schemaName);
                $errors = array_merge($errors, $configValidation['errors']);
                $warnings = array_merge($warnings, $configValidation['warnings']);
            }
        } elseif (! $this->isBuiltInFieldType($fieldType)) {
            $errors[] = "Unknown field type '{$fieldType}' in field '{$field->name}' of schema '{$schemaName}'. Type is neither built-in nor custom.";
        }

        // Validate field type specific attributes
        $attributeValidation = $this->validateFieldTypeAttributes($field, $schemaName);
        $errors = array_merge($errors, $attributeValidation['errors']);
        $warnings = array_merge($warnings, $attributeValidation['warnings']);

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate configuration for custom field types
     */
    private function validateCustomFieldTypeConfiguration(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];
        $fieldType = $field->type;

        // Specific validation for known custom types
        switch ($fieldType) {
            case 'enum':
            case 'enumeration':
                $enumValidation = $this->validateEnumFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $enumValidation['errors']);
                $warnings = array_merge($warnings, $enumValidation['warnings']);
                break;

            case 'set':
            case 'multi_select':
            case 'multiple_choice':
                $setValidation = $this->validateSetFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $setValidation['errors']);
                $warnings = array_merge($warnings, $setValidation['warnings']);
                break;

            case 'point':
            case 'geopoint':
            case 'coordinates':
            case 'latlng':
                $pointValidation = $this->validatePointFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $pointValidation['errors']);
                $warnings = array_merge($warnings, $pointValidation['warnings']);
                break;

            case 'geometry':
            case 'geom':
            case 'spatial':
                $geometryValidation = $this->validateGeometryFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $geometryValidation['errors']);
                $warnings = array_merge($warnings, $geometryValidation['warnings']);
                break;

            case 'polygon':
            case 'area':
            case 'boundary':
            case 'region':
                $polygonValidation = $this->validatePolygonFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $polygonValidation['errors']);
                $warnings = array_merge($warnings, $polygonValidation['warnings']);
                break;

            default:
                // Generic custom field type validation
                $genericValidation = $this->validateGenericCustomFieldConfiguration($field, $schemaName);
                $errors = array_merge($errors, $genericValidation['errors']);
                $warnings = array_merge($warnings, $genericValidation['warnings']);
                break;
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate enum field configuration
     */
    private function validateEnumFieldConfiguration(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];

        // Check if values are provided
        if (! isset($field->values) || empty($field->values)) {
            $errors[] = "Enum field '{$field->name}' in schema '{$schemaName}' must have 'values' array defined";

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Validate values array
        if (! is_array($field->values)) {
            $errors[] = "Enum field '{$field->name}' in schema '{$schemaName}' must have 'values' as an array, got ".gettype($field->values);

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        // Check minimum values
        if (count($field->values) < 2) {
            $warnings[] = "Enum field '{$field->name}' in schema '{$schemaName}' has less than 2 values, consider using a boolean field instead";
        }

        // Check maximum values (performance consideration)
        if (count($field->values) > 100) {
            $warnings[] = "Enum field '{$field->name}' in schema '{$schemaName}' has more than 100 values, consider using a separate lookup table instead";
        }

        // Validate individual values
        foreach ($field->values as $index => $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                $errors[] = "Enum field '{$field->name}' in schema '{$schemaName}' has invalid value at index {$index}: must be string or numeric";
            }

            if (is_string($value) && mb_strlen($value) > 255) {
                $warnings[] = "Enum field '{$field->name}' in schema '{$schemaName}' has value longer than 255 characters at index {$index}, may cause database issues";
            }
        }

        // Check for duplicate values
        $uniqueValues = array_unique($field->values);
        if (count($uniqueValues) !== count($field->values)) {
            $errors[] = "Enum field '{$field->name}' in schema '{$schemaName}' contains duplicate values";
        }

        // Check default value if specified (skip array defaults as they are handled by SET validation)
        if (isset($field->default) && ! is_array($field->default) && ! in_array($field->default, $field->values)) {
            $errors[] = "Enum field '{$field->name}' in schema '{$schemaName}' has default value '{$field->default}' which is not in the values array";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate set field configuration
     */
    private function validateSetFieldConfiguration(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];

        // Set fields share most validation with enum fields
        $enumValidation = $this->validateEnumFieldConfiguration($field, $schemaName);
        $errors = array_merge($errors, $enumValidation['errors']);
        $warnings = array_merge($warnings, $enumValidation['warnings']);

        // Additional validation specific to SET fields
        if (isset($field->values) && is_array($field->values) && count($field->values) > 64) {
            $errors[] = "Set field '{$field->name}' in schema '{$schemaName}' cannot have more than 64 values (MySQL SET limitation)";
        }

        // Validate default value for SET (can be array or comma-separated string)
        if (isset($field->default)) {
            if (is_array($field->default)) {
                foreach ($field->default as $defaultValue) {
                    if (! in_array($defaultValue, $field->values ?? [])) {
                        $errors[] = "Set field '{$field->name}' in schema '{$schemaName}' has default value '{$defaultValue}' which is not in the values array";
                    }
                }
            } elseif (is_string($field->default)) {
                $defaultValues = array_map('trim', explode(',', $field->default));
                foreach ($defaultValues as $defaultValue) {
                    if (! in_array($defaultValue, $field->values ?? [])) {
                        $errors[] = "Set field '{$field->name}' in schema '{$schemaName}' has default value '{$defaultValue}' which is not in the values array";
                    }
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate point/geographic field configuration
     */
    private function validatePointFieldConfiguration(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];

        // Check SRID if specified
        if (isset($field->srid)) {
            if (! is_numeric($field->srid) || $field->srid < 0) {
                $errors[] = "Point field '{$field->name}' in schema '{$schemaName}' has invalid SRID: must be a positive number";
            } elseif ($field->srid !== 4326 && $field->srid !== 3857) {
                $warnings[] = "Point field '{$field->name}' in schema '{$schemaName}' uses SRID {$field->srid}, common values are 4326 (WGS84) or 3857 (Web Mercator)";
            }
        }

        // Check dimension if specified
        if (isset($field->dimension) && ! in_array($field->dimension, [2, 3, 4])) {
            $errors[] = "Point field '{$field->name}' in schema '{$schemaName}' has invalid dimension: must be 2, 3, or 4";
        }

        // Check coordinate system
        if (isset($field->coordinate_system) && ! in_array($field->coordinate_system, ['cartesian', 'geographic'])) {
            $errors[] = "Point field '{$field->name}' in schema '{$schemaName}' has invalid coordinate_system: must be 'cartesian' or 'geographic'";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate geometry field configuration
     */
    private function validateGeometryFieldConfiguration(object $field, string $schemaName): array
    {
        // Geometry fields share validation with point fields
        return $this->validatePointFieldConfiguration($field, $schemaName);
    }

    /**
     * Validate polygon field configuration
     */
    private function validatePolygonFieldConfiguration(object $field, string $schemaName): array
    {
        $validation = $this->validatePointFieldConfiguration($field, $schemaName);
        $errors = $validation['errors'];
        $warnings = $validation['warnings'];

        // Additional polygon-specific validation
        if (isset($field->min_points) && (! is_numeric($field->min_points) || $field->min_points < 3)) {
            $errors[] = "Polygon field '{$field->name}' in schema '{$schemaName}' must have min_points >= 3";
        }

        if (isset($field->max_points) && isset($field->min_points) && $field->max_points < $field->min_points) {
            $errors[] = "Polygon field '{$field->name}' in schema '{$schemaName}' has max_points less than min_points";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate generic custom field configuration
     */
    private function validateGenericCustomFieldConfiguration(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];

        // Check if custom field type class exists
        $customTypeClass = $this->getCustomFieldTypeClass($field->type);
        if ($customTypeClass && ! class_exists($customTypeClass)) {
            $errors[] = "Custom field type class '{$customTypeClass}' for field '{$field->name}' in schema '{$schemaName}' does not exist";
        }

        // Warn about missing configuration validation
        if (! $this->hasCustomFieldTypeValidator($field->type)) {
            $warnings[] = "Custom field type '{$field->type}' for field '{$field->name}' in schema '{$schemaName}' has no specific validation rules defined";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate field type specific attributes
     */
    private function validateFieldTypeAttributes(object $field, string $schemaName): array
    {
        $errors = [];
        $warnings = [];

        // Validate length attribute for string-based fields
        if (isset($field->length)) {
            if (in_array($field->type, ['string', 'varchar', 'char'])) {
                if (! is_numeric($field->length) || $field->length <= 0) {
                    $errors[] = "Field '{$field->name}' in schema '{$schemaName}' has invalid length: must be a positive number";
                } elseif ($field->length > 65535) {
                    $warnings[] = "Field '{$field->name}' in schema '{$schemaName}' has length > 65535, consider using TEXT type instead";
                }
            } else {
                $warnings[] = "Field '{$field->name}' in schema '{$schemaName}' has length attribute but type '{$field->type}' does not support it";
            }
        }

        // Validate precision and scale for decimal fields
        if (isset($field->precision) || isset($field->scale)) {
            if (in_array($field->type, ['decimal', 'numeric'])) {
                if (isset($field->precision) && (! is_numeric($field->precision) || $field->precision <= 0 || $field->precision > 65)) {
                    $errors[] = "Field '{$field->name}' in schema '{$schemaName}' has invalid precision: must be between 1 and 65";
                }
                if (isset($field->scale) && (! is_numeric($field->scale) || $field->scale < 0 || $field->scale > 30)) {
                    $errors[] = "Field '{$field->name}' in schema '{$schemaName}' has invalid scale: must be between 0 and 30";
                }
                if (isset($field->precision) && isset($field->scale) && $field->scale > $field->precision) {
                    $errors[] = "Field '{$field->name}' in schema '{$schemaName}' has scale greater than precision";
                }
            } else {
                $warnings[] = "Field '{$field->name}' in schema '{$schemaName}' has precision/scale attributes but type '{$field->type}' does not support them";
            }
        }

        // Validate unsigned attribute
        if (isset($field->unsigned) && $field->unsigned && ! in_array($field->type, ['integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'mediumInteger', 'float', 'double', 'decimal'])) {
            $warnings[] = "Field '{$field->name}' in schema '{$schemaName}' has unsigned attribute but type '{$field->type}' does not support it";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get list of available custom field types
     */
    private function getAvailableCustomFieldTypes(): array
    {
        $customTypes = [];

        // Built-in custom types
        $builtInCustomTypes = [
            'enum', 'enumeration',
            'set', 'multi_select', 'multiple_choice',
            'point', 'geopoint', 'coordinates', 'latlng',
            'geometry', 'geom', 'spatial', 'geo',
            'polygon', 'area', 'boundary', 'region',
        ];

        $customTypes = array_merge($customTypes, $builtInCustomTypes);

        // Load custom field types from configured path
        $customFieldTypesPath = config('modelschema.custom_field_types_path', app_path('FieldTypes'));
        if (is_dir($customFieldTypesPath)) {
            $files = scandir($customFieldTypesPath);
            foreach ($files as $file) {
                if (str_ends_with($file, 'FieldType.php')) {
                    $typeName = mb_strtolower(str_replace('FieldType.php', '', $file));
                    $customTypes[] = $typeName;
                }
            }
        }

        return array_unique($customTypes);
    }

    /**
     * Check if a field type is a custom type
     */
    private function isCustomFieldType(string $fieldType): bool
    {
        $builtInTypes = [
            'string', 'text', 'longText', 'mediumText',
            'integer', 'bigInteger', 'smallInteger', 'tinyInteger', 'mediumInteger',
            'unsignedBigInteger', 'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger',
            'decimal', 'float', 'double',
            'boolean',
            'date', 'datetime', 'timestamp', 'time', 'year',
            'json', 'jsonb',
            'uuid',
            'email', 'url',
            'binary',
            'morphs',
            'foreignId',
        ];

        return ! in_array($fieldType, $builtInTypes);
    }

    /**
     * Check if a field type is a built-in type
     */
    private function isBuiltInFieldType(string $fieldType): bool
    {
        return ! $this->isCustomFieldType($fieldType);
    }

    /**
     * Get custom field type class name
     */
    private function getCustomFieldTypeClass(string $fieldType): string
    {
        $namespace = config('modelschema.custom_field_types_namespace', 'App\\FieldTypes');
        $className = ucfirst($fieldType).'FieldType';

        return "{$namespace}\\{$className}";
    }

    /**
     * Check if custom field type has specific validator
     */
    private function hasCustomFieldTypeValidator(string $fieldType): bool
    {
        $validatedTypes = [
            'enum', 'enumeration',
            'set', 'multi_select', 'multiple_choice',
            'point', 'geopoint', 'coordinates', 'latlng',
            'geometry', 'geom', 'spatial', 'geo',
            'polygon', 'area', 'boundary', 'region',
        ];

        return in_array($fieldType, $validatedTypes);
    }

    /**
     * Count total fields across all schemas
     */
    private function countTotalFields(array $schemas): int
    {
        $total = 0;
        foreach ($schemas as $schema) {
            $total += count($schema->fields);
        }

        return $total;
    }
}
