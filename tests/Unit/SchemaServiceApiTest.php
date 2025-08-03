<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->tempDir = sys_get_temp_dir().'/laravel-modelschema-api-test-'.uniqid();
    $this->filesystem->makeDirectory($this->tempDir, 0755, true);
    $this->schemaService = new SchemaService($this->filesystem);
});

afterEach(function () {
    if ($this->filesystem->exists($this->tempDir)) {
        $this->filesystem->deleteDirectory($this->tempDir);
    }
});

it('can parse and separate schema with new core structure', function () {
    $yamlContent = <<<'YAML'
core:
  model: TestModel
  table: test_models
  fields:
    id:
      type: bigInteger
      nullable: false
    name:
      type: string
      length: 255
  options:
    timestamps: true

# Extension data from other packages
turbomaker:
  crud_enabled: true
  api_routes: true

arc:
  livewire_components: true
  admin_panel: true
YAML;

    $result = $this->schemaService->parseAndSeparateSchema($yamlContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['core_schema', 'core_data', 'extension_data', 'full_data']);

    // Core schema should be ModelSchema instance
    expect($result['core_schema'])->toBeInstanceOf(ModelSchema::class);
    expect($result['core_schema']->name)->toBe('TestModel');
    expect($result['core_schema']->table)->toBe('test_models');

    // Core data should only contain core keys
    expect($result['core_data'])->toHaveKeys(['model', 'table', 'fields', 'options']);
    expect($result['core_data'])->not->toHaveKey('turbomaker');
    expect($result['core_data'])->not->toHaveKey('arc');

    // Extension data should contain other packages data
    expect($result['extension_data'])->toHaveKeys(['turbomaker', 'arc']);
    expect($result['extension_data']['turbomaker']['crud_enabled'])->toBe(true);
    expect($result['extension_data']['arc']['livewire_components'])->toBe(true);
});

it('can parse and separate schema with old flat structure', function () {
    $yamlContent = <<<'YAML'
model: TestModel
table: test_models
fields:
  id:
    type: bigInteger
    nullable: false
  name:
    type: string
    length: 255
options:
  timestamps: true

# Extension data from other packages
turbomaker:
  crud_enabled: true
  api_routes: true

arc:
  livewire_components: true
YAML;

    $result = $this->schemaService->parseAndSeparateSchema($yamlContent);

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['core_schema', 'core_data', 'extension_data', 'full_data']);

    // Core schema should be ModelSchema instance
    expect($result['core_schema'])->toBeInstanceOf(ModelSchema::class);
    expect($result['core_schema']->name)->toBe('TestModel');

    // Extension data should contain other packages data
    expect($result['extension_data'])->toHaveKeys(['turbomaker', 'arc']);
});

it('can validate only core schema from complete YAML', function () {
    $yamlContent = <<<'YAML'
core:
  model: TestModel
  table: test_models
  fields:
    id:
      type: bigInteger
      nullable: false
    name:
      type: string
      length: 255

# Extension data that might have errors
turbomaker:
  invalid_field: "this could be invalid"
  but_we_dont_validate_it: true
YAML;

    $errors = $this->schemaService->validateCoreSchema($yamlContent);

    // Should be empty - our core schema is valid
    expect($errors)->toBeEmpty();
});

it('can validate core schema and detect core errors only', function () {
    $yamlContent = <<<'YAML'
core:
  model: TestModel
  table: test_models
  fields: {}  # Empty fields should cause validation error

turbomaker:
  this_extension_data: "should be ignored in validation"
YAML;

    $errors = $this->schemaService->validateCoreSchema($yamlContent);

    // Should contain error about empty fields in core
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('must have at least one field');
});

