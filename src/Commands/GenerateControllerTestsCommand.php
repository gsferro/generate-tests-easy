<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateControllerTestsCommand extends BaseGenerateTestsCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:controller
                            {controller : The name of the controller to generate tests for}
                            {--force : Force overwrite existing tests}
                            {--verbose : Show detailed information during generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tests for a specific controller';

    /**
     * Generate the tests.
     *
     * @return int
     */
    protected function generateTests(): int
    {
        $controllerName = $this->argument('controller');
        
        // If the controller name doesn't include the namespace, assume it's in App\Http\Controllers
        if (!Str::contains($controllerName, '\\')) {
            $controllerName = "App\\Http\\Controllers\\{$controllerName}";
        }
        
        $this->info("Generating tests for controller: {$controllerName}");
        
        // Check if the controller exists
        if (!class_exists($controllerName)) {
            $this->error("Controller {$controllerName} does not exist.");
            return 1;
        }
        
        // Get the controller analyzer
        $analyzer = app('generate-tests-easy.analyzer.controller');
        
        // Analyze the controller
        $analysis = $analyzer->analyze($controllerName);
        
        // Get the appropriate generator based on whether it's an API controller
        $generatorName = $analysis['isApi'] ? 'api' : 'controller';
        $generator = app("generate-tests-easy.generator.{$generatorName}");
        
        // Generate tests
        $generator->generate($analysis, $this->getTestPath(), $this->option('force'));
        
        $this->info("Tests for controller {$controllerName} generated successfully!");
        
        return 0;
    }
}