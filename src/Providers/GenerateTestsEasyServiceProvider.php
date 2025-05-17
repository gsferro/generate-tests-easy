<?php

namespace Gsferro\GenerateTestsEasy\Providers;

use Gsferro\GenerateTestsEasy\Commands\GenerateAllTestsCommand;
use Gsferro\GenerateTestsEasy\Commands\GenerateControllerTestsCommand;
use Gsferro\GenerateTestsEasy\Commands\GenerateDatabaseTestsCommand;
use Gsferro\GenerateTestsEasy\Commands\GenerateModelTestsCommand;
use Illuminate\Support\ServiceProvider;

class GenerateTestsEasyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../Config/generate-tests-easy.php' => config_path('generate-tests-easy.php'),
        ], 'generate-tests-easy-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateAllTestsCommand::class,
                GenerateModelTestsCommand::class,
                GenerateControllerTestsCommand::class,
                GenerateDatabaseTestsCommand::class,
            ]);
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/generate-tests-easy.php', 'generate-tests-easy'
        );

        // Register analyzers
        $this->registerAnalyzers();

        // Register generators
        $this->registerGenerators();
    }

    /**
     * Register the analyzers.
     *
     * @return void
     */
    protected function registerAnalyzers()
    {
        $analyzers = config('generate-tests-easy.analyzers', []);

        foreach ($analyzers as $name => $class) {
            $this->app->singleton("generate-tests-easy.analyzer.{$name}", function ($app) use ($class) {
                return new $class();
            });
        }
    }

    /**
     * Register the generators.
     *
     * @return void
     */
    protected function registerGenerators()
    {
        $generators = config('generate-tests-easy.generators', []);

        foreach ($generators as $name => $class) {
            $this->app->singleton("generate-tests-easy.generator.{$name}", function ($app) use ($class) {
                return new $class();
            });
        }
    }
}