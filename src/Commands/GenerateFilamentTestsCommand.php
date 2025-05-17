<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Gsferro\GenerateTestsEasy\Analyzers\FilamentResourceAnalyzer;
use Gsferro\GenerateTestsEasy\Generators\FilamentTestGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateFilamentTestsCommand extends BaseGenerateTestsCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:filament
                            {resource? : The name of the Filament resource to generate tests for}
                            {--all : Generate tests for all Filament resources}
                            {--force : Force overwrite existing tests}
                            {--verbose : Show detailed information during generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tests for Filament resources';

    /**
     * The Filament resource analyzer.
     *
     * @var FilamentResourceAnalyzer
     */
    protected $analyzer;

    /**
     * The Filament test generator.
     *
     * @var FilamentTestGenerator
     */
    protected $generator;

    /**
     * Create a new command instance.
     *
     * @param FilamentResourceAnalyzer $analyzer
     * @param FilamentTestGenerator $generator
     * @return void
     */
    public function __construct(FilamentResourceAnalyzer $analyzer, FilamentTestGenerator $generator)
    {
        parent::__construct();

        $this->analyzer = $analyzer;
        $this->generator = $generator;
    }

    /**
     * Generate the tests.
     *
     * @return int
     */
    protected function generateTests(): int
    {
        // Check if Filament is installed
        if (!class_exists('Filament\\Filament')) {
            $this->error('Filament is not installed.');
            return 1;
        }

        // Generate tests for a specific resource or all resources
        if ($this->option('all')) {
            return $this->generateTestsForAllResources();
        } else {
            $resource = $this->argument('resource');
            if (!$resource) {
                $this->error('Please specify a resource or use the --all option.');
                return 1;
            }
            return $this->generateTestsForResource($resource);
        }
    }

    /**
     * Generate tests for all Filament resources.
     *
     * @return int
     */
    protected function generateTestsForAllResources(): int
    {
        $this->info('Generating tests for all Filament resources...');

        // Find all resources
        $resources = $this->analyzer->findResources();

        if (empty($resources)) {
            $this->info('No Filament resources found.');
            return 0;
        }

        // Create a progress bar
        $bar = $this->output->createProgressBar(count($resources));
        $bar->start();

        // Generate tests for each resource
        foreach ($resources as $resource) {
            if ($this->option('verbose')) {
                $this->info("Generating tests for resource: {$resource['shortName']}");
            }

            $this->generator->generate($resource, $this->getTestPath(), $this->option('force'));

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Filament resource tests generated successfully!');

        return 0;
    }

    /**
     * Generate tests for a specific Filament resource.
     *
     * @param string $resourceName The name of the resource
     * @return int
     */
    protected function generateTestsForResource(string $resourceName): int
    {
        $this->info("Generating tests for Filament resource: {$resourceName}");

        // If the resource name doesn't include the namespace, assume it's in App\Filament\Resources
        if (!Str::contains($resourceName, '\\')) {
            $resourceName = "App\\Filament\\Resources\\{$resourceName}";
            
            // Add "Resource" suffix if not present
            if (!Str::endsWith($resourceName, 'Resource')) {
                $resourceName .= 'Resource';
            }
        }

        // Check if the resource exists
        if (!class_exists($resourceName)) {
            $this->error("Resource {$resourceName} does not exist.");
            return 1;
        }

        // Analyze the resource
        try {
            $resource = $this->analyzer->analyze($resourceName);
        } catch (\Exception $e) {
            $this->error("Error analyzing resource: {$e->getMessage()}");
            return 1;
        }

        // Generate tests
        $this->generator->generate($resource, $this->getTestPath(), $this->option('force'));

        $this->info("Tests for Filament resource {$resourceName} generated successfully!");

        return 0;
    }
}