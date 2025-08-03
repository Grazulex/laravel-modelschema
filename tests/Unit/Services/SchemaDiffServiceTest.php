<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\LoggingService;
use Grazulex\LaravelModelschema\Services\SchemaDiffService;

describe('SchemaDiffService', function () {
    beforeEach(function () {
        $this->logger = new LoggingService();
        $this->service = new SchemaDiffService($this->logger);
    });

    test('compares schemas with no changes', function () {
        $field1 = new Field('name', 'string', false);
        $field2 = new Field('email', 'email', false);

        $schema1 = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field1, 'email' => $field2]
        );

        $schema2 = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field1, 'email' => $field2]
        );

        $diff = $this->service->compareSchemas($schema1, $schema2);

        expect($diff['summary']['compatibility'])->toBe('fully_compatible');
        expect($diff['field_changes']['added'])->toBeEmpty();
        expect($diff['field_changes']['removed'])->toBeEmpty();
        expect($diff['field_changes']['modified'])->toBeEmpty();
        expect($diff['breaking_changes'])->toBeEmpty();
    });

    test('detects added fields', function () {
        $oldField = new Field('name', 'string', false);
        $newField1 = new Field('name', 'string', false);
        $newField2 = new Field('email', 'email', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField1, 'email' => $newField2]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['added'])->toHaveKey('email');
        expect($diff['field_changes']['added']['email']['type'])->toBe('email');
        expect($diff['summary']['fields']['added'])->toBe(1);
        expect($diff['summary']['compatibility'])->toBe('fully_compatible');
    });

    test('detects removed fields as breaking changes', function () {
        $oldField1 = new Field('name', 'string', false);
        $oldField2 = new Field('email', 'email', false);
        $newField = new Field('name', 'string', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField1, 'email' => $oldField2]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['removed'])->toHaveKey('email');
        expect($diff['summary']['fields']['removed'])->toBe(1);
        expect($diff['breaking_changes'])->toHaveCount(1);
        expect($diff['breaking_changes'][0]['type'])->toBe('field_removed');
        expect($diff['summary']['compatibility'])->toBe('incompatible');
    });

    test('detects field type changes', function () {
        $oldField = new Field('age', 'string', false);
        $newField = new Field('age', 'integer', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['age' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['age' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('age');
        expect($diff['field_changes']['modified']['age']['type']['old'])->toBe('string');
        expect($diff['field_changes']['modified']['age']['type']['new'])->toBe('integer');
        expect($diff['field_changes']['modified']['age']['type']['breaking'])->toBeTrue();
    });

    test('detects nullable changes', function () {
        $oldField = new Field('name', 'string', true); // nullable
        $newField = new Field('name', 'string', false); // not nullable

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('name');
        expect($diff['field_changes']['modified']['name']['nullable']['breaking'])->toBeTrue();
    });

    test('detects length changes as potentially breaking', function () {
        $oldField = new Field('title', 'string', false, false, false, null, 255);
        $newField = new Field('title', 'string', false, false, false, null, 100);

        $oldSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('title');
        expect($diff['field_changes']['modified']['title']['length']['breaking'])->toBeTrue();
    });

    test('handles compatible type transitions', function () {
        $oldField = new Field('content', 'text', false);
        $newField = new Field('content', 'longText', false);

        $oldSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['content' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['content' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('content');
        expect($diff['field_changes']['modified']['content']['type']['breaking'])->toBeFalse();
    });

    test('detects table name changes', function () {
        $field = new Field('name', 'string', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'app_users',
            fields: ['name' => $field]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['schema_changes'])->toHaveKey('table_name');
        expect($diff['schema_changes']['table_name']['old'])->toBe('users');
        expect($diff['schema_changes']['table_name']['new'])->toBe('app_users');
        expect($diff['breaking_changes'])->toHaveCount(1);
        expect($diff['breaking_changes'][0]['type'])->toBe('table_renamed');
    });

    test('detects relationship changes', function () {
        $field = new Field('name', 'string', false);
        $oldRelation = new Relationship('posts', 'hasMany', 'Post');
        $newRelation = new Relationship('articles', 'hasMany', 'Article');

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field],
            relationships: ['posts' => $oldRelation]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field],
            relationships: ['articles' => $newRelation]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['relationship_changes']['added'])->toHaveKey('articles');
        expect($diff['relationship_changes']['removed'])->toHaveKey('posts');
        expect($diff['summary']['relationships']['added'])->toBe(1);
        expect($diff['summary']['relationships']['removed'])->toBe(1);
    });

    test('analyzes migration impact', function () {
        $oldField1 = new Field('name', 'string', false);
        $oldField2 = new Field('email', 'email', false);
        $newField1 = new Field('name', 'string', false);
        $newField2 = new Field('age', 'integer', true);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField1, 'email' => $oldField2]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField1, 'age' => $newField2]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['migration_impact']['requires_migration'])->toBeTrue();
        expect($diff['migration_impact']['data_loss_risk'])->toBe('high');
        expect($diff['migration_impact']['migration_operations'])->toHaveCount(2);

        $operations = collect($diff['migration_impact']['migration_operations']);
        expect($operations->pluck('type')->toArray())->toContain('add_column');
        expect($operations->pluck('type')->toArray())->toContain('drop_column');
    });

    test('analyzes validation impact', function () {
        $oldField = new Field('name', 'string', true); // nullable
        $newField = new Field('name', 'string', false); // required

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['validation_impact']['rules_changed'])->toBeTrue();
        expect($diff['validation_impact']['modified_validation'])->toHaveKey('name');
    });

    test('generates diff report', function () {
        $oldField = new Field('name', 'string', true);
        $newField = new Field('name', 'string', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);
        $report = $this->service->generateDiffReport($diff);

        expect($report)->toContain('# Schema Diff Report');
        expect($report)->toContain('Schema: User');
        expect($report)->toContain('Table: users');
        expect($report)->toContain('Fields Modified');
    });

    test('handles precision and scale changes', function () {
        $oldField = new Field('price', 'decimal', false, false, false, null, null, 10, 2);
        $newField = new Field('price', 'decimal', false, false, false, null, null, 8, 2);

        $oldSchema = new ModelSchema(
            name: 'Product',
            table: 'products',
            fields: ['price' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'Product',
            table: 'products',
            fields: ['price' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('price');
        expect($diff['field_changes']['modified']['price']['precision']['breaking'])->toBeTrue();
    });

    test('detects unique constraint additions as breaking', function () {
        $oldField = new Field('email', 'email', false, false);
        $newField = new Field('email', 'email', false, true); // unique added

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['email' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['email' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('email');
        expect($diff['field_changes']['modified']['email']['unique']['breaking'])->toBeTrue();
    });

    test('handles attribute changes', function () {
        $oldField = new Field('title', 'string', false, false, false, null, null, null, null, [], [], null, ['old_attr' => 'value']);
        $newField = new Field('title', 'string', false, false, false, null, null, null, null, [], [], null, ['new_attr' => 'value']);

        $oldSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $oldField]
        );

        $newSchema = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $newField]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['field_changes']['modified'])->toHaveKey('title');
        expect($diff['field_changes']['modified']['title']['attributes']['added'])->toHaveKey('new_attr');
        expect($diff['field_changes']['modified']['title']['attributes']['removed'])->toHaveKey('old_attr');
    });

    test('categorizes changes by impact level', function () {
        $oldField1 = new Field('name', 'string', false);
        $oldField2 = new Field('email', 'email', false);
        $newField1 = new Field('name', 'integer', false); // type change - high impact
        $newField2 = new Field('email', 'email', false, false, true); // index change - low impact

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField1, 'email' => $oldField2]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField1, 'email' => $newField2]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        $breakingChanges = collect($diff['breaking_changes']);
        $typeChange = $breakingChanges->firstWhere('change_type', 'type');

        expect($typeChange['impact'])->toBe('high');
    });

    test('handles relationship type changes', function () {
        $field = new Field('name', 'string', false);
        $oldRelation = new Relationship('profile', 'hasOne', 'Profile');
        $newRelation = new Relationship('profile', 'belongsTo', 'Profile');

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field],
            relationships: ['profile' => $oldRelation]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $field],
            relationships: ['profile' => $newRelation]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);

        expect($diff['relationship_changes']['modified'])->toHaveKey('profile');
        expect($diff['relationship_changes']['modified']['profile']['type']['breaking'])->toBeTrue();
    });

    test('identifies data loss risk levels', function () {
        // Test case 1: Field removal - high risk
        $oldField1 = new Field('name', 'string', false);
        $oldField2 = new Field('email', 'email', false);
        $newField1 = new Field('name', 'string', false);

        $oldSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $oldField1, 'email' => $oldField2]
        );

        $newSchema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => $newField1]
        );

        $diff = $this->service->compareSchemas($oldSchema, $newSchema);
        expect($diff['migration_impact']['data_loss_risk'])->toBe('high');

        // Test case 2: Length reduction - medium risk
        $oldField = new Field('title', 'string', false, false, false, null, 255);
        $newField = new Field('title', 'string', false, false, false, null, 100);

        $oldSchema2 = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $oldField]
        );

        $newSchema2 = new ModelSchema(
            name: 'Post',
            table: 'posts',
            fields: ['title' => $newField]
        );

        $diff2 = $this->service->compareSchemas($oldSchema2, $newSchema2);
        expect($diff2['migration_impact']['data_loss_risk'])->toBe('medium');
    });
});
