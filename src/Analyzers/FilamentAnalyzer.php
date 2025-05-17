<?php

namespace Gsferro\GenerateTestsEasy\Analyzers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class FilamentAnalyzer
{
    /**
     * Analyze Filament resources and panels.
     *
     * @param array $options
     * @return array
     */
    public function analyze(array $options = []): array
    {
        // Check if Filament is installed
        if (!class_exists('Filament\\Filament')) {
            return [
                'installed' => false,
                'resources' => [],
                'panels' => [],
            ];
        }

        // Find all Filament resources
        $resources = $this->findResources($options);

        // Find all Filament panels
        $panels = $this->findPanels($options);

        return [
            'installed' => true,
            'resources' => $resources,
            'panels' => $panels,
        ];
    }

    /**
     * Find all Filament resources.
     *
     * @param array $options
     * @return array
     */
    protected function findResources(array $options = []): array
    {
        $result = [];

        // Get the namespace for Filament resources
        $namespace = 'App\\Filament\\Resources';

        // Get the path for Filament resources
        $path = app_path('Filament/Resources');

        // Check if the directory exists
        if (!File::isDirectory($path)) {
            return $result;
        }

        // Get all PHP files in the directory and subdirectories
        $files = File::allFiles($path);

        foreach ($files as $file) {
            // Skip files in the Pages directory
            if (Str::contains($file->getPathname(), 'Pages')) {
                continue;
            }

            // Get the class name
            $className = $this->getClassNameFromFile($file, $namespace);

            // Skip if the class doesn't exist
            if (!class_exists($className)) {
                continue;
            }

            // Skip if the class is not a Filament resource
            if (!is_subclass_of($className, 'Filament\\Resources\\Resource')) {
                continue;
            }

            // Analyze the resource
            $result[] = $this->analyzeResource($className);
        }

        return $result;
    }

    /**
     * Find all Filament panels.
     *
     * @param array $options
     * @return array
     */
    protected function findPanels(array $options = []): array
    {
        $result = [];

        // Get the namespace for Filament panels
        $namespace = 'App\\Providers';

        // Get the path for Filament panel providers
        $path = app_path('Providers');

        // Check if the directory exists
        if (!File::isDirectory($path)) {
            return $result;
        }

        // Get all PHP files in the directory
        $files = File::files($path);

        foreach ($files as $file) {
            // Get the class name
            $className = $this->getClassNameFromFile($file, $namespace);

            // Skip if the class doesn't exist
            if (!class_exists($className)) {
                continue;
            }

            // Check if the class is a Filament panel provider
            if (!$this->isFilamentPanelProvider($className)) {
                continue;
            }

            // Analyze the panel
            $result[] = $this->analyzePanel($className);
        }

        return $result;
    }

    /**
     * Get the class name from a file.
     *
     * @param \SplFileInfo $file
     * @param string $namespace
     * @return string
     */
    protected function getClassNameFromFile(\SplFileInfo $file, string $namespace): string
    {
        // Get the relative path
        $relativePath = Str::after($file->getPathname(), app_path() . DIRECTORY_SEPARATOR);

        // Remove the file extension
        $relativePath = Str::beforeLast($relativePath, '.php');

        // Convert the path to a namespace
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);

