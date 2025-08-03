<?php

declare(strict_types=1);

/**
 * Schema Optimization Service Usage Examples
 *
 * This file demonstrates how to use the SchemaOptimizationService
 * to analyze and optimize Laravel model schemas.
 */

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\SchemaOptimizationService;

// Example 1: Basic Schema Analysis
function basicSchemaAnalysis()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Create a schema from configuration
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger', 'primary' => true],
            'email' => ['type' => 'string', 'length' => 255, 'unique' => true],
            'password' => ['type' => 'string'],
            'name' => ['type' => 'string', 'length' => 100],
            'description' => ['type' => 'text'],
            'api_token' => ['type' => 'string', 'length' => 80],
            'status' => ['type' => 'string', 'length' => 20],
            'created_at' => ['type' => 'timestamp'],
            'updated_at' => ['type' => 'timestamp'],
        ],
        'relationships' => [
            'posts' => ['type' => 'hasMany', 'model' => 'Post'],
            'profile' => ['type' => 'hasOne', 'model' => 'Profile'],
        ],
    ];

    $schema = ModelSchema::fromArray('User', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== Basic Schema Analysis ===\n";
    echo "Model: {$analysis['model']}\n";
    echo "Optimization Score: {$analysis['optimization_score']}/100\n";
    echo "Performance Score: {$analysis['performance_score']}/100\n";
    echo "Overall Health: {$analysis['summary']['overall_health']}\n";
    echo 'Total Recommendations: '.count($analysis['recommendations'])."\n\n";

    return $analysis;
}

// Example 2: Performance Optimization Analysis
function performanceOptimizationAnalysis()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Schema with performance issues
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'user_id' => ['type' => 'bigInteger'], // Should be indexed
            'email' => ['type' => 'email'], // Should be indexed
            'slug' => ['type' => 'string'], // Should be indexed
            'title' => ['type' => 'string', 'length' => 1000], // Too large
            'content' => ['type' => 'text'],
            'created_at' => ['type' => 'timestamp'], // Should be indexed
            'status' => ['type' => 'string'], // Should be indexed
        ],
        'relationships' => [
            'user' => ['type' => 'belongsTo', 'model' => 'User'],
            'comments' => ['type' => 'hasMany', 'model' => 'Comment'],
            'tags' => ['type' => 'belongsToMany', 'model' => 'Tag'],
            'category' => ['type' => 'belongsTo', 'model' => 'Category'],
        ],
    ];

    $schema = ModelSchema::fromArray('Article', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== Performance Analysis ===\n";

    // Check for indexing recommendations
    $performanceRecommendations = collect($analysis['categories']['performance']['recommendations']);

    if ($indexRecommendation = $performanceRecommendations->firstWhere('action', 'add_indexes')) {
        echo "Index Recommendations:\n";
        echo $indexRecommendation['implementation']."\n\n";
    }

    // Check for eager loading recommendations
    if ($eagerLoadRecommendation = $performanceRecommendations->firstWhere('action', 'prevent_n_plus_one')) {
        echo "Eager Loading Recommendations:\n";
        echo $eagerLoadRecommendation['implementation']."\n\n";
    }

    // Performance metrics
    $metrics = $analysis['categories']['performance']['metrics'];
    echo "Performance Metrics:\n";
    echo "- Indexable fields: {$metrics['indexable_fields']}\n";
    echo "- Relationships: {$metrics['relationships_count']}\n";
    echo "- Large text fields: {$metrics['large_text_fields']}\n\n";

    return $analysis;
}

