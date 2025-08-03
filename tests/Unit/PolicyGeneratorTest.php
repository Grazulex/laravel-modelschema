<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\PolicyGenerator;

describe('PolicyGenerator', function () {
    beforeEach(function () {
        $this->generator = new PolicyGenerator();
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

        $this->schemaWithSoftDeletes = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger', 'nullable' => false],
                'name' => ['type' => 'string', 'nullable' => false],
                'email' => ['type' => 'string', 'unique' => true],
                'deleted_at' => ['type' => 'timestamp', 'nullable' => true],
            ],
            'options' => [
                'timestamps' => true,
                'soft_deletes' => true,
            ],
        ]);
    });

    it('has correct basic properties', function () {
        expect($this->generator->getGeneratorName())->toBe('policy');
        expect($this->generator->getAvailableFormats())->toContain('json');
        expect($this->generator->getAvailableFormats())->toContain('yaml');
    });

    it('can generate policies data in JSON format as insertable fragment', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKey('json');
        expect($result)->toHaveKey('yaml');

        $jsonData = json_decode($result['json'], true);
        expect($jsonData)->toHaveKey('policies');
        expect($jsonData['policies'])->toHaveKey('UserPolicy');

        $policy = $jsonData['policies']['UserPolicy'];
        expect($policy)->toHaveKey('class_name');
        expect($policy)->toHaveKey('model');
        expect($policy)->toHaveKey('methods');
        expect($policy['class_name'])->toBe('UserPolicy');
        expect($policy['model'])->toBe('User');
    });

    it('generates proper YAML format', function () {
        $result = $this->generator->generate($this->schema);

        expect($result['yaml'])->toBeString();
        expect($result['yaml'])->toContain('policies:');
        expect($result['yaml'])->toContain('UserPolicy:');
    });

    it('can generate with custom options', function () {
        $options = ['policy_namespace' => 'App\\Policies\\Custom'];
        $result = $this->generator->generate($this->schema, $options);

        $jsonData = json_decode($result['json'], true);
        expect($jsonData['policies']['UserPolicy']['namespace'])->toBe('App\\Policies\\Custom');
    });

    it('generates standard authorization methods', function () {
        $result = $this->generator->generate($this->schema);

        $jsonData = json_decode($result['json'], true);
        $methods = $jsonData['policies']['UserPolicy']['methods'];

        expect($methods)->toHaveKey('viewAny');
        expect($methods)->toHaveKey('view');
        expect($methods)->toHaveKey('create');
        expect($methods)->toHaveKey('update');
        expect($methods)->toHaveKey('delete');

        // Check method structure
        expect($methods['view'])->toHaveKey('parameters');
        expect($methods['view'])->toHaveKey('return_type');
        expect($methods['view']['return_type'])->toBe('bool');
    });

    it('handles soft deletes properly', function () {
        $result = $this->generator->generate($this->schemaWithSoftDeletes);

        $jsonData = json_decode($result['json'], true);
        $methods = $jsonData['policies']['UserPolicy']['methods'];

        // Should have restore and forceDelete methods for soft deletes
        expect($methods)->toHaveKey('restore');
        expect($methods)->toHaveKey('forceDelete');

        expect($methods['restore']['return_type'])->toBe('bool');
        expect($methods['forceDelete']['return_type'])->toBe('bool');
    });

    it('generates proper authorization logic structure', function () {
        $result = $this->generator->generate($this->schema);

        $jsonData = json_decode($result['json'], true);
        $policy = $jsonData['policies']['UserPolicy'];

        expect($policy)->toHaveKey('authorization_logic');
        expect($policy['authorization_logic'])->toHaveKey('ownership_field');
        expect($policy['authorization_logic'])->toHaveKey('ownership_check');
        expect($policy['authorization_logic'])->toHaveKey('patterns');
        expect($policy['authorization_logic'])->toHaveKey('supports_soft_deletes');
        expect($policy['authorization_logic'])->toHaveKey('supports_publishing');
    });

    it('includes proper use statements and imports', function () {
        $result = $this->generator->generate($this->schema);

        $jsonData = json_decode($result['json'], true);
        $policy = $jsonData['policies']['UserPolicy'];

        expect($policy)->toHaveKey('model_class');
        expect($policy)->toHaveKey('namespace');
        expect($policy['namespace'])->toBe('App\\Policies');
    });
});
