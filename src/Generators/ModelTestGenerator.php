<?php

namespace Gsferro\GenerateTestsEasy\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelTestGenerator
{
    /**
     * Generate tests for a model.
     *
     * @param array $analysis The analyzed model data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    public function generate(array $analysis, string $testPath, bool $force = false): void
    {
        // Get the model class name
        $modelClass = $analysis['class'];
        $modelShortName = basename(str_replace('\\', '/', $modelClass));

        // Create the test file path
        $testFilePath = $testPath . '/Unit/Models/' . $modelShortName . 'Test.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            return;
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Get the stub content
        $stubPath = __DIR__ . '/../Stubs/Models/ModelTest.stub';
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replacePlaceholders($stubContent, $analysis);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        echo "Test file created: " . $testFilePath . PHP_EOL;

        // Generate relationship tests if the model has relationships
        if (!empty($analysis['relationships'])) {
            $this->generateRelationshipTests($analysis, $testPath, $force);
        }

        // Generate scope tests if the model has scopes
        if (!empty($analysis['scopes'])) {
            $this->generateScopeTests($analysis, $testPath, $force);
        }

        // Generate validation tests if the model has validation rules
        if (!empty($analysis['validationRules'])) {
            $this->generateValidationTests($analysis, $testPath, $force);
        }
    }

    /**
     * Generate tests for a model based on a database table.
     *
     * @param array $table The analyzed table data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    public function generateFromTable(array $table, string $testPath, bool $force = false): void
    {
        // Convert table data to model analysis format
        $analysis = [
            'class' => $this->getModelClassFromTable($table['name']),
            'namespace' => 'App\\Models',
            'table' => $table['name'],
            'primaryKey' => 'id',
            'incrementing' => true,
            'timestamps' => true,
            'fillable' => array_column($table['columns'], 'name'),
            'relationships' => [],
            'scopes' => [],
            'validationRules' => [],
            'traits' => [],
        ];

        // Generate tests using the standard generate method
        $this->generate($analysis, $testPath, $force);
    }

    /**
     * Replace placeholders in the stub content.
     *
     * @param string $stubContent The stub content
     * @param array $analysis The analyzed model data
     * @return string The processed content
     */
    protected function replacePlaceholders(string $stubContent, array $analysis): string
    {
        // Extract the class name without the namespace
        $modelClass = basename(str_replace('\\', '/', $analysis['class']));
        // Extract the namespace, but keep backslashes for PHP namespace format
        $modelNamespace = dirname(str_replace('\\', '/', $analysis['class']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);
        $tableName = $analysis['table'] ?? Str::snake(Str::pluralStudly($modelClass));
        $primaryKey = $analysis['primaryKey'] ?? 'id';
        $incrementing = $analysis['incrementing'] ?? true;
        $timestamps = $analysis['timestamps'] ?? true;
        $fillable = $analysis['fillable'] ?? [];

        // Factory setup
        $factorySetup = "try {
        \$this->factoryModel = {$modelClass}::factory()->create();
    } catch (\Throwable \$e) {
        // Factory may not exist
    }";

        // Incrementing check
        $incrementingCheck = $incrementing 
            ? "expect(\$this->model->getIncrementing())->toBeTrue();" 
            : "expect(\$this->model->getIncrementing())->toBeFalse();";

        // Timestamps check
        $timestampsCheck = $timestamps 
            ? "expect(\$this->model->usesTimestamps())->toBeTrue();" 
            : "expect(\$this->model->usesTimestamps())->toBeFalse();";

        // Format fillable array
        $fillableStr = json_encode($fillable);

        // Placeholder for relationship tests
        $relationshipTests = '';

        // Placeholder for scope tests
        $scopeTests = '';

        // Placeholder for validation tests
        $validationTests = '';

        // Placeholder for trait tests
        $traitTests = '';

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ tableName }}', $tableName, $content);
        $content = str_replace('{{ primaryKey }}', $primaryKey, $content);
        $content = str_replace('{{ incrementingCheck }}', $incrementingCheck, $content);
        $content = str_replace('{{ timestampsCheck }}', $timestampsCheck, $content);
        $content = str_replace('{{ fillable }}', $fillableStr, $content);
        $content = str_replace('{{ factorySetup }}', $factorySetup, $content);
        $content = str_replace('{{ relationshipTests }}', $relationshipTests, $content);
        $content = str_replace('{{ scopeTests }}', $scopeTests, $content);
        $content = str_replace('{{ validationTests }}', $validationTests, $content);
        $content = str_replace('{{ traitTests }}', $traitTests, $content);

        return $content;
    }

    /**
     * Generate relationship tests for a model.
     *
     * @param array $analysis The analyzed model data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    protected function generateRelationshipTests(array $analysis, string $testPath, bool $force = false): void
    {
        // Get the model class name
        $modelClass = $analysis['class'];
        $modelShortName = basename(str_replace('\\', '/', $modelClass));

        // Create the test file path
        $testFilePath = $testPath . '/Unit/Models/' . $modelShortName . 'RelationshipsTest.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            return;
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Get the stub content
        $stubPath = __DIR__ . '/../Stubs/Models/ModelRelationshipsTest.stub';
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replaceRelationshipPlaceholders($stubContent, $analysis);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        echo "Test file created: " . $testFilePath . PHP_EOL;
    }

    /**
     * Generate scope tests for a model.
     *
     * @param array $analysis The analyzed model data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    protected function generateScopeTests(array $analysis, string $testPath, bool $force = false): void
    {
        // Get the model class name
        $modelClass = $analysis['class'];
        $modelShortName = basename(str_replace('\\', '/', $modelClass));

        // Create the test file path
        $testFilePath = $testPath . '/Unit/Models/' . $modelShortName . 'ScopesTest.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            return;
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Get the stub content
        $stubPath = __DIR__ . '/../Stubs/Models/ModelScopesTest.stub';
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replaceScopePlaceholders($stubContent, $analysis);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        echo "Test file created: " . $testFilePath . PHP_EOL;
    }

    /**
     * Generate validation tests for a model.
     *
     * @param array $analysis The analyzed model data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    protected function generateValidationTests(array $analysis, string $testPath, bool $force = false): void
    {
        // Get the model class name
        $modelClass = $analysis['class'];
        $modelShortName = basename(str_replace('\\', '/', $modelClass));

        // Create the test file path
        $testFilePath = $testPath . '/Unit/Models/' . $modelShortName . 'ValidationTest.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            return;
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Get the stub content
        $stubPath = __DIR__ . '/../Stubs/Models/ModelValidationTest.stub';
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replaceValidationPlaceholders($stubContent, $analysis);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        echo "Test file created: " . $testFilePath . PHP_EOL;
    }

    /**
     * Replace placeholders in the relationship test stub.
     *
     * @param string $stubContent The stub content
     * @param array $analysis The analyzed model data
     * @return string The processed content
     */
    protected function replaceRelationshipPlaceholders(string $stubContent, array $analysis): string
    {
        $modelClass = basename(str_replace('\\', '/', $analysis['class']));
        $modelNamespace = dirname(str_replace('\\', '/', $analysis['class']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);

        // Factory setup
        $factorySetup = "try {
        \$this->factoryModel = {$modelClass}::factory()->create();
    } catch (\Throwable \$e) {
        // Factory may not exist
    }";

        // Generate relationship method checks
        $relationshipMethodChecks = '';
        if (!empty($analysis['relationships'])) {
            foreach ($analysis['relationships'] as $name => $relationship) {
                $relationshipMethodChecks .= "expect(method_exists(\$this->model, '{$name}'))->toBeTrue();" . PHP_EOL . '    ';
            }
        } else {
            $relationshipMethodChecks = "// No relationships defined in model";
        }

        // Generate relationship test cases
        $relationshipTests = '';
        foreach ($analysis['relationships'] as $name => $relationship) {
            $type = $relationship['type'];

            // Check if 'related' key exists in the relationship array
            if (!isset($relationship['related'])) {
                // Skip this relationship or use a generic test without the related model
                $relationshipTests .= "test('{$modelClass} has {$type} relationship named {$name}', function() {
    // Check that the relationship method exists
    expect(method_exists(\$this->model, '{$name}'))
        ->toBeTrue();

    // Check that the relationship is of the correct type
    \$relationship = \$this->model->{$name}();
    expect(\$relationship)
        ->toBeInstanceOf(\\Illuminate\\Database\\Eloquent\\Relations\\{$type}::class);
});" . PHP_EOL . PHP_EOL;
                continue;
            }

            $related = $relationship['related'];
            $relatedShortName = basename(str_replace('\\', '/', $related));

            $relationshipTests .= "test('{$modelClass} has {$type} relationship with {$relatedShortName}', function() {
    // Check that the relationship method exists
    expect(method_exists(\$this->model, '{$name}'))
        ->toBeTrue();

    // Check that the relationship is of the correct type
    \$relationship = \$this->model->{$name}();
    expect(\$relationship)
        ->toBeInstanceOf(\\Illuminate\\Database\\Eloquent\\Relations\\{$type}::class);

    // Check that the relationship is with the correct model
    expect(\$relationship->getRelated())
        ->toBeInstanceOf({$related}::class);
});" . PHP_EOL . PHP_EOL;
        }

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ factorySetup }}', $factorySetup, $content);
        $content = str_replace('{{ relationshipMethodChecks }}', $relationshipMethodChecks, $content);
        $content = str_replace('{{ individualRelationshipTests }}', $relationshipTests, $content);

        return $content;
    }

    /**
     * Replace placeholders in the scope test stub.
     *
     * @param string $stubContent The stub content
     * @param array $analysis The analyzed model data
     * @return string The processed content
     */
    protected function replaceScopePlaceholders(string $stubContent, array $analysis): string
    {
        $modelClass = basename(str_replace('\\', '/', $analysis['class']));
        $modelNamespace = dirname(str_replace('\\', '/', $analysis['class']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);

        // Generate scope test cases
        $scopeTests = '';
        foreach ($analysis['scopes'] as $name => $scope) {
            $scopeMethod = 'scope' . ucfirst($name);

            $scopeTests .= "test('{$modelClass} has {$name} scope', function() {
    // Check that the scope method exists
    expect(method_exists(\$this->model, '{$scopeMethod}'))
        ->toBeTrue();

    // Check that the scope can be called on the query builder
    \$query = {$modelClass}::query();
    expect(method_exists(\$query, '{$name}'))
        ->toBeTrue();
});" . PHP_EOL . PHP_EOL;
        }

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ scopeTests }}', $scopeTests, $content);

        return $content;
    }

    /**
     * Replace placeholders in the validation test stub.
     *
     * @param string $stubContent The stub content
     * @param array $analysis The analyzed model data
     * @return string The processed content
     */
    protected function replaceValidationPlaceholders(string $stubContent, array $analysis): string
    {
        $modelClass = basename(str_replace('\\', '/', $analysis['class']));
        $modelNamespace = dirname(str_replace('\\', '/', $analysis['class']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);

        // Generate validation test cases
        $validationTests = '';
        $validationRules = '';

        if (!empty($analysis['validationRules'])) {
            $validationRules = json_encode($analysis['validationRules'], JSON_PRETTY_PRINT);

            foreach ($analysis['validationRules'] as $field => $rules) {
                $validationTests .= "test('{$modelClass} validates {$field}', function() {
    // Check that the field has validation rules
    \$rules = \$this->model->getValidationRules();
    expect(\$rules)
        ->toHaveKey('{$field}');

    // Check that the rules are correct
    expect(\$rules['{$field}'])
        ->toBe('{$rules}');
});" . PHP_EOL . PHP_EOL;
            }
        }

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ validationRules }}', $validationRules, $content);
        $content = str_replace('{{ validationTests }}', $validationTests, $content);

        return $content;
    }

    /**
     * Get the model class name from a table name.
     *
     * @param string $tableName The table name
     * @return string The model class name
     */
    protected function getModelClassFromTable(string $tableName): string
    {
        // Convert snake_case to StudlyCase and singularize
        $modelName = Str::studly(Str::singular($tableName));

        return "App\\Models\\{$modelName}";
    }
}