it('can extract core content for file generation', function () {
    $yamlContent = <<<'YAML'
core:
  model: UserModel
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
    soft_deletes: false

turbomaker:
  crud_enabled: true
YAML;

    $content = $this->schemaService->extractCoreContentForGeneration($yamlContent);

    expect($content)->toBeArray();
    expect($content)->toHaveKeys([
        'schema', 'model_name', 'table_name', 'fields', 'relationships',
        'all_fields', 'fillable_fields', 'casts', 'validation_rules',
        'has_timestamps', 'has_soft_deletes', 'model_namespace', 'model_class',
    ]);

    expect($content['model_name'])->toBe('UserModel');
    expect($content['table_name'])->toBe('users');
    expect($content['has_timestamps'])->toBe(true);
    expect($content['has_soft_deletes'])->toBe(false);
    expect($content['fillable_fields'])->toContain('name');
    expect($content['fillable_fields'])->toContain('email');
});

it('can get available stubs', function () {
    $stubs = $this->schemaService->getAvailableStubs();

    expect($stubs)->toBeArray();
    expect($stubs)->not->toBeEmpty();

    // Should have at least basic stub
    expect($stubs)->toHaveKey('basic.schema');
    expect($stubs['basic.schema'])->toHaveKeys(['name', 'file', 'path', 'description']);
});

it('can get stub content', function () {
    $content = $this->schemaService->getStubContent('basic.schema.stub');

    expect($content)->toBeString();
    expect($content)->toContain('{{MODEL_NAME}}');
    expect($content)->toContain('{{TABLE_NAME}}');
});

it('can generate complete YAML from stub with extension data', function () {
    $replacements = [
        'MODEL_NAME' => 'Product',
        'TABLE_NAME' => 'products',
    ];

    $extensionData = [
        'laravel_arc' => [
            'views' => ['index', 'show', 'create', 'edit'],
            'routes' => ['resource'],
        ],
    ];

    $yaml = $this->schemaService->generateCompleteYamlFromStub('basic.schema.stub', $replacements, $extensionData);

    expect($yaml)->toBeString();
    expect($yaml)->toContain('Product');
    expect($yaml)->toContain('products');
    expect($yaml)->toContain('laravel_arc');
    expect($yaml)->toContain('views');
});

it('can get default stub for app initialization', function () {
    $content = $this->schemaService->getDefaultStub();

    expect($content)->toBeString();
    expect($content)->toContain('{{MODEL_NAME}}');
    expect($content)->toContain('{{TABLE_NAME}}');
});

it('can get processed default stub with replacements', function () {
    $replacements = [
        'MODEL_NAME' => 'TestModel',
        'TABLE_NAME' => 'test_models',
    ];

    $content = $this->schemaService->getProcessedDefaultStub($replacements);

    expect($content)->toBeString();
    expect($content)->toContain('TestModel');
    expect($content)->toContain('test_models');
    expect($content)->not->toContain('{{MODEL_NAME}}');
});

it('can get default complete YAML for app integration', function () {
    $replacements = [
        'MODEL_NAME' => 'Article',
        'TABLE_NAME' => 'articles',
    ];

    $extensionData = [
        'app_specific' => [
            'custom_config' => 'value',
        ],
    ];

    $yaml = $this->schemaService->getDefaultCompleteYaml($replacements, $extensionData);

    expect($yaml)->toBeString();
    expect($yaml)->toContain('Article');
    expect($yaml)->toContain('articles');
    expect($yaml)->toContain('app_specific');
    expect($yaml)->toContain('core:');
});

it('can wrap core data in new structure', function () {
    $coreData = [
        'model' => 'TestModel',
        'table' => 'test_models',
        'fields' => ['id' => ['type' => 'bigInteger']],
    ];

    $extensionData = [
        'turbomaker' => ['crud_enabled' => true],
        'arc' => ['admin_panel' => true],
    ];

    $wrapped = $this->schemaService->wrapInCoreStructure($coreData, $extensionData);

    expect($wrapped)->toHaveKey('core');
    expect($wrapped['core'])->toBe($coreData);
    expect($wrapped)->toHaveKey('turbomaker');
    expect($wrapped)->toHaveKey('arc');
    expect($wrapped['turbomaker']['crud_enabled'])->toBe(true);
});
