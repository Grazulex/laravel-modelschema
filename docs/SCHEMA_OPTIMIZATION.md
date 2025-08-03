# Schema Optimization Service

The SchemaOptimizationService provides comprehensive analysis and optimization recommendations for Laravel model schemas. It evaluates your schemas across multiple dimensions and provides actionable insights to improve performance, storage efficiency, validation, maintainability, and security.

## Overview

The service analyzes schemas across five key categories:

1. **Performance** - Database query optimization, indexing, relationships
2. **Storage** - Field type efficiency, size optimization
3. **Validation** - Rule efficiency and completeness  
4. **Maintenance** - Code quality, naming conventions, documentation
5. **Security** - Data protection, access controls, vulnerability prevention

## Basic Usage

```php
use Grazulex\LaravelModelschema\Services\SchemaOptimizationService;
use Grazulex\LaravelModelschema\Schema\ModelSchema;

// Create the service (typically injected via DI)
$optimizationService = app(SchemaOptimizationService::class);

// Load or create a schema
$schema = ModelSchema::fromYamlFile('user.schema.yaml');

// Analyze the schema
$analysis = $optimizationService->analyzeSchema($schema);
```

## Analysis Results

The service returns a comprehensive analysis with the following structure:

```php
[
    'model' => 'User',
    'timestamp' => '2025-08-03T10:30:00Z',
    'performance_score' => 85.5,
    'optimization_score' => 78.2,
    'recommendations' => [...], // All recommendations across categories
    'categories' => [
        'performance' => [...],
        'storage' => [...],
        'validation' => [...],
        'maintenance' => [...],
        'security' => [...]
    ],
    'summary' => [
        'overall_health' => 'good',
        'primary_concerns' => [...],
        'quick_wins' => [...],
        'long_term_improvements' => [...]
    ],
    'priority_actions' => [...] // Top 5 recommendations sorted by priority
]
```

## Performance Analysis

The service identifies performance optimization opportunities:

### Indexing Recommendations
```php
// Automatically detects fields that would benefit from indexes
$performanceCategory = $analysis['categories']['performance'];

// Fields like user_id, email, slug, created_at are flagged for indexing
if ($indexRecommendation = collect($performanceCategory['recommendations'])
    ->firstWhere('action', 'add_indexes')) {
    
    echo "Add indexes for: " . implode(', ', $indexRecommendation['fields']);
    echo $indexRecommendation['implementation']; // Shows migration code
}
```

### Relationship Optimization
```php
// Detects N+1 query potential
if ($eagerLoadRecommendation = collect($performanceCategory['recommendations'])
    ->firstWhere('action', 'prevent_n_plus_one')) {
    
    echo $eagerLoadRecommendation['implementation'];
    // Output: $models = Model::with(['user', 'comments'])->get();
}
```

### Performance Metrics
```php
$metrics = $analysis['categories']['performance']['metrics'];
echo "Indexable fields: " . $metrics['indexable_fields'];
echo "Relationships: " . $metrics['relationships_count'];
echo "Large text fields: " . $metrics['large_text_fields'];
```

## Storage Optimization

### String Length Optimization
```php
// Detects oversized string fields
$storageCategory = $analysis['categories']['storage'];

if ($stringOptimization = collect($storageCategory['recommendations'])
    ->firstWhere('action', 'optimize_string_lengths')) {
    
    foreach ($stringOptimization['fields'] as $field) {
        echo "Field {$field['name']}: reduce from {$field['current_length']} to {$field['recommended_length']}";
    }
}
```

### Field Type Efficiency
```php
// Suggests more efficient field types
if ($typeOptimization = collect($storageCategory['recommendations'])
    ->firstWhere('action', 'optimize_field_types')) {
    
    foreach ($typeOptimization['fields'] as $field) {
        echo "Field {$field['field']}: change from {$field['current_type']} to {$field['recommended_type']}";
        echo "Reason: {$field['reason']}";
    }
}
```

## Security Analysis

### Sensitive Data Detection
```php
$securityCategory = $analysis['categories']['security'];

if ($sensitiveDataRecommendation = collect($securityCategory['recommendations'])
    ->firstWhere('action', 'protect_sensitive_data')) {
    
    foreach ($sensitiveDataRecommendation['fields'] as $field) {
        echo "Field {$field['field']} ({$field['type']}): {$field['recommendation']}";
    }
}
```

### Common Security Recommendations
- **Password fields**: Use Hash::make() for storage and bcrypt validation
- **Secret tokens**: Store in encrypted format using Laravel's encryption
- **API keys**: Store in environment variables, not database
- **PII data**: Encrypt and limit access with policies

## Validation Optimization

### Redundant Rules Detection
```php
$validationCategory = $analysis['categories']['validation'];

if ($redundantRules = collect($validationCategory['recommendations'])
    ->firstWhere('action', 'optimize_validation_rules')) {
    
    echo "Remove redundant validation rules: ";
    print_r($redundantRules['rules']);
}
```

