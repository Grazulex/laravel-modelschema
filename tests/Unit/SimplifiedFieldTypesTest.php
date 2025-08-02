<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;

// Create a comprehensive dataset for all field types
dataset('fieldTypes', [
    'string',
    'text',
    'integer',
    'bigInteger',
    'decimal',
    'float',
    'boolean',
    'json',
    'date',
    'timestamp',
    'uuid',
    'email',
]);

it('can register and get all field types', function (string $type) {
    expect(FieldTypeRegistry::has($type))->toBeTrue();

    $fieldType = FieldTypeRegistry::get($type);
    expect($fieldType)->not->toBeNull();
    expect($fieldType->getMigrationMethod())->toBeString();
})->with('fieldTypes');

it('generates validation rules for all field types', function (string $type) {
    $fieldType = FieldTypeRegistry::get($type);
    $rules = $fieldType->getValidationRules([]);

    expect($rules)->toBeArray();
})->with('fieldTypes');

it('provides migration parameters for all field types', function (string $type) {
    $fieldType = FieldTypeRegistry::get($type);

    $params = $fieldType->getMigrationParameters([]);
    expect($params)->toBeArray();
})->with('fieldTypes');
