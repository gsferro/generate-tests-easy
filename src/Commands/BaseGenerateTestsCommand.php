<?php

namespace Gsferro\GenerateTestsEasy\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class BaseGenerateTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate-tests:base {--force : Force overwrite existing tests} {--verbose : Show detailed information during generation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Base command for generating tests';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Check if Pest is installed
        if (!$this->isPestInstalled() && config('generate-tests-easy.install_pest', true)) {
            $this->installPest();
        }

        // Configure Pest Arch presets if enabled
        if (config('generate-tests-easy.configure_arch_presets', true)) {
            $this->configurePestArchPresets();
        }

        return $this->generateTests();
    }

    /**
     * Generate the tests.
     *
     * @return int
     */
    abstract protected function generateTests(): int;

    /**
     * Check if Pest is installed.
     *
     * @return bool
     */
    protected function isPestInstalled(): bool
    {
        return File::exists(base_path('vendor/pestphp/pest/bin/pest')) && 
               File::exists(base_path('tests/Pest.php'));
    }

    /**
     * Install Pest.
     *
     * @return void
     */
    protected function installPest(): void
    {
        $this->info('Installing Pest...');

        // Run Pest installer
        $this->executeCommand('php artisan pest:install');

        // Create Pest.php if it doesn't exist
        if (!File::exists(base_path('tests/Pest.php'))) {
            $this->createPestFile();
        }

        $this->info('Pest installed successfully.');
    }

    /**
     * Configure Pest Arch presets.
     *
     * @return void
     */
    protected function configurePestArchPresets(): void
    {
        $this->info('Configuring Pest Arch presets...');

        // Create or update the arch.php file
        $archFile = base_path('tests/Arch.php');

        $content = <<<'PHP'
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
PHP;

        File::put($archFile, $content);

        $this->info('Pest Arch presets configured successfully.');
    }

    /**
     * Create the Pest.php file.
     *
     * @return void
     */
    protected function createPestFile(): void
    {
        $pestFile = base_path('tests/Pest.php');

        $content = <<<'PHP'
<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
PHP;

        File::put($pestFile, $content);
    }

    /**
     * Execute a shell command.
     *
     * @param string $command
     * @return int
     */
    protected function executeCommand(string $command): int
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($output) {
                $this->info($output);
            }

            if ($error) {
                $this->error($error);
            }

            return $exitCode;
        }

        return 1;
    }

    /**
     * Get the test path.
     *
     * @return string
     */
    protected function getTestPath(): string
    {
        return base_path(config('generate-tests-easy.test_path', 'tests'));
    }

    /**
     * Create a directory if it doesn't exist.
     *
     * @param string $path
     * @return void
     */
    protected function createDirectory(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    /**
     * Write content to a file.
     *
     * @param string $path
     * @param string $content
     * @return void
     */
    protected function writeFile(string $path, string $content): void
    {
        $this->createDirectory(dirname($path));

        if (File::exists($path) && !$this->option('force')) {
            if (!$this->confirm("The file {$path} already exists. Do you want to overwrite it?")) {
                $this->info("Skipped {$path}");
                return;
            }
        }

        File::put($path, $content);
        $this->info("Created {$path}");
    }
}