### Missing Validation
```php
if ($missingValidation = collect($validationCategory['recommendations'])
    ->firstWhere('action', 'add_missing_validation')) {
    
    echo "Add validation for important fields: ";
    print_r($missingValidation['fields']);
}
```

## Maintenance Analysis

### Naming Conventions
```php
$maintenanceCategory = $analysis['categories']['maintenance'];

if ($namingIssues = collect($maintenanceCategory['recommendations'])
    ->firstWhere('action', 'improve_naming')) {
    
    echo "Follow Laravel naming conventions: ";
    print_r($namingIssues['issues']);
}
```

### Complexity Analysis
```php
$complexityScore = $maintenanceCategory['metrics']['complexity_score'];
if ($complexityScore > 80) {
    echo "Schema complexity is high ($complexityScore). Consider breaking into smaller schemas.";
}
```

## Optimization Scores

### Understanding Scores
- **Performance Score**: Focuses on query efficiency and database performance
- **Optimization Score**: Weighted average across all categories
- **Category Scores**: Individual scores for each analysis category

```php
echo "Overall optimization score: {$analysis['optimization_score']}/100";
echo "Performance score: {$analysis['performance_score']}/100";

// Health status based on optimization score
echo "Health status: {$analysis['summary']['overall_health']}";
// Possible values: excellent (90+), good (80+), fair (70+), poor (60+), critical (<60)
```

## Priority Actions

The service automatically prioritizes recommendations:

```php
foreach ($analysis['priority_actions'] as $action) {
    echo "Priority: {$action['priority']}";
    echo "Category: {$action['category']}";
    echo "Action: {$action['action']}";
    echo "Description: {$action['description']}";
    echo "Impact: {$action['impact']}";
    echo "---";
}
```

## Optimization Summary

```php
$summary = $analysis['summary'];

echo "Overall Health: {$summary['overall_health']}";

if (!empty($summary['primary_concerns'])) {
    echo "Primary Concerns:";
    foreach ($summary['primary_concerns'] as $concern) {
        echo "- $concern";
    }
}

if (!empty($summary['quick_wins'])) {
    echo "Quick Wins:";
    foreach ($summary['quick_wins'] as $win) {
        echo "- $win";
    }
}

if (!empty($summary['long_term_improvements'])) {
    echo "Long-term Improvements:";
    foreach ($summary['long_term_improvements'] as $improvement) {
        echo "- $improvement";
    }
}
```

## Configuration

The service uses configurable thresholds for optimization analysis:

```php
// These can be customized in your service configuration
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
```

## Integration Examples

### CI/CD Pipeline
```php
// In your deployment script
$analysis = $optimizationService->analyzeSchema($schema);

if ($analysis['optimization_score'] < 70) {
    echo "Schema optimization score too low: {$analysis['optimization_score']}";
    exit(1);
}

// Log recommendations for review
foreach ($analysis['priority_actions'] as $action) {
    if ($action['priority'] === 'critical') {
        echo "CRITICAL: {$action['description']}";
    }
}
```

### Development Workflow
```php
// During development, get optimization feedback
$analysis = $optimizationService->analyzeSchema($schema);

echo "Quick optimization wins:";
foreach ($analysis['summary']['quick_wins'] as $win) {
    echo "- $win";
}

// Focus on high-impact, low-effort improvements
$quickWinActions = collect($analysis['recommendations'])
    ->whereIn('action', ['add_indexes', 'optimize_string_lengths', 'optimize_validation_rules']);
```

### Monitoring and Alerts
```php
// Set up monitoring for schema health
$analysis = $optimizationService->analyzeSchema($schema);

if ($analysis['categories']['security']['score'] < 80) {
    // Alert: Security recommendations need attention
    Log::warning('Schema security score low', [
        'model' => $analysis['model'],
        'score' => $analysis['categories']['security']['score'],
        'recommendations' => $analysis['categories']['security']['recommendations']
    ]);
}
```

## Best Practices

1. **Regular Analysis**: Run optimization analysis as part of your regular development workflow
2. **Prioritize Security**: Always address critical and high-priority security recommendations first
3. **Performance Focus**: Index fields that are frequently queried or used in WHERE clauses
4. **Gradual Improvement**: Implement quick wins first, then tackle long-term improvements
5. **Monitor Trends**: Track optimization scores over time to ensure schema quality doesn't degrade

## Service Dependencies

The SchemaOptimizationService depends on:
- `EnhancedValidationService` - For validation analysis capabilities
- `LoggingService` - For detailed operation logging and performance tracking
- `ModelSchema` - For schema parsing and field/relationship access

These dependencies are automatically injected when using Laravel's service container.

## Error Handling

The service includes comprehensive error handling:

```php
try {
    $analysis = $optimizationService->analyzeSchema($schema);
} catch (SchemaException $e) {
    Log::error('Schema optimization failed', [
        'message' => $e->getMessage(),
        'schema' => $schema->name
    ]);
    
    // Fallback or alternative handling
}
```

All optimization operations are logged for debugging and monitoring purposes.
