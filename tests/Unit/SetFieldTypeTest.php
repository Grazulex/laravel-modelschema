<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\FieldTypes\SetFieldType;

describe('SetFieldType', function () {
    beforeEach(function () {
        $this->fieldType = new SetFieldType();
    });

    it('has correct basic properties', function () {
        expect($this->fieldType->getType())->toBe('set');
        expect($this->fieldType->getAliases())->toBe(['multi_select', 'multiple_choice']);
        expect($this->fieldType->getMigrationMethod())->toBe('set');
        expect($this->fieldType->getCastType())->toBe('array');
    });

    it('validates set configuration correctly', function () {
        // Valid configuration
        $validConfig = [
            'values' => ['read', 'write', 'execute'],
            'max_selections' => 2,
            'separator' => ',',
        ];
        expect($this->fieldType->validate($validConfig))->toBe([]);

        // Missing values
        $missingValues = [];
        $errors = $this->fieldType->validate($missingValues);
        expect($errors)->toContain('Set field type requires "values" to be specified');

        // Invalid values type
        $invalidValues = ['values' => 'not_an_array'];
        $errors = $this->fieldType->validate($invalidValues);
        expect($errors)->toContain('Set "values" must be an array');

        // Empty values
        $emptyValues = ['values' => []];
        $errors = $this->fieldType->validate($emptyValues);
        expect($errors)->toContain('Set "values" cannot be empty');

        // Invalid max_selections
        $invalidMaxSelections = [
            'values' => ['a', 'b', 'c'],
            'max_selections' => 0,
        ];
        $errors = $this->fieldType->validate($invalidMaxSelections);
        expect($errors)->toContain('Set max_selections must be a positive integer');

        // Max selections greater than available values
        $tooManySelections = [
            'values' => ['a', 'b'],
            'max_selections' => 5,
        ];
        $errors = $this->fieldType->validate($tooManySelections);
        expect($errors)->toContain('Set max_selections cannot be greater than the number of available values');

        // Invalid separator
        $invalidSeparator = [
            'values' => ['a', 'b'],
            'separator' => '',
        ];
        $errors = $this->fieldType->validate($invalidSeparator);
        expect($errors)->toContain('Set separator must be a non-empty string');
    });

    it('generates correct validation rules', function () {
        $config = [
            'values' => ['read', 'write', 'execute'],
            'max_selections' => 2,
            'required' => true,
        ];

        $rules = $this->fieldType->getValidationRules($config);

        expect($rules)->toContain('required');
        expect($rules)->toContain('array');
        expect($rules)->toContain('*.in:read,write,execute');
        expect($rules)->toContain('max:2');
    });

    it('transforms configuration correctly', function () {
        $config = [
            'values' => ['read', 'write', 'read'], // with duplicates
        ];

        $transformed = $this->fieldType->transformConfig($config);

        expect($transformed['values'])->toBe(['read', 'write']);
        expect($transformed['separator'])->toBe(',');
        expect($transformed['max_selections'])->toBe(2);
    });

    it('supports set-specific attributes', function () {
        expect($this->fieldType->supportsAttribute('values'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('separator'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('max_selections'))->toBeTrue();
        expect($this->fieldType->supportsAttribute('invalid_attribute'))->toBeFalse();
    });

    it('generates migration parameters correctly', function () {
        $config = [
            'values' => ['small', 'medium', 'large'],
        ];

        $params = $this->fieldType->getMigrationParameters($config);

        expect($params[0])->toBe(['small', 'medium', 'large']);
    });

    it('provides set options for forms', function () {
        $config = [
            'values' => ['user_read', 'admin_write', 'guest-access'],
        ];

        $options = $this->fieldType->getSetOptions($config);

        expect($options)->toBe([
            'user_read' => 'User read',
            'admin_write' => 'Admin write',
            'guest-access' => 'Guest access',
        ]);
    });

    it('validates multiple values correctly', function () {
        $config = [
            'values' => ['read', 'write', 'execute'],
            'max_selections' => 2,
        ];

        expect($this->fieldType->areValidValues(['read', 'write'], $config))->toBeTrue();
        expect($this->fieldType->areValidValues(['read'], $config))->toBeTrue();
        expect($this->fieldType->areValidValues(['read', 'write', 'execute'], $config))->toBeFalse(); // too many
        expect($this->fieldType->areValidValues(['read', 'invalid'], $config))->toBeFalse(); // invalid value
    });

    it('converts between string and array representations', function () {
        $config = [
            'values' => ['read', 'write', 'execute'],
            'separator' => '|',
        ];

        $values = ['read', 'write'];
        $string = $this->fieldType->valuesToString($values, $config);
        expect($string)->toBe('read|write');

        $parsedValues = $this->fieldType->stringToValues($string, $config);
        expect($parsedValues)->toBe(['read', 'write']);
    });

    it('handles custom separators', function () {
        $config = [
            'values' => ['a', 'b', 'c'],
            'separator' => ' | ',
        ];

        $values = ['a', 'c'];
        $string = $this->fieldType->valuesToString($values, $config);
        expect($string)->toBe('a | c');

        $parsedValues = $this->fieldType->stringToValues('a | c', $config);
        expect($parsedValues)->toBe(['a', 'c']);
    });

    it('handles empty values in string conversion', function () {
        $config = ['separator' => ','];

        $parsedValues = $this->fieldType->stringToValues('a,,b,', $config);
        expect($parsedValues)->toBe(['a', 'b']);
    });
});
