<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\FieldTypes\EnumFieldType;
use Grazulex\LaravelModelschema\FieldTypes\SetFieldType;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;

describe('New Field Types Integration', function () {
    beforeEach(function () {
        // Force re-initialization for clean state
        FieldTypeRegistry::initialize();
    });

    it('can register and use enum field type', function () {
        expect(FieldTypeRegistry::has('enum'))->toBeTrue();
        expect(FieldTypeRegistry::has('enumeration'))->toBeTrue(); // alias

        $enumType = FieldTypeRegistry::get('enum');
        expect($enumType)->toBeInstanceOf(EnumFieldType::class);

        $aliasType = FieldTypeRegistry::get('enumeration');
        expect($aliasType)->toBeInstanceOf(EnumFieldType::class);
    });

    it('can register and use set field type', function () {
        expect(FieldTypeRegistry::has('set'))->toBeTrue();
        expect(FieldTypeRegistry::has('multi_select'))->toBeTrue(); // alias
        expect(FieldTypeRegistry::has('multiple_choice'))->toBeTrue(); // alias

        $setType = FieldTypeRegistry::get('set');
        expect($setType)->toBeInstanceOf(SetFieldType::class);

        $aliasType = FieldTypeRegistry::get('multi_select');
        expect($aliasType)->toBeInstanceOf(SetFieldType::class);
    });

    it('includes new types in all registered types list', function () {
        $allTypes = FieldTypeRegistry::all();

        expect($allTypes)->toContain('enum');
        expect($allTypes)->toContain('set');
        expect($allTypes)->toContain('enumeration'); // alias
        expect($allTypes)->toContain('multi_select'); // alias
        expect($allTypes)->toContain('multiple_choice'); // alias
    });

    it('includes new types in base types only', function () {
        $baseTypes = FieldTypeRegistry::getBaseTypes();

        expect($baseTypes)->toContain('enum');
        expect($baseTypes)->toContain('set');
        // Note: getBaseTypes() might include aliases depending on implementation
    });

    it('can validate enum field configurations', function () {
        $enumType = FieldTypeRegistry::get('enum');

        $config = [
            'values' => ['active', 'inactive', 'pending'],
            'default_value' => 'active',
        ];

        $rules = $enumType->getValidationRules($config);
        expect($rules)->toContain('string');
        expect($rules)->toContain('in:active,inactive,pending');

        $params = $enumType->getMigrationParameters($config);
        expect($params[0])->toBe(['active', 'inactive', 'pending']);
    });

    it('can validate set field configurations', function () {
        $setType = FieldTypeRegistry::get('set');

        $config = [
            'values' => ['read', 'write', 'execute'],
            'max_selections' => 2,
        ];

        $rules = $setType->getValidationRules($config);
        expect($rules)->toContain('array');
        expect($rules)->toContain('*.in:read,write,execute');
        expect($rules)->toContain('max:2');

        $params = $setType->getMigrationParameters($config);
        expect($params[0])->toBe(['read', 'write', 'execute']);
    });
});
