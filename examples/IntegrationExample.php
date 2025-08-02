<?php

declare(strict_types=1);

/**
 * Complete Integration Example
 *
 * This example demonstrates how parent applications (like TurboMaker, Arc)
 * can integrate with Laravel ModelSchema to generate insertable fragments.
 */

namespace App\Examples;

use Exception;
use Grazulex\LaravelModelschema\Services\GenerationService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use InvalidArgumentException;

class IntegrationExample
{
    private SchemaService $schemaService;

    private GenerationService $generationService;

    public function __construct()
    {
        $this->schemaService = new SchemaService();
        $this->generationService = new GenerationService();
    }

    /**
     * Complete workflow example for parent applications
     */
    public function completeWorkflowExample(): array
    {
        // 1. Generate complete YAML from stub with parent app data
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
