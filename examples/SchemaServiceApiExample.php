<?php

declare(strict_types=1);

/**
 * Schema Service API Examples
 *
 * This file demonstrates all the methods available in SchemaService
 * and how parent applications should use them.
 */

namespace App\Examples;

use Exception;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\FileUploadFieldTypePlugin;

class SchemaServiceApiExample
{
    private SchemaService $schemaService;

    public function __construct()
    {
        $this->schemaService = new SchemaService();
    }

    /**
     * Example 1: Parse and separate core from extensions
     */
    public function parseAndSeparateExample(): void
    {
        $yamlContent = <<<'YAML'
core:
  model: User
  table: users
  fields:
    name:
      type: string
      nullable: false
    email:
      type: string
      unique: true

# Extensions from parent apps
turbomaker:
  views: ['index', 'create']
  routes: ['web', 'api']

arc:
  permissions: ['view', 'create', 'edit']
YAML;

        $result = $this->schemaService->parseAndSeparateSchema($yamlContent);

        echo "Core Schema:\n";
        print_r($result['core']);

        echo "\nExtensions:\n";
        print_r($result['extensions']);
    }

    /**
     * Example 2: Validate only the core schema
     */
    public function validateCoreExample(): void
    {
        $yamlContent = <<<'YAML'
core:
  model: User
  # Missing required 'table' field - should cause validation error
  fields:
    name:
      type: string
YAML;

        $errors = $this->schemaService->validateCoreSchema($yamlContent);

        if (empty($errors)) {
            echo "Core schema is valid!\n";
        } else {
            echo "Core schema validation errors:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }
    }

    /**
     * Example 3: Extract core content for generation
     */
    public function extractCoreContentExample(): void
    {
        $yamlContent = <<<YAML
core:
  model: Product
  table: products
  fields:
    name:
      type: string
      nullable: false
    price:
      type: decimal:8,2
  relations:
    category:
      type: belongsTo
      model: App\Models\Category
  options:
    timestamps: true
    soft_deletes: false

turbomaker:
  views: ['index', 'create']
YAML;

        $coreData = $this->schemaService->extractCoreContentForGeneration($yamlContent);

        echo "Extracted core data for generation:\n";
        print_r($coreData);
    }

    /**
     * Example 4: Generate complete YAML from stub
     */
    public function generateCompleteYamlExample(): void
    {
        $stubContent = <<<'YAML'
core:
  model: {{MODEL_NAME}}
  table: {{TABLE_NAME}}
  fields:
    name:
      type: string
      nullable: false
    email:
      type: string
      unique: true
  options:
    timestamps: true
    soft_deletes: false
YAML;

        // Save stub to temp file for demonstration
        $stubPath = sys_get_temp_dir().'/test.schema.stub';
        file_put_contents($stubPath, $stubContent);

        $replacements = [
            'MODEL_NAME' => 'User',
            'TABLE_NAME' => 'users',
        ];

        $extensionData = [
            'turbomaker' => [
                'views' => ['index', 'create', 'edit'],
                'routes' => ['web', 'api'],
            ],
            'arc' => [
                'permissions' => ['view', 'create', 'edit', 'delete'],
            ],
        ];

        $completeYaml = $this->schemaService->generateCompleteYamlFromStub(
            $stubPath,
            $replacements,
            $extensionData
        );

        echo "Generated complete YAML:\n";
        echo $completeYaml;

        // Cleanup
        unlink($stubPath);
    }

    /**
     * Example 5: Get generation data from complete YAML
     */
    public function getGenerationDataExample(): void
    {
        $completeYaml = <<<'YAML'
core:
  model: User
  table: users
  fields:
    name:
      type: string
      nullable: false
    email:
      type: string
      unique: true
  options:
    timestamps: true
    soft_deletes: false

turbomaker:
  views: ['index', 'create']
  routes: ['web', 'api']
YAML;

        $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($completeYaml);

        echo "Available generators:\n";
        foreach ($generationData['generation_data'] as $generatorName => $data) {
            echo "- {$generatorName}\n";
            echo '  JSON: '.mb_substr($data['json'], 0, 100)."...\n";
            echo '  YAML: '.mb_substr($data['yaml'], 0, 100)."...\n\n";
        }
    }

    /**
     * Example 6: Complete integration workflow
     */
    public function completeWorkflowExample(): void
    {
        echo "=== Complete Workflow Example ===\n\n";

        // Step 1: Generate complete YAML from stub
        echo "Step 1: Generating complete YAML from stub...\n";
        $stubContent = <<<'YAML'
core:
  model: {{MODEL_NAME}}
  table: {{TABLE_NAME}}
  fields:
    name:
      type: string
      nullable: false
    email:
      type: string
      unique: true
YAML;

        $stubPath = sys_get_temp_dir().'/workflow.schema.stub';
        file_put_contents($stubPath, $stubContent);

        $completeYaml = $this->schemaService->generateCompleteYamlFromStub(
            $stubPath,
            ['MODEL_NAME' => 'User', 'TABLE_NAME' => 'users'],
            ['turbomaker' => ['views' => ['index']]]
        );

        echo "✓ Complete YAML generated\n\n";

        // Step 2: Validate the complete YAML
        echo "Step 2: Validating complete YAML...\n";
        $errors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);

        if (empty($errors)) {
            echo "✓ Validation passed\n\n";
        } else {
            echo "✗ Validation failed:\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }

            return;
        }

        // Step 3: Extract generation data
        echo "Step 3: Extracting generation data...\n";
        $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($completeYaml);

        echo '✓ Generation data extracted for '.count($generationData['generation_data'])." generators\n\n";

        // Step 4: Use the fragments
        echo "Step 4: Using fragments in parent app...\n";
        foreach ($generationData['generation_data'] as $generatorName => $data) {
            $fragmentData = json_decode($data['json'], true);
            echo "✓ {$generatorName} fragment ready for integration\n";
        }

        echo "\n=== Workflow Complete ===\n";

        // Cleanup
        unlink($stubPath);
    }