        // Return the full class name
        return 'App\\' . $relativePath;
    }

    /**
     * Check if a class is a Filament panel provider.
     *
     * @param string $className
     * @return bool
     */
    protected function isFilamentPanelProvider(string $className): bool
    {
        // Check if the class is a service provider
        if (!is_subclass_of($className, 'Illuminate\\Support\\ServiceProvider')) {
            return false;
        }

        // Check if the class has a configurePanel method
        if (!method_exists($className, 'panel') && !method_exists($className, 'configurePanel')) {
            return false;
        }

        return true;
    }

    /**
     * Analyze a Filament resource.
     *
     * @param string $className
     * @return array
     */
    protected function analyzeResource(string $className): array
    {
        // Create a reflection class
        $reflection = new ReflectionClass($className);

        // Get the resource name
        $name = $this->getResourceName($reflection);

        // Get the model
        $model = $this->getResourceModel($className);

        // Get the form schema
        $formSchema = $this->getFormSchema($className);

        // Get the table columns
        $tableColumns = $this->getTableColumns($className);

        // Get the pages
        $pages = $this->getResourcePages($className);

        // Get the navigation group
        $navigationGroup = $this->getNavigationGroup($className);

        return [
            'name' => $name,
            'class' => $className,
            'model' => $model,
            'formSchema' => $formSchema,
            'tableColumns' => $tableColumns,
            'pages' => $pages,
            'navigationGroup' => $navigationGroup,
        ];
    }

    /**
     * Analyze a Filament panel.
     *
     * @param string $className
     * @return array
     */
    protected function analyzePanel(string $className): array
    {
        // Create a reflection class
        $reflection = new ReflectionClass($className);

        // Get the panel name
        $name = $this->getPanelName($reflection);

        // Get the panel path
        $path = $this->getPanelPath($className);

        // Get the panel resources
        $resources = $this->getPanelResources($className);

        // Get the panel pages
        $pages = $this->getPanelPages($className);

        // Get the panel widgets
        $widgets = $this->getPanelWidgets($className);

        return [
            'name' => $name,
            'class' => $className,
            'path' => $path,
            'resources' => $resources,
            'pages' => $pages,
            'widgets' => $widgets,
        ];
    }

    /**
     * Get the resource name.
     *
     * @param ReflectionClass $reflection
     * @return string
     */
    protected function getResourceName(ReflectionClass $reflection): string
    {
        // Get the short name
        $shortName = $reflection->getShortName();

        // Remove "Resource" suffix
        $name = Str::beforeLast($shortName, 'Resource');

        return $name;
    }

    /**
     * Get the resource model.
     *
     * @param string $className
     * @return string|null
     */
    protected function getResourceModel(string $className): ?string
    {
        // Check if the resource has a getModel method
        if (method_exists($className, 'getModel')) {
            return $className::getModel();
        }

        // Try to guess the model from the resource name
        $resourceName = (new ReflectionClass($className))->getShortName();
        $modelName = Str::beforeLast($resourceName, 'Resource');
        $modelClass = "App\\Models\\{$modelName}";

        if (class_exists($modelClass)) {
            return $modelClass;
        }

        return null;
    }

    /**
     * Get the form schema.
     *
     * @param string $className
     * @return array
     */
    protected function getFormSchema(string $className): array
    {
        // Check if the resource has a form method
        if (!method_exists($className, 'form')) {
            return [];
        }

        // Try to get the form schema
        try {
            $form = $className::form(new \Filament\Forms\Form(new \Filament\Forms\ComponentContainer()));
            return $this->extractFormComponents($form);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract form components.
     *
     * @param \Filament\Forms\Form $form
     * @return array
     */
    protected function extractFormComponents($form): array
    {
        $result = [];

        // Check if the form has a getComponents method
        if (!method_exists($form, 'getComponents')) {
            return $result;
        }

        // Get the components
        $components = $form->getComponents();

        foreach ($components as $component) {
            $result[] = [
                'name' => $component->getName(),
                'type' => get_class($component),
                'label' => $component->getLabel(),
                'required' => $component->isRequired(),
            ];
        }

        return $result;
    }

    /**
     * Get the table columns.
     *
     * @param string $className
     * @return array
     */
    protected function getTableColumns(string $className): array
    {
        // Check if the resource has a table method
        if (!method_exists($className, 'table')) {
            return [];
        }

        // Try to get the table columns
        try {
            $table = $className::table(new \Filament\Tables\Table());
            return $this->extractTableColumns($table);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract table columns.
     *
     * @param \Filament\Tables\Table $table
     * @return array
     */
    protected function extractTableColumns($table): array
    {
        $result = [];

        // Check if the table has a getColumns method
        if (!method_exists($table, 'getColumns')) {
            return $result;
        }

        // Get the columns
        $columns = $table->getColumns();

        foreach ($columns as $column) {
            $result[] = [
                'name' => $column->getName(),
                'type' => get_class($column),
                'label' => $column->getLabel(),
                'sortable' => $column->isSortable(),
                'searchable' => $column->isSearchable(),
            ];
        }

        return $result;
    }

    /**
     * Get the resource pages.
     *
     * @param string $className
     * @return array
     */
    protected function getResourcePages(string $className): array
    {
        // Check if the resource has a getPages method
        if (!method_exists($className, 'getPages')) {
            return [];
        }

        // Get the pages
        $pages = $className::getPages();

        $result = [];
        foreach ($pages as $name => $pageClass) {
            $result[$name] = [
                'name' => $name,
                'class' => $pageClass,
            ];
        }

        return $result;
    }

    /**
     * Get the navigation group.
     *
     * @param string $className
     * @return string|null
     */
    protected function getNavigationGroup(string $className): ?string
    {
        // Check if the resource has a getNavigationGroup method
        if (method_exists($className, 'getNavigationGroup')) {
            return $className::getNavigationGroup();
        }

        return null;
    }

    /**
     * Get the panel name.
     *
     * @param ReflectionClass $reflection
     * @return string
     */
    protected function getPanelName(ReflectionClass $reflection): string
    {
        // Get the short name
        $shortName = $reflection->getShortName();

        // Remove "PanelProvider" suffix
        $name = Str::beforeLast($shortName, 'PanelProvider');

        return $name;
    }

    /**
     * Get the panel path.
     *
     * @param string $className
     * @return string|null
     */
    protected function getPanelPath(string $className): ?string
    {
        // Create an instance of the panel provider
        $provider = app()->make($className);

        // Check if the provider has a panel method
        if (method_exists($provider, 'panel')) {
            $panel = $provider->panel(new \Filament\Panel());
            return $panel->getPath();
        }

        // Check if the provider has a configurePanel method
        if (method_exists($provider, 'configurePanel')) {
            $panel = new \Filament\Panel();
            $provider->configurePanel($panel);
            return $panel->getPath();
        }

        return null;
    }

    /**
     * Get the panel resources.
     *
     * @param string $className
     * @return array
     */
    protected function getPanelResources(string $className): array
    {
        // Create an instance of the panel provider
        $provider = app()->make($className);

        // Check if the provider has a panel method
        if (method_exists($provider, 'panel')) {
            $panel = $provider->panel(new \Filament\Panel());
            return $panel->getResources();
        }

        // Check if the provider has a configurePanel method
        if (method_exists($provider, 'configurePanel')) {
            $panel = new \Filament\Panel();
            $provider->configurePanel($panel);
            return $panel->getResources();
        }

        return [];
    }

    /**
     * Get the panel pages.
     *
     * @param string $className
     * @return array
     */
    protected function getPanelPages(string $className): array
    {
        // Create an instance of the panel provider
        $provider = app()->make($className);

        // Check if the provider has a panel method
        if (method_exists($provider, 'panel')) {
            $panel = $provider->panel(new \Filament\Panel());
            return $panel->getPages();
        }

        // Check if the provider has a configurePanel method
        if (method_exists($provider, 'configurePanel')) {
            $panel = new \Filament\Panel();
            $provider->configurePanel($panel);
            return $panel->getPages();
        }

        return [];
    }

    /**
     * Get the panel widgets.
     *
     * @param string $className
     * @return array
     */
    protected function getPanelWidgets(string $className): array
    {
        // Create an instance of the panel provider
        $provider = app()->make($className);

        // Check if the provider has a panel method
        if (method_exists($provider, 'panel')) {
            $panel = $provider->panel(new \Filament\Panel());
            return $panel->getWidgets();
        }

        // Check if the provider has a configurePanel method
        if (method_exists($provider, 'configurePanel')) {
            $panel = new \Filament\Panel();
            $provider->configurePanel($panel);
            return $panel->getWidgets();
        }

        return [];
    }
}