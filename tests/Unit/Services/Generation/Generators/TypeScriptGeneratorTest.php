<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Examples\UrlFieldTypePlugin;
use Grazulex\LaravelModelschema\FieldTypes\AbstractFieldType;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Services\Generation\GenerationService;
use Grazulex\LaravelModelschema\Services\Generation\Generators\TypeScriptGenerator;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Symfony\Component\Yaml\Yaml;

final class TypeScriptAwareMoneyFieldType extends AbstractFieldType
{
    public function getType(): string
    {
        return 'ts_money';
    }

    public function getTypeScriptType(array $config): string
    {
        return '{ amount: number; currency: string }';
    }

    protected function getMigrationMethod(): string
    {
        return 'json';
    }
}

describe('TypeScriptGenerator', function () {
    beforeEach(function () {
        FieldTypeRegistry::clear();
        $this->generator = new TypeScriptGenerator();

        $this->schema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'title' => ['type' => 'string', 'comment' => 'Public title'],
                'body' => ['type' => 'text', 'nullable' => true],
                'views' => ['type' => 'integer'],
                'price' => ['type' => 'decimal', 'precision' => 8, 'scale' => 2],
                'rating' => ['type' => 'float'],
                'published' => ['type' => 'boolean'],
                'published_at' => ['type' => 'datetime', 'nullable' => true],
                'meta' => ['type' => 'json', 'nullable' => true],
                'status' => ['type' => 'enum', 'attributes' => ['values' => ['draft', 'published']]],
                'channels' => ['type' => 'set', 'attributes' => ['values' => ['web', 'mail']]],
            ],
            'relationships' => [
                'author' => ['type' => 'belongsTo', 'model' => 'App\Models\User'],
                'comments' => ['type' => 'hasMany', 'model' => 'App\Models\Comment'],
                'featuredImage' => ['type' => 'hasOne', 'model' => 'Image'],
                'tags' => ['type' => 'belongsToMany', 'model' => 'App\Models\Tag'],
            ],
        ]);
    });

    afterEach(function () {
        FieldTypeRegistry::clear();
    });

    $propertiesOf = function (array $data): array {
        return array_column($data['properties'], 'type', 'name');
    };

    test('exposes its name and formats', function () {
        expect($this->generator->getGeneratorName())->toBe('typescript');
        expect($this->generator->getAvailableFormats())->toBe(['json', 'yaml', 'ts']);
    });

    test('returns metadata, json, yaml and ts outputs', function () {
        $result = $this->generator->generate($this->schema);

        expect($result)->toHaveKeys(['metadata', 'json', 'yaml', 'ts']);
        expect($result['metadata']['generator'])->toBe('typescript');

        $json = json_decode($result['json'], true);
        expect($json)->toHaveKey('typescript');
        expect($json['typescript']['name'])->toBe('Post');
        expect($json['typescript']['style'])->toBe('interface');
        expect($json['typescript']['file_name'])->toBe('Post.ts');
        expect($json['typescript']['model_class'])->toBe('App\Models\Post');
        expect($json['typescript']['code'])->toBe($result['ts']);

        $yaml = Yaml::parse($result['yaml']);
        expect($yaml['typescript']['properties'])->toBe($json['typescript']['properties']);
    });

    test('maps the built-in field types', function () use ($propertiesOf) {
        $types = $propertiesOf($this->generator->buildTypeData($this->schema));

        expect($types['title'])->toBe('string');
        expect($types['body'])->toBe('string | null');
        expect($types['views'])->toBe('number');
        expect($types['price'])->toBe('string');
        expect($types['rating'])->toBe('number');
        expect($types['published'])->toBe('boolean');
        expect($types['published_at'])->toBe('string | null');
        expect($types['meta'])->toBe('Record<string, unknown> | unknown[] | null');
    });

    test('turns enum and set values into literal unions', function () use ($propertiesOf) {
        $types = $propertiesOf($this->generator->buildTypeData($this->schema));

        expect($types['status'])->toBe("'draft' | 'published'");
        expect($types['channels'])->toBe("('web' | 'mail')[]");
    });

    test('falls back to string types for enum and set without values', function () use ($propertiesOf) {
        $schema = new ModelSchema('Post', 'posts', [
            new Field('status', 'enum'),
            new Field('channels', 'set'),
            new Field('level', 'enum', attributes: ['values' => [1, 2, "it's"]]),
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema));

        expect($types['status'])->toBe('string');
        expect($types['channels'])->toBe('string[]');
        expect($types['level'])->toBe("1 | 2 | 'it\\'s'");
    });

    test('resolves field type aliases through the registry', function () use ($propertiesOf) {
        $schema = new ModelSchema('Item', 'items', [
            new Field('name', 'varchar'),
            new Field('count', 'bigint'),
            new Field('active', 'bool'),
            new Field('payload', 'jsonb'),
            new Field('owner_id', 'fk'),
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema));

        expect($types['name'])->toBe('string');
        expect($types['count'])->toBe('number');
        expect($types['active'])->toBe('boolean');
        expect($types['payload'])->toBe('Record<string, unknown> | unknown[]');
        expect($types['owner_id'])->toBe('number');
    });

    test('adds the implied id, timestamps and foreign keys', function () use ($propertiesOf) {
        $data = $this->generator->buildTypeData($this->schema);
        $types = $propertiesOf($data);

        expect(array_key_first($types))->toBe('id');
        expect($types['id'])->toBe('number');
        expect($types['author_id'])->toBe('number | null');
        expect($types['created_at'])->toBe('string | null');
        expect($types['updated_at'])->toBe('string | null');
        expect($types)->not->toHaveKey('deleted_at');
    });

    test('adds deleted_at for soft deletes and keeps declared columns', function () use ($propertiesOf) {
        $schema = ModelSchema::fromArray('Post', [
            'fields' => [
                'id' => ['type' => 'uuid'],
                'created_at' => ['type' => 'timestamp'],
            ],
            'options' => ['soft_deletes' => true],
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema));

        expect($types['id'])->toBe('string');
        expect($types['created_at'])->toBe('string');
        expect($types['updated_at'])->toBe('string | null');
        expect($types['deleted_at'])->toBe('string | null');
    });

    test('can skip the implied columns', function () use ($propertiesOf) {
        $schema = ModelSchema::fromArray('Post', [
            'fields' => ['title' => ['type' => 'string']],
            'options' => ['soft_deletes' => true],
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema, ['implicit_columns' => false]));

        expect(array_keys($types))->toBe(['title']);
    });

    test('expands morphs fields into their two columns', function () use ($propertiesOf) {
        $schema = new ModelSchema('Comment', 'comments', [
            new Field('commentable', 'morphs'),
            new Field('owner', 'polymorphic', nullable: true),
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema));

        expect($types)->not->toHaveKey('commentable');
        expect($types['commentable_id'])->toBe('number');
        expect($types['commentable_type'])->toBe('string');
        expect($types['owner_id'])->toBe('number | null');
        expect($types['owner_type'])->toBe('string | null');
    });

    test('describes relationships as optional snake_case properties', function () {
        $data = $this->generator->buildTypeData($this->schema);
        $properties = array_column($data['properties'], null, 'name');

        expect($properties['author']['type'])->toBe('User | null');
        expect($properties['author']['optional'])->toBeTrue();
        expect($properties['author']['source'])->toBe('relation');
        expect($properties['comments']['type'])->toBe('Comment[]');
        expect($properties['featured_image']['type'])->toBe('Image | null');
        expect($properties['tags']['type'])->toBe('Tag[]');
        expect($properties['title']['optional'])->toBeFalse();
        expect($properties['title']['source'])->toBe('field');
    });

    test('imports the related types', function () {
        $data = $this->generator->buildTypeData($this->schema);

        expect($data['imports'])->toBe([
            ['name' => 'Comment', 'path' => './Comment'],
            ['name' => 'Image', 'path' => './Image'],
            ['name' => 'Tag', 'path' => './Tag'],
            ['name' => 'User', 'path' => './User'],
        ]);
        expect($data['code'])->toContain("import type { User } from './User';");
    });

    test('supports a custom import path and disabling imports', function () {
        $data = $this->generator->buildTypeData($this->schema, ['import_path' => '@/types/']);
        expect($data['code'])->toContain("import type { Tag } from '@/types/Tag';");

        $data = $this->generator->buildTypeData($this->schema, ['imports' => false]);
        expect($data['imports'])->toBe([]);
        expect($data['code'])->not->toContain('import type');
        expect($data['code'])->toContain('comments?: Comment[];');
    });

    test('does not import the model itself and leaves morphTo untyped', function () {
        $schema = new ModelSchema('Category', 'categories', [new Field('name', 'string')], [
            new Relationship('parent', 'belongsTo', 'App\Models\Category'),
            new Relationship('children', 'hasMany', 'Category'),
            new Relationship('owner', 'morphTo', ''),
        ]);

        $data = $this->generator->buildTypeData($schema, ['type_name' => 'CategoryData']);
        $properties = array_column($data['properties'], 'type', 'name');

        expect($data['imports'])->toBe([]);
        expect($data['name'])->toBe('CategoryData');
        expect($data['file_name'])->toBe('CategoryData.ts');
        expect($properties['parent'])->toBe('CategoryData | null');
        expect($properties['children'])->toBe('CategoryData[]');
        expect($properties['owner'])->toBe('unknown');
    });

    test('can leave relationships out', function () {
        $data = $this->generator->buildTypeData($this->schema, ['relations' => false]);
        $names = array_column($data['properties'], 'name');

        expect($names)->not->toContain('comments');
        expect($names)->toContain('author_id');
        expect($data['imports'])->toBe([]);
    });

    test('excludes properties and their imports', function () {
        $data = $this->generator->buildTypeData($this->schema, ['exclude' => ['body', 'tags']]);
        $names = array_column($data['properties'], 'name');

        expect($names)->not->toContain('body');
        expect($names)->not->toContain('tags');
        expect(array_column($data['imports'], 'name'))->not->toContain('Tag');
    });

    test('renders an interface by default', function () {
        $schema = new ModelSchema('User', 'users', [
            new Field('name', 'string', comment: 'Display name'),
            new Field('email', 'email', nullable: true),
        ], [
            new Relationship('posts', 'hasMany', 'App\Models\Post'),
        ], ['timestamps' => false]);

        $result = $this->generator->generate($schema);

        expect($result['ts'])->toBe(<<<'TS'
            import type { Post } from './Post';

            export interface User {
                id: number;
                /** Display name */
                name: string;
                email: string | null;
                /** hasMany relationship */
                posts?: Post[];
            }

            TS);
    });

    test('renders a type alias with the type style', function () {
        $schema = new ModelSchema('User', 'users', [new Field('name', 'string')], [], ['timestamps' => false]);

        $result = $this->generator->generate($schema, ['style' => 'type']);

        expect($result['ts'])->toBe(<<<'TS'
            export type User = {
                id: number;
                name: string;
            };

            TS);
        expect(json_decode($result['json'], true)['typescript']['style'])->toBe('type');
    });

    test('rejects an unknown style', function () {
        $this->generator->generate($this->schema, ['style' => 'class']);
    })->throws(InvalidArgumentException::class, 'Unsupported TypeScript style');

    test('quotes property names that are not identifiers', function () {
        $schema = new ModelSchema('Legacy', 'legacy', [new Field('first-name', 'string')]);

        expect($this->generator->generate($schema)['ts'])->toContain("'first-name': string;");
    });

    test('lets the type_map option override the mapping', function () use ($propertiesOf) {
        $schema = new ModelSchema('Product', 'products', [
            new Field('price', 'money'),
            new Field('released_on', 'date'),
            new Field('mystery', 'not_registered'),
        ]);

        $types = $propertiesOf($this->generator->buildTypeData($schema, [
            'type_map' => ['decimal' => 'number', 'date' => 'Date', 'not_registered' => 'Mystery'],
        ]));

        expect($types['price'])->toBe('number');
        expect($types['released_on'])->toBe('Date');
        expect($types['mystery'])->toBe('Mystery');
    });

    test('uses unknown for field types that are not registered', function () {
        $field = new Field('mystery', 'not_registered');

        expect($this->generator->resolveFieldType($field))->toBe('unknown');
    });

    test('maps custom field types through their cast type', function () {
        FieldTypeRegistry::initialize();
        FieldTypeRegistry::register('url', UrlFieldTypePlugin::class);

        expect($this->generator->resolveFieldType(new Field('website', 'url')))->toBe('string');
        expect($this->generator->resolveFieldType(new Field('area', 'polygon')))->toBe('string');
    });

    test('lets custom field types declare their own TypeScript type', function () {
        FieldTypeRegistry::initialize();
        FieldTypeRegistry::register('ts_money', TypeScriptAwareMoneyFieldType::class);

        expect($this->generator->resolveFieldType(new Field('price', 'ts_money')))
            ->toBe('{ amount: number; currency: string }');
    });

    test('is available through the GenerationService', function () {
        $service = new GenerationService();

        $result = $service->generateTypeScript($this->schema, ['style' => 'type']);
        expect($result['ts'])->toContain('export type Post = {');

        expect($service->generate($this->schema, 'typescript')['ts'])->toContain('export interface Post {');
        expect($service->getGenerator('typescript'))->toBeInstanceOf(TypeScriptGenerator::class);
    });

    test('is opt-in in generateAll', function () {
        $service = new GenerationService();
        $schema = new ModelSchema('Post', 'posts', ['title' => new Field('title', 'string')]);

        $results = $service->generateAll($schema, ['model' => true, 'migration' => true]);
        expect($results)->toHaveKeys(['model', 'migration']);
        expect($results)->not->toHaveKey('typescript');

        $results = $service->generateAll($schema, ['model' => true, 'migration' => false, 'typescript' => true]);
        expect($results)->toHaveKeys(['model', 'typescript']);
        expect($results['typescript']['ts'])->toContain('export interface Post {');

        $status = $service->generateAllWithStatus($schema, ['model' => false, 'migration' => false, 'typescript' => true]);
        expect($status['typescript']['success'])->toBeTrue();

        $debug = $service->generateAllWithDebug($schema, ['model' => false, 'migration' => false, 'typescript' => true]);
        expect($debug)->toHaveKey('typescript');
    });

    test('is combined by generateMultiple', function () {
        $service = new GenerationService();
        $schema = new ModelSchema('Post', 'posts', ['title' => new Field('title', 'string')]);

        $result = $service->generateMultiple($schema, ['model', 'typescript'], [
            'typescript' => ['style' => 'type'],
        ]);

        $combined = json_decode($result['json'], true);
        expect($combined['typescript']['typescript']['style'])->toBe('type');
        expect($result['individual_results']['typescript']['ts'])->toContain('export type Post = {');
    });
});
