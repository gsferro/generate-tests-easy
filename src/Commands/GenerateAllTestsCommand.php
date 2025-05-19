<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateAllTestsCommand extends BaseGenerateTestsCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:all
                            {--force : Force overwrite existing tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tests for all models, controllers, and other components';

    /**
     * Generate the tests.
     *
     * @return int
     */
    protected function generateTests(): int
    {
        $this->info('Generating tests for all components...');

        // Generate model tests
        $this->generateModelTests();

        // Generate controller tests
        $this->generateControllerTests();

        // Generate database tests
        $this->generateDatabaseTests();

        // Generate Livewire tests if Livewire is installed
        if ($this->isLivewireInstalled()) {
            $this->generateLivewireTests();
        }

        // Generate Filament tests if Filament is installed
        if ($this->isFilamentInstalled()) {
            $this->generateFilamentTests();
        }

        $this->info('All tests generated successfully!');

        return 0;
    }

    /**
     * Generate tests for all models.
     *
     * @return void
     */
    protected function generateModelTests(): void
    {
        $this->info('Generating tests for models...');

        $modelFiles = File::glob(app_path('Models/*.php'));
        $bar = $this->output->createProgressBar(count($modelFiles));
        $bar->start();

        foreach ($modelFiles as $modelFile) {
            $modelName = pathinfo($modelFile, PATHINFO_FILENAME);

            if ($this->option('verbose')) {
                $this->info("Generating tests for model: {$modelName}");
            }

            // Get the model analyzer
            $analyzer = app('generate-tests-easy.analyzer.model');

            // Analyze the model
            $analysis = $analyzer->analyze("App\\Models\\{$modelName}");

            // Get the model test generator
            $generator = app('generate-tests-easy.generator.model');

            // Set the confirmation callback
            $generator->setConfirmCallback(function($message, $type = 'confirm') {
                if ($type === 'confirm') {
                    return $this->confirm($message);
                } else if ($type === 'info') {
                    $this->info($message);
                }
            });

            // Generate tests
            $generator->generate($analysis, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Model tests generated successfully!');
    }

    /**
     * Generate tests for all controllers.
     *
     * @return void
     */
    protected function generateControllerTests(): void
    {
        $this->info('Generating tests for controllers...');

        $controllerFiles = File::glob(app_path('Http/Controllers/*.php'));
        $bar = $this->output->createProgressBar(count($controllerFiles));
        $bar->start();

        foreach ($controllerFiles as $controllerFile) {
            $controllerName = pathinfo($controllerFile, PATHINFO_FILENAME);

            if ($this->option('verbose')) {
                $this->info("Generating tests for controller: {$controllerName}");
            }

            // Get the controller analyzer
            $analyzer = app('generate-tests-easy.analyzer.controller');

            // Analyze the controller
            $analysis = $analyzer->analyze("App\\Http\\Controllers\\{$controllerName}");

            // Get the appropriate generator based on whether it's an API controller
            $generatorName = $analysis['isApi'] ? 'api' : 'controller';
            $generator = app("generate-tests-easy.generator.{$generatorName}");

            // Generate tests
            $generator->generate($analysis, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Controller tests generated successfully!');
    }

    /**
     * Generate tests based on database structure.
     *
     * @return void
     */
    protected function generateDatabaseTests(): void
    {
        $this->info('Generating tests based on database structure...');

        // Get the database analyzer
        $analyzer = app('generate-tests-easy.analyzer.database');

        // Analyze the database
        $analysis = $analyzer->analyze();

        // Get the model test generator
        $generator = app('generate-tests-easy.generator.model');

        // Generate tests for each table
        $bar = $this->output->createProgressBar(count($analysis['tables']));
        $bar->start();

        foreach ($analysis['tables'] as $table) {
            if ($this->option('verbose')) {
                $this->info("Generating tests for table: {$table['name']}");
            }

            // Generate tests
            $generator->generateFromTable($table, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Database tests generated successfully!');
    }

    /**
     * Generate tests for Livewire components.
     *
     * @return void
     */
    protected function generateLivewireTests(): void
    {
        $this->info('Generating tests for Livewire components...');

        // Get the Livewire analyzer
        $analyzer = app('generate-tests-easy.analyzer.livewire');

        // Analyze Livewire components
        $analysis = $analyzer->analyze();

        // Get the Livewire test generator
        $generator = app('generate-tests-easy.generator.livewire');

        // Generate tests for each component
        $bar = $this->output->createProgressBar(count($analysis['components']));
        $bar->start();

        foreach ($analysis['components'] as $component) {
            if ($this->option('verbose')) {
                $this->info("Generating tests for Livewire component: {$component['name']}");
            }

            // Generate tests
            $generator->generate($component, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Livewire tests generated successfully!');
    }

    /**
     * Generate tests for Filament resources.
     *
     * @return void
     */
    protected function generateFilamentTests(): void
    {
        $this->info('Generating tests for Filament resources...');

        // Get the Filament analyzer
        $analyzer = app('generate-tests-easy.analyzer.filament');

        // Analyze Filament resources
        $analysis = $analyzer->analyze();

        // Get the Filament test generator
        $generator = app('generate-tests-easy.generator.filament');

        // Generate tests for each resource
        $bar = $this->output->createProgressBar(count($analysis['resources']));
        $bar->start();

        foreach ($analysis['resources'] as $resource) {
            if ($this->option('verbose')) {
                $this->info("Generating tests for Filament resource: {$resource['name']}");
            }

            // Generate tests
            $generator->generate($resource, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Filament tests generated successfully!');
    }

    /**
     * Check if Livewire is installed.
     *
     * @return bool
     */
    protected function isLivewireInstalled(): bool
    {
        return class_exists('Livewire\\Livewire');
    }

    /**
     * Check if Filament is installed.
     *
     * @return bool
     */
    protected function isFilamentInstalled(): bool
    {
        // Check if filament/filament is in composer.json
        $composerJsonPath = base_path('composer.json');
        if (!File::exists($composerJsonPath)) {
            return false;
        }

        $composerJson = json_decode(File::get($composerJsonPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // Check in both require and require-dev sections
        return isset($composerJson['require']['filament/filament']) || 
               isset($composerJson['require-dev']['filament/filament']);
    }
}
