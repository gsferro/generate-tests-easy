<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateDatabaseTestsCommand extends BaseGenerateTestsCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:database
                            {--connection= : The database connection to use}
                            {--tables= : Comma-separated list of tables to generate tests for}
                            {--exclude= : Comma-separated list of tables to exclude}
                            {--force : Force overwrite existing tests}
                            {--verbose : Show detailed information during generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tests based on database structure';

    /**
     * Generate the tests.
     *
     * @return int
     */
    protected function generateTests(): int
    {
        $this->info('Generating tests based on database structure...');
        
        // Get the database analyzer
        $analyzer = app('generate-tests-easy.analyzer.database');
        
        // Set options for the analyzer
        $options = [
            'connection' => $this->option('connection'),
            'tables' => $this->option('tables') ? explode(',', $this->option('tables')) : null,
            'exclude' => $this->option('exclude') ? explode(',', $this->option('exclude')) : null,
        ];
        
        // Analyze the database
        $analysis = $analyzer->analyze($options);
        
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
        
        return 0;
    }
}