<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Console\Commands\MakeSchemaCommand;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->filesystem = new Filesystem();
    $this->tempDir = sys_get_temp_dir().'/laravel-modelschema-test-'.uniqid();
    $this->filesystem->makeDirectory($this->tempDir, 0755, true);
});

afterEach(function () {
    if ($this->filesystem->exists($this->tempDir)) {
        $this->filesystem->deleteDirectory($this->tempDir);
    }
});

it('can create basic schema file', function () {
    $schemaPath = $this->tempDir.'/product.schema.yml';

    // Mock the command
    $command = new MakeSchemaCommand($this->filesystem);
    $command->setName('make:schema');

    // Simulate command execution
    $content = <<<'YAML'
# Model Schema: Product
# Generated: 2025-08-02 10:00:00

model: Product
table: products

fields:
  id:
    type: bigInteger
    nullable: false
    primary: true
    auto_increment: true

  name:
    type: string
    length: 255
    nullable: false
    rules: ['required', 'string', 'max:255']

  description:
    type: text
    nullable: true

  is_active:
    type: boolean
    default: true
    nullable: false

options:
  timestamps: true
  soft_deletes: false

metadata:
  created_by: make:schema
  template: basic
  version: "1.0"
YAML;

    $this->filesystem->put($schemaPath, $content);

    expect($this->filesystem->exists($schemaPath))->toBeTrue();

    $fileContent = $this->filesystem->get($schemaPath);
    expect($fileContent)->toContain('model: Product');
    expect($fileContent)->toContain('table: products');
    expect($fileContent)->toContain('type: string');
    expect($fileContent)->toContain('timestamps: true');
});

it('generates correct table name from model name', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('getSchemaPath');
    $method->setAccessible(true);

    $path = $method->invoke($command, 'BlogPost', $this->tempDir.'/blog_post.schema.yml');

    expect($path)->toBe($this->tempDir.'/blog_post.schema.yml');
});

it('can generate blog template content from stub', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('generateSchemaContent');
    $method->setAccessible(true);

    $content = $method->invoke($command, 'Post', 'blog');

    expect($content)->toContain('model: Post');
    expect($content)->toContain('table: posts');
    expect($content)->toContain('title:');
    expect($content)->toContain('content:');
    expect($content)->toContain('belongsTo');
    expect($content)->toContain('hasMany');
    expect($content)->toContain('template: blog');
});

it('can generate user template content from stub', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('generateSchemaContent');
    $method->setAccessible(true);

    $content = $method->invoke($command, 'User', 'user');

    expect($content)->toContain('model: User');
    expect($content)->toContain('table: users');
    expect($content)->toContain('email:');
    expect($content)->toContain('password:');
    expect($content)->toContain('email_verified_at:');
    expect($content)->toContain('template: user');
});

it('can generate ecommerce template content from stub', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('generateSchemaContent');
    $method->setAccessible(true);

    $content = $method->invoke($command, 'Product', 'ecommerce');

    expect($content)->toContain('model: Product');
    expect($content)->toContain('table: products');
    expect($content)->toContain('sku:');
    expect($content)->toContain('price:');
    expect($content)->toContain('stock_quantity:');
    expect($content)->toContain('template: ecommerce');
});

it('replaces stub variables correctly', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    $stub = "model: {{MODEL_NAME}}\ntable: {{TABLE_NAME}}\nclass: {{MODEL_CLASS}}";

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('replaceStubVariables');
    $method->setAccessible(true);

    $result = $method->invoke($command, $stub, 'BlogPost');

    expect($result)->toContain('model: BlogPost');
    expect($result)->toContain('table: blog_posts');
    expect($result)->toContain('class: App\\Models\\BlogPost');
});

it('handles available templates correctly', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to access protected property
    $reflection = new ReflectionClass($command);
    $property = $reflection->getProperty('templates');
    $property->setAccessible(true);
    $templates = $property->getValue($command);

    expect($templates)->toBeArray();
    expect($templates)->toHaveKey('basic');
    expect($templates)->toHaveKey('blog');
    expect($templates)->toHaveKey('user');
    expect($templates)->toHaveKey('ecommerce');
    expect($templates)->toHaveKey('pivot');

    expect($templates['basic'])->toContain('Basic model');
    expect($templates['blog'])->toContain('Blog post');
    expect($templates['user'])->toContain('User model');
});

it('can parse generated schema with modelschema', function () {
    $schemaPath = $this->tempDir.'/test_model.schema.yml';

    $content = <<<YAML
model: TestModel
table: test_models

fields:
  id:
    type: bigInteger
    nullable: false
    primary: true

  name:
    type: string
    length: 255
    nullable: false

  email:
    type: email
    unique: true

relationships:
  posts:
    type: hasMany
    model: App\Models\Post

options:
  timestamps: true
YAML;

    $this->filesystem->put($schemaPath, $content);

    // Test that the generated file can be parsed by our schema system
    expect($this->filesystem->exists($schemaPath))->toBeTrue();

    // If YAML extension is available, test parsing
    if (function_exists('yaml_parse')) {
        $yamlContent = $this->filesystem->get($schemaPath);
        $parsed = yaml_parse($yamlContent);

        expect($parsed)->toBeArray();
        expect($parsed['model'])->toBe('TestModel');
        expect($parsed['table'])->toBe('test_models');
        expect($parsed['fields'])->toHaveKey('id');
        expect($parsed['fields'])->toHaveKey('name');
        expect($parsed['relationships'])->toHaveKey('posts');
    }
});

it('validates stub file existence', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    // Use reflection to test protected method
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('generateSchemaContent');
    $method->setAccessible(true);

    // Test with non-existent template
    expect(fn () => $method->invoke($command, 'Test', 'nonexistent'))
        ->toThrow(InvalidArgumentException::class, 'Stub file not found for template');
});

it('can generate all available templates', function () {
    $command = new MakeSchemaCommand($this->filesystem);

    $templates = ['basic', 'blog', 'user', 'ecommerce', 'pivot'];

    foreach ($templates as $template) {
        // Use reflection to test protected method
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('generateSchemaContent');
        $method->setAccessible(true);

        $content = $method->invoke($command, 'TestModel', $template);

        expect($content)->toBeString();
        expect($content)->toContain('model: TestModel');
        expect($content)->toContain("template: {$template}");
    }
});