// Example 3: Security Analysis
function securityAnalysis()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Schema with security concerns
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'username' => ['type' => 'string'],
            'password' => ['type' => 'string'], // Security concern
            'api_secret' => ['type' => 'string'], // Security concern
            'access_token' => ['type' => 'text'], // Security concern
            'credit_card_number' => ['type' => 'string'], // Major security concern
            'ssn' => ['type' => 'string'], // Major security concern
            'bank_account' => ['type' => 'string'], // Major security concern
        ],
    ];

    $schema = ModelSchema::fromArray('UserSensitive', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== Security Analysis ===\n";
    echo "Security Score: {$analysis['categories']['security']['score']}/100\n";

    $securityRecommendations = collect($analysis['categories']['security']['recommendations']);

    if ($sensitiveDataRecommendation = $securityRecommendations->firstWhere('action', 'protect_sensitive_data')) {
        echo "\nSensitive Data Protection:\n";
        foreach ($sensitiveDataRecommendation['fields'] as $field) {
            echo "- {$field['field']} ({$field['type']}): {$field['recommendation']}\n";
        }
    }

    // Security metrics
    $metrics = $analysis['categories']['security']['metrics'];
    echo "\nSecurity Metrics:\n";
    echo "- Sensitive fields: {$metrics['sensitive_fields']}\n";
    echo "- Mass assignment risks: {$metrics['mass_assignment_risks']}\n";
    echo "- SQL injection risks: {$metrics['sql_injection_risks']}\n\n";

    return $analysis;
}

// Example 4: Storage Optimization
function storageOptimization()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Schema with storage inefficiencies
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'title' => ['type' => 'string', 'length' => 2000], // Too large for title
            'short_description' => ['type' => 'text'], // Could be string
            'code' => ['type' => 'string', 'length' => 10], // Could be smaller
            'price' => ['type' => 'decimal', 'precision' => 20, 'scale' => 8], // Over-precise
            'quantity' => ['type' => 'integer'], // Could be smallInteger
            'is_active' => ['type' => 'string'], // Should be boolean
        ],
    ];

    $schema = ModelSchema::fromArray('Product', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== Storage Optimization ===\n";
    echo "Storage Score: {$analysis['categories']['storage']['score']}/100\n";

    $storageRecommendations = collect($analysis['categories']['storage']['recommendations']);

    // String length optimization
    if ($stringOptimization = $storageRecommendations->firstWhere('action', 'optimize_string_lengths')) {
        echo "\nString Length Optimizations:\n";
        echo $stringOptimization['implementation']."\n";
    }

    // Field type optimization
    if ($typeOptimization = $storageRecommendations->firstWhere('action', 'optimize_field_types')) {
        echo "\nField Type Optimizations:\n";
        echo $typeOptimization['implementation']."\n";
    }

    // Storage metrics
    $metrics = $analysis['categories']['storage']['metrics'];
    echo "\nStorage Metrics:\n";
    echo "- Oversized strings: {$metrics['oversized_strings']}\n";
    echo "- Inefficient types: {$metrics['inefficient_types']}\n";
    echo "- Storage efficiency score: {$metrics['total_storage_score']}/100\n\n";

    return $analysis;
}

// Example 5: Comprehensive Analysis with Priority Actions
function comprehensiveAnalysis()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Complex schema with multiple issues
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'user_id' => ['type' => 'bigInteger'], // Missing index
            'email' => ['type' => 'string', 'length' => 500], // Oversized
            'password' => ['type' => 'string'], // Security issue
            'description' => ['type' => 'string', 'length' => 5000], // Should be text
            'api_key' => ['type' => 'string'], // Security issue
            'status' => ['type' => 'string'], // Missing index
            'priority' => ['type' => 'integer'], // Could be smallInteger
            'metadata' => ['type' => 'json'],
            'created_at' => ['type' => 'timestamp'],
        ],
        'relationships' => [
            'user' => ['type' => 'belongsTo', 'model' => 'User'],
            'attachments' => ['type' => 'hasMany', 'model' => 'Attachment'],
            'comments' => ['type' => 'hasMany', 'model' => 'Comment'],
        ],
    ];

    $schema = ModelSchema::fromArray('Task', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== Comprehensive Analysis ===\n";
    echo "Model: {$analysis['model']}\n";
    echo "Overall Score: {$analysis['optimization_score']}/100\n";
    echo "Health Status: {$analysis['summary']['overall_health']}\n\n";

    // Category scores
    echo "Category Scores:\n";
    foreach ($analysis['categories'] as $category => $data) {
        echo '- '.ucfirst($category).": {$data['score']}/100\n";
    }
    echo "\n";

    // Priority actions
    echo "Priority Actions (Top 5):\n";
    foreach ($analysis['priority_actions'] as $i => $action) {
        echo ($i + 1).". [{$action['priority']}] {$action['description']}\n";
        echo "   Category: {$action['category']}, Impact: {$action['impact']}\n";
    }
    echo "\n";

    // Summary insights
    $summary = $analysis['summary'];

    if (! empty($summary['primary_concerns'])) {
        echo "Primary Concerns:\n";
        foreach ($summary['primary_concerns'] as $concern) {
            echo "- $concern\n";
        }
        echo "\n";
    }

    if (! empty($summary['quick_wins'])) {
        echo "Quick Wins:\n";
        foreach ($summary['quick_wins'] as $win) {
            echo "- $win\n";
        }
        echo "\n";
    }

    if (! empty($summary['long_term_improvements'])) {
        echo "Long-term Improvements:\n";
        foreach ($summary['long_term_improvements'] as $improvement) {
            echo "- $improvement\n";
        }
        echo "\n";
    }

    return $analysis;
}

