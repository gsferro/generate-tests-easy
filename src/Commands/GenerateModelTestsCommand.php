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
                            {model : The name of the model to generate tests for}
                            {--force : Force overwrite existing tests}
                            {--verbose : Show detailed information during generation}';

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
        
        // Generate tests
        $generator->generate($analysis, $this->getTestPath(), $this->option('force'));
        
        $this->info("Tests for model {$modelName} generated successfully!");
        
        return 0;
    }
}