    /**
     * Example 7: Error handling
     */
    public function errorHandlingExample(): void
    {
        echo "=== Error Handling Examples ===\n\n";

        // Invalid YAML
        echo "Testing invalid YAML:\n";
        try {
            $this->schemaService->parseAndSeparateSchema('invalid: yaml: content:');
        } catch (Exception $e) {
            echo '✓ Caught exception: '.$e->getMessage()."\n";
        }

        // Missing core section
        echo "\nTesting missing core section:\n";
        $errors = $this->schemaService->validateCoreSchema("turbomaker:\n  views: ['index']");
        if (! empty($errors)) {
            echo '✓ Validation caught missing core: '.implode(', ', $errors)."\n";
        }

        // Invalid stub file
        echo "\nTesting invalid stub file:\n";
        try {
            $this->schemaService->generateCompleteYamlFromStub('/nonexistent/file.stub', [], []);
        } catch (Exception $e) {
            echo '✓ Caught exception: '.$e->getMessage()."\n";
        }
    }

    /**
     * Example: Trait-Based Field Configuration
     * 
     * Demonstrates how to use trait-based field types in schemas
     */
    public function traitBasedFieldExample(): void
    {
        echo "=== Trait-Based Field Configuration Example ===\n";
        
        $yamlWithTraits = <<<'YAML'
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
    
    logo:
      type: file_upload
      nullable: true
      # Trait-based file upload configuration
      allowed_extensions: ["jpg", "png", "svg"]
      max_file_size: "2MB"
      storage_disk: "s3"
      auto_optimize: true
      generate_thumbnails:
        small: "150x150"
        medium: "300x300"
      
    coordinates:
      type: coordinates
      nullable: true
      # Trait-based geographic configuration
      coordinate_system: "WGS84"
      precision: 6
      validate_bounds:
        latitude: [45.0, 50.0]
        longitude: [2.0, 8.0]
      default_country: "FR"
  
  options:
    timestamps: true
    soft_deletes: false
YAML;

        try {
            // Parse schema with trait-based fields
            $result = $this->schemaService->parseAndSeparateSchema($yamlWithTraits);
            echo "✅ Parse successful\n";
            echo "Core fields found: " . count($result['core']['fields'] ?? []) . "\n";
            
            // Validate including trait configurations
            $errors = $this->schemaService->validateCoreSchema($yamlWithTraits);
            if (empty($errors)) {
                echo "✅ Trait validation passed\n";
            } else {
                echo "❌ Trait validation errors:\n";
                foreach ($errors as $error) {
                    echo "  - $error\n";
                }
            }
            
            // Extract generation data with processed traits
            $generationData = $this->schemaService->getGenerationDataFromCompleteYaml($yamlWithTraits);
            echo "✅ Generation data extracted with trait processing\n";
            
            // Show how traits are processed in the model fragment
            $modelFragment = json_decode($generationData['generation_data']['model']['json'], true);
            echo "Model fragment includes trait-processed fields:\n";
            foreach ($modelFragment['fields'] ?? [] as $fieldName => $fieldData) {
                echo "  - $fieldName: " . ($fieldData['type'] ?? 'unknown') . "\n";
                if (isset($fieldData['custom_attributes'])) {
                    echo "    Traits: " . implode(', ', array_keys($fieldData['custom_attributes'])) . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Example: Cross-Trait Validation
     * 
     * Shows how traits can interact for complex validation scenarios
     */
    public function crossTraitValidationExample(): void
    {
        echo "=== Cross-Trait Validation Example ===\n";
        
        // This schema has conflicting trait configurations
        $conflictingYaml = <<<'YAML'
core:
  model: SecureFile
  table: secure_files
  fields:
    document:
      type: file_upload
      # This configuration has trait conflicts:
      # - virus_scan requires local/s3 storage
      # - but we're using 'gcs' which doesn't support it
      virus_scan: true
      storage_disk: "gcs"
      encryption_enabled: true
      allowed_extensions: ["pdf", "doc", "docx"]
      max_file_size: "10MB"
    
    backup_url:
      type: url
      # This has trait conflicts:
      # - verify_ssl: true but allows 'http' scheme
      verify_ssl: true
      schemes: ["http", "https"]
      timeout: 30
YAML;

        try {
            // This should detect cross-trait validation errors
            $errors = $this->schemaService->validateCoreSchema($conflictingYaml);
            
            if (!empty($errors)) {
                echo "✅ Cross-trait validation working - conflicts detected:\n";
                foreach ($errors as $error) {
                    echo "  - $error\n";
                }
            } else {
                echo "⚠️  Cross-trait validation may need improvement\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error during validation: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Example: Plugin Manager Integration
     * 
     * Shows how plugins register and process traits
     */
    public function pluginManagerIntegrationExample(): void
    {
        echo "=== Plugin Manager Integration Example ===\n";
        
        try {
            $pluginManager = new FieldTypePluginManager();
            
            // Register trait-based plugins
            $pluginManager->registerPlugin(new UrlFieldTypePlugin());
            $pluginManager->registerPlugin(new FileUploadFieldTypePlugin());
            
            echo "✅ Trait-based plugins registered\n";
            
            // Show plugin capabilities
            $urlPlugin = $pluginManager->getPlugin('url');
            if ($urlPlugin) {
                echo "URL Plugin traits: " . implode(', ', $urlPlugin->getCustomAttributes()) . "\n";
                
                // Test trait configuration processing
                $config = [
                    'schemes' => ['https'],
                    'timeout' => 30,
                    // Missing other traits - should get defaults
                ];
                
                $processed = $urlPlugin->processCustomAttributes($config);
                echo "Processed config (with trait defaults):\n";
                foreach ($processed as $key => $value) {
                    echo "  - $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Plugin manager error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    /**
     * Run all trait-based examples
     */
    public function runTraitExamples(): void
    {
        echo "🚀 Running Trait-Based Schema Service Examples\n\n";
        
        $this->traitBasedFieldExample();
        $this->crossTraitValidationExample();
        $this->pluginManagerIntegrationExample();
        
        echo "✨ All trait examples completed!\n";
    }
}

// Run examples
$examples = new SchemaServiceApiExample();

echo "🚀 Running Schema Service API Examples\n\n";

echo "1. Parse and Separate Example:\n";
$examples->parseAndSeparateExample();

echo "\n2. Validate Core Example:\n";
$examples->validateCoreExample();

echo "\n3. Extract Core Content Example:\n";
$examples->extractCoreContentExample();

echo "\n4. Generate Complete YAML Example:\n";
$examples->generateCompleteYamlExample();

echo "\n5. Get Generation Data Example:\n";
$examples->getGenerationDataExample();

echo "\n6. Complete Workflow Example:\n";
$examples->completeWorkflowExample();

echo "\n7. Error Handling Example:\n";
$examples->errorHandlingExample();

echo "\n" . str_repeat("=", 50) . "\n";
echo "🎯 NEW: Trait-Based Examples\n";
echo str_repeat("=", 50) . "\n\n";

echo "8. Trait-Based Field Configuration:\n";
$examples->traitBasedFieldExample();

echo "\n9. Cross-Trait Validation:\n";
$examples->crossTraitValidationExample();

echo "\n10. Plugin Manager Integration:\n";
$examples->pluginManagerIntegrationExample();

echo "\n✨ All examples completed!\n";
