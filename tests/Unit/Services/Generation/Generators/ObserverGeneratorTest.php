<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\Generators\ObserverGenerator;
use Tests\TestCase;

/**
 * @covers \Grazulex\LaravelModelschema\Services\Generation\Generators\ObserverGenerator
 */
class ObserverGeneratorTest extends TestCase
{
    private ObserverGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ObserverGenerator();
    }

    public function test_get_generator_name(): void
    {
        $this->assertEquals('observer', $this->generator->getGeneratorName());
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
        $this->assertArrayHasKey('observers', $jsonData);
        $this->assertArrayHasKey('UserObserver', $jsonData['observers']);

        $observer = $jsonData['observers']['UserObserver'];
        $this->assertEquals('UserObserver', $observer['class_name']);
        $this->assertEquals('App\\Observers', $observer['namespace']);
        $this->assertEquals('App\\Models\\User', $observer['model_class']);
        $this->assertArrayHasKey('events', $observer);
        $this->assertArrayHasKey('methods', $observer);
    }

    public function test_generate_with_soft_deletes(): void
    {
        $schema = $this->createTestSchemaWithSoftDeletes();
        $result = $this->generator->generate($schema);

        $jsonData = json_decode($result['json'], true);
        $observer = $jsonData['observers']['UserObserver'];

        // Check that soft delete events are included
        $this->assertArrayHasKey('restoring', $observer['events']);
        $this->assertArrayHasKey('restored', $observer['events']);
        $this->assertArrayHasKey('forceDeleted', $observer['events']);

        // Check that soft delete methods are included
        $this->assertArrayHasKey('restoring', $observer['methods']);
        $this->assertArrayHasKey('restored', $observer['methods']);
        $this->assertArrayHasKey('forceDeleted', $observer['methods']);
    }

    public function test_generate_with_custom_namespace(): void
    {
        $schema = $this->createTestSchema();
        $options = ['observer_namespace' => 'App\\Custom\\Observers'];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $observer = $jsonData['observers']['UserObserver'];
        $this->assertEquals('App\\Custom\\Observers', $observer['namespace']);
    }

    public function test_generate_with_selective_events(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'observe_creating' => false,
            'observe_updating' => false,
            'observe_deleting' => false,
        ];
        $result = $this->generator->generate($schema, $options);

        $jsonData = json_decode($result['json'], true);
        $observer = $jsonData['observers']['UserObserver'];

        $this->assertArrayNotHasKey('creating', $observer['events']);
        $this->assertArrayNotHasKey('updating', $observer['events']);
        $this->assertArrayNotHasKey('deleting', $observer['events']);

        $this->assertArrayHasKey('created', $observer['events']);
        $this->assertArrayHasKey('updated', $observer['events']);
        $this->assertArrayHasKey('deleted', $observer['events']);
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

    private function createTestSchemaWithSoftDeletes(): ModelSchema
    {
        $fields = [
            'id' => new Field('id', 'bigInteger'),
            'name' => new Field('name', 'string'),
            'email' => new Field('email', 'string'),
            'deleted_at' => new Field('deleted_at', 'timestamp', nullable: true),
            'created_at' => new Field('created_at', 'timestamp'),
            'updated_at' => new Field('updated_at', 'timestamp'),
        ];

        return new ModelSchema(
            name: 'User',
            table: 'users',
            fields: $fields
        );
    }
}
