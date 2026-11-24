<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Services\SchemaService;

describe('SchemaService::validateYamlWithLineNumbers', function () {
    beforeEach(function () {
        $this->service = new SchemaService();
    });

    it('returns no error for a valid schema', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  table: posts
  fields:
    title:
      type: string
  relations:
    user:
      type: belongsTo
      model: App\Models\User
YAML;

        expect($this->service->validateYamlWithLineNumbers($yaml))->toBe([]);
    });

    it('reports an unknown field type on the line of its type key', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  table: posts
  fields:
    title:
      type: string
    body:
      nullable: true
      type: unknown_type
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toBe([[
            'message' => "Unknown field type 'unknown_type' in field 'body'",
            'line' => 9,
            'path' => 'core.fields.body.type',
            'formatted' => "Line 9: Unknown field type 'unknown_type' in field 'body'",
        ]]);
    });

    it('keeps the same messages as validateFromCompleteAppYaml', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  fields:
    title:
      type: nope
  relationships:
    author:
      type: belongsToSomething
      model: App\Models\User
YAML;

        $messages = array_column($this->service->validateYamlWithLineNumbers($yaml), 'message');

        expect($messages)->toBe($this->service->validateFromCompleteAppYaml($yaml));
    });

    it('reports an unknown relationship type with the relations alias', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  fields:
    title:
      type: string
  relations:
    author:
      model: App\Models\User
      type: belongsToSomething
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0]['line'])->toBe(9);
        expect($errors[0]['path'])->toBe('core.relations.author.type');
    });

    it('supports the flat format', function () {
        $yaml = <<<'YAML'
model: Post
table: posts
fields:
  title:
    type: string
  views:
    type: counter
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0]['line'])->toBe(7);
        expect($errors[0]['path'])->toBe('fields.views.type');
    });

    it('reports a schema without fields', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  fields: {}
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0]['message'])->toBe("Schema 'Post' must have at least one field");
        expect($errors[0]['line'])->toBe(3);
        expect($errors[0]['path'])->toBe('core.fields');
    });

    it('reports an invalid version on its line', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  version: latest
  fields:
    title:
      type: string
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0]['message'])->toContain("Invalid schema version 'latest'");
        expect($errors[0]['line'])->toBe(3);
        expect($errors[0]['path'])->toBe('core.version');
    });

    it('reports attributes with a wrong type instead of failing', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  fields:
    title:
      type: string
      length: "255"
    published:
      type: boolean
      nullable: "yes"
    summary:
  relations:
    author: App\Models\User
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect(array_column($errors, 'formatted'))->toBe([
            "Line 6: Field 'title': 'length' must be an integer, string given",
            "Line 9: Field 'published': 'nullable' must be a boolean, string given",
            "Line 10: Field 'summary' must be a mapping of field attributes",
            "Line 12: Relationship 'author' must be a mapping of relationship attributes",
        ]);
        expect(array_column($errors, 'path'))->toBe([
            'core.fields.title.length',
            'core.fields.published.nullable',
            'core.fields.summary',
            'core.relations.author',
        ]);
    });

    it('reports security issues on the offending value', function () {
        $yaml = <<<'YAML'
core:
  model: Post
  fields:
    title:
      type: string
      default: "exec('ls')"
YAML;

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->not->toBeEmpty();
        expect($errors[0]['message'])->toStartWith('Security issue:');
        expect($errors[0]['line'])->toBe(6);
        expect($errors[0]['path'])->toBe('core.fields.title.default');
    });

    it('reports YAML syntax errors with their line', function () {
        $yaml = "core:\n  model: Post\n\tfields:\n    title:\n      type: string\n";

        $errors = $this->service->validateYamlWithLineNumbers($yaml);

        expect($errors)->toHaveCount(1);
        expect($errors[0]['message'])->toStartWith('Invalid YAML format:');
        expect($errors[0]['line'])->toBe(3);
        expect($errors[0]['formatted'])->toStartWith('Line 3: Invalid YAML format:');
        expect($errors[0]['path'])->toBeNull();
    });

    it('reports content without schema data', function () {
        $errors = $this->service->validateYamlWithLineNumbers('just a string');

        expect($errors)->toBe([[
            'message' => 'No core schema data found in YAML',
            'line' => null,
            'path' => null,
            'formatted' => 'No core schema data found in YAML',
        ]]);
    });

    it('validates a file', function () {
        $path = sys_get_temp_dir().'/modelschema-lines-'.uniqid().'.yaml';
        file_put_contents($path, "model: Post\nfields:\n  title:\n    type: unknown\n");

        try {
            $errors = $this->service->validateYamlFileWithLineNumbers($path);

            expect($errors)->toHaveCount(1);
            expect($errors[0]['line'])->toBe(4);
        } finally {
            @unlink($path);
        }
    });

    it('throws when the file does not exist', function () {
        $this->service->validateYamlFileWithLineNumbers('/path/that/does/not/exist.yaml');
    })->throws(SchemaException::class, 'Schema file not found');
});
