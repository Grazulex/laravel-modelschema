<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Generation;

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Tests\TestCase;

/**
 * Test des nouveaux générateurs Observer, Service, Action, Rule
 */
class GenerationServiceNewGeneratorsTest extends TestCase
{
    private GenerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GenerationService();
    }

    public function test_get_generator_instances_includes_new_generators(): void
    {
        $generators = $this->service->getGeneratorInstances();

        $this->assertArrayHasKey('observers', $generators);
        $this->assertArrayHasKey('services', $generators);
        $this->assertArrayHasKey('actions', $generators);
        $this->assertArrayHasKey('rules', $generators);
    }

    public function test_get_available_generators_includes_new_generators(): void
    {
        $availableGenerators = $this->service->getAvailableGenerators();

        $this->assertArrayHasKey('observers', $availableGenerators);
        $this->assertArrayHasKey('services', $availableGenerators);
        $this->assertArrayHasKey('actions', $availableGenerators);
        $this->assertArrayHasKey('rules', $availableGenerators);

        // Check descriptions
        $this->assertStringContainsString('Observer', $availableGenerators['observers']['name']);
        $this->assertStringContainsString('Service', $availableGenerators['services']['name']);
        $this->assertStringContainsString('Action', $availableGenerators['actions']['name']);
        $this->assertStringContainsString('Rule', $availableGenerators['rules']['name']);
    }

    public function test_get_available_generator_names_includes_new_generators(): void
    {
        $generatorNames = $this->service->getAvailableGeneratorNames();

        $this->assertContains('observer', $generatorNames);
        $this->assertContains('service', $generatorNames);
        $this->assertContains('action', $generatorNames);
        $this->assertContains('rule', $generatorNames);
    }

    public function test_generate_observers(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->service->generateObservers($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);
        $this->assertNotEmpty($result['json']);
        $this->assertNotEmpty($result['yaml']);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('observers', $jsonData);
    }

    public function test_generate_services(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->service->generateServices($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);
        $this->assertNotEmpty($result['json']);
        $this->assertNotEmpty($result['yaml']);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('services', $jsonData);
    }

    public function test_generate_actions(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->service->generateActions($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);
        $this->assertNotEmpty($result['json']);
        $this->assertNotEmpty($result['yaml']);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('actions', $jsonData);
    }

    public function test_generate_rules(): void
    {
        $schema = $this->createTestSchema();
        $result = $this->service->generateRules($schema);

        $this->assertArrayHasKey('json', $result);
        $this->assertArrayHasKey('yaml', $result);
        $this->assertNotEmpty($result['json']);
        $this->assertNotEmpty($result['yaml']);

        $jsonData = json_decode($result['json'], true);
        $this->assertArrayHasKey('rules', $jsonData);
    }

    public function test_generate_all_with_new_generators(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'model' => true,
            'migration' => true,
            'observers' => true,
            'services' => true,
            'actions' => true,
            'rules' => true,
        ];

        $result = $this->service->generateAll($schema, $options);

        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('migration', $result);
        $this->assertArrayHasKey('observers', $result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('rules', $result);

        // Check each result has the expected structure
        foreach (['observers', 'services', 'actions', 'rules'] as $type) {
            $this->assertArrayHasKey('json', $result[$type]);
            $this->assertArrayHasKey('yaml', $result[$type]);
            $this->assertNotEmpty($result[$type]['json']);
            $this->assertNotEmpty($result[$type]['yaml']);
        }
    }

    public function test_generate_all_with_status_includes_new_generators(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'observers' => true,
            'services' => true,
            'actions' => true,
            'rules' => true,
        ];

        $result = $this->service->generateAllWithStatus($schema, $options);

        $this->assertArrayHasKey('observers', $result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('rules', $result);

        // Check each result has success status
        foreach (['observers', 'services', 'actions', 'rules'] as $type) {
            $this->assertArrayHasKey('success', $result[$type]);
            $this->assertTrue($result[$type]['success']);
            $this->assertArrayHasKey('json', $result[$type]);
            $this->assertArrayHasKey('yaml', $result[$type]);
        }
    }

    public function test_generate_all_with_debug_includes_new_generators(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'observers' => true,
            'services' => true,
            'actions' => true,
            'rules' => true,
            'debug' => true,
        ];

        // Capture output for debug mode
        ob_start();
        $result = $this->service->generateAllWithDebug($schema, $options);
        $output = ob_get_clean();

        $this->assertArrayHasKey('observers', $result);
        $this->assertArrayHasKey('services', $result);
        $this->assertArrayHasKey('actions', $result);
        $this->assertArrayHasKey('rules', $result);

        // Check debug output mentions new generators
        $this->assertStringContainsString('observers', $output);
        $this->assertStringContainsString('services', $output);
        $this->assertStringContainsString('actions', $output);
        $this->assertStringContainsString('rules', $output);
    }

    public function test_generate_specific_type(): void
    {
        $schema = $this->createTestSchema();

        // Test each new generator type
        $types = ['observers', 'services', 'actions', 'rules'];

        foreach ($types as $type) {
            $result = $this->service->generate($schema, $type);

            $this->assertArrayHasKey('json', $result);
            $this->assertArrayHasKey('yaml', $result);
            $this->assertNotEmpty($result['json']);
            $this->assertNotEmpty($result['yaml']);
        }
    }

    public function test_generate_multiple_with_new_generators(): void
    {
        $schema = $this->createTestSchema();
        $generators = ['observers', 'services', 'actions', 'rules'];

        $result = $this->service->generateMultiple($schema, $generators);

        $this->assertArrayHasKey('individual_results', $result);
        $individualResults = $result['individual_results'];

        $this->assertArrayHasKey('observers', $individualResults);
        $this->assertArrayHasKey('services', $individualResults);
        $this->assertArrayHasKey('actions', $individualResults);
        $this->assertArrayHasKey('rules', $individualResults);

        foreach ($generators as $generator) {
            $this->assertArrayHasKey('json', $individualResults[$generator]);
            $this->assertArrayHasKey('yaml', $individualResults[$generator]);
        }
    }

    public function test_all_generators_work_together(): void
    {
        $schema = $this->createTestSchema();
        $options = [
            'model' => true,
            'migration' => true,
            'requests' => true,
            'resources' => true,
            'factory' => true,
            'seeder' => true,
            'controllers' => true,
            'tests' => true,
            'policies' => true,
            'observers' => true,
            'services' => true,
            'actions' => true,
            'rules' => true,
        ];

        $result = $this->service->generateAll($schema, $options);

        // All 13 generators should be present
        $this->assertCount(13, $result);

        $expectedGenerators = [
            'model', 'migration', 'requests', 'resources', 'factory',
            'seeder', 'controllers', 'tests', 'policies',
            'observers', 'services', 'actions', 'rules',
        ];

        foreach ($expectedGenerators as $generator) {
            $this->assertArrayHasKey($generator, $result);
            $this->assertArrayHasKey('json', $result[$generator]);
            $this->assertArrayHasKey('yaml', $result[$generator]);
        }
    }

    public function test_get_generator_info_includes_new_generators(): void
    {
        $generatorInfo = $this->service->getGeneratorInfo();

        $this->assertArrayHasKey('observers', $generatorInfo);
        $this->assertArrayHasKey('services', $generatorInfo);
        $this->assertArrayHasKey('actions', $generatorInfo);
        $this->assertArrayHasKey('rules', $generatorInfo);

        foreach (['observers', 'services', 'actions', 'rules'] as $type) {
            $info = $generatorInfo[$type];
            $this->assertArrayHasKey('name', $info);
            $this->assertArrayHasKey('description', $info);
            $this->assertArrayHasKey('formats', $info);
            $this->assertContains('json', $info['formats']);
            $this->assertContains('yaml', $info['formats']);
        }
    }

    private function createTestSchema(): ModelSchema
    {
        $fields = [
            new Field('id', 'bigInteger'),
            new Field('name', 'string'),
            new Field('email', 'string'),
            new Field('status', 'string'),
            new Field('category_id', 'unsignedBigInteger'),
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
