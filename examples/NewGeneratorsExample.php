<?php

declare(strict_types=1);

/**
 * New Generators Example - Observer, Service, Action, Rule
 * 
 * This example demonstrates the 4 new generators introduced in Laravel ModelSchema v2.0:
 * - ObserverGenerator: Eloquent observer event handlers
 * - ServiceGenerator: Business logic service classes  
 * - ActionGenerator: Single-responsibility action classes
 * - RuleGenerator: Custom validation rule classes
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

echo "🚀 Laravel ModelSchema - New Generators Example (v2.0)\n";
echo "====================================================\n\n";

// Create a sample schema
$schema = new ModelSchema(
    name: 'User',
    table: 'users',
    fields: [
        new Field('id', 'bigInteger'),
        new Field('name', 'string', false), // not nullable
        new Field('email', 'string', false, true), // not nullable, unique
        new Field('email_verified_at', 'timestamp', true), // nullable
        new Field('password', 'string'),
        new Field('status', 'enum'),
        new Field('profile_image', 'string', true), // nullable
        new Field('last_login_at', 'timestamp', true), // nullable
        new Field('created_at', 'timestamp'),
        new Field('updated_at', 'timestamp'),
    ],
    relationships: [
        new Relationship('posts', 'hasMany', 'App\Models\Post'),
        new Relationship('roles', 'belongsToMany', 'App\Models\Role', 'user_roles'),
        new Relationship('profile', 'hasOne', 'App\Models\UserProfile'),
    ]
);

$generationService = new GenerationService();

echo "📋 Schema: {$schema->name} (table: {$schema->table})\n";
echo "📊 Fields: " . count($schema->fields) . "\n";
echo "🔗 Relationships: " . count($schema->relationships) . "\n\n";

// 1. Observer Generator
echo "🔍 1. OBSERVER GENERATOR\n";
echo "========================\n";

$observerResult = $generationService->generateObservers($schema, [
    'events' => ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted'],
    'namespace' => 'App\Observers',
]);

echo "✅ Observer fragment generated\n";
$observerData = json_decode($observerResult['json'], true);
echo "📦 Class: {$observerData['observers']['class_name']}\n";
echo "📂 Namespace: {$observerData['observers']['namespace']}\n";
echo "🎯 Events: " . implode(', ', array_keys($observerData['observers']['events'])) . "\n\n";

// 2. Service Generator  
echo "⚙️ 2. SERVICE GENERATOR\n";
echo "=======================\n";

$serviceResult = $generationService->generateServices($schema, [
    'namespace' => 'App\Services',
    'repository_pattern' => true,
    'business_methods' => true,
    'caching' => true,
]);

echo "✅ Service fragment generated\n";
$serviceData = json_decode($serviceResult['json'], true);
echo "📦 Class: {$serviceData['services']['class_name']}\n";
echo "📂 Namespace: {$serviceData['services']['namespace']}\n";
echo "🔧 Methods: " . implode(', ', array_keys($serviceData['services']['methods'])) . "\n";
echo "📚 Dependencies: " . implode(', ', $serviceData['services']['dependencies']) . "\n\n";

// 3. Action Generator
echo "⚡ 3. ACTION GENERATOR\n";
echo "=====================\n";

$actionResult = $generationService->generateActions($schema, [
    'namespace' => 'App\Actions\User',
    'crud_actions' => true,
    'business_actions' => true,
    'custom_actions' => [
        'SendWelcomeEmail',
        'UpdateLastLogin',
        'SuspendAccount',
    ],
]);

echo "✅ Action fragments generated\n";
$actionData = json_decode($actionResult['json'], true);
echo "📦 CRUD Actions: " . count($actionData['actions']['crud_actions']) . "\n";
foreach ($actionData['actions']['crud_actions'] as $action) {
    echo "   - {$action['class_name']}\n";
}
echo "🚀 Business Actions: " . count($actionData['actions']['business_actions']) . "\n";
foreach ($actionData['actions']['business_actions'] as $action) {
    echo "   - {$action['class_name']}\n";
}
echo "\n";

// 4. Rule Generator
echo "📏 4. RULE GENERATOR\n";
echo "===================\n";

$ruleResult = $generationService->generateRules($schema, [
    'namespace' => 'App\Rules',
    'business_rules' => true,
    'foreign_key_rules' => true,
    'unique_rules' => true,
    'complex_rules' => true,
]);

echo "✅ Rule fragments generated\n";
$ruleData = json_decode($ruleResult['json'], true);
echo "🏢 Business Rules: " . count($ruleData['rules']['business_rules']) . "\n";
foreach ($ruleData['rules']['business_rules'] as $rule) {
    echo "   - {$rule['class_name']} (for {$rule['field']})\n";
}
echo "🔑 Foreign Key Rules: " . count($ruleData['rules']['foreign_key_rules']) . "\n";
foreach ($ruleData['rules']['foreign_key_rules'] as $rule) {
    echo "   - {$rule['class_name']} (for {$rule['field']})\n";
}
echo "\n";

// 5. Generate Multiple New Components
echo "🔄 5. GENERATE MULTIPLE NEW COMPONENTS\n";
echo "=====================================\n";

$multipleResult = $generationService->generateMultiple($schema, [
    'observers', 'services', 'actions', 'rules'
], [
    'enhanced' => true,
    'namespace_prefix' => 'App',
]);

echo "✅ Multiple components generated\n";
echo "📊 Components in result:\n";
foreach ($multipleResult['individual_results'] as $componentType => $result) {
    $data = json_decode($result['json'], true);
    echo "   - {$componentType}: ✅\n";
}
echo "\n";

// 6. Generate Everything (All 13 Generators)
echo "🏆 6. GENERATE ALL COMPONENTS (13 GENERATORS)\n";
echo "=============================================\n";

$allResult = $generationService->generateAll($schema, [
    'model' => true,
    'migration' => true,
    'requests' => true,
    'resources' => true,
    'factory' => true,
    'seeder' => true,
    'controllers' => true,
    'tests' => true,
    'policies' => true,
    'observers' => true,    // New
    'services' => true,     // New
    'actions' => true,      // New
    'rules' => true,        // New
]);

echo "✅ All 13 generators executed successfully!\n";
echo "📊 Generated components:\n";

$componentCounts = [
    'Core Laravel' => ['model', 'migration', 'requests', 'resources', 'factory', 'seeder'],
    'Advanced' => ['controllers', 'tests', 'policies'],
    'Business Logic (New)' => ['observers', 'services', 'actions', 'rules'],
];

foreach ($componentCounts as $category => $components) {
    echo "\n📂 {$category}:\n";
    foreach ($components as $component) {
        if (isset($allResult[$component])) {
            echo "   ✅ {$component}\n";
        }
    }
}

echo "\n";

// 7. Show Fragment Examples
echo "📋 7. FRAGMENT STRUCTURE EXAMPLES\n";
echo "=================================\n";

echo "\n🔍 Observer Fragment Structure:\n";
echo "```json\n";
echo json_encode(json_decode($observerResult['json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n```\n\n";

echo "⚙️ Service Fragment Structure (abbreviated):\n";
echo "```json\n";
$serviceExample = [
    'services' => [
        'class_name' => $serviceData['services']['class_name'],
        'namespace' => $serviceData['services']['namespace'],
        'methods' => array_slice($serviceData['services']['methods'], 0, 2, true),
        '...' => 'and more methods'
    ]
];
echo json_encode($serviceExample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n```\n\n";

// 8. Integration Example
echo "🔗 8. INTEGRATION EXAMPLE\n";
echo "=========================\n";

echo "Example of how parent applications can use these fragments:\n\n";

echo "```php\n";
echo "// In your parent application (TurboMaker, Arc, etc.)\n";
echo "\$fragments = \$generationService->generateMultiple(\$schema, [\n";
echo "    'observers', 'services', 'actions', 'rules'\n";
echo "]);\n\n";

echo "// Extract observer fragment\n";
echo "\$observerData = json_decode(\$fragments['individual_results']['observers']['json'], true);\n";
echo "\$observerClass = \$observerData['observers']['class_name'];\n\n";

echo "// Generate observer file using your template\n";
echo "\$observerContent = view('your-app.observer-template', [\n";
echo "    'class_name' => \$observerClass,\n";
echo "    'namespace' => \$observerData['observers']['namespace'],\n";
echo "    'events' => \$observerData['observers']['events'],\n";
echo "    'model' => \$observerData['observers']['model']\n";
echo "])->render();\n\n";

echo "file_put_contents(\n";
echo "    app_path(\"Observers/{\$observerClass}.php\"),\n";
echo "    \$observerContent\n";
echo ");\n";
echo "```\n\n";

echo "🎉 New Generators Example Complete!\n";
echo "===================================\n\n";

echo "📚 Next Steps:\n";
echo "- Review the generated fragments in detail\n";
echo "- Integrate these generators into your parent application\n";
echo "- Customize the generation options for your specific needs\n";
echo "- Explore the enhanced features and validation options\n\n";

echo "📖 Documentation:\n";
echo "- Architecture Guide: docs/ARCHITECTURE.md\n";
echo "- Fragment Examples: examples/FRAGMENTS.md\n";
echo "- Integration Guide: examples/IntegrationExample.php\n\n";

echo "Done! ✨\n";
