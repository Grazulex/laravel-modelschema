<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\SchemaService;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->tempDir = sys_get_temp_dir().'/laravel-modelschema-app-integration-test-'.uniqid();
    $this->filesystem->makeDirectory($this->tempDir, 0755, true);
    $this->schemaService = new SchemaService($this->filesystem);
});

afterEach(function () {
    if ($this->filesystem->exists($this->tempDir)) {
        $this->filesystem->deleteDirectory($this->tempDir);
    }
});

it('can generate complete YAML from stub and extension data', function () {
    $replacements = [
        'MODEL_NAME' => 'Product',
        'TABLE_NAME' => 'products',
        'NAMESPACE' => 'App\\Models\\Catalog',
    ];

    $extensionData = [
        'turbomaker' => [
            'crud_enabled' => true,
            'api_routes' => true,
            'form_fields' => ['name', 'price'],
        ],
        'arc' => [
            'livewire_components' => true,
            'admin_panel' => true,
        ],
    ];

    $completeYaml = $this->schemaService->generateCompleteYamlFromStub(
        'basic.schema.stub',
        $replacements,
        $extensionData
    );

    expect($completeYaml)->toBeString();

    // Parse and verify structure
    $data = Symfony\Component\Yaml\Yaml::parse($completeYaml);

    expect($data)->toHaveKey('core');
    expect($data)->toHaveKey('turbomaker');
    expect($data)->toHaveKey('arc');

    // Verify core data
    expect($data['core']['model'])->toBe('Product');
    expect($data['core']['table'])->toBe('products');

    // Verify extension data is preserved
    expect($data['turbomaker']['crud_enabled'])->toBe(true);
    expect($data['arc']['livewire_components'])->toBe(true);
});

it('can merge core data with app data', function () {
    $coreData = [
        'model' => 'User',
        'table' => 'users',
        'fields' => [
            'id' => ['type' => 'bigInteger'],
            'name' => ['type' => 'string'],
        ],
    ];

    $appData = [
        'app_name' => 'MyApp',
        'version' => '1.0.0',
        'turbomaker' => [
            'crud_enabled' => true,
        ],
        'arc' => [
            'admin_panel' => true,
        ],
    ];

    $merged = $this->schemaService->mergeWithAppData($coreData, $appData);

    expect($merged)->toHaveKey('core');
    expect($merged)->toHaveKey('app_name');
    expect($merged)->toHaveKey('turbomaker');
    expect($merged)->toHaveKey('arc');

    // Verify core data is intact
    expect($merged['core']['model'])->toBe('User');
    expect($merged['core']['table'])->toBe('users');

    // Verify app data is preserved
    expect($merged['app_name'])->toBe('MyApp');
    expect($merged['turbomaker']['crud_enabled'])->toBe(true);
});

it('can validate core schema from complete app YAML', function () {
    $completeYaml = <<<'YAML'
app_name: MyLaravelApp
version: 1.0.0

core:
  model: Product
  table: products
  fields:
    id:
      type: bigInteger
      nullable: false
    name:
      type: string
      length: 255
      nullable: false

turbomaker:
  crud_enabled: true
  api_routes: true

arc:
  livewire_components: true
YAML;

    $errors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);

    // Should be empty - valid core schema
    expect($errors)->toBeEmpty();
});

it('detects validation errors in core schema from complete app YAML', function () {
    $completeYaml = <<<'YAML'
app_name: MyLaravelApp

core:
  model: Product
  table: products
  fields: {}  # Empty fields should cause error

turbomaker:
  crud_enabled: true
YAML;

    $errors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);

    // Should contain error about empty fields
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must have at least one field');
});

it('can extract generation data from complete app YAML', function () {
    $completeYaml = <<<'YAML'
app_name: MyLaravelApp

core:
  model: User
  table: users
  fields:
    id:
      type: bigInteger
      nullable: false
      primary: true
    name:
      type: string
      length: 255
      nullable: false
    email:
      type: email
      nullable: false
      unique: true
  relationships:
    posts:
      type: hasMany
      model: Post
  options:
    timestamps: true

turbomaker:
  crud_enabled: true
YAML;

    $result = $this->schemaService->getGenerationDataFromCompleteYaml($completeYaml);

    expect($result)->toHaveKeys(['core_schema', 'core_data', 'extension_data', 'generation_data']);

    // Verify core schema
    expect($result['core_schema'])->toBeInstanceOf(Grazulex\LaravelModelschema\Schema\ModelSchema::class);
    expect($result['core_schema']->name)->toBe('User');

    // Verify extension data
    expect($result['extension_data'])->toHaveKey('turbomaker');
    expect($result['extension_data']['turbomaker']['crud_enabled'])->toBe(true);

    // Verify generation data
    expect($result['generation_data'])->toHaveKeys(['model', 'migration', 'requests', 'resources', 'factory', 'seeder']);

    // Each generator should have json and yaml formats
    foreach (['model', 'migration', 'requests', 'resources', 'factory', 'seeder'] as $type) {
        expect($result['generation_data'][$type])->toHaveKey('json');
        expect($result['generation_data'][$type])->toHaveKey('yaml');
        expect($result['generation_data'][$type])->toHaveKey('metadata');
    }
});

it('handles missing core data gracefully', function () {
    $completeYaml = <<<'YAML'
app_name: MyLaravelApp
turbomaker:
  crud_enabled: true
# No core section
YAML;

    $errors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);

    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('No core schema data found');
});

it('provides API for app integration workflow', function () {
    // 1. App starts with a stub
    $replacements = ['MODEL_NAME' => 'Article', 'TABLE_NAME' => 'articles'];
    $extensionData = ['turbomaker' => ['crud_enabled' => true]];

    $completeYaml = $this->schemaService->generateCompleteYamlFromStub(
        'basic.schema.stub',
        $replacements,
        $extensionData
    );

    // 2. App validates the complete YAML
    $validationErrors = $this->schemaService->validateFromCompleteAppYaml($completeYaml);
    expect($validationErrors)->toBeEmpty();

    // 3. App gets generation data
    $generationResult = $this->schemaService->getGenerationDataFromCompleteYaml($completeYaml);

    // 4. Verify the complete workflow
    expect($generationResult['core_schema']->name)->toBe('Article');
    expect($generationResult['extension_data']['turbomaker']['crud_enabled'])->toBe(true);
    expect($generationResult['generation_data'])->toHaveKey('model');

    // 5. Parse model generation JSON to verify it's insertable
    $modelJson = json_decode($generationResult['generation_data']['model']['json'], true);
    expect($modelJson)->toHaveKey('model');
    expect($modelJson['model']['name'])->toBe('Article');
});
