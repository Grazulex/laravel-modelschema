<?php

declare(strict_types=1);

it('has correct package structure', function () {
    expect(file_exists(__DIR__.'/../../composer.json'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../src/LaravelModelschemaServiceProvider.php'))->toBeTrue();
    expect(file_exists(__DIR__.'/../../src/Config/modelschema.php'))->toBeTrue();
});

it('composer.json has correct package name', function () {
    $composerJson = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composerJson['name'])->toBe('grazulex/laravel-modelschema');
    expect($composerJson['description'])->toContain('model schema');
});

it('has correct namespace in autoload', function () {
    $composerJson = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);

    expect($composerJson['autoload']['psr-4'])->toHaveKey('Grazulex\\LaravelModelschema\\');
    expect($composerJson['autoload']['psr-4']['Grazulex\\LaravelModelschema\\'])->toBe('src/');
});
