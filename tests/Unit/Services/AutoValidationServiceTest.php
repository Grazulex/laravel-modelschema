<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Examples\JsonSchemaFieldTypePlugin;
use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\AutoValidationService;
use Grazulex\LaravelModelschema\Support\FieldTypePluginManager;

beforeEach(function () {
    $this->pluginManager = new FieldTypePluginManager();
    $this->service = new AutoValidationService($this->pluginManager);
});

describe('AutoValidationService', function () {
    test('generates basic validation rules for standard field types', function () {
        $schema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: [
                'name' => new Field('name', 'string', false),
                'email' => new Field('email', 'email', false),
                'age' => new Field('age', 'integer', true),
                'is_active' => new Field('is_active', 'boolean', false),
            ]
        );

        $rules = $this->service->generateValidationRules($schema);

        expect($rules)->toEqual([
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
            'age' => ['nullable', 'integer'],
            'is_active' => ['required', 'boolean'],
        ]);
    });

    test('generates validation rules for individual fields', function () {
        $field = new Field('email', 'email', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toEqual(['required', 'email']);
    });

    test('handles nullable fields correctly', function () {
        $field = new Field('optional_field', 'string', true);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toEqual(['nullable', 'string']);
    });

    test('generates rules for numeric field types', function () {
        $schema = new ModelSchema(
            name: 'Product',
            table: 'products',
            fields: [
                'price' => new Field('price', 'decimal', false),
                'weight' => new Field('weight', 'float', false),
                'quantity' => new Field('quantity', 'integer', false),
                'rating' => new Field('rating', 'double', true),
            ]
        );

        $rules = $this->service->generateValidationRules($schema);

        expect($rules)->toEqual([
            'price' => ['required', 'numeric'],
            'weight' => ['required', 'numeric'],
            'quantity' => ['required', 'integer'],
            'rating' => ['nullable', 'numeric'],
        ]);
    });

    test('generates rules for date and time fields', function () {
        $schema = new ModelSchema(
            name: 'Event',
            table: 'events',
            fields: [
                'start_date' => new Field('start_date', 'date', false),
                'start_time' => new Field('start_time', 'time', false),
                'created_at' => new Field('created_at', 'dateTime', false),
                'updated_at' => new Field('updated_at', 'timestamp', true),
            ]
        );

        $rules = $this->service->generateValidationRules($schema);

        expect($rules)->toEqual([
            'start_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i:s'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['nullable', 'date'],
        ]);
    });

    test('generates rules for enum fields with values', function () {
        $field = new Field(
            name: 'status',
            type: 'enum',
            nullable: false,
            attributes: ['values' => ['active', 'inactive', 'pending']]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string');
        expect($rules)->toContain('in:active,inactive,pending');
    });

    test('generates rules for foreign key fields', function () {
        $field = new Field(
            name: 'user_id',
            type: 'foreignId',
            nullable: false
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('integer');
        expect($rules)->toContain('exists:users,id');
    });

    test('handles Laravel field attributes for string length', function () {
        $field = new Field(
            name: 'title',
            type: 'string',
            nullable: false,
            attributes: ['length' => 255]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string');
        expect($rules)->toContain('max:255');
    });

    test('handles unique constraint from attributes', function () {
        $field = new Field(
            name: 'username',
            type: 'string',
            nullable: false,
            attributes: ['unique' => true]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string');
        expect($rules)->toContain('unique:{{table}},username');
    });

    test('handles decimal precision and scale', function () {
        $field = new Field(
            name: 'price',
            type: 'decimal',
            nullable: false,
            attributes: ['precision' => 8, 'scale' => 2]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('numeric');
        expect($rules)->toContain('decimal:0,2');
    });

    test('generates rules for custom field type plugins', function () {
        // Register URL plugin
        $urlPlugin = new UrlFieldTypePlugin();
        $this->pluginManager->registerPlugin($urlPlugin);

        $field = new Field('website', 'url', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('url'); // from schemes custom attribute
    });

    test('generates custom validation rules for spatial fields', function () {
        $field = new Field('location', 'point', false);
        $customRules = $this->service->generateCustomValidationRules($field);

        expect($customRules)->toContain('spatial_format');
    });

    test('generates validation rules in string format for Laravel requests', function () {
        $schema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: [
                'name' => new Field('name', 'string', false),
                'email' => new Field('email', 'email', false),
            ]
        );

        $rulesString = $this->service->generateValidationRulesForRequest($schema, 'string');

        expect($rulesString)->toBeString();
        expect($rulesString)->toContain("'name' => 'required|string'");
        expect($rulesString)->toContain("'email' => 'required|email'");
    });

    test('generates validation messages for better UX', function () {
        $schema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: [
                'full_name' => new Field('full_name', 'string', false),
                'email_address' => new Field('email_address', 'email', false),
                'user_age' => new Field('user_age', 'integer', true),
            ]
        );

        $messages = $this->service->generateValidationMessages($schema);

        expect($messages)->toHaveKey('full_name.required');
        expect($messages['full_name.required'])->toBe('The Full Name field is required.');

        expect($messages)->toHaveKey('email_address.email');
        expect($messages['email_address.email'])->toBe('The Email Address must be a valid email address.');

        expect($messages)->toHaveKey('user_age.integer');
        expect($messages['user_age.integer'])->toBe('The User Age must be an integer.');
    });

    test('handles JSON schema field with custom attributes', function () {
        // Register JSON schema plugin
        $jsonPlugin = new JsonSchemaFieldTypePlugin();
        $this->pluginManager->registerPlugin($jsonPlugin);

        $field = new Field('metadata', 'json_schema', false);
        $customRules = $this->service->generateCustomValidationRules($field);

        // Should handle JSON validation but might not have schema attribute by default
        expect($customRules)->toBeArray();
    });

    test('handles unknown field types gracefully', function () {
        $field = new Field('unknown_field', 'unknown_type', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string'); // fallback to string
    });

    test('removes duplicate rules', function () {
        $field = new Field(
            name: 'email',
            type: 'email',
            nullable: false,
            attributes: ['unique' => true, 'length' => 255]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        // Count occurrences of each rule
        $ruleCounts = array_count_values($rules);

        // No rule should appear more than once
        foreach ($ruleCounts as $count) {
            expect($count)->toBe(1);
        }
    });

    test('determines target table from foreign key field names', function () {
        $field = new Field('category_id', 'foreignId', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('exists:categories,id');
    });

    test('handles explicit table references in attributes', function () {
        $field = new Field(
            name: 'owner_id',
            type: 'foreignId',
            nullable: false,
            attributes: [
                'references' => [
                    'table' => 'users',
                    'column' => 'id',
                ],
            ]
        );

        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('exists:users,id');
    });

    test('handles unsignedBigInteger with min constraint', function () {
        $field = new Field('id', 'unsignedBigInteger', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('integer');
        expect($rules)->toContain('min:0');
    });

    test('handles set field type as array', function () {
        $field = new Field('tags', 'set', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('array');
    });

    test('handles UUID field type', function () {
        $field = new Field('uuid', 'uuid', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('uuid');
    });

    test('handles binary field type', function () {
        $field = new Field('data', 'binary', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string');
    });

    test('handles morphs field type', function () {
        $field = new Field('morph', 'morphs', false);
        $rules = $this->service->generateFieldValidationRules($field);

        expect($rules)->toContain('required');
        expect($rules)->toContain('string'); // fallback
    });
});
