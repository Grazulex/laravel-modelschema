<?php

declare(strict_types=1);

/**
 * Complete Integration Example with Trait-Based Architecture
 *
 * This example demonstrates how parent applications (like TurboMaker, Arc)
 * can integrate with Laravel ModelSchema's modern trait-based plugin system
 * to generate insertable fragments.
 */

namespace App\Examples;

use Exception;
use Grazulex\LaravelModelschema\Services\GenerationService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use InvalidArgumentException;

class IntegrationExample
{
    private SchemaService $schemaService;
    private GenerationService $generationService;
    private FieldTypePluginManager $pluginManager;

    public function __construct()
    {
        $this->schemaService = new SchemaService();
        $this->generationService = new GenerationService();
        $this->pluginManager = new FieldTypePluginManager();
        
        // Register trait-based plugins
        $this->registerTraitBasedPlugins();
    }

    /**
     * Register modern trait-based plugins
     */
    private function registerTraitBasedPlugins(): void
    {
        // Register URL plugin with trait-based configuration
        $this->pluginManager->registerPlugin(new UrlFieldTypePlugin());
        
        // Register JSON Schema plugin with trait-based validation
        $this->pluginManager->registerPlugin(new JsonSchemaFieldTypePlugin());
    }

    /**
     * Complete workflow example with trait-based plugins for parent applications
     */
    public function completeWorkflowExample(): array
    {
        // 1. Generate complete YAML from stub with parent app data and trait-based fields
        $completeYaml = $this->schemaService->generateCompleteYamlFromStub(
            'user.schema.stub',
            [
                'MODEL_NAME' => 'User',
                'TABLE_NAME' => 'users',
                'NAMESPACE' => 'App\\Models',
            ],
            [
                // Parent app extensions
                'turbomaker' => [
                    'views' => ['index', 'create', 'edit', 'show'],
                    'routes' => ['api', 'web'],
                    'controllers' => ['UserController', 'ApiUserController'],
                    // Trait-based field configurations
                    'field_traits' => [
                        'url_fields' => [
                            'schemes' => ['https'],
                            'verify_ssl' => true,
                            'timeout' => 30
                        ],
                        'json_fields' => [
                            'strict_validation' => true,
                            'schema_format' => 'draft-07'
                        ]
                    ]
                ],
                'arc' => [
                    'permissions' => ['view', 'create', 'edit', 'delete'],
                    'roles' => ['admin', 'user'],
                ],
            ]
        );

        // 2. Validate the complete YAML (focuses on core section)
        $errors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);
        if (! empty($errors)) {
            throw new InvalidArgumentException('Schema validation failed: '.implode(', ', $errors));
        }

        // 3. Extract all generation data as insertable fragments
        $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($completeYaml);

