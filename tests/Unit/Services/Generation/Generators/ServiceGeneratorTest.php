<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ServiceGenerator;
use Tests\TestCase;

/**
 * @covers \Grazulex\LaravelModelschema\Services\Generation\Generators\ServiceGenerator
 */
class ServiceGeneratorTest extends TestCase
{
    private ServiceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ServiceGenerator();
    }

    public function test_get_generator_name(): void
    {
        $this->assertEquals('service', $this->generator->getGeneratorName());
    }

    public function test_get_available_formats(): void
    {
        $formats = $this->generator->getAvailableFormats();
        $this->assertContains('json', $formats);
        $this->assertContains('yaml', $formats);
    }

    public function test_generate_json_format(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->generator->generate($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('services', $jsonData);
        $this->assertArrayHasKey('UserService', $jsonData['services']);

        $service = $jsonData['services']['UserService'];
        $this->assertEquals('UserService', $service['class_name']);
        $this->assertEquals('App\\Services', $service['namespace']);
        $this->assertEquals('App\\Models\\User', $service['model_class']);
        $this->assertArrayHasKey('methods', $service);
        $this->assertArrayHasKey('imports', $service);
    }

    public function test_generate_with_custom_namespace(): void
    {
        $schema = $this->createTestSchema();
        $options = ['service_namespace' => 'App\\Custom\\Services'];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];
        $this->assertEquals('App\\Custom\\Services', $service['namespace']);
    }

    public function test_generate_with_repository(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'use_repository' => true,
            'repository_class' => 'App\\Repositories\\UserRepository',
        ];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];

        $this->assertEquals('App\\Repositories\\UserRepository', $service['repository_class']);
        $this->assertArrayHasKey('userRepository', $service['properties']);
        $this->assertContains('App\\Repositories\\UserRepository', $service['imports']);
    }

    public function test_generate_with_caching(): void
    {
        $schema = $this->createTestSchema();
        $options = ['use_cache' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];

        $this->assertContains('Illuminate\\Support\\Facades\\Cache', $service['imports']);
        $this->assertArrayHasKey('cachePrefix', $service['properties']);
        $this->assertArrayHasKey('cacheTtl', $service['properties']);
    }

    public function test_service_methods(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];
        $methods = $service['methods'];

        // Check CRUD methods
        $this->assertArrayHasKey('create', $methods);
        $this->assertArrayHasKey('update', $methods);
        $this->assertArrayHasKey('delete', $methods);
        $this->assertArrayHasKey('findById', $methods);
        $this->assertArrayHasKey('getAll', $methods);
        $this->assertArrayHasKey('paginate', $methods);

        // Check method details
        $this->assertEquals('User', $methods['create']['return_type']);
        $this->assertEquals('User', $methods['update']['return_type']);
        $this->assertEquals('bool', $methods['delete']['return_type']);
        $this->assertEquals('User|null', $methods['findById']['return_type']);
    }

    public function test_generate_with_business_methods(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_business_methods' => true];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];
        $methods = $service['methods'];

        $this->assertArrayHasKey('validateData', $methods);
        $this->assertArrayHasKey('applyFilters', $methods);
    }

    public function test_generate_without_business_methods(): void
    {
        $schema = $this->createTestSchema();
        $options = ['include_business_methods' => false];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $service = $jsonData['services']['UserService'];
        $methods = $service['methods'];

        $this->assertArrayNotHasKey('validateData', $methods);
        $this->assertArrayNotHasKey('applyFilters', $methods);
    }

    private function createTestSchema(): ModelSchema
    {
        $fields = [
            new Field('id', 'bigInteger'),
            new Field('name', 'string'),
            new Field('email', 'string'),
            new Field('created_at', 'timestamp'),
            new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }
}
