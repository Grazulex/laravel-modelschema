<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\SchemaCacheService;
use Grazulex\LaravelModelschema\Services\SchemaDiffService;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;

describe('ModelSchema version', function () {
    it('is null when the schema is not versioned', function () {
        $schema = new ModelSchema('User', 'users');

        expect($schema->version)->toBeNull();
        expect($schema->hasVersion())->toBeFalse();
    });

    it('keeps existing schemas without a version unchanged', function () {
        $schema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => ['name' => ['type' => 'string']],
            'metadata' => ['version' => '1.0'],
        ]);

        expect($schema->version)->toBeNull();
        expect($schema->metadata)->toBe(['version' => '1.0']);
        expect($schema->toArray())->not->toHaveKey('version');
        expect(array_keys($schema->toArray()))->toBe([
            'model', 'table', 'fields', 'relationships', 'options', 'metadata',
        ]);
    });

    it('accepts a version in the constructor', function () {
        $schema = new ModelSchema(name: 'User', table: 'users', version: '2.1.3');

        expect($schema->version)->toBe('2.1.3');
        expect($schema->hasVersion())->toBeTrue();
    });

    it('normalizes versions to major.minor.patch', function (mixed $input, string $expected) {
        expect(ModelSchema::normalizeVersion($input))->toBe($expected);
        expect(ModelSchema::isValidVersion($input))->toBeTrue();
    })->with([
        'integer' => [1, '1.0.0'],
        'zero' => [0, '0.0.0'],
        'float' => [2.1, '2.1.0'],
        'whole float' => [1.0, '1.0.0'],
        'major only string' => ['3', '3.0.0'],
        'major.minor string' => ['1.2', '1.2.0'],
        'full string' => ['1.2.3', '1.2.3'],
        'two digit segments' => ['10.20.30', '10.20.30'],
        'surrounding spaces' => [' 1.4.0 ', '1.4.0'],
    ]);

    it('returns null when normalizing a missing version', function () {
        expect(ModelSchema::normalizeVersion(null))->toBeNull();
        expect(ModelSchema::isValidVersion(null))->toBeFalse();
    });

    it('rejects invalid versions', function (mixed $input) {
        expect(ModelSchema::isValidVersion($input))->toBeFalse();

        expect(fn () => ModelSchema::normalizeVersion($input))
            ->toThrow(SchemaException::class, 'Invalid schema version');
    })->with([
        'empty string' => [''],
        'text' => ['latest'],
        'v prefix' => ['v1.0.0'],
        'pre-release suffix' => ['1.0.0-beta'],
        'four segments' => ['1.2.3.4'],
        'trailing dot' => ['1.2.'],
        'leading zero' => ['01.2.3'],
        'negative integer' => [-1],
        'negative float' => [-1.5],
        'infinite float' => [INF],
        'boolean' => [true],
        'array' => [['major' => 1]],
    ]);

    it('rejects an invalid version in the constructor', function () {
        expect(fn () => new ModelSchema(name: 'User', table: 'users', version: 'draft'))
            ->toThrow(SchemaException::class, "Invalid schema version 'draft'");
    });

    it('reads the version from an array configuration', function () {
        $schema = ModelSchema::fromArray('User', [
            'version' => '1.2',
            'fields' => ['name' => ['type' => 'string']],
        ]);

        expect($schema->version)->toBe('1.2.0');
    });

    it('rejects an invalid version in an array configuration', function () {
        expect(fn () => ModelSchema::fromArray('User', [
            'version' => 'next',
            'fields' => ['name' => ['type' => 'string']],
        ]))->toThrow(SchemaException::class, "Invalid schema version 'next'");
    });

    it('reads the version from YAML content', function (string $yamlVersion, string $expected) {
        $yaml = <<<YAML
model: User
version: {$yamlVersion}
table: users
fields:
  name:
    type: string
YAML;

        expect(ModelSchema::fromYaml($yaml)->version)->toBe($expected);
    })->with([
        'unquoted integer' => ['2', '2.0.0'],
        'unquoted float' => ['1.5', '1.5.0'],
        'unquoted semantic version' => ['1.2.3', '1.2.3'],
        'quoted version' => ['"1.10"', '1.10.0'],
    ]);

    it('treats an empty YAML version as unversioned', function () {
        $yaml = <<<'YAML'
model: User
version:
fields:
  name:
    type: string
YAML;

        expect(ModelSchema::fromYaml($yaml)->version)->toBeNull();
    });

    it('rejects an invalid version in YAML content', function () {
        $yaml = <<<'YAML'
model: User
version: one
fields:
  name:
    type: string
YAML;

        expect(fn () => ModelSchema::fromYaml($yaml))
            ->toThrow(SchemaException::class, "Invalid schema version 'one'");
    });

    it('validates the version when reading a YAML file', function () {
        $directory = sys_get_temp_dir().'/laravel-modelschema-version-test-'.uniqid();
        mkdir($directory, 0755, true);

        $validFile = $directory.'/valid.schema.yml';
        $invalidFile = $directory.'/invalid.schema.yml';
        file_put_contents($validFile, "model: User\nversion: 3.1\nfields:\n  name:\n    type: string\n");
        file_put_contents($invalidFile, "model: User\nversion: 3.x\nfields:\n  name:\n    type: string\n");

        try {
            expect(ModelSchema::fromYamlFile($validFile)->version)->toBe('3.1.0');

            expect(fn () => ModelSchema::fromYamlFile($invalidFile))
                ->toThrow(SchemaException::class, "Invalid schema version '3.x'");
        } finally {
            unlink($validFile);
            unlink($invalidFile);
            rmdir($directory);
        }
    });

    it('exports the version and reads it back', function () {
        $schema = new ModelSchema(
            name: 'User',
            table: 'users',
            fields: ['name' => new Field('name', 'string')],
            version: '1.4',
        );

        $array = $schema->toArray();

        expect($array['version'])->toBe('1.4.0');
        expect(json_decode($schema->toJson(), true)['version'])->toBe('1.4.0');
        expect(ModelSchema::fromArray('User', $array)->version)->toBe('1.4.0');
    });

    it('compares versions', function () {
        $v1 = new ModelSchema(name: 'User', table: 'users', version: '1.9.0');
        $v2 = new ModelSchema(name: 'User', table: 'users', version: '1.10.0');
        $unversioned = new ModelSchema('User', 'users');

        expect($v2->compareVersion($v1))->toBe(1);
        expect($v1->compareVersion($v2))->toBe(-1);
        expect($v1->compareVersion('1.9'))->toBe(0);

        expect($v2->isNewerThan($v1))->toBeTrue();
        expect($v1->isNewerThan($v2))->toBeFalse();
        expect($v1->isOlderThan($v2))->toBeTrue();
        expect($v1->isOlderThan('1.9.0'))->toBeFalse();
        expect($v1->isNewerThan(1))->toBeTrue();

        // An unversioned schema is compared as 0.0.0
        expect($v1->isNewerThan($unversioned))->toBeTrue();
        expect($unversioned->isOlderThan($v1))->toBeTrue();
        expect($unversioned->compareVersion(null))->toBe(0);
    });

    it('rejects an invalid version when comparing', function () {
        $schema = new ModelSchema(name: 'User', table: 'users', version: '1.0.0');

        expect(fn () => $schema->compareVersion('newest'))
            ->toThrow(SchemaException::class, 'Invalid schema version');
    });
});

