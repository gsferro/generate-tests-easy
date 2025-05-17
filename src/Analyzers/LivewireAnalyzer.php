<?php

namespace Gsferro\GenerateTestsEasy\Analyzers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class LivewireAnalyzer
{
    /**
     * Analyze Livewire components.
     *
     * @param array $options
     * @return array
     */
    public function analyze(array $options = []): array
    {
        // Check if Livewire is installed
        if (!class_exists('Livewire\\Component')) {
            return [
                'installed' => false,
                'components' => [],
            ];
        }

        // Find all Livewire components
        $components = $this->findComponents($options);

        return [
            'installed' => true,
            'components' => $components,
        ];
    }

    /**
     * Find all Livewire components.
     *
     * @param array $options
     * @return array
     */
    protected function findComponents(array $options = []): array
    {
        $result = [];

        // Check if Livewire v3 is installed
        $isLivewireV3 = class_exists('Livewire\\Livewire');

        // Get the namespace for Livewire components
        $namespace = $isLivewireV3 ? 'App\\Livewire' : 'App\\Http\\Livewire';

        // Get the path for Livewire components
        $path = $isLivewireV3 ? app_path('Livewire') : app_path('Http/Livewire');

        // Check if the directory exists
        if (!File::isDirectory($path)) {
            return $result;
        }

        // Get all PHP files in the directory and subdirectories
        $files = File::allFiles($path);

        foreach ($files as $file) {
            // Get the class name
            $className = $this->getClassNameFromFile($file, $namespace);

            // Skip if the class doesn't exist
            if (!class_exists($className)) {
                continue;
            }

            // Skip if the class is not a Livewire component
            if (!is_subclass_of($className, 'Livewire\\Component')) {
                continue;
            }

            // Analyze the component
            $result[] = $this->analyzeComponent($className);
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

        // Remove 'Http/Livewire' or 'Livewire' from the path
        $relativePath = Str::after($relativePath, 'Http\\Livewire\\');
        $relativePath = Str::after($relativePath, 'Livewire\\');

        // Return the full class name
        return $namespace . '\\' . $relativePath;
    }

    /**
     * Analyze a Livewire component.
     *
     * @param string $className
     * @return array
     */
    protected function analyzeComponent(string $className): array
    {
        // Create a reflection class
        $reflection = new ReflectionClass($className);

        // Get the component name
        $name = $this->getComponentName($reflection);

        // Get the properties
        $properties = $this->getProperties($reflection);

        // Get the methods
        $methods = $this->getMethods($reflection);

        // Get the events
        $events = $this->getEvents($reflection);

        // Get the listeners
        $listeners = $this->getListeners($reflection);

        // Get the rules
        $rules = $this->getRules($reflection);

        // Get the validationAttributes
        $validationAttributes = $this->getValidationAttributes($reflection);

        // Get the queryString
        $queryString = $this->getQueryString($reflection);

        return [
            'name' => $name,
            'class' => $className,
            'properties' => $properties,
            'methods' => $methods,
            'events' => $events,
            'listeners' => $listeners,
            'rules' => $rules,
            'validationAttributes' => $validationAttributes,
            'queryString' => $queryString,
        ];
    }

    /**
     * Get the component name.
     *
     * @param ReflectionClass $reflection
     * @return string
     */
    protected function getComponentName(ReflectionClass $reflection): string
    {
        // Check if the component has a getName method
        if (method_exists($reflection->getName(), 'getName')) {
            return $reflection->getName()::getName();
        }

        // Get the short name
        $shortName = $reflection->getShortName();

        // Convert to kebab case
        return Str::kebab($shortName);
    }

    /**
     * Get the properties of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getProperties(ReflectionClass $reflection): array
    {
        $result = [];

        // Get all public properties
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Skip properties that are not defined in this class
            if ($property->class !== $reflection->getName()) {
                continue;
            }

            // Skip static properties
            if ($property->isStatic()) {
                continue;
            }

            $result[$property->getName()] = [
                'name' => $property->getName(),
                'type' => $property->getType() ? $property->getType()->getName() : null,
                'docComment' => $property->getDocComment(),
            ];
        }

        return $result;
    }

    /**
     * Get the methods of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getMethods(ReflectionClass $reflection): array
    {
        $result = [];

        // Get all public methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods that are not defined in this class
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Skip methods that are inherited from the parent class
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            // Skip magic methods
            if (Str::startsWith($method->getName(), '__')) {
                continue;
            }

            $result[$method->getName()] = [
                'name' => $method->getName(),
                'parameters' => $this->getMethodParameters($method),
                'returnType' => $method->getReturnType() ? $method->getReturnType()->getName() : null,
                'docComment' => $method->getDocComment(),
            ];
        }

        return $result;
    }

    /**
     * Get the events of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getEvents(ReflectionClass $reflection): array
    {
        $result = [];

        // Create an instance of the component
        $component = $this->createComponentInstance($reflection);

        // Check if the component has an emit method
        if (!method_exists($component, 'emit')) {
            return $result;
        }

        // Get all methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods that are not defined in this class
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Get the method body
            $body = $this->getMethodBody($method);

            // Check if the method emits an event
            if (preg_match_all('/\$this->emit\([\'"]([^\'"]+)[\'"]/', $body, $matches)) {
                foreach ($matches[1] as $event) {
                    $result[$event] = [
                        'name' => $event,
                        'method' => $method->getName(),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Get the listeners of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getListeners(ReflectionClass $reflection): array
    {
        $result = [];

        // Create an instance of the component
        $component = $this->createComponentInstance($reflection);

        // Check if the component has a getListeners method
        if (!method_exists($component, 'getListeners')) {
            return $result;
        }

        // Get the listeners
        $listeners = $component->getListeners();

        foreach ($listeners as $event => $listener) {
            $result[$event] = [
                'event' => $event,
                'listener' => $listener,
            ];
        }

        return $result;
    }

    /**
     * Get the rules of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getRules(ReflectionClass $reflection): array
    {
        // Check if the component has a rules property
        if (property_exists($reflection->getName(), 'rules')) {
            return $reflection->getDefaultProperties()['rules'] ?? [];
        }

        return [];
    }

    /**
     * Get the validation attributes of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getValidationAttributes(ReflectionClass $reflection): array
    {
        // Check if the component has a validationAttributes property
        if (property_exists($reflection->getName(), 'validationAttributes')) {
            return $reflection->getDefaultProperties()['validationAttributes'] ?? [];
        }

        return [];
    }

    /**
     * Get the query string of a component.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getQueryString(ReflectionClass $reflection): array
    {
        // Check if the component has a queryString property
        if (property_exists($reflection->getName(), 'queryString')) {
            return $reflection->getDefaultProperties()['queryString'] ?? [];
        }

        return [];
    }

    /**
     * Get the parameters of a method.
     *
     * @param ReflectionMethod $method
     * @return array
     */
    protected function getMethodParameters(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = [
                'name' => $parameter->getName(),
                'type' => $parameter->getType() ? $parameter->getType()->getName() : null,
                'isOptional' => $parameter->isOptional(),
                'hasDefaultValue' => $parameter->isDefaultValueAvailable(),
                'defaultValue' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            ];
        }

        return $parameters;
    }

    /**
     * Get the body of a method.
     *
     * @param ReflectionMethod $method
     * @return string
     */
    protected function getMethodBody(ReflectionMethod $method): string
    {
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        $lines = file($filename);
        $body = '';

        for ($i = $startLine; $i < $endLine; $i++) {
            $body .= $lines[$i];
        }

        return $body;
    }

    /**
     * Create an instance of a component.
     *
     * @param ReflectionClass $reflection
     * @return object|null
     */
    protected function createComponentInstance(ReflectionClass $reflection): ?object
    {
        try {
            return app()->make($reflection->getName());
        } catch (\Exception $e) {
            return null;
        }
    }
}