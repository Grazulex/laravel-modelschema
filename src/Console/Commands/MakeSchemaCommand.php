<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

class MakeSchemaCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:schema {name : The name of the model} 
                           {--template=basic : The template to use (basic, blog, ecommerce, user)}
                           {--path= : Custom path for the schema file}
                           {--force : Overwrite existing schema file}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new model schema YAML file from a stub template';

    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * Available templates with their descriptions
     */
    protected array $templates = [
        'basic' => 'Basic model with id, timestamps',
        'blog' => 'Blog post model with title, content, author relationship',
        'ecommerce' => 'Product model with pricing, categories, inventory',
        'user' => 'User model with authentication fields and profile relationship',
        'pivot' => 'Pivot table model for many-to-many relationships',
    ];

    /**
     * Create a new command instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $template = $this->option('template');
        $customPath = $this->option('path');
        $force = $this->option('force');

        // Validate template
        if (! array_key_exists($template, $this->templates)) {
            $this->error("Template '{$template}' does not exist.");
            $this->info('Available templates:');
            foreach ($this->templates as $key => $description) {
                $this->line("  {$key}: {$description}");
            }

            return self::FAILURE;
        }

        // Generate file path
        $path = $this->getSchemaPath($name, $customPath);

        // Check if file exists
        if ($this->files->exists($path) && ! $force) {
            $this->error("Schema file already exists: {$path}");
            $this->info('Use --force to overwrite the existing file.');

            return self::FAILURE;
        }

        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        // Generate schema content
        $content = $this->generateSchemaContent($name, $template);

        // Write file
        $this->files->put($path, $content);

        $this->info("Schema file created successfully: {$path}");
        $this->newLine();
        $this->line('Next steps:');
        $this->line('1. Edit the schema file to customize fields and relationships');
        $this->line("2. Use ModelSchemaManager::fromYamlFile('{$path}') to load the schema");

        return self::SUCCESS;
    }

    /**
     * Get the schema file path
     */
    protected function getSchemaPath(string $name, ?string $customPath = null): string
    {
        if ($customPath !== null && $customPath !== '' && $customPath !== '0') {
            return $customPath;
        }

        // Use Laravel's resource path or fallback
        $basePath = function_exists('resource_path')
            ? resource_path('schemas')
            : 'resources/schemas';

        $filename = Str::snake($name).'.schema.yml';

        return $basePath.'/'.$filename;
    }

    /**
     * Generate schema content based on template
     */
    protected function generateSchemaContent(string $name, string $template): string
    {
        $stubPath = $this->getStubPath($template);

        if (! $this->files->exists($stubPath)) {
            throw new InvalidArgumentException("Stub file not found for template '{$template}': {$stubPath}");
        }

        $stub = $this->files->get($stubPath);

        return $this->replaceStubVariables($stub, $name);
    }

    /**
     * Get stub file path
     */
    protected function getStubPath(string $template): string
    {
        return __DIR__.'/stubs/'.$template.'.schema.stub';
    }

    /**
     * Replace variables in stub content
     */
    protected function replaceStubVariables(string $stub, string $name): string
    {
        $replacements = [
            '{{MODEL_NAME}}' => $name,
            '{{TABLE_NAME}}' => Str::snake(Str::pluralStudly($name)),
            '{{MODEL_CLASS}}' => "App\\Models\\{$name}",
            '{{SNAKE_NAME}}' => Str::snake($name),
            '{{KEBAB_NAME}}' => Str::kebab($name),
            '{{CREATED_AT}}' => now()->format('Y-m-d H:i:s'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get current timestamp
     */
    protected function getCurrentTimestamp(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
