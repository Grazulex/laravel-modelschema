<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\LaravelModelschemaServiceProvider;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;

it('registers services correctly', function () {
    $app = app();
    $provider = new LaravelModelschemaServiceProvider($app);

    // Test that the provider registers correctly
    expect($provider)->toBeInstanceOf(LaravelModelschemaServiceProvider::class);
});

it('field type registry is properly initialized', function () {
    $registry = app(FieldTypeRegistry::class);

    // Check that common field types are registered
    expect($registry->has('string'))->toBeTrue();
    expect($registry->has('integer'))->toBeTrue();
    expect($registry->has('boolean'))->toBeTrue();
    expect($registry->has('text'))->toBeTrue();
    expect($registry->has('json'))->toBeTrue();
});

it('can resolve field types from registry', function () {
    $registry = app(FieldTypeRegistry::class);

    $stringType = $registry->get('string');
    $integerType = $registry->get('integer');

    expect($stringType)->not->toBeNull();
    expect($integerType)->not->toBeNull();
});
