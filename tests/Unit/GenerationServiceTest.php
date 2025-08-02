<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;

beforeEach(function () {
    $this->generationService = new GenerationService();

    // Create a sample schema for testing
    $fields = [
        Field::fromArray('id', ['type' => 'bigInteger', 'nullable' => false, 'primary' => true]),
        Field::fromArray('name', ['type' => 'string', 'length' => 255, 'nullable' => false]),
        Field::fromArray('email', ['type' => 'email', 'nullable' => false, 'unique' => true]),
        Field::fromArray('active', ['type' => 'boolean', 'default' => true]),
    ];

    $relationships = [
        Relationship::fromArray('posts', ['type' => 'hasMany', 'model' => 'Post']),
        Relationship::fromArray('role', ['type' => 'belongsTo', 'model' => 'Role']),
    ];

    $this->sampleSchema = new ModelSchema(
        name: 'User',
        table: 'users',
        fields: $fields,
        relationships: $relationships,
        options: ['timestamps' => true, 'soft_deletes' => false]
    );
});

it('can generate model data in JSON format as insertable fragment', function () {
    $result = $this->generationService->generateModel($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('metadata');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "model" key that can be inserted
    expect($jsonData)->toHaveKey('model');
    expect($jsonData['model'])->toHaveKey('name');
    expect($jsonData['model']['name'])->toBe('User');
    expect($jsonData['model'])->toHaveKey('table');
    expect($jsonData['model']['table'])->toBe('users');
    expect($jsonData['model'])->toHaveKey('fillable');
    expect($jsonData['model'])->toHaveKey('relationships');
});

it('can generate model data in YAML format as insertable fragment', function () {
    $result = $this->generationService->generateModel($this->sampleSchema);

    expect($result)->toHaveKey('yaml');

    // Parse YAML to verify structure
    $yamlData = Symfony\Component\Yaml\Yaml::parse($result['yaml']);

    // Should be a fragment with "model" key that can be inserted
    expect($yamlData)->toHaveKey('model');
    expect($yamlData['model'])->toHaveKey('name');
    expect($yamlData['model']['name'])->toBe('User');
    expect($yamlData['model'])->toHaveKey('table');
    expect($yamlData['model']['table'])->toBe('users');
});

it('can generate migration data as insertable fragment', function () {
    $result = $this->generationService->generateMigration($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('yaml');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "migration" key that can be inserted
    expect($jsonData)->toHaveKey('migration');
    expect($jsonData['migration'])->toHaveKey('table');
    expect($jsonData['migration']['table'])->toBe('users');
    expect($jsonData['migration'])->toHaveKey('fields');
    expect($jsonData['migration'])->toHaveKey('class_name');
    expect($jsonData['migration']['class_name'])->toBe('CreateUsersTable');
});

it('can generate requests data as insertable fragment', function () {
    $result = $this->generationService->generateRequests($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('yaml');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "requests" key that can be inserted
    expect($jsonData)->toHaveKey('requests');
    expect($jsonData['requests'])->toHaveKey('store_request');
    expect($jsonData['requests'])->toHaveKey('update_request');
    expect($jsonData['requests']['store_request']['name'])->toBe('StoreUserRequest');
    expect($jsonData['requests']['update_request']['name'])->toBe('UpdateUserRequest');
});

it('can generate resources data as insertable fragment', function () {
    $result = $this->generationService->generateResources($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('yaml');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "resources" key that can be inserted
    expect($jsonData)->toHaveKey('resources');
    expect($jsonData['resources'])->toHaveKey('resource');
    expect($jsonData['resources'])->toHaveKey('collection');
    expect($jsonData['resources']['resource']['name'])->toBe('UserResource');
    expect($jsonData['resources']['collection']['name'])->toBe('UserCollection');
});

it('can generate factory data as insertable fragment', function () {
    $result = $this->generationService->generateFactory($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('yaml');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "factory" key that can be inserted
    expect($jsonData)->toHaveKey('factory');
    expect($jsonData['factory'])->toHaveKey('name');
    expect($jsonData['factory']['name'])->toBe('UserFactory');
    expect($jsonData['factory'])->toHaveKey('fields');
    expect($jsonData['factory'])->toHaveKey('model_class');
});

it('can generate seeder data as insertable fragment', function () {
    $result = $this->generationService->generateSeeder($this->sampleSchema);

    expect($result)->toHaveKey('json');
    expect($result)->toHaveKey('yaml');

    // Parse JSON to verify structure
    $jsonData = json_decode($result['json'], true);

    // Should be a fragment with "seeder" key that can be inserted
    expect($jsonData)->toHaveKey('seeder');
    expect($jsonData['seeder'])->toHaveKey('name');
    expect($jsonData['seeder']['name'])->toBe('UserSeeder');
    expect($jsonData['seeder'])->toHaveKey('model_class');
    expect($jsonData['seeder'])->toHaveKey('dependencies');
});

it('generates fragments that can be merged into parent JSON', function () {
    // Generate all data
    $modelResult = $this->generationService->generateModel($this->sampleSchema);
    $migrationResult = $this->generationService->generateMigration($this->sampleSchema);
    $requestsResult = $this->generationService->generateRequests($this->sampleSchema);

    // Parse JSON fragments
    $modelData = json_decode($modelResult['json'], true);
    $migrationData = json_decode($migrationResult['json'], true);
    $requestsData = json_decode($requestsResult['json'], true);

    // Simulate parent app merging fragments
    $parentAppData = [
        'app_name' => 'MyLaravelApp',
        'version' => '1.0.0',
        'turbomaker' => [
            'crud_enabled' => true,
            'api_routes' => true,
        ],
        'arc' => [
            'livewire_components' => true,
        ],
    ];

    // Merge our fragments
    $completeData = array_merge($parentAppData, $modelData, $migrationData, $requestsData);

    // Verify the complete structure contains everything
    expect($completeData)->toHaveKey('app_name');
    expect($completeData)->toHaveKey('turbomaker');
    expect($completeData)->toHaveKey('arc');
    expect($completeData)->toHaveKey('model');
    expect($completeData)->toHaveKey('migration');
    expect($completeData)->toHaveKey('requests');

    // Verify our data is intact
    expect($completeData['model']['name'])->toBe('User');
    expect($completeData['migration']['table'])->toBe('users');
    expect($completeData['requests']['store_request']['name'])->toBe('StoreUserRequest');
});

it('provides available generators information', function () {
    $generators = $this->generationService->getAvailableGenerators();

    expect($generators)->toBeArray();
    expect($generators)->toHaveKey('model');
    expect($generators)->toHaveKey('migration');
    expect($generators)->toHaveKey('requests');
    expect($generators)->toHaveKey('resources');
    expect($generators)->toHaveKey('factory');
    expect($generators)->toHaveKey('seeder');

    // Verify each has correct structure
    foreach ($generators as $generator) {
        expect($generator)->toHaveKey('name');
        expect($generator)->toHaveKey('description');
        expect($generator)->toHaveKey('outputs');
        expect($generator['outputs'])->toContain('json');
        expect($generator['outputs'])->toContain('yaml');
        expect($generator['outputs'])->not->toContain('php');
    }
});
