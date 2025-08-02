<?php

declare(strict_types=1);

it('can create basic configuration', function () {
    $config = include __DIR__.'/../../src/Config/modelschema.php';

    expect($config)
        ->toBeArray();
});

it('config path is accessible', function () {
    expect(file_exists(__DIR__.'/../../src/Config/modelschema.php'))
        ->toBeTrue();
});

it('config has expected structure', function () {
    $config = include __DIR__.'/../../src/Config/modelschema.php';

    expect($config)
        ->toBeArray()
        ->toHaveKey('generation')
        ->toHaveKey('validation')
        ->toHaveKey('documentation')
        ->toHaveKey('migrations');
});
