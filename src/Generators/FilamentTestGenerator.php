<?php

namespace Gsferro\GenerateTestsEasy\Generators;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FilamentTestGenerator
{
    /**
     * Callback function for confirming file overwrites.
     *
     * @var callable|null
     */
    protected $confirmCallback = null;

    /**
     * Set the callback function for confirming file overwrites.
     *
     * @param callable $callback
     * @return $this
     */
    public function setConfirmCallback(callable $callback): self
    {
        $this->confirmCallback = $callback;
        return $this;
    }
    /**
     * Generate tests for a Filament resource.
     *
     * @param array $resource The analyzed resource data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    public function generate(array $resource, string $testPath, bool $force = false): void
    {
        // Generate the resource test
        $this->generateResourceTest($resource, $testPath, $force);

        // Generate tests for each page
        foreach ($resource['pages'] as $pageName => $page) {
            $this->generatePageTest($resource, $page, $pageName, $testPath, $force);
        }
    }

    /**
     * Generate a test for a Filament resource.
     *
     * @param array $resource The analyzed resource data
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    protected function generateResourceTest(array $resource, string $testPath, bool $force = false): void
    {
        // Get the resource class name
        $resourceClass = $resource['shortName'];

        // Create the test file path
        $testFilePath = $testPath . '/Feature/Filament/' . $resourceClass . 'Test.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            // If we have a confirmation callback, use it to ask for confirmation
            if ($this->confirmCallback && !call_user_func($this->confirmCallback, "The file {$testFilePath} already exists. Do you want to overwrite it?")) {
                // If the user doesn't want to overwrite, skip this file
                if ($this->confirmCallback) {
                    call_user_func($this->confirmCallback, "Skipped {$testFilePath}", 'info');
                }
                return;
            }
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Get the stub content
        $stubPath = __DIR__ . '/../Stubs/Filament/FilamentResourceTest.stub';
        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replaceResourcePlaceholders($stubContent, $resource);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        if ($this->confirmCallback) {
            call_user_func($this->confirmCallback, "Test file created: {$testFilePath}", 'info');
        } else {
            echo "Test file created: " . $testFilePath . PHP_EOL;
        }
    }

    /**
     * Generate a test for a Filament resource page.
     *
     * @param array $resource The analyzed resource data
     * @param array $page The analyzed page data
     * @param string $pageName The name of the page
     * @param string $testPath The path where tests should be generated
     * @param bool $force Whether to force overwrite existing tests
     * @return void
     */
    protected function generatePageTest(array $resource, array $page, string $pageName, string $testPath, bool $force = false): void
    {
        // Get the page class name
        $pageClass = $page['class'];
        $pageShortName = basename(str_replace('\\', '/', $pageClass));

        // Create the test file path
        $testFilePath = $testPath . '/Feature/Filament/' . $resource['shortName'] . '/' . $pageShortName . 'Test.php';

        // Check if the test file already exists and we're not forcing overwrite
        if (File::exists($testFilePath) && !$force) {
            // If we have a confirmation callback, use it to ask for confirmation
            if ($this->confirmCallback && !call_user_func($this->confirmCallback, "The file {$testFilePath} already exists. Do you want to overwrite it?")) {
                // If the user doesn't want to overwrite, skip this file
                if ($this->confirmCallback) {
                    call_user_func($this->confirmCallback, "Skipped {$testFilePath}", 'info');
                }
                return;
            }
        }

        // Create the test directory if it doesn't exist
        if (!File::isDirectory(dirname($testFilePath))) {
            File::makeDirectory(dirname($testFilePath), 0755, true);
        }

        // Determine the page type and get the appropriate stub
        $pageType = $this->determinePageType($pageName, $pageShortName);
        $stubPath = __DIR__ . '/../Stubs/Filament/FilamentResource' . $pageType . 'PageTest.stub';

        // If the specific stub doesn't exist, use the generic page stub
        if (!File::exists($stubPath)) {
            $stubPath = __DIR__ . '/../Stubs/Filament/FilamentResourcePageTest.stub';
        }

        $stubContent = File::get($stubPath);

        // Replace placeholders in the stub
        $content = $this->replacePagePlaceholders($stubContent, $resource, $page, $pageName, $pageType);

        // Write the test file
        File::put($testFilePath, $content);

        // Output the created test file name
        if ($this->confirmCallback) {
            call_user_func($this->confirmCallback, "Test file created: {$testFilePath}", 'info');
        } else {
            echo "Test file created: " . $testFilePath . PHP_EOL;
        }
    }

