<?php

use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->tempDir = sys_get_temp_dir() . '/laravel-modelschema-service-test-' . uniqid();
    $this->filesystem->makeDirectory($this->tempDir, 0755, true);
    $this->schemaService = new SchemaService($this->filesystem);
});

afterEach(function () {
    if ($this->filesystem->exists($this->tempDir)) {
        $this->filesystem->deleteDirectory($this->tempDir);
    }
});

it('can parse yaml file', function () {
    $yamlContent = <<<YAML
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
YAML;

    $filePath = $this->tempDir . '/test.yml';
    $this->filesystem->put($filePath, $yamlContent);

    $schema = $this->schemaService->parseYamlFile($filePath);

    expect($schema)->toBeInstanceOf(ModelSchema::class);
    expect($schema->name)->toBe('TestModel');
    expect($schema->table)->toBe('test_models');
    expect($schema->fields)->toHaveCount(2);
});

it('throws exception for non-existent file', function () {
    expect(fn() => $this->schemaService->parseYamlFile('/non/existent/file.yml'))
        ->toThrow(SchemaException::class, 'not found');
});

it('can parse yaml content', function () {
    $yamlContent = <<<YAML
model: ContentModel
table: content_models
fields:
  title:
    type: string
YAML;

    $schema = $this->schemaService->parseYamlContent($yamlContent, 'CustomName');

    expect($schema)->toBeInstanceOf(ModelSchema::class);
    expect($schema->name)->toBe('ContentModel'); // From YAML, not the parameter
    expect($schema->table)->toBe('content_models');
});

it('validates schema correctly', function () {
    $fields = [
        Field::fromArray('id', ['type' => 'bigInteger']),
        Field::fromArray('name', ['type' => 'string']),
    ];

    $relationships = [
        Relationship::fromArray('posts', ['type' => 'hasMany', 'model' => 'Post']),
    ];

    $schema = new ModelSchema('ValidModel', 'valid_models', $fields, $relationships);

    $errors = $this->schemaService->validateSchema($schema);

    expect($errors)->toBeEmpty();
});

it('detects validation errors', function () {
    // Schema without fields
    $emptySchema = new ModelSchema('EmptyModel', 'empty_models', []);
    $errors = $this->schemaService->validateSchema($emptySchema);
    expect($errors)->toContain("Schema 'EmptyModel' must have at least one field");

    // Schema with invalid field type
    $invalidFields = [
        Field::fromArray('test', ['type' => 'invalidType']),
    ];
    $invalidSchema = new ModelSchema('InvalidModel', 'invalid_models', $invalidFields);
    $errors = $this->schemaService->validateSchema($invalidSchema);
    expect($errors)->toContain("Unknown field type 'invalidType' in field 'test'");

    // Schema with invalid relationship type
    $validFields = [Field::fromArray('id', ['type' => 'bigInteger'])];
    $invalidRelationships = [
        Relationship::fromArray('invalid', ['type' => 'invalidRelation', 'model' => 'Test']),
    ];
    $invalidRelSchema = new ModelSchema('InvalidRelModel', 'invalid_models', $validFields, $invalidRelationships);
    $errors = $this->schemaService->validateSchema($invalidRelSchema);
    expect($errors)->toContain("Unknown relationship type 'invalidRelation' in relationship 'invalid'");
});

it('returns core schema keys', function () {
    $coreKeys = $this->schemaService->getCoreSchemaKeys();

    expect($coreKeys)->toBeArray();
    expect($coreKeys)->toContain('model');
    expect($coreKeys)->toContain('table');
    expect($coreKeys)->toContain('fields');
    expect($coreKeys)->toContain('relationships');
    expect($coreKeys)->toContain('options');
    expect($coreKeys)->toContain('metadata');
});

it('extracts core schema data', function () {
    $yamlData = [
        'model' => 'TestModel',
        'table' => 'test_models',
        'fields' => ['id' => ['type' => 'bigInteger']],
        'relationships' => ['posts' => ['type' => 'hasMany']],
        'options' => ['timestamps' => true],
        'metadata' => ['version' => '1.0'],
        'custom_field' => 'custom_value',
        'another_extension' => ['data' => 'here'],
    ];

    $coreData = $this->schemaService->extractCoreSchema($yamlData);

    expect($coreData)->toHaveKey('model');
    expect($coreData)->toHaveKey('table');
    expect($coreData)->toHaveKey('fields');
    expect($coreData)->toHaveKey('relationships');
    expect($coreData)->toHaveKey('options');
    expect($coreData)->toHaveKey('metadata');
    expect($coreData)->not->toHaveKey('custom_field');
    expect($coreData)->not->toHaveKey('another_extension');
});

