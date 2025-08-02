<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ControllerGenerator;

describe('ControllerGenerator', function () {
    beforeEach(function () {
        $this->generator = new ControllerGenerator();
        $this->schema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'name' => ['type' => 'string', 'nullable' => false, 'rules' => ['required', 'string', 'max:255']],
                'email' => ['type' => 'string', 'unique' => true, 'rules' => ['required', 'email', 'unique:users']],
                'created_at' => ['type' => 'timestamp'],
                'updated_at' => ['type' => 'timestamp'],
            ],
            'relationships' => [
                'posts' => [
                    'type' => 'hasMany',
                    'model' => 'App\Models\Post',
                ],
            ],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => false,
            ],
        ]);
    });

    it('can generate controllers data in JSON format as insertable fragment', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('controllers');

        $controllers = $jsonData['controllers'];
        expect($controllers)->toHaveKey('api_controller');
        expect($controllers)->toHaveKey('web_controller');
        expect($controllers)->toHaveKey('resource_routes');

        // Check API controller structure
        $apiController = $controllers['api_controller'];
        expect($apiController['name'])->toBe('UserApiController');
        expect($apiController['namespace'])->toBe('App\Http\Controllers\Api');
        expect($apiController['methods'])->toHaveKey('index');
        expect($apiController['methods'])->toHaveKey('store');
        expect($apiController['methods'])->toHaveKey('show');
        expect($apiController['methods'])->toHaveKey('update');
        expect($apiController['methods'])->toHaveKey('destroy');

        // Check Web controller structure
        $webController = $controllers['web_controller'];
        expect($webController['name'])->toBe('UserController');
        expect($webController['namespace'])->toBe('App\Http\Controllers');
        expect($webController['methods'])->toHaveKey('index');
        expect($webController['methods'])->toHaveKey('create');
        expect($webController['methods'])->toHaveKey('store');
        expect($webController['methods'])->toHaveKey('show');
        expect($webController['methods'])->toHaveKey('edit');
        expect($webController['methods'])->toHaveKey('update');
        expect($webController['methods'])->toHaveKey('destroy');

        // Check routes structure
        $routes = $controllers['resource_routes'];
        expect($routes)->toHaveKey('api_routes');
        expect($routes)->toHaveKey('web_routes');
        expect($routes['api_routes']['resource'])->toBe('user');
        expect($routes['web_routes']['resource'])->toBe('user');
    });

    it('can generate controllers data in YAML format as insertable fragment', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKey('yaml');

        $yamlContent = $result['yaml'];
        expect($yamlContent)->toContain('controllers:');
        expect($yamlContent)->toContain('api_controller:');
        expect($yamlContent)->toContain('web_controller:');
        expect($yamlContent)->toContain('UserApiController');
        expect($yamlContent)->toContain('UserController');
    });

    it('generates proper validation rules for controllers', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController)->toHaveKey('validation');

        $validation = $apiController['validation'];
        expect($validation)->toHaveKey('store');
        expect($validation)->toHaveKey('update');

        // Check that store validation includes required rules
        expect($validation['store']['name'])->toContain('required');
        expect($validation['store']['email'])->toContain('required');
        expect($validation['store']['email'])->toContain('email');

        // Check that update validation handles unique rules properly
        expect($validation['update']['email'])->toContain('unique:users,email,{id}');
    });

    it('generates middleware configuration', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $middleware = $jsonData['controllers']['middleware'];
        expect($middleware)->toHaveKey('global');
        expect($middleware)->toHaveKey('authentication');
        expect($middleware)->toHaveKey('authorization');

        expect($middleware['global']['api'])->toContain('api');
        expect($middleware['global']['web'])->toContain('web');
        expect($middleware['authentication']['api'])->toBe('auth:sanctum');
        expect($middleware['authentication']['web'])->toBe('auth');
    });

    it('handles soft deletes properly', function () {
        // Create schema with soft deletes
        $schemaWithSoftDeletes = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'title' => ['type' => 'string'],
                'deleted_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'options' => [
                'soft_deletes' => true,
            ],
        ]);

        $result = $this->generator->generate($schemaWithSoftDeletes);
        $jsonData = json_decode($result['json'], true);

        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController['methods'])->toHaveKey('restore');
        expect($apiController['methods'])->toHaveKey('forceDestroy');

        $routes = $jsonData['controllers']['resource_routes'];
        expect($routes['additional_routes'])->toHaveKey('restore');
    });

    it('generates proper resource routes configuration', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $routes = $jsonData['controllers']['resource_routes'];

        // API routes
        $apiRoutes = $routes['api_routes'];
        expect($apiRoutes['prefix'])->toBe('api');
        expect($apiRoutes['name'])->toBe('api.user');
        expect($apiRoutes['controller'])->toBe('UserApiController');
        expect($apiRoutes['middleware'])->toContain('api');
        expect($apiRoutes['middleware'])->toContain('auth:sanctum');

        // Web routes
        $webRoutes = $routes['web_routes'];
        expect($webRoutes['name'])->toBe('user');
        expect($webRoutes['controller'])->toBe('UserController');
        expect($webRoutes['middleware'])->toContain('web');
        expect($webRoutes['middleware'])->toContain('auth');
    });

    it('includes relationship handling in controller methods', function () {
        $result = $this->generator->generate($this->schema);
        $jsonData = json_decode($result['json'], true);

        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController)->toHaveKey('relationships');

        $relationships = $apiController['relationships'];
        expect($relationships)->toHaveKey('posts');
        expect($relationships['posts']['type'])->toBe('hasMany');
        expect($relationships['posts']['model'])->toBe('App\Models\Post');
        expect($relationships['posts']['load_count'])->toBe(true);
    });

    it('can generate with custom options', function () {
        $options = [
            'api_controller_namespace' => 'App\Http\Controllers\Api\V1',
            'web_controller_namespace' => 'App\Http\Controllers\Web',
            'enable_policies' => false,
            'route_prefix' => 'admin',
        ];

        $result = $this->generator->generate($this->schema, $options);
        $jsonData = json_decode($result['json'], true);

        $apiController = $jsonData['controllers']['api_controller'];
        expect($apiController['namespace'])->toBe('App\Http\Controllers\Api\V1');

        $webController = $jsonData['controllers']['web_controller'];
        expect($webController['namespace'])->toBe('App\Http\Controllers\Web');

        // Should not have policies when disabled
        $policies = $jsonData['controllers']['policies'];
        expect($policies)->toBeEmpty();
    });
});
