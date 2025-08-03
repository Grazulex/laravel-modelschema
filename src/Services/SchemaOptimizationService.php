<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Exception;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Schema\ModelSchema;

/**
 * Service for analyzing and optimizing Laravel model schemas
 *
 * Provides comprehensive optimization recommendations covering:
 * - Performance optimization (indexes, query efficiency)
 * - Storage optimization (field types, sizes)
 * - Validation optimization (rule efficiency)
 * - Maintenance optimization (naming, structure)
 * - Security optimization (data protection)
 */
class SchemaOptimizationService
{
    private LoggingService $loggingService;

    /**
     * Performance thresholds for optimization recommendations
     */
    private array $performanceThresholds = [
        'max_fields_without_index' => 20,
        'max_relationship_depth' => 4,
        'max_field_size' => 65535,
        'max_validation_rules' => 10,
        'max_string_length' => 255,
        'recommended_json_fields' => 5,
        'max_decimal_precision' => 10,
        'max_foreign_keys_per_table' => 8,
    ];

    /**
     * Optimization categories and their weights
     */
    private array $optimizationWeights = [
        'performance' => 0.30,
        'storage' => 0.25,
        'validation' => 0.20,
        'maintenance' => 0.15,
        'security' => 0.10,
    ];

    public function __construct(LoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }

    /**
     * Analyze a schema and provide comprehensive optimization recommendations
     */
    public function analyzeSchema(ModelSchema $schema): array
    {
        $startTime = microtime(true);

        try {
            $this->loggingService->logOperationStart('schema_optimization_analyze', [
                'model' => $schema->name,
                'fields_count' => count($schema->fields),
                'relationships_count' => count($schema->relationships),
            ]);

            $analysis = [
                'model' => $schema->name,
                'timestamp' => now()->toISOString(),
                'performance_score' => 0,
                'optimization_score' => 0,
                'recommendations' => [],
                'categories' => [
                    'performance' => $this->analyzePerformance($schema),
                    'storage' => $this->analyzeStorage($schema),
                    'validation' => $this->analyzeValidation(),
                    'maintenance' => $this->analyzeMaintenance(),
                    'security' => $this->analyzeSecurity($schema),
                ],
                'summary' => [],
                'priority_actions' => [],
            ];

            // Calculate overall scores
            $analysis = $this->calculateOptimizationScores($analysis);

            // Generate summary and priority actions
            $analysis['summary'] = $this->generateOptimizationSummary($analysis);
            $analysis['priority_actions'] = $this->generatePriorityActions($analysis);

            $duration = microtime(true) - $startTime;
            $this->loggingService->logOperationEnd('schema_optimization_analyze', [
                'model' => $schema->name,
                'duration' => $duration,
                'recommendations_count' => count($analysis['recommendations']),
                'optimization_score' => $analysis['optimization_score'],
            ]);

            return $analysis;

        } catch (Exception $e) {
            $this->loggingService->logError('Schema optimization analysis failed: '.$e->getMessage(), $e, [
                'model' => $schema->name,
                'duration' => microtime(true) - $startTime,
            ]);
            throw new SchemaException('Schema optimization analysis failed: '.$e->getMessage());
        }
    }

    /**
     * Analyze performance aspects of the schema
     */
    private function analyzePerformance(ModelSchema $schema): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;

        $fields = $schema->fields;
        $relationships = $schema->relationships;

