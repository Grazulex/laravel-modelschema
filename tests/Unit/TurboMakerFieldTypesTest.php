<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\FieldTypes\EmailFieldType;
use Grazulex\LaravelModelschema\FieldTypes\JsonFieldType;
use Grazulex\LaravelModelschema\FieldTypes\StringFieldType;
use Grazulex\LaravelModelschema\FieldTypes\UuidFieldType;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;

beforeEach(function () {
    FieldTypeRegistry::clear();
});

it('can register and use all TurboMaker field types', function () {
    FieldTypeRegistry::initialize();

    // Test basic types
    expect(FieldTypeRegistry::has('string'))->toBeTrue();
    expect(FieldTypeRegistry::has('integer'))->toBeTrue();
    expect(FieldTypeRegistry::has('boolean'))->toBeTrue();
    expect(FieldTypeRegistry::has('decimal'))->toBeTrue();

    // Test specialized integer types
    expect(FieldTypeRegistry::has('bigInteger'))->toBeTrue();
    expect(FieldTypeRegistry::has('tinyInteger'))->toBeTrue();
    expect(FieldTypeRegistry::has('smallInteger'))->toBeTrue();
    expect(FieldTypeRegistry::has('mediumInteger'))->toBeTrue();
    expect(FieldTypeRegistry::has('unsignedBigInteger'))->toBeTrue();

    // Test text types
    expect(FieldTypeRegistry::has('text'))->toBeTrue();
    expect(FieldTypeRegistry::has('longText'))->toBeTrue();
    expect(FieldTypeRegistry::has('mediumText'))->toBeTrue();

    // Test date/time types
    expect(FieldTypeRegistry::has('date'))->toBeTrue();
    expect(FieldTypeRegistry::has('datetime'))->toBeTrue();
    expect(FieldTypeRegistry::has('time'))->toBeTrue();
    expect(FieldTypeRegistry::has('timestamp'))->toBeTrue();

    // Test specialized types
    expect(FieldTypeRegistry::has('email'))->toBeTrue();
    expect(FieldTypeRegistry::has('uuid'))->toBeTrue();
    expect(FieldTypeRegistry::has('json'))->toBeTrue();
    expect(FieldTypeRegistry::has('binary'))->toBeTrue();
    expect(FieldTypeRegistry::has('foreignId'))->toBeTrue();
    expect(FieldTypeRegistry::has('morphs'))->toBeTrue();
    expect(FieldTypeRegistry::has('float'))->toBeTrue();
    expect(FieldTypeRegistry::has('double'))->toBeTrue();
});

it('can use all TurboMaker aliases', function () {
    FieldTypeRegistry::initialize();

    // Basic aliases
    expect(FieldTypeRegistry::has('varchar'))->toBeTrue();
    expect(FieldTypeRegistry::has('char'))->toBeTrue();
    expect(FieldTypeRegistry::has('int'))->toBeTrue();
    expect(FieldTypeRegistry::has('bool'))->toBeTrue();

    // Integer aliases
    expect(FieldTypeRegistry::has('bigint'))->toBeTrue();
    expect(FieldTypeRegistry::has('long'))->toBeTrue();
    expect(FieldTypeRegistry::has('tinyint'))->toBeTrue();
    expect(FieldTypeRegistry::has('smallint'))->toBeTrue();
    expect(FieldTypeRegistry::has('mediumint'))->toBeTrue();

    // Specialized aliases
    expect(FieldTypeRegistry::has('guid'))->toBeTrue();
    expect(FieldTypeRegistry::has('jsonb'))->toBeTrue();
    expect(FieldTypeRegistry::has('blob'))->toBeTrue();
    expect(FieldTypeRegistry::has('email_address'))->toBeTrue();
    expect(FieldTypeRegistry::has('foreign_id'))->toBeTrue();
    expect(FieldTypeRegistry::has('fk'))->toBeTrue();
    expect(FieldTypeRegistry::has('polymorphic'))->toBeTrue();
});