// Example 6: CI/CD Integration
function cicdIntegration()
{
    $optimizationService = app(SchemaOptimizationService::class);

    // Load schema from file (in real scenario)
    $schemaConfig = [
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'name' => ['type' => 'string'],
            'api_secret' => ['type' => 'string'], // Security issue
        ],
    ];

    $schema = ModelSchema::fromArray('CITest', $schemaConfig);
    $analysis = $optimizationService->analyzeSchema($schema);

    echo "=== CI/CD Integration Example ===\n";

    // Quality gate: minimum optimization score
    $minimumScore = 75;
    if ($analysis['optimization_score'] < $minimumScore) {
        echo "❌ QUALITY GATE FAILED\n";
        echo "Optimization score {$analysis['optimization_score']} is below minimum {$minimumScore}\n\n";

        // Show blocking issues
        $criticalActions = array_filter($analysis['priority_actions'],
            fn ($action) => $action['priority'] === 'critical');

        if (! empty($criticalActions)) {
            echo "Critical Issues:\n";
            foreach ($criticalActions as $action) {
                echo "- {$action['description']}\n";
            }
        }

        return false; // Would exit(1) in real CI
    }

    echo "✅ QUALITY GATE PASSED\n";
    echo "Optimization score: {$analysis['optimization_score']}\n";

    // Show warnings for monitoring
    $highPriorityActions = array_filter($analysis['priority_actions'],
        fn ($action) => $action['priority'] === 'high');

    if (! empty($highPriorityActions)) {
        echo "\n⚠️  High Priority Recommendations:\n";
        foreach ($highPriorityActions as $action) {
            echo "- {$action['description']}\n";
        }
    }

    return true;
}

// Example 7: Monitoring and Alerting
function monitoringExample()
{
    $optimizationService = app(SchemaOptimizationService::class);

    $schemas = [
        'User' => ['fields' => ['id' => ['type' => 'bigInteger'], 'password' => ['type' => 'string']]],
        'Product' => ['fields' => ['id' => ['type' => 'bigInteger'], 'price' => ['type' => 'decimal']]],
        'Order' => ['fields' => ['id' => ['type' => 'bigInteger'], 'secret_key' => ['type' => 'string']]],
    ];

    echo "=== Monitoring Example ===\n";

    foreach ($schemas as $modelName => $config) {
        $schema = ModelSchema::fromArray($modelName, $config);
        $analysis = $optimizationService->analyzeSchema($schema);

        echo "Model: {$modelName}\n";
        echo "Score: {$analysis['optimization_score']}/100 ({$analysis['summary']['overall_health']})\n";

        // Alert conditions
        if ($analysis['categories']['security']['score'] < 80) {
            echo "🚨 SECURITY ALERT: Low security score ({$analysis['categories']['security']['score']})\n";
        }

        if ($analysis['categories']['performance']['score'] < 70) {
            echo "⚠️  PERFORMANCE WARNING: Performance issues detected\n";
        }

        echo "---\n";
    }
}

// Example usage (uncomment to run)
/*
echo "Schema Optimization Service Examples\n";
echo "====================================\n\n";

basicSchemaAnalysis();
performanceOptimizationAnalysis();
securityAnalysis();
storageOptimization();
comprehensiveAnalysis();
cicdIntegration();
monitoringExample();
*/
