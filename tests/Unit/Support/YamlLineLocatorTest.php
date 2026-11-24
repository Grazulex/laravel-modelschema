<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Support\YamlLineLocator;

describe('YamlLineLocator', function () {
    it('indexes nested mapping keys with their line', function () {
        $yaml = <<<'YAML'
core:
  model: User
  table: users
  fields:
    name:
      type: string
    email:
      type: email
      unique: true
YAML;

        $locator = YamlLineLocator::fromString($yaml);

        expect($locator->getLine('core'))->toBe(1);
        expect($locator->getLine('core.model'))->toBe(2);
        expect($locator->getLine('core.fields'))->toBe(4);
        expect($locator->getLine('core.fields.name.type'))->toBe(6);
        expect($locator->getLine('core.fields.email'))->toBe(7);
        expect($locator->getLine('core.fields.email.unique'))->toBe(9);
    });

    it('falls back to the closest declared parent', function () {
        $yaml = <<<'YAML'
fields:
  title: { type: string, nullable: true }
YAML;

        $locator = new YamlLineLocator($yaml);

        expect($locator->getLine('fields.title.type'))->toBe(2);
        expect($locator->getLine('fields.title.type', false))->toBeNull();
        expect($locator->has('fields.title'))->toBeTrue();
        expect($locator->has('fields.title.type'))->toBeFalse();
        expect($locator->getLine('unknown.key'))->toBeNull();
        expect($locator->getLine(''))->toBeNull();
    });

    it('skips comments, blank lines and block scalars', function () {
        $yaml = <<<'YAML'
# Schema for users
model: User

fields:
  # the bio
  bio:
    type: text
    comment: |
      first: line
      second: line
  age:
    type: integer
YAML;

        $locator = new YamlLineLocator($yaml);

        expect($locator->getLine('model'))->toBe(2);
        expect($locator->getLine('fields.bio'))->toBe(6);
        expect($locator->getLine('fields.bio.comment'))->toBe(8);
        expect($locator->has('fields.bio.comment.first'))->toBeFalse();
        expect($locator->getLine('fields.age.type'))->toBe(12);
    });

    it('indexes sequence items by position', function () {
        $yaml = <<<'YAML'
fields:
  name:
    rules:
      - required
      - string
  tags:
    values:
    - one
    - two
items:
  - name: first
    value: 1
  - name: second
    value: 2
YAML;

        $locator = new YamlLineLocator($yaml);

        expect($locator->getLine('fields.name.rules.0'))->toBe(4);
        expect($locator->getLine('fields.name.rules.1'))->toBe(5);
        expect($locator->getLine('fields.tags.values.1'))->toBe(9);
        expect($locator->getLine('items.0.name'))->toBe(11);
        expect($locator->getLine('items.0.value'))->toBe(12);
        expect($locator->getLine('items.1.name'))->toBe(13);
        expect($locator->getLine('items.1.value'))->toBe(14);
    });

    it('supports quoted keys and keys given as segments', function () {
        $yaml = <<<'YAML'
"core":
  'fields':
    "first.name":
      type: string
YAML;

        $locator = new YamlLineLocator($yaml);

        expect($locator->getLine(['core', 'fields', 'first.name', 'type']))->toBe(4);
        expect($locator->all())->toBe([
            'core' => 1,
            'core.fields' => 2,
            'core.fields.first.name' => 3,
            'core.fields.first.name.type' => 4,
        ]);
    });

    it('handles windows line endings', function () {
        $locator = new YamlLineLocator("model: User\r\nfields:\r\n  name:\r\n    type: string\r\n");

        expect($locator->getLine('fields.name.type'))->toBe(4);
    });
});
