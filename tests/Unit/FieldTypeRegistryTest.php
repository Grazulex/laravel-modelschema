<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\FieldTypes\EmailFieldType;
use Grazulex\LaravelModelschema\FieldTypes\StringFieldType;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Tests\TestCase;

class FieldTypeRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldTypeRegistry::clear();
    }

    protected function tearDown(): void
    {
        FieldTypeRegistry::clear();
        parent::tearDown();
    }

    public function test_can_register_and_get_field_type(): void
    {
        FieldTypeRegistry::register('string', StringFieldType::class);

        $fieldType = FieldTypeRegistry::get('string');

        expect($fieldType)->toBeInstanceOf(StringFieldType::class);
        expect($fieldType->getType())->toBe('string');
    }

    public function test_can_register_and_use_aliases(): void
    {
        FieldTypeRegistry::register('string', StringFieldType::class);
        FieldTypeRegistry::registerAlias('varchar', 'string');

        $fieldType = FieldTypeRegistry::get('varchar');

        expect($fieldType)->toBeInstanceOf(StringFieldType::class);
        expect($fieldType->getType())->toBe('string');
    }

    public function test_throws_exception_for_unknown_field_type(): void
    {
        expect(fn () => FieldTypeRegistry::get('unknown'))
            ->toThrow(SchemaException::class);
    }

    public function test_can_check_if_field_type_exists(): void
    {
        FieldTypeRegistry::register('string', StringFieldType::class);

        expect(FieldTypeRegistry::has('string'))->toBeTrue();
        expect(FieldTypeRegistry::has('unknown'))->toBeFalse();
    }

    public function test_initializes_default_field_types(): void
    {
        FieldTypeRegistry::initialize();

        expect(FieldTypeRegistry::has('string'))->toBeTrue();
        expect(FieldTypeRegistry::has('integer'))->toBeTrue();
        expect(FieldTypeRegistry::has('decimal'))->toBeTrue();
        expect(FieldTypeRegistry::has('boolean'))->toBeTrue();
        expect(FieldTypeRegistry::has('text'))->toBeTrue();
        expect(FieldTypeRegistry::has('datetime'))->toBeTrue();
        expect(FieldTypeRegistry::has('email'))->toBeTrue();
    }

    public function test_initializes_default_aliases(): void
    {
        FieldTypeRegistry::initialize();

        expect(FieldTypeRegistry::has('varchar'))->toBeTrue();
        expect(FieldTypeRegistry::has('int'))->toBeTrue();
        expect(FieldTypeRegistry::has('bool'))->toBeTrue();
        expect(FieldTypeRegistry::has('timestamp'))->toBeTrue();
        expect(FieldTypeRegistry::has('email_address'))->toBeTrue();
    }

    public function test_can_get_all_registered_types(): void
    {
        FieldTypeRegistry::initialize();

        $types = FieldTypeRegistry::all();

        expect($types)->toContain('string', 'integer', 'decimal', 'boolean', 'text', 'datetime', 'email');
        expect($types)->toContain('varchar', 'int', 'bool', 'timestamp', 'email_address');
    }

    public function test_can_get_base_types_only(): void
    {
        FieldTypeRegistry::initialize();

        $baseTypes = FieldTypeRegistry::getBaseTypes();

        expect($baseTypes)->toContain('string', 'integer', 'decimal', 'boolean', 'text', 'datetime', 'email');
        expect($baseTypes)->not->toContain('varchar', 'int', 'bool', 'timestamp', 'email_address');
    }

    public function test_caches_field_type_instances(): void
    {
        FieldTypeRegistry::register('string', StringFieldType::class);

        $instance1 = FieldTypeRegistry::get('string');
        $instance2 = FieldTypeRegistry::get('string');

        expect($instance1)->toBe($instance2);
    }

    public function test_email_field_type_validation(): void
    {
        $emailFieldType = new EmailFieldType();

        expect($emailFieldType->getType())->toBe('email');
        expect($emailFieldType->getAliases())->toContain('email_address');

        $rules = $emailFieldType->getValidationRules();
        expect($rules)->toContain('email');
        expect($rules)->toContain('max:255');
    }

    public function test_email_field_type_config_transformation(): void
    {
        $emailFieldType = new EmailFieldType();

        $config = $emailFieldType->transformConfig([]);

        expect($config['length'])->toBe(255);
        expect($config['index'])->toBeTrue();
    }
}
