<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema;

use Grazulex\LaravelModelschema\Console\Commands\MakeSchemaCommand;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Illuminate\Support\ServiceProvider;

final class LaravelModelschemaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/Config/modelschema.php' => config_path('modelschema.php'),
        ], 'modelschema-config');

        // Initialize field type registry
        FieldTypeRegistry::initialize();

        // Discover custom field types from app
        $this->discoverCustomFieldTypes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeSchemaCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/modelschema.php', 'modelschema');
    }

    /**
     * Discover custom field types from the application
     */
    private function discoverCustomFieldTypes(): void
    {
        $customFieldTypesPath = config('modelschema.custom_field_types_path');
        $customFieldTypesNamespace = config('modelschema.custom_field_types_namespace');

        if ($customFieldTypesPath && $customFieldTypesNamespace && is_dir($customFieldTypesPath)) {
            FieldTypeRegistry::discoverFieldTypes($customFieldTypesPath, $customFieldTypesNamespace);
        }
    }
}