describe('SchemaService version support', function () {
    beforeEach(function () {
        $this->schemaService = new SchemaService(new Filesystem());
    });

    it('lists version as a core schema key', function () {
        expect($this->schemaService->getCoreSchemaKeys())->toContain('version');
    });

    it('parses the version from YAML content', function () {
        $yaml = <<<'YAML'
model: Article
version: 2.0.1
fields:
  title:
    type: string
YAML;

        $schema = $this->schemaService->parseYamlContent($yaml);

        expect($schema->version)->toBe('2.0.1');
        // Second call is served from the cache
        expect($this->schemaService->parseYamlContent($yaml)->version)->toBe('2.0.1');
    });

    it('reads the version from the core section and leaves the application version alone', function () {
        $yaml = <<<'YAML'
app_name: MyLaravelApp
version: app-release-7

core:
  model: Product
  version: 1.3
  table: products
  fields:
    name:
      type: string

turbomaker:
  crud_enabled: true
YAML;

        $result = $this->schemaService->parseAndSeparateSchema($yaml);

        expect($result['core_schema']->version)->toBe('1.3.0');
        expect($result['extension_data']['version'])->toBe('app-release-7');
        expect($this->schemaService->validateFromCompleteAppYaml($yaml))->toBeEmpty();
    });

    it('treats the version of a flat schema as core data', function () {
        $yaml = <<<'YAML'
model: Product
version: 4
table: products
fields:
  name:
    type: string
custom_extension:
  enabled: true
YAML;

        $result = $this->schemaService->parseAndSeparateSchema($yaml);

        expect($result['core_schema']->version)->toBe('4.0.0');
        expect($result['core_data'])->toHaveKey('version');
        expect($result['extension_data'])->not->toHaveKey('version');
        expect($result['extension_data'])->toHaveKey('custom_extension');
    });

    it('throws when the core version is invalid', function () {
        $yaml = <<<'YAML'
core:
  model: Product
  version: beta
  fields:
    name:
      type: string
YAML;

        expect(fn () => $this->schemaService->parseAndSeparateSchema($yaml))
            ->toThrow(SchemaException::class, "Invalid schema version 'beta'");
    });

    it('reports an invalid version as a validation error for complete app YAML', function () {
        $yaml = <<<'YAML'
core:
  model: Product
  version: beta
  fields:
    name:
      type: string
YAML;

        $errors = $this->schemaService->validateFromCompleteAppYaml($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0])->toContain("Invalid schema version 'beta'");
    });

    it('writes the version to YAML and reads it back', function () {
        $schema = new ModelSchema(
            name: 'Product',
            table: 'products',
            fields: ['name' => new Field('name', 'string')],
            version: '1.2.0',
        );

        $yaml = $this->schemaService->convertSchemaToYaml($schema);

        expect($yaml)->toContain('version:');
        expect($yaml)->toContain('1.2.0');
        expect(ModelSchema::fromYaml($yaml)->version)->toBe('1.2.0');
    });

    it('does not write a version for unversioned schemas', function () {
        $schema = new ModelSchema('Product', 'products', ['name' => new Field('name', 'string')]);

        expect($this->schemaService->convertSchemaToYaml($schema))->not->toContain('version');
    });
});

