<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\LaravelModelschemaServiceProvider;

it('can load the service provider', function () {
    $provider = new LaravelModelschemaServiceProvider(app());

    expect($provider)->toBeInstanceOf(LaravelModelschemaServiceProvider::class);
});

it('can access the configuration', function () {
    $config = config('modelschema');

    expect($config)
        ->toBeArray()
        ->toHaveKey('generation')
        ->toHaveKey('validation')
        ->toHaveKey('documentation')
        ->toHaveKey('migrations');
});

it('has valid default configuration values', function () {
    $config = config('modelschema');

    expect($config['generation']['auto_generate'])->toBeTrue();
    expect($config['validation']['strict_mode'])->toBeTrue();
    expect($config['documentation']['auto_generate'])->toBeTrue();
    expect($config['migrations']['auto_generate'])->toBeFalse();
});