it('extracts extension data', function () {
    $yamlData = [
        'model' => 'TestModel',
        'table' => 'test_models',
        'fields' => ['id' => ['type' => 'bigInteger']],
        'custom_field' => 'custom_value',
        'another_extension' => ['data' => 'here'],
        'migrations' => ['auto_generate' => true],
    ];

    $extensionData = $this->schemaService->extractExtensionData($yamlData);

    expect($extensionData)->not->toHaveKey('model');
    expect($extensionData)->not->toHaveKey('table');
    expect($extensionData)->not->toHaveKey('fields');
    expect($extensionData)->toHaveKey('custom_field');
    expect($extensionData)->toHaveKey('another_extension');
    expect($extensionData)->toHaveKey('migrations');
    expect($extensionData['custom_field'])->toBe('custom_value');
});

it('handles relations alias for relationships', function () {
    $yamlData = [
        'model' => 'TestModel',
        'relations' => ['posts' => ['type' => 'hasMany']],
    ];

    $coreData = $this->schemaService->extractCoreSchema($yamlData);

    expect($coreData)->toHaveKey('relationships');
    expect($coreData['relationships'])->toBe(['posts' => ['type' => 'hasMany']]);
});

it('validates field types correctly', function () {
    expect($this->schemaService->isValidFieldType('string'))->toBeTrue();
    expect($this->schemaService->isValidFieldType('integer'))->toBeTrue();
    expect($this->schemaService->isValidFieldType('email'))->toBeTrue();
    expect($this->schemaService->isValidFieldType('invalidType'))->toBeFalse();
});

it('validates relationship types correctly', function () {
    expect($this->schemaService->isValidRelationshipType('belongsTo'))->toBeTrue();
    expect($this->schemaService->isValidRelationshipType('hasMany'))->toBeTrue();
    expect($this->schemaService->isValidRelationshipType('morphToMany'))->toBeTrue();
    expect($this->schemaService->isValidRelationshipType('invalidRelation'))->toBeFalse();
});

it('returns supported field types', function () {
    // Ensure registry is initialized
    \Grazulex\LaravelModelschema\Support\FieldTypeRegistry::initialize();
    
    $fieldTypes = $this->schemaService->getSupportedFieldTypes();

    expect($fieldTypes)->toBeArray();
    // The registry should have some types after initialization
    expect(count($fieldTypes))->toBeGreaterThan(0);
});

it('returns supported relationship types', function () {
    $relationshipTypes = $this->schemaService->getSupportedRelationshipTypes();

    expect($relationshipTypes)->toBeArray();
    expect($relationshipTypes)->toHaveKey('belongsTo');
    expect($relationshipTypes)->toHaveKey('hasMany');
    expect($relationshipTypes)->toHaveKey('morphToMany');
    expect($relationshipTypes['belongsTo'])->toBeString();
});

it('can save schema to yaml file', function () {
    $fields = [
        Field::fromArray('id', ['type' => 'bigInteger']),
        Field::fromArray('name', ['type' => 'string', 'length' => 255]),
    ];

    $schema = new ModelSchema('SavedModel', 'saved_models', $fields);
    $filePath = $this->tempDir . '/saved_schema.yml';

    $this->schemaService->saveSchemaToYaml($schema, $filePath);

    expect($this->filesystem->exists($filePath))->toBeTrue();

    $content = $this->filesystem->get($filePath);
    expect($content)->toContain('SavedModel');
    expect($content)->toContain('saved_models');
    expect($content)->toContain('bigInteger');
});

it('can convert schema to yaml string', function () {
    $fields = [Field::fromArray('id', ['type' => 'bigInteger'])];
    $schema = new ModelSchema('YamlModel', 'yaml_models', $fields);

    $yamlString = $this->schemaService->convertSchemaToYaml($schema);

    expect($yamlString)->toBeString();
    expect($yamlString)->toContain('YamlModel');
    expect($yamlString)->toContain('yaml_models');
});
