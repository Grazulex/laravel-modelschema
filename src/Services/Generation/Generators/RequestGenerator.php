<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Form Requests Data
 */
class RequestGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'requests';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml'];
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        return match ($format) {
            'json' => $this->generateJson($schema, $options),
            'yaml' => $this->generateYaml($schema, $options),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    protected function generateJson(ModelSchema $schema, array $options): string
    {
        // Check if we want enhanced structure or simple structure
        $isEnhanced = $options['enhanced'] ?? true;

        if ($isEnhanced) {
            // Enhanced structure with simplified keys
            $requestsData = [
                'store' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        } else {
            // Standard structure with full key names
            $requestsData = [
                'store_request' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update_request' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        }

        // Retourne la structure prête à être insérée : "requests": { ... }
        return $this->toJsonFormat(['requests' => $requestsData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Check if we want enhanced structure or simple structure
        $isEnhanced = $options['enhanced'] ?? true;

        if ($isEnhanced) {
            // Enhanced structure with simplified keys
            $requestsData = [
                'store' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        } else {
            // Standard structure with full key names
            $requestsData = [
                'store_request' => [
                    'name' => "Store{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getStoreValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
                'update_request' => [
                    'name' => "Update{$schema->name}Request",
                    'namespace' => ($options['requests_namespace'] ?? 'App\\Http\\Requests'),
                    'validation_rules' => $this->getUpdateValidationRules($schema),
                    'messages' => $this->getValidationMessages($schema),
                ],
            ];
        }

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['requests' => $requestsData], 4, 2);
    }

    protected function getStoreValidationRules(ModelSchema $schema): array
    {
        $rules = [];

        foreach ($schema->getFillableFields() as $field) {
            $fieldRules = $field->getValidationRules();
            if (! empty($fieldRules)) {
                $rules[$field->name] = $fieldRules;
            }
        }

        return $rules;
    }

    protected function getUpdateValidationRules(ModelSchema $schema): array
    {
        $rules = $this->getStoreValidationRules($schema);

        // Pour les updates, rendre les champs optionnels
        foreach ($rules as $field => $fieldRules) {
            if (is_array($fieldRules)) {
                // Remplacer 'required' par 'sometimes'
                $rules[$field] = array_map(function ($rule) {
                    return $rule === 'required' ? 'sometimes' : $rule;
                }, $fieldRules);
            } else {
                $rules[$field] = str_replace('required', 'sometimes', $fieldRules);
            }
        }

        return $rules;
    }

    protected function getValidationMessages(ModelSchema $schema): array
    {
        // Messages de validation par défaut
        return [
            'required' => 'The :attribute field is required.',
            'email' => 'The :attribute must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'max' => 'The :attribute may not be greater than :max characters.',
        ];
    }
}
