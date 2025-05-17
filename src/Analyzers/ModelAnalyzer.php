<?php

namespace Gsferro\GenerateTestsEasy\Analyzers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class ModelAnalyzer
{
    /**
     * Analyze a model and extract its structure.
     *
     * @param string $modelClass
     * @return array
     */
    public function analyze(string $modelClass): array
    {
        // Check if the model exists
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model {$modelClass} does not exist.");
        }

        // Create a reflection class for the model
        $reflection = new ReflectionClass($modelClass);

        // Check if the class is a model
        if (!$reflection->isSubclassOf(Model::class)) {
            throw new \InvalidArgumentException("{$modelClass} is not a valid Eloquent model.");
        }

        // Create an instance of the model
        $model = new $modelClass();

        // Get basic model information
        $result = [
            'class' => $modelClass,
            'shortName' => $reflection->getShortName(),
            'namespace' => $reflection->getNamespaceName(),
            'table' => $model->getTable(),
            'primaryKey' => $model->getKeyName(),
            'incrementing' => $model->incrementing,
            'keyType' => $model->getKeyType(),
            'timestamps' => $model->timestamps,
            'fillable' => $model->getFillable(),
            'guarded' => $model->getGuarded(),
            'hidden' => $model->getHidden(),
            'visible' => $model->getVisible(),
            'casts' => $model->getCasts(),
            'dates' => $model->getDates(),
            'relationships' => $this->getRelationships($reflection, $model),
            'scopes' => $this->getScopes($reflection),
            'traits' => $this->getTraits($reflection),
            'hasFactory' => method_exists($model, 'factory'),
            'hasUuid' => $this->hasUuid($model),
            'validationRules' => $this->getValidationRules($modelClass),
        ];

        return $result;
    }

    /**
     * Get the relationships defined in the model.
     *
     * @param ReflectionClass $reflection
     * @param Model $model
     * @return array
     */
    protected function getRelationships(ReflectionClass $reflection, Model $model): array
    {
        $relationships = [];

        // Get all public methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods that are not defined in this class
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Skip common methods that are not relationships
            if (in_array($method->getName(), ['__construct', 'boot', 'booted'])) {
                continue;
            }

            try {
                // Check if the method returns a relation
                $methodName = $method->getName();
                if (method_exists($model, $methodName)) {
                    $returnType = $method->getReturnType();
                    
                    // Check if the return type is a relation
                    if ($returnType && strpos($returnType->getName(), 'Illuminate\\Database\\Eloquent\\Relations') !== false) {
                        $relationships[$methodName] = [
                            'name' => $methodName,
                            'type' => basename(str_replace('\\', '/', $returnType->getName())),
                        ];
                        continue;
                    }
                    
                    // If no return type hint, try to call the method
                    $result = $model->$methodName();
                    if (is_object($result) && $result instanceof Relation) {
                        $relationships[$methodName] = [
                            'name' => $methodName,
                            'type' => basename(str_replace('\\', '/', get_class($result))),
                            'related' => get_class($result->getRelated()),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // If we can't call the method, skip it
                continue;
            }
        }

        return $relationships;
    }

    /**
     * Get the scopes defined in the model.
     *
     * @param ReflectionClass $reflection
     * @return array
     */
    protected function getScopes(ReflectionClass $reflection): array
    {
        $scopes = [];

        // Get all public methods
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            // Skip methods that are not defined in this class
            if ($method->class !== $reflection->getName()) {
                continue;
            }

            // Check if the method is a scope
            $methodName = $method->getName();
            if (strpos($methodName, 'scope') === 0) {
                $scopeName = lcfirst(substr($methodName, 5));
                $scopes[$scopeName] = [
                    'name' => $scopeName,
                    'method' => $methodName,
                    'parameters' => $this->getMethodParameters($method),
                ];
            }
        }

        return $scopes;
    }

    /**
     * Get the traits used by the model.
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
     * Check if the model uses UUID.
     *
     * @param Model $model
     * @return bool
     */
    protected function hasUuid(Model $model): bool
    {
        // Check if the model has a getUuidColumnName method
        if (method_exists($model, 'getUuidColumnName')) {
            return true;
        }

        // Check if the model uses a UUID trait
        $traits = class_uses_recursive(get_class($model));
        foreach ($traits as $trait) {
            if (Str::contains($trait, ['Uuid', 'UUID'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the validation rules defined in the model.
     *
     * @param string $modelClass
     * @return array
     */
    protected function getValidationRules(string $modelClass): array
    {
        // Check if the model has validation rules
        if (property_exists($modelClass, 'rules')) {
            return $modelClass::$rules;
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