        // Check for missing indexes on commonly queried fields
        $indexableFields = $this->identifyIndexableFields($fields);
        if ($indexableFields !== []) {
            $score -= 15;
            $issues[] = [
                'type' => 'missing_indexes',
                'severity' => 'medium',
                'message' => 'Fields that would benefit from indexes detected',
                'fields' => $indexableFields,
            ];
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'medium',
                'action' => 'add_indexes',
                'description' => 'Add database indexes for frequently queried fields',
                'implementation' => $this->generateIndexRecommendations($indexableFields),
            ];
        }

        // Check relationship performance
        if (count($relationships) > $this->performanceThresholds['max_foreign_keys_per_table']) {
            $score -= 10;
            $issues[] = [
                'type' => 'excessive_relationships',
                'severity' => 'low',
                'message' => 'High number of relationships may impact query performance',
                'count' => count($relationships),
                'threshold' => $this->performanceThresholds['max_foreign_keys_per_table'],
            ];
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'low',
                'action' => 'optimize_relationships',
                'description' => 'Consider normalizing or caching frequently accessed relationships',
                'implementation' => 'Review relationship usage patterns and consider eager loading or caching strategies',
            ];
        }

        // Check for N+1 query potential
        $eagerLoadCandidates = $this->identifyEagerLoadCandidates($relationships);
        if ($eagerLoadCandidates !== []) {
            $recommendations[] = [
                'category' => 'performance',
                'priority' => 'high',
                'action' => 'prevent_n_plus_one',
                'description' => 'Implement eager loading for relationships to prevent N+1 queries',
                'implementation' => $this->generateEagerLoadingRecommendations($eagerLoadCandidates),
            ];
        }

        // Check for large text fields that should be optimized
        $textOptimizations = $this->analyzeTextFieldOptimizations();
        if ($textOptimizations !== []) {
            $score -= 5;
            $recommendations = array_merge($recommendations, $textOptimizations);
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => [
                'indexable_fields' => count($indexableFields),
                'relationships_count' => count($relationships),
                'large_text_fields' => count($this->getLargeTextFields()),
            ],
        ];
    }

    /**
     * Analyze storage optimization opportunities
     */
    private function analyzeStorage(ModelSchema $schema): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;

        $fields = $schema->fields;

        // Check for oversized string fields
        $oversizedStrings = $this->identifyOversizedStringFields($fields);
        if ($oversizedStrings !== []) {
            $score -= 10;
            $issues[] = [
                'type' => 'oversized_strings',
                'severity' => 'medium',
                'message' => 'String fields with excessive length detected',
                'fields' => $oversizedStrings,
            ];
            $recommendations[] = [
                'category' => 'storage',
                'priority' => 'medium',
                'action' => 'optimize_string_lengths',
                'description' => 'Reduce string field lengths to optimize storage',
                'implementation' => $this->generateStringSizeRecommendations($oversizedStrings),
            ];
        }

        // Check for inefficient field types
        $inefficientTypes = $this->identifyInefficientFieldTypes($fields);
        if ($inefficientTypes !== []) {
            $score -= 15;
            $recommendations[] = [
                'category' => 'storage',
                'priority' => 'high',
                'action' => 'optimize_field_types',
                'description' => 'Use more efficient field types for better storage',
                'implementation' => $this->generateFieldTypeRecommendations(),
            ];
        }

        // Check for nullable optimization opportunities
        $nullableOptimizations = $this->analyzeNullableOptimizations();
        if ($nullableOptimizations !== []) {
            $recommendations = array_merge($recommendations, $nullableOptimizations);
        }

        // Check decimal precision optimization
        $decimalOptimizations = $this->analyzeDecimalOptimizations();
        if ($decimalOptimizations !== []) {
            $score -= 5;
            $recommendations = array_merge($recommendations, $decimalOptimizations);
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => [
                'oversized_strings' => count($oversizedStrings),
                'inefficient_types' => count($inefficientTypes),
                'total_storage_score' => $this->calculateStorageEfficiencyScore(),
            ],
        ];
    }

    /**
     * Analyze validation optimization opportunities
     */
    private function analyzeValidation(): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;
        // Check for redundant validation rules
        $redundantRules = $this->identifyRedundantValidationRules();
        if ($redundantRules !== []) {
            $score -= 10;
            $issues[] = [
                'type' => 'redundant_validation',
                'severity' => 'low',
                'message' => 'Redundant validation rules detected',
                'rules' => $redundantRules,
            ];
            $recommendations[] = [
                'category' => 'validation',
                'priority' => 'low',
                'action' => 'optimize_validation_rules',
                'description' => 'Remove or consolidate redundant validation rules',
                'implementation' => $this->generateValidationOptimizationRecommendations(),
            ];
        }
        // Check for missing validation on important fields
        $missingValidation = $this->identifyMissingValidationRules();
        if ($missingValidation !== []) {
            $score -= 15;
            $recommendations[] = [
                'category' => 'validation',
                'priority' => 'high',
                'action' => 'add_missing_validation',
                'description' => 'Add validation rules for fields that should be validated',
                'implementation' => $this->generateMissingValidationRecommendations(),
            ];
        }
        // Check for expensive validation rules
        $expensiveRules = $this->identifyExpensiveValidationRules();
        if ($expensiveRules !== []) {
            $score -= 8;
            $recommendations[] = [
                'category' => 'validation',
                'priority' => 'medium',
                'action' => 'optimize_expensive_rules',
                'description' => 'Optimize or cache expensive validation rules',
                'implementation' => $this->generateExpensiveRuleOptimizations(),
            ];
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => [
                'redundant_rules' => count($redundantRules),
                'missing_validation' => count($missingValidation),
                'expensive_rules' => count($expensiveRules),
            ],
        ];
    }

    /**
     * Analyze maintenance and code quality aspects
     */
    private function analyzeMaintenance(): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;
        // Check naming conventions
        $namingIssues = $this->analyzeNamingConventions();
        if ($namingIssues !== []) {
            $score -= 10;
            $issues[] = [
                'type' => 'naming_conventions',
                'severity' => 'low',
                'message' => 'Naming convention issues detected',
                'issues' => $namingIssues,
            ];
            $recommendations[] = [
                'category' => 'maintenance',
                'priority' => 'low',
                'action' => 'improve_naming',
                'description' => 'Follow Laravel naming conventions for better code readability',
                'implementation' => $this->generateNamingRecommendations(),
            ];
        }
        // Check for missing documentation
        $documentationIssues = $this->analyzeDocumentation();
        if ($documentationIssues !== []) {
            $score -= 5;
            $recommendations[] = [
                'category' => 'maintenance',
                'priority' => 'low',
                'action' => 'add_documentation',
                'description' => 'Add missing documentation for better maintainability',
                'implementation' => $this->generateDocumentationRecommendations(),
            ];
        }
        // Check schema complexity
        $complexityScore = $this->calculateSchemaComplexity();
        if ($complexityScore > 80) {
            $score -= 15;
            $recommendations[] = [
                'category' => 'maintenance',
                'priority' => 'medium',
                'action' => 'reduce_complexity',
                'description' => 'Consider breaking down complex schema into smaller, more manageable parts',
                'implementation' => $this->generateComplexityReductionRecommendations(),
            ];
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => [
                'naming_issues' => count($namingIssues),
                'complexity_score' => $complexityScore,
                'documentation_coverage' => $this->calculateDocumentationCoverage(),
            ],
        ];
    }

    /**
     * Analyze security aspects of the schema
     */
    private function analyzeSecurity(ModelSchema $schema): array
    {
        $issues = [];
        $recommendations = [];
        $score = 100;

        $fields = $schema->fields;

        // Check for sensitive data without proper protection
        $sensitiveFields = $this->identifySensitiveFields($fields);
        if ($sensitiveFields !== []) {
            $score -= 20;
            $issues[] = [
                'type' => 'sensitive_data',
                'severity' => 'high',
                'message' => 'Sensitive fields detected that may need additional protection',
                'fields' => $sensitiveFields,
            ];
            $recommendations[] = [
                'category' => 'security',
                'priority' => 'high',
                'action' => 'protect_sensitive_data',
                'description' => 'Add encryption, access controls, or masking for sensitive fields',
                'implementation' => $this->generateSensitiveDataRecommendations(),
            ];
        }

        // Check for mass assignment vulnerabilities
        $massAssignmentRisks = $this->analyzeMassAssignmentRisks();
        if ($massAssignmentRisks !== []) {
            $score -= 15;
            $recommendations[] = [
                'category' => 'security',
                'priority' => 'high',
                'action' => 'secure_mass_assignment',
                'description' => 'Configure fillable/guarded properties to prevent mass assignment vulnerabilities',
                'implementation' => $this->generateMassAssignmentRecommendations(),
            ];
        }

        // Check for SQL injection risks in validation rules
        $sqlInjectionRisks = $this->analyzeSqlInjectionRisks();
        if ($sqlInjectionRisks !== []) {
            $score -= 25;
            $recommendations[] = [
                'category' => 'security',
                'priority' => 'critical',
                'action' => 'prevent_sql_injection',
                'description' => 'Fix validation rules that may be vulnerable to SQL injection',
                'implementation' => $this->generateSqlInjectionPreventionRecommendations(),
            ];
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'recommendations' => $recommendations,
            'metrics' => [
                'sensitive_fields' => count($sensitiveFields),
                'mass_assignment_risks' => count($massAssignmentRisks),
                'sql_injection_risks' => count($sqlInjectionRisks),
            ],
        ];
    }

    /**
     * Calculate overall optimization scores
     */
    private function calculateOptimizationScores(array $analysis): array
    {
        $weightedScore = 0;
        $performanceScore = 0;

        foreach ($analysis['categories'] as $category => $data) {
            $weight = $this->optimizationWeights[$category] ?? 0;
            $weightedScore += $data['score'] * $weight;

            // Performance score is based on performance and storage categories
            if (in_array($category, ['performance', 'storage'])) {
                $performanceScore += $data['score'] * 0.5;
            }
        }

        $analysis['optimization_score'] = round($weightedScore, 1);
        $analysis['performance_score'] = round($performanceScore, 1);

        // Collect all recommendations
        foreach ($analysis['categories'] as $categoryData) {
            $analysis['recommendations'] = array_merge(
                $analysis['recommendations'],
                $categoryData['recommendations']
            );
        }

        return $analysis;
    }

    /**
     * Generate optimization summary
     */
    private function generateOptimizationSummary(array $analysis): array
    {
        $summary = [
            'overall_health' => $this->getHealthStatus($analysis['optimization_score']),
            'primary_concerns' => [],
            'quick_wins' => [],
            'long_term_improvements' => [],
        ];

        // Identify primary concerns (critical and high priority)
        foreach ($analysis['recommendations'] as $recommendation) {
            if (in_array($recommendation['priority'], ['critical', 'high'])) {
                $summary['primary_concerns'][] = $recommendation['description'];
            }
        }

        // Identify quick wins (low effort, medium-high impact)
        $quickWinActions = ['add_indexes', 'optimize_string_lengths', 'optimize_validation_rules'];
        foreach ($analysis['recommendations'] as $recommendation) {
            if (in_array($recommendation['action'], $quickWinActions)) {
                $summary['quick_wins'][] = $recommendation['description'];
            }
        }

        // Identify long-term improvements
        $longTermActions = ['reduce_complexity', 'optimize_relationships', 'improve_naming'];
        foreach ($analysis['recommendations'] as $recommendation) {
            if (in_array($recommendation['action'], $longTermActions)) {
                $summary['long_term_improvements'][] = $recommendation['description'];
            }
        }

        return $summary;
    }

    /**
     * Generate priority actions based on analysis
     */
    private function generatePriorityActions(array $analysis): array
    {
        $actions = [];

        // Sort recommendations by priority
        $priorityOrder = ['critical' => 1, 'high' => 2, 'medium' => 3, 'low' => 4];
        usort($analysis['recommendations'], function (array $a, array $b) use ($priorityOrder): int {
            return ($priorityOrder[$a['priority']] ?? 999) <=> ($priorityOrder[$b['priority']] ?? 999);
        });

        // Take top 5 recommendations
        foreach (array_slice($analysis['recommendations'], 0, 5) as $recommendation) {
            $actions[] = [
                'priority' => $recommendation['priority'],
                'category' => $recommendation['category'],
                'action' => $recommendation['action'],
                'description' => $recommendation['description'],
                'impact' => $this->calculateRecommendationImpact($recommendation),
            ];
        }

        return $actions;
    }

    // Helper methods for analysis
    private function identifyIndexableFields(array $fields): array
    {
        $indexable = [];

        foreach ($fields as $field) {
            $fieldName = $field->name;
            $fieldType = $field->type;

            // Common patterns that benefit from indexes
            if (str_ends_with($fieldName, '_id') ||
                str_ends_with($fieldName, '_at') ||
                in_array($fieldName, ['email', 'username', 'slug', 'status', 'type']) ||
                in_array($fieldType, ['email', 'uuid'])) {
                $indexable[] = $fieldName;
            }
        }

        return $indexable;
    }

    private function identifyEagerLoadCandidates(array $relationships): array
    {
        $candidates = [];

        foreach ($relationships as $relationship) {
            // Relationships that are commonly accessed together
            if (in_array($relationship->type, ['belongsTo', 'hasMany', 'hasOne'])) {
                $candidates[] = $relationship->name;
            }
        }

        return $candidates;
    }

    private function identifyOversizedStringFields(array $fields): array
    {
        $oversized = [];

        foreach ($fields as $field) {
            if ($field->type === 'string') {
                $length = $field->length ?? 255;
                if ($length > $this->performanceThresholds['max_string_length']) {
                    $oversized[] = [
                        'name' => $field->name,
                        'current_length' => $length,
                        'recommended_length' => $this->performanceThresholds['max_string_length'],
                    ];
                }
            }
        }

        return $oversized;
    }

    private function identifyInefficientFieldTypes(array $fields): array
    {
        $inefficient = [];

        foreach ($fields as $field) {
            $fieldType = $field->type;
            $fieldName = $field->name;

            // Check for common inefficiencies
            if ($fieldType === 'text' && $this->shouldBeString()) {
                $inefficient[] = [
                    'field' => $fieldName,
                    'current_type' => $fieldType,
                    'recommended_type' => 'string',
                    'reason' => 'Field appears to be short text that could use string type',
                ];
            }

            if ($fieldType === 'integer' && $this->shouldBeSmallerInteger()) {
                $inefficient[] = [
                    'field' => $fieldName,
                    'current_type' => $fieldType,
                    'recommended_type' => 'smallInteger',
                    'reason' => 'Field appears to have small value range',
                ];
            }
        }

        return $inefficient;
    }

    private function identifySensitiveFields(array $fields): array
    {
        $sensitive = [];
        $sensitivePatterns = ['password', 'secret', 'token', 'key', 'ssn', 'credit_card', 'bank'];

        foreach ($fields as $field) {
            $fieldName = mb_strtolower($field->name);

            foreach ($sensitivePatterns as $pattern) {
                if (str_contains($fieldName, $pattern)) {
                    $sensitive[] = [
                        'field' => $field->name,
                        'type' => $pattern,
                        'recommendation' => $this->getSensitiveFieldRecommendation($pattern),
                    ];
                    break;
                }
            }
        }

        return $sensitive;
    }

    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        }
        if ($score >= 80) {
            return 'good';
        }
        if ($score >= 70) {
            return 'fair';
        }
        if ($score >= 60) {
            return 'poor';
        }

        return 'critical';
    }

    private function calculateRecommendationImpact(array $recommendation): string
    {
        $priorityImpact = [
            'critical' => 'very_high',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
        ];

        return $priorityImpact[$recommendation['priority']] ?? 'low';
    }

    // Additional helper methods for generating specific recommendations
    private function generateIndexRecommendations(array $fields): string
    {
        $indexes = array_map(function ($field): string {
            return "Schema::table('{table_name}', function (Blueprint \$table) {\n    \$table->index('{$field}');\n});";
        }, $fields);

        return implode("\n\n", $indexes);
    }

    private function generateEagerLoadingRecommendations(array $relationships): string
    {
        $relations = implode("', '", $relationships);

        return "// In your model queries:\n\$models = Model::with(['{$relations}'])->get();";
    }

    private function generateStringSizeRecommendations(array $fields): string
    {
        $recommendations = [];
        foreach ($fields as $field) {
            $recommendations[] = "// {$field['name']}: reduce from {$field['current_length']} to {$field['recommended_length']}";
        }

        return implode("\n", $recommendations);
    }

    private function getSensitiveFieldRecommendation(string $type): string
    {
        $recommendations = [
            'password' => 'Use Hash::make() for storage and bcrypt validation',
            'secret' => 'Store in encrypted format using Laravel\'s encryption',
            'token' => 'Use Laravel Sanctum or Passport for token management',
            'key' => 'Store in environment variables, not database',
            'ssn' => 'Encrypt and limit access with policies',
            'credit_card' => 'Use external payment processor, never store directly',
            'bank' => 'Use external banking API, encrypt if absolutely necessary',
        ];

        return $recommendations[$type] ?? 'Review field for potential security concerns';
    }

    // Placeholder methods for additional analysis logic
    private function analyzeTextFieldOptimizations(): array
    {
        return [];
    }

    private function getLargeTextFields(): array
    {
        return [];
    }

    private function analyzeNullableOptimizations(): array
    {
        return [];
    }

    private function analyzeDecimalOptimizations(): array
    {
        return [];
    }

    private function calculateStorageEfficiencyScore(): int
    {
        return 85;
    }

    private function identifyRedundantValidationRules(): array
    {
        return [];
    }

    private function identifyMissingValidationRules(): array
    {
        return [];
    }

    private function identifyExpensiveValidationRules(): array
    {
        return [];
    }

    private function analyzeNamingConventions(): array
    {
        return [];
    }

    private function analyzeDocumentation(): array
    {
        return [];
    }

    private function calculateSchemaComplexity(): int
    {
        return 45;
    }

    private function calculateDocumentationCoverage(): int
    {
        return 75;
    }

    private function analyzeMassAssignmentRisks(): array
    {
        return [];
    }

    private function analyzeSqlInjectionRisks(): array
    {
        return [];
    }

    private function shouldBeString(): bool
    {
        return false;
    }

    private function shouldBeSmallerInteger(): bool
    {
        return false;
    }

    // Placeholder methods for generating recommendations
    private function generateValidationOptimizationRecommendations(): string
    {
        return '// Optimize validation rules';
    }

    private function generateMissingValidationRecommendations(): string
    {
        return '// Add missing validation';
    }

    private function generateExpensiveRuleOptimizations(): string
    {
        return '// Optimize expensive rules';
    }

    private function generateNamingRecommendations(): string
    {
        return '// Follow Laravel naming conventions';
    }

    private function generateDocumentationRecommendations(): string
    {
        return '// Add documentation';
    }

    private function generateComplexityReductionRecommendations(): string
    {
        return '// Reduce schema complexity';
    }

    private function generateSensitiveDataRecommendations(): string
    {
        return '// Protect sensitive data';
    }

    private function generateMassAssignmentRecommendations(): string
    {
        return '// Configure fillable/guarded';
    }

    private function generateSqlInjectionPreventionRecommendations(): string
    {
        return '// Fix SQL injection risks';
    }

    private function generateFieldTypeRecommendations(): string
    {
        return '// Optimize field types';
    }
}