    /**
     * Determine the type of a Filament resource page.
     *
     * @param string $pageName The name of the page
     * @param string $pageShortName The short name of the page class
     * @return string The page type (List, Create, Edit)
     */
    protected function determinePageType(string $pageName, string $pageShortName): string
    {
        if ($pageName === 'index' || Str::contains($pageShortName, ['List', 'Index'])) {
            return 'List';
        }

        if ($pageName === 'create' || Str::contains($pageShortName, ['Create', 'New'])) {
            return 'Create';
        }

        if ($pageName === 'edit' || Str::contains($pageShortName, ['Edit', 'Update'])) {
            return 'Edit';
        }

        if ($pageName === 'view' || Str::contains($pageShortName, ['View', 'Show', 'Detail'])) {
            return 'View';
        }

        return '';
    }

    /**
     * Replace placeholders in the resource test stub.
     *
     * @param string $stubContent The stub content
     * @param array $resource The analyzed resource data
     * @return string The processed content
     */
    protected function replaceResourcePlaceholders(string $stubContent, array $resource): string
    {
        $resourceClass = $resource['shortName'];
        $resourceNamespace = $resource['namespace'];
        $modelClass = basename(str_replace('\\', '/', $resource['model']));
        $modelNamespace = dirname(str_replace('\\', '/', $resource['model']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);
        $routePrefix = 'admin'; // Default route prefix, can be customized
        $resourceSlug = Str::kebab(Str::beforeLast($resourceClass, 'Resource'));

        // Generate page assertions
        $pageAssertions = $this->generatePageAssertions($resource);

        // Generate form assertions
        $formAssertions = $this->generateFormAssertions($resource);

        // Generate table assertions
        $tableAssertions = $this->generateTableAssertions($resource);

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ resourceNamespace }}', $resourceNamespace, $content);
        $content = str_replace('{{ resourceClass }}', $resourceClass, $content);
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ resourceName }}', Str::beforeLast($resourceClass, 'Resource'), $content);
        $content = str_replace('{{ routePrefix }}', $routePrefix, $content);
        $content = str_replace('{{ resourceSlug }}', $resourceSlug, $content);
        $content = str_replace('{{ pageAssertions }}', $pageAssertions, $content);
        $content = str_replace('{{ formAssertions }}', $formAssertions, $content);
        $content = str_replace('{{ tableAssertions }}', $tableAssertions, $content);
        $content = str_replace('{{ customTests }}', '', $content);

        return $content;
    }

    /**
     * Replace placeholders in the page test stub.
     *
     * @param string $stubContent The stub content
     * @param array $resource The analyzed resource data
     * @param array $page The analyzed page data
     * @param string $pageName The name of the page
     * @param string $pageType The type of the page
     * @return string The processed content
     */
    protected function replacePagePlaceholders(string $stubContent, array $resource, array $page, string $pageName, string $pageType): string
    {
        $resourceClass = $resource['shortName'];
        $resourceNamespace = $resource['namespace'];
        $pageClass = basename(str_replace('\\', '/', $page['class']));
        $pageNamespace = dirname(str_replace('\\', '/', $page['class']));
        // Convert forward slashes back to backslashes for PHP namespace
        $pageNamespace = str_replace('/', '\\', $pageNamespace);
        $modelClass = basename(str_replace('\\', '/', $resource['model']));
        $modelNamespace = dirname(str_replace('\\', '/', $resource['model']));
        // Convert forward slashes back to backslashes for PHP namespace
        $modelNamespace = str_replace('/', '\\', $modelNamespace);
        $routePrefix = 'admin'; // Default route prefix, can be customized
        $resourceSlug = Str::kebab(Str::beforeLast($resourceClass, 'Resource'));

        // Generate route for the page
        $pageRoute = '';
        if ($pageType === 'Create') {
            $pageRoute = '/create';
        } elseif ($pageType === 'Edit') {
            $pageRoute = '/{record}/edit';
        } elseif ($pageType === 'View') {
            $pageRoute = '/{record}';
        }

        // Generate auth setup
        $authSetup = '$user = \App\Models\User::factory()->create();' . PHP_EOL . '    $this->actingAs($user);';

        // Generate model factory setup
        $modelFactorySetup = '$model = ' . $modelClass . '::factory()->create();';

        // Generate model factory data setup
        $modelFactoryDataSetup = '$data = ' . $modelClass . '::factory()->make()->toArray();';

        // Generate model factory update data setup
        $modelFactoryUpdateDataSetup = '$data = ' . $modelClass . '::factory()->make()->toArray();';

        // Generate form field assertions
        $formFieldAssertions = $this->generateFormFieldAssertions($resource);

        // Generate form data assertions
        $formDataAssertions = $this->generateFormDataAssertions($resource);

        // Generate form fill assertions
        $formFillAssertions = $this->generateFormFillAssertions($resource);

        // Generate invalid form fill assertions
        $invalidFormFillAssertions = $this->generateInvalidFormFillAssertions($resource);

        // Generate form error assertions
        $formErrorAssertions = $this->generateFormErrorAssertions($resource);

        // Generate database assertions
        $databaseAssertions = $this->generateDatabaseAssertions($resource);

        // Generate table column assertions
        $tableColumnAssertions = $this->generateTableColumnAssertions($resource);

        // Generate sort assertions
        $sortAssertions = $this->generateSortAssertions($resource);

        // Generate relationship tests for view pages
        $relationshipTests = '';
        if ($pageType === 'View') {
            $relationshipTests = $this->generateRelationshipTests($resource);
        }

        // Generate search term
        $searchTerm = 'test';

        // Replace placeholders
        $content = $stubContent;
        $content = str_replace('{{ pageNamespace }}', $pageNamespace, $content);
        $content = str_replace('{{ pageClass }}', $pageClass, $content);
        $content = str_replace('{{ resourceNamespace }}', $resourceNamespace, $content);
        $content = str_replace('{{ resourceClass }}', $resourceClass, $content);
        $content = str_replace('{{ modelNamespace }}', $modelNamespace, $content);
        $content = str_replace('{{ modelClass }}', $modelClass, $content);
        $content = str_replace('{{ routePrefix }}', $routePrefix, $content);
        $content = str_replace('{{ resourceSlug }}', $resourceSlug, $content);
        $content = str_replace('{{ pageRoute }}', $pageRoute, $content);
        $content = str_replace('{{ authSetup }}', $authSetup, $content);
        $content = str_replace('{{ modelFactorySetup }}', $modelFactorySetup, $content);
        $content = str_replace('{{ modelFactoryDataSetup }}', $modelFactoryDataSetup, $content);
        $content = str_replace('{{ modelFactoryUpdateDataSetup }}', $modelFactoryUpdateDataSetup, $content);
        $content = str_replace('{{ formFieldAssertions }}', $formFieldAssertions, $content);
        $content = str_replace('{{ formDataAssertions }}', $formDataAssertions, $content);
        $content = str_replace('{{ formFillAssertions }}', $formFillAssertions, $content);
        $content = str_replace('{{ invalidFormFillAssertions }}', $invalidFormFillAssertions, $content);
        $content = str_replace('{{ formErrorAssertions }}', $formErrorAssertions, $content);
        $content = str_replace('{{ databaseAssertions }}', $databaseAssertions, $content);
        $content = str_replace('{{ tableColumnAssertions }}', $tableColumnAssertions, $content);
        $content = str_replace('{{ sortAssertions }}', $sortAssertions, $content);
        $content = str_replace('{{ relationshipTests }}', $relationshipTests, $content);
        $content = str_replace('{{ searchTerm }}', $searchTerm, $content);
        $content = str_replace('{{ tableName }}', Str::snake(Str::pluralStudly(Str::beforeLast($resourceClass, 'Resource'))), $content);
        $content = str_replace('{{ customTests }}', '', $content);

        return $content;
    }

    /**
     * Generate assertions for resource pages.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generatePageAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['pages']) && !empty($resource['pages'])) {
            foreach ($resource['pages'] as $pageName => $page) {
                $assertions .= "expect(\$pages)->toHaveKey('{$pageName}');" . PHP_EOL . '    ';
            }
        } else {
            $assertions = "expect(\$pages)->toBeArray();" . PHP_EOL . '    expect(\$pages)->not()->toBeEmpty();';
        }

        return $assertions;
    }

    /**
     * Generate assertions for form schema.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateFormAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            foreach ($resource['formSchema'] as $field) {
                $assertions .= "expect(\$form->getComponents())->toContain(function (\$component) {" . PHP_EOL;
                $assertions .= "        return \$component->getName() === '{$field['name']}';" . PHP_EOL;
                $assertions .= "    });" . PHP_EOL . '    ';
            }
        } else {
            $assertions = "expect(\$form->getComponents())->toBeArray();" . PHP_EOL . '    expect(\$form->getComponents())->not()->toBeEmpty();';
        }

        return $assertions;
    }

    /**
     * Generate assertions for table schema.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateTableAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['tableColumns']) && !empty($resource['tableColumns'])) {
            foreach ($resource['tableColumns'] as $column) {
                $assertions .= "expect(\$table->getColumns())->toContain(function (\$column) {" . PHP_EOL;
                $assertions .= "        return \$column->getName() === '{$column['name']}';" . PHP_EOL;
                $assertions .= "    });" . PHP_EOL . '    ';
            }
        } else {
            $assertions = "expect(\$table->getColumns())->toBeArray();" . PHP_EOL . '    expect(\$table->getColumns())->not()->toBeEmpty();';
        }

        return $assertions;
    }

    /**
     * Generate assertions for form fields.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateFormFieldAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            foreach ($resource['formSchema'] as $field) {
                $assertions .= "->assertFormFieldExists('{$field['name']}')" . PHP_EOL . '        ';
            }
        } else {
            $assertions = "->assertFormExists()";
        }

        return $assertions;
    }

    /**
     * Generate assertions for form data.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateFormDataAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            foreach ($resource['formSchema'] as $field) {
                if (!$field['required']) {
                    continue;
                }
                $assertions .= "->assertFormSet('{$field['name']}', \$model->{$field['name']})" . PHP_EOL . '        ';
            }
        }

        return $assertions;
    }

    /**
     * Generate assertions for filling a form.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateFormFillAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            foreach ($resource['formSchema'] as $field) {
                $assertions .= "->fillForm([" . PHP_EOL;
                $assertions .= "            '{$field['name']}' => \$data['{$field['name']}']," . PHP_EOL;
                $assertions .= "        ])" . PHP_EOL . '        ';
            }
        } else {
            $assertions = "->fillForm(\$data)";
        }

        return $assertions;
    }

    /**
     * Generate assertions for filling a form with invalid data.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateInvalidFormFillAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            foreach ($resource['formSchema'] as $field) {
                if (!$field['required']) {
                    continue;
                }
                $assertions .= "->fillForm([" . PHP_EOL;
                $assertions .= "            '{$field['name']}' => null," . PHP_EOL;
                $assertions .= "        ])" . PHP_EOL . '        ';
                break;
            }
        } else {
            $assertions = "->fillForm(['name' => null])";
        }

        return $assertions;
    }

    /**
     * Generate assertions for form errors.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateFormErrorAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            $assertions = '[';
            foreach ($resource['formSchema'] as $field) {
                if (!$field['required']) {
                    continue;
                }
                $assertions .= "'{$field['name']}', ";
                break;
            }
            $assertions .= ']';
        } else {
            $assertions = "['name']";
        }

        return $assertions;
    }

    /**
     * Generate assertions for database checks.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateDatabaseAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['formSchema']) && !empty($resource['formSchema'])) {
            $assertions = '[';
            foreach ($resource['formSchema'] as $field) {
                $assertions .= "'{$field['name']}' => \$data['{$field['name']}'], ";
            }
            $assertions .= ']';
        } else {
            $assertions = "\$data";
        }

        return $assertions;
    }

    /**
     * Generate assertions for table columns.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateTableColumnAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['tableColumns']) && !empty($resource['tableColumns'])) {
            foreach ($resource['tableColumns'] as $column) {
                $assertions .= "->assertCanSeeTableColumn('{$column['name']}')" . PHP_EOL . '        ';
            }
        }

        return $assertions;
    }

    /**
     * Generate assertions for sorting.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated assertions
     */
    protected function generateSortAssertions(array $resource): string
    {
        $assertions = '';

        if (isset($resource['tableColumns']) && !empty($resource['tableColumns'])) {
            foreach ($resource['tableColumns'] as $column) {
                if (!isset($column['sortable']) || !$column['sortable']) {
                    continue;
                }
                $assertions .= "->sortTable('{$column['name']}')" . PHP_EOL . '        ';
                $assertions .= "->assertCanSeeTableRecords([\$model])" . PHP_EOL . '        ';
                break;
            }
        }

        return $assertions;
    }

    /**
     * Generate tests for relationships in a view page.
     *
     * @param array $resource The analyzed resource data
     * @return string The generated tests
     */
    protected function generateRelationshipTests(array $resource): string
    {
        // Get the model class
        $modelClass = $resource['model'];

        // Try to instantiate the model to analyze its relationships
        try {
            $modelInstance = new $modelClass();
            $modelReflection = new \ReflectionClass($modelClass);

            // Get all public methods
            $methods = $modelReflection->getMethods(\ReflectionMethod::IS_PUBLIC);

            $tests = '';

            foreach ($methods as $method) {
                // Skip methods that are not defined in this class
                if ($method->class !== $modelClass) {
                    continue;
                }

                // Skip common methods that are not relationships
                if (in_array($method->getName(), ['__construct', 'boot', 'booted'])) {
                    continue;
                }

                try {
                    // Check if the method returns a relation
                    $methodName = $method->getName();
                    if (method_exists($modelInstance, $methodName)) {
                        $returnType = $method->getReturnType();

                        // Check if the return type is a relation
                        if ($returnType && strpos($returnType->getName(), 'Illuminate\\Database\\Eloquent\\Relations') !== false) {
                            $tests .= $this->generateRelationshipTest($methodName);
                            continue;
                        }

                        // If no return type hint, try to call the method
                        $result = $modelInstance->$methodName();
                        if (is_object($result) && strpos(get_class($result), 'Illuminate\\Database\\Eloquent\\Relations') !== false) {
                            $tests .= $this->generateRelationshipTest($methodName);
                        }
                    }
                } catch (\Exception $e) {
                    // If we can't call the method, skip it
                    continue;
                }
            }

            return $tests;

        } catch (\Exception $e) {
            // If we can't instantiate the model, return an empty string
            return '';
        }
    }

    /**
     * Generate a test for a specific relationship.
     *
     * @param string $relationshipName The name of the relationship
     * @return string The generated test
     */
    protected function generateRelationshipTest(string $relationshipName): string
    {
        return "test('View page displays {$relationshipName} relationship', function () {
    // Create a test record with relationships
    \$model = \$this->model;

    // Test the Livewire component
    Livewire::test(\$this->pageClass, [
        'record' => \$model->id,
    ])
        ->assertSuccessful()
        ->assertSeeHtml('{$relationshipName}');
})->group('filament', 'relationships');" . PHP_EOL . PHP_EOL;
    }
}
