<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateModelTestsCommand extends BaseGenerateTestsCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:model
                            {model? : The name of the model to generate tests for}
                            {--force : Force overwrite existing tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tests for a specific model';

    /**
     * Generate the tests.
     *
     * @return int
     */
    protected function generateTests(): int
    {
        $modelName = $this->argument('model');

        // If no model is provided, list all models and prompt for selection
        if (is_null($modelName)) {
            $modelName = $this->promptForModel();

            // If user cancelled the selection
            if (is_null($modelName)) {
                $this->error('Model selection cancelled.');
                return 1;
            }
        }

        // If the model name doesn't include the namespace, assume it's in App\Models
        if (!Str::contains($modelName, '\\')) {
            $modelName = "App\\Models\\{$modelName}";
        }

        $this->info("Generating tests for model: {$modelName}");

        // Check if the model exists
        if (!class_exists($modelName)) {
            $this->error("Model {$modelName} does not exist.");
            return 1;
        }

        // Get the model analyzer
        $analyzer = app('generate-tests-easy.analyzer.model');

        // Analyze the model
        $analysis = $analyzer->analyze($modelName);

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

        $this->info("Tests for model {$modelName} generated successfully!");

        return 0;
    }

    /**
     * Prompt the user to select a model from the list of available models.
     *
     * @return string|null The selected model name, or null if selection was cancelled
     */
    protected function promptForModel(): ?string
    {
        // Get all model files from the app/Models directory
        $modelFiles = File::glob(app_path('Models/*.php'));

        if (empty($modelFiles)) {
            $this->error('No models found in app/Models directory.');
            return null;
        }

        // Extract model names from file paths
        $models = [];
        foreach ($modelFiles as $modelFile) {
            $modelName = pathinfo($modelFile, PATHINFO_FILENAME);
            $models[] = $modelName;
        }

        // Display the list of models
        $this->info('Available models:');
        foreach ($models as $index => $model) {
            $this->line(sprintf(' [%d] %s', $index + 1, $model));
        }

        // Prompt the user to select a model
        $selectedIndex = $this->ask('Enter the number of the model you want to generate tests for (or press Enter to cancel)');

        // Handle cancellation
        if (empty($selectedIndex)) {
            return null;
        }

        // Validate the selection
        $selectedIndex = (int) $selectedIndex - 1;
        if (!isset($models[$selectedIndex])) {
            $this->error('Invalid selection.');
            return null;
        }

        return $models[$selectedIndex];
    }
}
