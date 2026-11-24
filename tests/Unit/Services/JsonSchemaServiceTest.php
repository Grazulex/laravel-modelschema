<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\JsonSchemaService;
use Grazulex\LaravelModelschema\Services\SchemaService;

describe('JsonSchemaService', function () {
    it('generates a draft-07 schema covering the core and flat formats', function () {
        $schema = (new JsonSchemaService())->generate();

        expect($schema['$schema'])->toBe('http://json-schema.org/draft-07/schema#');
        expect($schema['title'])->toBe('Laravel ModelSchema');
        expect($schema)->not->toHaveKey('$id');
        expect($schema['type'])->toBe('object');
        expect($schema['if'])->toBe(['required' => ['core']]);
        expect($schema['then']['properties']['core'])->toBe(['$ref' => '#/definitions/core']);
        expect($schema['else'])->toBe(['$ref' => '#/definitions/core']);
        expect($schema['definitions'])->toHaveKeys(['core', 'strictCore', 'field', 'relationship', 'version', 'stringList']);
    });

    it('describes the core keys', function () {
        $core = (new JsonSchemaService())->generate()['definitions']['core'];

        expect($core['required'])->toBe(['fields']);
        expect(array_keys($core['properties']))->toEqualCanonicalizing(JsonSchemaService::CORE_KEYS);
        expect($core['properties']['fields']['minProperties'])->toBe(1);
        expect($core['properties']['fields']['additionalProperties'])->toBe(['$ref' => '#/definitions/field']);
        expect($core['properties']['relations']['additionalProperties'])->toBe(['$ref' => '#/definitions/relationship']);
        expect($core['properties']['version'])->toBe(['$ref' => '#/definitions/version']);
        expect($core['additionalProperties'])->toBeTrue();
    });

    it('lists the registered field types and aliases', function () {
        $service = new JsonSchemaService();
        $typeDefinition = $service->generate()['definitions']['field']['properties']['type'];

        expect($typeDefinition['type'])->toBe('string');
        expect($typeDefinition['enum'])->toContain('string', 'decimal', 'enum', 'foreignId', 'point', 'varchar', 'bool');
        expect($typeDefinition['enum'])->toBe($service->getFieldTypes());
        expect($typeDefinition['enum'])->each->toBeString();
    });

    it('accepts extra field types', function () {
        $typeDefinition = (new JsonSchemaService())
            ->generate(['field_types' => ['url', 'string', '']])['definitions']['field']['properties']['type'];

        expect($typeDefinition['enum'])->toContain('url');
        expect(array_count_values($typeDefinition['enum'])['string'])->toBe(1);
        expect($typeDefinition['enum'])->not->toContain('');
    });

    it('can suggest field types without rejecting unknown ones', function () {
        $typeDefinition = (new JsonSchemaService())
            ->generate(['allow_unknown_field_types' => true])['definitions']['field']['properties']['type'];

        expect($typeDefinition)->not->toHaveKey('enum');
        expect($typeDefinition['anyOf'][0]['enum'])->toContain('string');
        expect($typeDefinition['anyOf'][1])->toBe(['type' => 'string']);
    });

    it('lists the relationship types', function () {
        $relationship = (new JsonSchemaService())->generate()['definitions']['relationship'];

        expect($relationship['properties']['type']['enum'])->toBe(array_keys(JsonSchemaService::RELATIONSHIP_TYPES));
        expect($relationship['properties']['type']['enum'])
            ->toBe(array_keys((new SchemaService())->getSupportedRelationshipTypes()));
    });

    it('matches the version format accepted by ModelSchema', function () {
        $pattern = (new JsonSchemaService())->generate()['definitions']['version']['oneOf'][0]['pattern'];
        $regex = '/'.$pattern.'/';

        foreach (['1', '1.2', '1.2.3', ' 1.4.0 ', '0.0.1', '10.20.30'] as $valid) {
            expect(preg_match($regex, $valid))->toBe(1, "'{$valid}' should match");
        }

        foreach (['', 'v1', '1.2.3.4', '01.2', '1.x', 'latest'] as $invalid) {
            expect(preg_match($regex, $invalid))->toBe(0, "'{$invalid}' should not match");
        }
    });

    it('supports a custom id, title and strict core section', function () {
        $schema = (new JsonSchemaService())->generate([
            'id' => 'https://example.com/modelschema.json',
            'title' => 'My schemas',
            'strict' => true,
        ]);

        expect($schema['$id'])->toBe('https://example.com/modelschema.json');
        expect($schema['title'])->toBe('My schemas');
        expect($schema['then']['properties']['core'])->toBe(['$ref' => '#/definitions/strictCore']);
        expect($schema['definitions']['strictCore']['additionalProperties'])->toBeFalse();
        // The flat format keeps extension keys at the root, so it is never strict
        expect($schema['else'])->toBe(['$ref' => '#/definitions/core']);
    });

    it('encodes to valid JSON with objects where JSON Schema expects them', function () {
        $json = (new JsonSchemaService())->toJson();
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        expect($decoded->{'$schema'})->toBe('http://json-schema.org/draft-07/schema#');
        expect($decoded->definitions->field->properties->default)->toBeObject();
        expect($decoded->definitions->core->properties->metadata)->toBeObject();
        expect($json)->toContain('"$ref": "#/definitions/field"');
    });

    it('writes the schema to a file', function () {
        $directory = sys_get_temp_dir().'/modelschema-json-schema-'.uniqid();
        $path = $directory.'/nested/modelschema.schema.json';

        try {
            $result = (new JsonSchemaService())->exportToFile($path, ['title' => 'Exported']);

            expect($result)->toBe($path);
            expect(file_exists($path))->toBeTrue();

            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            expect($decoded['title'])->toBe('Exported');
        } finally {
            @unlink($path);
            @rmdir($directory.'/nested');
            @rmdir($directory);
        }
    });

    it('is available from the SchemaService', function () {
        $service = new SchemaService();

        expect($service->exportJsonSchema())->toBe((new JsonSchemaService())->generate());
        expect($service->exportJsonSchema(['strict' => true])['then']['properties']['core']['$ref'])
            ->toBe('#/definitions/strictCore');

        $path = sys_get_temp_dir().'/modelschema-'.uniqid().'.json';

        try {
            expect($service->exportJsonSchemaToFile($path))->toBe($path);
            expect(json_decode((string) file_get_contents($path), true)['title'])->toBe('Laravel ModelSchema');
        } finally {
            @unlink($path);
        }
    });
});
