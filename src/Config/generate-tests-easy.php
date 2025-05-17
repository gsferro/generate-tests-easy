<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Path
    |--------------------------------------------------------------------------
    |
    | This value is the path where tests will be generated. By default, this
    | is the "tests" directory in the root of your application.
    |
    */
    'test_path' => 'tests',

    /*
    |--------------------------------------------------------------------------
    | Install Pest
    |--------------------------------------------------------------------------
    |
    | This option determines whether the package should install Pest if it's
    | not already installed in your project.
    |
    */
    'install_pest' => true,

    /*
    |--------------------------------------------------------------------------
    | Configure Arch Presets
    |--------------------------------------------------------------------------
    |
    | This option determines whether the package should configure Pest Arch
    | presets for PHP, security, and Laravel.
    |
    */
    'configure_arch_presets' => true,

    /*
    |--------------------------------------------------------------------------
    | Coverage Target
    |--------------------------------------------------------------------------
    |
    | This value is the target percentage for test coverage. This is used
    | when generating tests to ensure adequate coverage.
    |
    */
    'coverage_target' => 80,

    /*
    |--------------------------------------------------------------------------
    | Custom Stubs Path
    |--------------------------------------------------------------------------
    |
    | This value is the path to custom stubs that should be used instead of
    | the default stubs provided by the package.
    |
    */
    'stubs_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Analyzers
    |--------------------------------------------------------------------------
    |
    | This array contains the analyzers that will be used to analyze your
    | application's code. You can add your own analyzers here.
    |
    */
    'analyzers' => [
        'model' => Gsferro\GenerateTestsEasy\Analyzers\ModelAnalyzer::class,
        'controller' => Gsferro\GenerateTestsEasy\Analyzers\ControllerAnalyzer::class,
        'database' => Gsferro\GenerateTestsEasy\Analyzers\DatabaseAnalyzer::class,
        'livewire' => Gsferro\GenerateTestsEasy\Analyzers\LivewireAnalyzer::class,
        'filament' => Gsferro\GenerateTestsEasy\Analyzers\FilamentAnalyzer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Generators
    |--------------------------------------------------------------------------
    |
    | This array contains the generators that will be used to generate tests
    | for your application. You can add your own generators here.
    |
    */
    'generators' => [
        'model' => Gsferro\GenerateTestsEasy\Generators\ModelTestGenerator::class,
        'controller' => Gsferro\GenerateTestsEasy\Generators\ControllerTestGenerator::class,
        'api' => Gsferro\GenerateTestsEasy\Generators\ApiTestGenerator::class,
        'livewire' => Gsferro\GenerateTestsEasy\Generators\LivewireTestGenerator::class,
        'filament' => Gsferro\GenerateTestsEasy\Generators\FilamentTestGenerator::class,
    ],
];