describe('SchemaCacheService version support', function () {
    it('ignores cached schemas serialized before the version property existed', function () {
        $cache = new SchemaCacheService();
        $schema = new ModelSchema('User', 'users', ['name' => new Field('name', 'string')]);

        // Rebuild the payload an older release of the package would have stored
        $serialized = serialize($schema);
        $legacyPayload = str_replace(
            ['ModelSchema":7:{', 's:7:"version";N;'],
            ['ModelSchema":6:{', ''],
            $serialized
        );
        expect($legacyPayload)->not->toBe($serialized);

        $legacySchema = unserialize($legacyPayload);
        expect($legacySchema)->toBeInstanceOf(ModelSchema::class);

        $yaml = "model: User\nfields:\n  name:\n    type: string\n";
        Cache::put($cache->generateContentKey($yaml, 'User'), $legacySchema, 60);

        expect($cache->getSchemaByContent($yaml, 'User'))->toBeNull();

        $cache->putSchemaByContent($yaml, $schema, 'User');
        expect($cache->getSchemaByContent($yaml, 'User'))->toBeInstanceOf(ModelSchema::class);
    });
});

describe('SchemaDiffService version support', function () {
    beforeEach(function () {
        $this->diffService = new SchemaDiffService();
        $this->fields = ['name' => new Field('name', 'string')];
    });

    it('does not report a version change when versions are equal', function () {
        $old = new ModelSchema(name: 'User', table: 'users', fields: $this->fields, version: '1.0');
        $new = new ModelSchema(name: 'User', table: 'users', fields: $this->fields, version: '1.0.0');

        $diff = $this->diffService->compareSchemas($old, $new);

        expect($diff['schema_changes'])->not->toHaveKey('version');
    });

    it('does not report a version change for unversioned schemas', function () {
        $old = new ModelSchema('User', 'users', $this->fields);
        $new = new ModelSchema('User', 'users', $this->fields);

        expect($this->diffService->compareSchemas($old, $new)['schema_changes'])->toBeEmpty();
    });

    it('reports version changes', function (?string $oldVersion, ?string $newVersion, string $type) {
        $old = new ModelSchema(name: 'User', table: 'users', fields: $this->fields, version: $oldVersion);
        $new = new ModelSchema(name: 'User', table: 'users', fields: $this->fields, version: $newVersion);

        $diff = $this->diffService->compareSchemas($old, $new);

        expect($diff['schema_changes']['version'])->toBe([
            'old' => $old->version,
            'new' => $new->version,
            'type' => $type,
        ]);
        expect($diff['breaking_changes'])->toBeEmpty();
    })->with([
        'upgraded' => ['1.9.0', '1.10.0', 'upgraded'],
        'downgraded' => ['2.0.0', '1.5.0', 'downgraded'],
        'added' => [null, '1.0.0', 'added'],
        'removed' => ['1.0.0', null, 'removed'],
    ]);
});
