<?php

namespace SystemTestFramework\Reviewer;

use Illuminate\Foundation\Http\FormRequest;
use ReflectionClass;
use ReflectionMethod;

class FormRequestInspector
{
    /**
     * Inspect a Controller action string (e.g. "App\Http\Controllers\UserController@store")
     * and extract any injected FormRequest validation rules and edge case test data.
     */
    public function inspectAction(string $actionString): ?array
    {
        if (!str_contains($actionString, '@')) {
            return null;
        }

        [$controllerClass, $methodName] = explode('@', $actionString);

        if (!class_exists($controllerClass)) {
            return null;
        }

        try {
            $reflector = new ReflectionClass($controllerClass);
            if (!$reflector->hasMethod($methodName)) {
                return null;
            }

            $method = $reflector->getMethod($methodName);
            $formRequestClass = $this->findFormRequestParameter($method);

            if (!$formRequestClass) {
                return null;
            }

            return $this->extractRulesAndEdgeCases($formRequestClass);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function findFormRequestParameter(ReflectionMethod $method): ?string
    {
        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                if (is_subclass_of($className, FormRequest::class)) {
                    return $className;
                }
            }
        }
        return null;
    }

    protected function extractRulesAndEdgeCases(string $formRequestClass): array
    {
        $rules = [];
        try {
            /** @var FormRequest $instance */
            $instance = new $formRequestClass();
            if (method_exists($instance, 'rules')) {
                $rules = (array) $instance->rules();
            }
        } catch (\Throwable $e) {
            // If constructor requires container bindings, fall back to reflection
        }

        $edgeCases = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleArray = is_array($fieldRules) ? $fieldRules : explode('|', (string) $fieldRules);
            
            $isRequired = false;
            $maxLength = null;
            $isEmail = false;
            $isNumeric = false;

            foreach ($ruleArray as $r) {
                $r = trim((string) $r);
                if ($r === 'required') {
                    $isRequired = true;
                } elseif (str_starts_with($r, 'max:')) {
                    $maxLength = (int) substr($r, 4);
                } elseif ($r === 'email') {
                    $isEmail = true;
                } elseif (in_array($r, ['numeric', 'integer'])) {
                    $isNumeric = true;
                }
            }

            $edgeCases[$field] = [
                'required' => $isRequired,
                'max_length' => $maxLength,
                'is_email' => $isEmail,
                'is_numeric' => $isNumeric,
                'expected_empty_error' => $isRequired ? "The {$field} field is required." : null,
                'boundary_payload' => $maxLength ? str_repeat('A', $maxLength + 1) : null,
                'invalid_email_payload' => $isEmail ? 'invalid-email-address' : null,
            ];
        }

        return [
            'class' => $formRequestClass,
            'rules' => $rules,
            'edge_cases' => $edgeCases,
        ];
    }
}

