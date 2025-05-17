<?php

namespace Gsferro\GenerateTestsEasy\Analyzers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ControllerAnalyzer
{
    /**
     * Analyze a controller and extract its structure.
     *
     * @param string $controllerClass
     * @return array
     */
    public function analyze(string $controllerClass): array
    {
        // Check if the controller exists
        if (!class_exists($controllerClass)) {
            throw new \InvalidArgumentException("Controller {$controllerClass} does not exist.");
        }

        // Create a reflection class for the controller
        $reflection = new ReflectionClass($controllerClass);

        // Check if the class is a controller
        if (!$reflection->isSubclassOf(Controller::class)) {
            throw new \InvalidArgumentException("{$controllerClass} is not a valid controller.");
        }

        // Create an instance of the controller
        $controller = app()->make($controllerClass);

        // Get basic controller information
        $result = [
            'class' => $controllerClass,
            'shortName' => $reflection->getShortName(),
            'namespace' => $reflection->getNamespaceName(),
            'methods' => $this->getMethods($reflection),
            'traits' => $this->getTraits($reflection),
            'routes' => $this->getRoutes($controllerClass),
            'model' => $this->getAssociatedModel($controller),
            'isApi' => $this->isApiController($controller),
            'isResourceful' => $this->isResourcefulController($reflection),
            'middleware' => $this->getMiddleware($controller),
        ];

        return $result;
    }

    /**
     * Get the methods defined in the controller.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        // Get all public methods
        $reflectionMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($reflectionMethods as $method) {
            // Skip methods that are not defined in this class
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Skip constructor
            if ($method->getName() === '__construct') {
                continue;
            }

            $methods[$method->getName()] = [
                'name' => $method->getName(),
                'parameters' => $this->getMethodParameters($method),
                'returnType' => $method->getReturnType() ? $method->getReturnType()->getName() : null,
                'docComment' => $method->getDocComment(),
            ];
        }

        return $methods;
    }

    /**
     * Get the traits used by the controller.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getTraits(ReflectionClass $reflection): array
    {
        $traits = [];
        $allTraits = [];

        // Get traits used by this class
        $classTraits = $reflection->getTraits();
        foreach ($classTraits as $trait) {
            $allTraits[] = $trait->getName();
            // Also get traits used by the trait
            $traitTraits = $trait->getTraits();
            foreach ($traitTraits as $traitTrait) {
                $allTraits[] = $traitTrait->getName();
            }
        }

        // Get traits used by parent classes
        $parent = $reflection->getParentClass();
        while ($parent) {
            $parentTraits = $parent->getTraits();
            foreach ($parentTraits as $trait) {
                $allTraits[] = $trait->getName();
            }
            $parent = $parent->getParentClass();
        }

        // Remove duplicates and format
        $allTraits = array_unique($allTraits);
        foreach ($allTraits as $trait) {
            $shortName = basename(str_replace('\\', '/', $trait));
            $traits[$shortName] = [
                'name' => $shortName,
                'class' => $trait,
            ];
        }

        return $traits;
    }

    /**
     * Get the routes associated with the controller.
     *
     * @param string $controllerClass
     * @return array
     */
    protected function getRoutes(string $controllerClass): array
    {
        $routes = [];
        $allRoutes = Route::getRoutes();

        foreach ($allRoutes as $route) {
            $action = $route->getAction();
            
            // Check if the route uses this controller
            if (isset($action['controller']) && strpos($action['controller'], $controllerClass) === 0) {
                // Extract the method name from the action
                $methodName = substr($action['controller'], strlen($controllerClass) + 1);
                
                $routes[] = [
                    'uri' => $route->uri(),
                    'methods' => $route->methods(),
                    'name' => $route->getName(),
                    'action' => $methodName,
                    'middleware' => $route->middleware(),
                ];
            }
        }

        return $routes;
    }

    /**
     * Get the model associated with the controller.
     *
     * @param Controller $controller
     * @return array|null
     */
    protected function getAssociatedModel(Controller $controller): ?array
    {
        // Check if the controller has a model property
        if (property_exists($controller, 'model')) {
            $model = $controller->model;
            
            if (is_object($model)) {
                $modelClass = get_class($model);
                return [
                    'class' => $modelClass,
                    'shortName' => basename(str_replace('\\', '/', $modelClass)),
                    'instance' => $model,
                ];
            }
        }

        // Try to guess the model from the controller name
        $controllerClass = get_class($controller);
        $controllerName = basename(str_replace('\\', '/', $controllerClass));
        
        // Remove "Controller" suffix
        $modelName = Str::singular(str_replace('Controller', '', $controllerName));
        
        // Check if the model exists
        $modelClass = "App\\Models\\{$modelName}";
        if (class_exists($modelClass)) {
            return [
                'class' => $modelClass,
                'shortName' => $modelName,
                'instance' => new $modelClass(),
            ];
        }

        return null;
    }

    /**
     * Check if the controller is an API controller.
     *
     * @param Controller $controller
     * @return bool
     */
    protected function isApiController(Controller $controller): bool
    {
        // Check if the controller has an isAPI property
        if (property_exists($controller, 'isAPI')) {
            return (bool) $controller->isAPI;
        }

        // Check if the controller class name contains "Api"
        $controllerClass = get_class($controller);
        if (Str::contains($controllerClass, 'Api')) {
            return true;
        }

        // Check if the controller namespace contains "Api"
        if (Str::contains($controllerClass, '\\Api\\')) {
            return true;
        }

        // Check if the controller uses an API trait
        $traits = class_uses_recursive($controller);
        foreach ($traits as $trait) {
            if (Str::contains($trait, ['Api', 'API'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the controller is a resourceful controller.
     *
     * @param ReflectionClass $reflection
     * @return bool
     */
    protected function isResourcefulController(ReflectionClass $reflection): bool
    {
        // Check if the controller has the standard resource methods
        $resourceMethods = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $methodNames = array_map(function ($method) {
            return $method->getName();
        }, $methods);
        
        // If the controller has at least 4 of the 7 resource methods, consider it resourceful
        $count = count(array_intersect($resourceMethods, $methodNames));
        
        return $count >= 4;
    }

    /**
     * Get the middleware used by the controller.
     *
     * @param Controller $controller
     * @return array
     */
    protected function getMiddleware(Controller $controller): array
    {
        // Check if the controller has a middleware method
        if (method_exists($controller, 'middleware')) {
            return $controller->middleware();
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
}