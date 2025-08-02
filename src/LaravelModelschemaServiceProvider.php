<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                // Future commands will be registered here
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
}