        return $generationData;
    }

    /**
     * Example of using individual fragments in parent app generation
     */
    public function useFragmentsExample(): void
    {
        $generationData = $this->completeWorkflowExample();

        // Extract each fragment for use in parent app templates
        $modelData = json_decode($generationData['generation_data']['model']['json'], true);
        $migrationData = json_decode($generationData['generation_data']['migration']['json'], true);
        $requestsData = json_decode($generationData['generation_data']['requests']['json'], true);
        $resourcesData = json_decode($generationData['generation_data']['resources']['json'], true);
        $factoryData = json_decode($generationData['generation_data']['factory']['json'], true);
        $seederData = json_decode($generationData['generation_data']['seeder']['json'], true);

        // Example: Parent app uses these fragments in its own templates
        $this->generateParentAppModel($modelData);
        $this->generateParentAppMigration($migrationData);
        $this->generateParentAppRequests($requestsData);
        // ... etc
    }

    /**
     * Core/Extension separation example
     */
    public function coreExtensionSeparationExample(string $yamlContent): array
    {
        // Parse and separate core schema from extensions
        $separated = $this->schemaService->parseAndSeparateSchema($yamlContent);

        echo "Core Schema:\n";
        print_r($separated['core']);

        echo "\nExtensions:\n";
        print_r($separated['extensions']);

        // Validate only the core part
        $coreErrors = $this->schemaService->validateCoreSchema($yamlContent);
        if (! empty($coreErrors)) {
            echo 'Core validation errors: '.implode(', ', $coreErrors);
        }

        return $separated;
    }

    /**
     * Direct generation example (without stubs)
     */
    public function directGenerationExample(): array
    {
        // Create a schema directly
        $yamlContent = <<<YAML
core:
  model: Product
  table: products
  fields:
    name:
      type: string
      nullable: false
      rules: ['required', 'string', 'max:255']
    price:
      type: decimal:8,2
      rules: ['required', 'numeric', 'min:0']
    description:
      type: text
      nullable: true
  relations:
    category:
      type: belongsTo
      model: App\Models\Category
  options:
    timestamps: true
    soft_deletes: false
YAML;

        // Extract core data for generation
        $coreData = $this->schemaService->extractCoreContentForGeneration($yamlContent);

        // Generate fragments directly
        $schema = $this->schemaService->createSchemaFromCoreData($coreData);
        $fragments = $this->generationService->generateAll($schema);

        return $fragments;
    }

    /**
     * Example of trait-based field configuration workflow
     */
    public function traitBasedFieldConfigurationExample(): array
    {
        // Define schema with trait-based field configurations
        $yamlContent = '
core:
  model: Website
  table: websites
  fields:
    homepage:
      type: url
      nullable: false
      # Trait-based URL configuration
      schemes: ["https", "http"]
      verify_ssl: true
      timeout: 45
      domain_whitelist: 
        - "trusted.com"
        - "partner.org"
      max_redirects: 3
    
    api_config:
      type: json_schema
      nullable: true
      # Trait-based JSON Schema configuration
      schema:
        type: object
        properties:
          endpoint:
            type: string
            pattern: "^https?://"
          timeout:
            type: integer
            minimum: 1
            maximum: 300
        required: ["endpoint"]
      strict_validation: true
      schema_format: "draft-07"
      validation_mode: "strict"
  
  options:
    timestamps: true
    soft_deletes: false
';

        // 1. Parse schema with trait-based field configurations
        $result = $this->schemaService->parseAndSeparateSchema($yamlContent);
        
        // 2. Validate including trait-based configurations
        $errors = $this->schemaService->validateCoreSchema($yamlContent);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Trait validation failed: ' . implode(', ', $errors));
        }
        
        // 3. Extract generation data with processed traits
        $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($yamlContent);
        
        return [
            'core_schema' => $result['core'],
            'validation_passed' => empty($errors),
            'generation_fragments' => $generationData['generation_data'],
            'trait_processing_summary' => $this->getTraitProcessingSummary($generationData)
        ];
    }

    /**
     * Demonstrate trait plugin discovery and registration
     */
    public function traitPluginDiscoveryExample(): array
    {
        // Auto-discover trait-based plugins
        $discoveredPlugins = $this->pluginManager->discoverPlugins([
            'App\\FieldTypes\\*Plugin',
            'Custom\\Traits\\*FieldTypePlugin'
        ]);
        
        $pluginSummary = [];
        foreach ($discoveredPlugins as $plugin) {
            $pluginSummary[] = [
                'type' => $plugin->getType(),
                'version' => $plugin->getVersion(),
                'custom_traits' => $plugin->getCustomAttributes(),
                'trait_configs' => array_keys($plugin->customAttributeConfig ?? []),
                'metadata' => $plugin->getMetadata()
            ];
        }
        
        return [
            'discovered_count' => count($discoveredPlugins),
            'plugins' => $pluginSummary
        ];
    }

    /**
     * Get summary of trait processing for debugging
     */
    private function getTraitProcessingSummary(array $generationData): array
    {
        $summary = [
            'traits_applied' => 0,
            'default_values_used' => 0,
            'transformations_applied' => 0,
            'validations_passed' => 0
        ];
        
        // This would be enhanced with actual trait processing metrics
        // from the generation service in a real implementation
        
        return $summary;
    }

    /**
     * Example of how parent app would generate its own files
     */
    private function generateParentAppModel(array $modelData): void
    {
        // Parent app template integration example
        $template = <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$modelData['class_name']} extends Model
{
    protected \$table = '{$modelData['table']}';
    
    protected \$fillable = [
PHP;

        foreach ($modelData['fillable'] as $field) {
            $template .= "\n        '{$field}',";
        }

        $template .= <<<'PHP'

    ];

    protected $casts = [
PHP;

        foreach ($modelData['casts'] as $field => $cast) {
            $template .= "\n        '{$field}' => '{$cast}',";
        }

        $template .= <<<'PHP'

    ];
}
PHP;

        // Parent app would save this to its own file structure
        echo "Generated Model:\n".$template."\n";
    }

    private function generateParentAppMigration(array $migrationData): void
    {
        // Parent app migration template integration example
        echo "Generated Migration Data:\n";
        print_r($migrationData);
    }

    private function generateParentAppRequests(array $requestsData): void
    {
        // Parent app request template integration example
        echo "Generated Requests Data:\n";
        print_r($requestsData);
    }
}

/**
 * Usage Examples
 */

// Basic usage
$integration = new IntegrationExample();

// Complete workflow
try {
    $data = $integration->completeWorkflowExample();
    echo "Integration successful!\n";
} catch (Exception $e) {
    echo 'Integration failed: '.$e->getMessage()."\n";
}

// Direct generation
$fragments = $integration->directGenerationExample();
echo "Generated fragments:\n";
print_r(array_keys($fragments));
