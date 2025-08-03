<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\FieldTypes\EnumFieldType;

describe('EnumFieldType', function () {
    beforeEach(function () {
        $this->fieldType = new EnumFieldType();
    });

    it('has correct basic properties', function () {
        expect($this->fieldType->getType())->toBe('enum');
        expect($this->fieldType->getAliases())->toBe(['enumeration']);
        expect($this->fieldType->getMigrationMethod())->toBe('enum');
        expect($this->fieldType->getCastType())->toBe('string');
    });

    it('validates enum configuration correctly', function () {
        // Valid configuration
        $validConfig = [
            'values' => ['active', 'inactive', 'pending'],
            'default_value' => 'active',
        ];
        expect($this->fieldType->validate($validConfig))->toBe([]);

        // Missing values
        $missingValues = [];
        $errors = $this->fieldType->validate($missingValues);
        expect($errors)->toContain('Enum field type requires "values" to be specified');

        // Invalid values type
        $invalidValues = ['values' => 'not_an_array'];
        $errors = $this->fieldType->validate($invalidValues);
        expect($errors)->toContain('Enum "values" must be an array');

        // Empty values
        $emptyValues = ['values' => []];
        $errors = $this->fieldType->validate($emptyValues);
        expect($errors)->toContain('Enum "values" cannot be empty');

        // Invalid value types
        $invalidValueTypes = ['values' => ['valid', null, 'also_valid']];
        $errors = $this->fieldType->validate($invalidValueTypes);
        expect($errors)->toContain('Enum value at index 1 must be a string or number');

        // Duplicate values
        $duplicateValues = ['values' => ['active', 'inactive', 'active']];
        $errors = $this->fieldType->validate($duplicateValues);
        expect($errors)->toContain('Enum values must be unique');

        // Invalid default value
        $invalidDefault = [
            'values' => ['active', 'inactive'],
            'default_value' => 'invalid',
        ];
        $errors = $this->fieldType->validate($invalidDefault);
        expect($errors)->toContain('Enum default_value must be one of the specified values');
    });

    it('generates correct validation rules', function () {
        $config = [
            'values' => ['active', 'inactive', 'pending'],
            'required' => true,
        ];

        $rules = $this->fieldType->getValidationRules($config);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string');
        expect($rules)->toContain('in:active,inactive,pending');
    });

    it('transforms configuration correctly', function () {
        $config = [
            'values' => ['active', 'inactive', 'active'], // with duplicates
        ];

        $transformed = $this->fieldType->transformConfig($config);

        expect($transformed['values'])->toBe(['active', 'inactive']);
        expect($transformed['default_value'])->toBe('active');
    });

    it('supports enum-specific attributes', function () {
        expect($this->fieldType->supportsAttribute('values'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('default_value'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('strict'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('invalid_attribute'))->toBeFalse();
    });

    it('generates migration parameters correctly', function () {
        $config = [
            'values' => ['small', 'medium', 'large'],
        ];

        $params = $this->fieldType->getMigrationParameters($config);

        expect($params[0])->toBe(['small', 'medium', 'large']);
    });

    it('provides enum options for forms', function () {
        $config = [
            'values' => ['user_type', 'admin_role', 'guest-access'],
        ];

        $options = $this->fieldType->getEnumOptions($config);

        expect($options)->toBe([
            'user_type' => 'User type',
            'admin_role' => 'Admin role',
            'guest-access' => 'Guest access',
        ]);
    });

    it('validates individual values correctly', function () {
        $config = [
            'values' => ['active', 'inactive', 'pending'],
        ];

        expect($this->fieldType->isValidValue('active', $config))->toBeTrue();
        expect($this->fieldType->isValidValue('invalid', $config))->toBeFalse();
        expect($this->fieldType->isValidValue('', $config))->toBeFalse();
    });

    it('handles numeric enum values', function () {
        $config = [
            'values' => [1, 2, 3, 'mixed'],
        ];

        $errors = $this->fieldType->validate($config);
        expect($errors)->toBe([]);

        $rules = $this->fieldType->getValidationRules($config);
        expect($rules)->toContain('in:1,2,3,mixed');
    });
});