it('can get field type instances with correct methods', function () {
    FieldTypeRegistry::initialize();

    $stringType = FieldTypeRegistry::get('string');
    expect($stringType)->toBeInstanceOf(StringFieldType::class);
    expect($stringType->getType())->toBe('string');
    expect($stringType->getMigrationMethod())->toBe('string');
    expect($stringType->getCastType())->toBe('string');

    $emailType = FieldTypeRegistry::get('email');
    expect($emailType)->toBeInstanceOf(EmailFieldType::class);
    expect($emailType->getType())->toBe('email');
    expect($emailType->getMigrationMethod())->toBe('string');
    expect($emailType->getValidationRules([]))->toContain('email');

    $uuidType = FieldTypeRegistry::get('uuid');
    expect($uuidType)->toBeInstanceOf(UuidFieldType::class);
    expect($uuidType->getType())->toBe('uuid');
    expect($uuidType->getMigrationMethod())->toBe('uuid');

    $jsonType = FieldTypeRegistry::get('json');
    expect($jsonType)->toBeInstanceOf(JsonFieldType::class);
    expect($jsonType->getType())->toBe('json');
    expect($jsonType->getCastType())->toBe('array');
});

it('validates field configurations correctly', function () {
    FieldTypeRegistry::initialize();

    $emailType = FieldTypeRegistry::get('email');

    // Valid configuration
    $validConfig = ['length' => 255, 'nullable' => false];
    $errors = $emailType->validate($validConfig);
    expect($errors)->toBeEmpty();

    // Invalid configuration
    $invalidConfig = ['length' => 10]; // Too short for email
    $errors = $emailType->validate($invalidConfig);
    expect($errors)->toContain('Email field length should be at least 50 characters');
});

it('transforms field configurations correctly', function () {
    FieldTypeRegistry::initialize();

    $emailType = FieldTypeRegistry::get('email');

    // Transform with defaults
    $config = $emailType->transformConfig([]);
    expect($config['length'])->toBe(255);
    expect($config['index'])->toBeTrue();

    $uuidType = FieldTypeRegistry::get('uuid');
    $config = $uuidType->transformConfig([]);
    expect($config['index'])->toBeTrue();
});

it('provides correct migration parameters', function () {
    FieldTypeRegistry::initialize();

    $stringType = FieldTypeRegistry::get('string');
    $params = $stringType->getMigrationParameters(['length' => 100]);
    expect($params)->toBe([100]);

    $decimalType = FieldTypeRegistry::get('decimal');
    $params = $decimalType->getMigrationParameters(['precision' => 10, 'scale' => 2]);
    expect($params)->toBe([10, 2]);
});

it('supports attribute checking correctly', function () {
    FieldTypeRegistry::initialize();

    $stringType = FieldTypeRegistry::get('string');
    expect($stringType->supportsAttribute('length'))->toBeTrue();
    expect($stringType->supportsAttribute('fixed'))->toBeTrue();
    expect($stringType->supportsAttribute('precision'))->toBeFalse();

    $decimalType = FieldTypeRegistry::get('decimal');
    expect($decimalType->supportsAttribute('precision'))->toBeTrue();
    expect($decimalType->supportsAttribute('scale'))->toBeTrue();
    expect($decimalType->supportsAttribute('length'))->toBeFalse();

    $foreignIdType = FieldTypeRegistry::get('foreignId');
    expect($foreignIdType->supportsAttribute('references'))->toBeTrue();
    expect($foreignIdType->supportsAttribute('on'))->toBeTrue();
    expect($foreignIdType->supportsAttribute('onDelete'))->toBeTrue();
});

it('generates appropriate validation rules for different types', function () {
    FieldTypeRegistry::initialize();

    // Integer validation
    $intType = FieldTypeRegistry::get('integer');
    $rules = $intType->getValidationRules([]);
    expect($rules)->toContain('integer');

    // Email validation
    $emailType = FieldTypeRegistry::get('email');
    $rules = $emailType->getValidationRules([]);
    expect($rules)->toContain('email');
    expect($rules)->toContain('max:255');

    // UUID validation
    $uuidType = FieldTypeRegistry::get('uuid');
    $rules = $uuidType->getValidationRules([]);
    expect($rules)->toContain('uuid');

    // Boolean validation
    $boolType = FieldTypeRegistry::get('boolean');
    $rules = $boolType->getValidationRules([]);
    expect($rules)->toContain('boolean');

    // JSON validation
    $jsonType = FieldTypeRegistry::get('json');
    $rules = $jsonType->getValidationRules([]);
    expect($rules)->toContain('array');
});
