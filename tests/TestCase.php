<?php

namespace Tests;

use Gsferro\GenerateTestsEasy\Providers\GenerateTestsEasyServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup code here
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            GenerateTestsEasyServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        
        // Set up the generate-tests-easy configuration
        $app['config']->set('generate-tests-easy.test_path', 'tests');
        $app['config']->set('generate-tests-easy.install_pest', false);
        $app['config']->set('generate-tests-easy.configure_arch_presets', false);
    }
}