<?php

use Illuminate\Support\Facades\Artisan;

test('php preset', function () {
    Artisan::call('about');
    expect(arch()->preset()->php())->toBeOk();
});

test('security preset', function () {
    Artisan::call('about');
    expect(arch()->preset()->security())->toBeOk();
});

test('laravel preset', function () {
    Artisan::call('about');
    expect(arch()->preset()->laravel())->toBeOk();
});

// Additional architecture tests specific to the package
test('analyzers follow naming convention', function () {
    expect(arch()->expect('Gsferro\\GenerateTestsEasy\\Analyzers\\*Analyzer'))
        ->toHaveNameEndingWith('Analyzer');
});

test('generators follow naming convention', function () {
    expect(arch()->expect('Gsferro\\GenerateTestsEasy\\Generators\\*Generator'))
        ->toHaveNameEndingWith('Generator');
});

test('commands follow naming convention', function () {
    expect(arch()->expect('Gsferro\\GenerateTestsEasy\\Commands\\*Command'))
        ->toHaveNameEndingWith('Command');
});