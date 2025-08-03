<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use InvalidArgumentException;

/**
 * Generator for Laravel Test Data (Feature and Unit Tests)
 */
class TestGenerator extends AbstractGenerator
{
    public function getGeneratorName(): string
    {
        return 'test';
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
        // Structure que l'app parent peut insérer dans son JSON
        $testData = [
            'feature_tests' => $this->getFeatureTests($schema, $options),
            'unit_tests' => $this->getUnitTests($schema, $options),
            'test_traits' => $this->getTestTraits($schema),
            'factories_needed' => $this->getFactoriesNeeded($schema),
        ];

        // Retourne la structure prête à être insérée : "tests": { ... }
        return $this->toJsonFormat(['tests' => $testData]);
    }

    protected function generateYaml(ModelSchema $schema, array $options): string
    {
        // Structure que l'app parent peut insérer dans son YAML
        $testData = [
            'feature_tests' => $this->getFeatureTests($schema, $options),
            'unit_tests' => $this->getUnitTests($schema, $options),
            'test_traits' => $this->getTestTraits($schema),
            'factories_needed' => $this->getFactoriesNeeded($schema),
        ];

        // Retourne la structure YAML prête à être insérée
        return \Symfony\Component\Yaml\Yaml::dump(['tests' => $testData], 4, 2);
    }

    protected function getFeatureTests(ModelSchema $schema, array $options): array
    {
        $tests = [];
        $modelName = $schema->name;
        $routePrefix = $options['route_prefix'] ?? mb_strtolower($schema->table);
        $hasApi = $options['api_routes'] ?? true;
        $hasWeb = $options['web_routes'] ?? true;

        if ($hasApi) {
            $tests[] = [
                'name' => "{$modelName}ApiTest",
                'namespace' => $options['feature_namespace'] ?? 'Tests\\Feature\\Api',
                'type' => 'api_crud',
                'route_prefix' => $routePrefix,
                'model_class' => $schema->getModelClass(),
                'factory_class' => "{$modelName}Factory",
                'methods' => [
                    'test_can_create_'.mb_strtolower($modelName),
                    'test_can_read_'.mb_strtolower($modelName),
                    'test_can_update_'.mb_strtolower($modelName),
                    'test_can_delete_'.mb_strtolower($modelName),
                    'test_can_list_'.mb_strtolower($schema->table),
                    'test_validates_required_fields',
                    'test_handles_not_found',
                ],
                'fields_to_test' => $this->getTestableFields($schema),
                'relationships_to_test' => $this->getTestableRelationships($schema),
            ];
        }

        if ($hasWeb) {
            $tests[] = [
                'name' => "{$modelName}WebTest",
                'namespace' => $options['feature_namespace'] ?? 'Tests\\Feature\\Web',
                'type' => 'web_crud',
                'route_prefix' => $routePrefix,
                'model_class' => $schema->getModelClass(),
                'factory_class' => "{$modelName}Factory",
                'methods' => [
                    'test_can_view_index_page',
                    'test_can_view_show_page',
                    'test_can_view_create_page',
                    'test_can_view_edit_page',
                    'test_can_store_'.mb_strtolower($modelName),
                    'test_can_update_'.mb_strtolower($modelName),
                    'test_can_delete_'.mb_strtolower($modelName),
                    'test_redirects_on_validation_errors',
                ],
                'fields_to_test' => $this->getTestableFields($schema),
                'middleware' => $options['middleware'] ?? [],
            ];
        }

        return $tests;
    }

    protected function getUnitTests(ModelSchema $schema, array $options): array
    {
        $tests = [];
        $modelName = $schema->name;

        // Test du modèle
        $tests[] = [
            'name' => "{$modelName}Test",
            'namespace' => $options['unit_namespace'] ?? 'Tests\\Unit\\Models',
            'type' => 'model',
            'model_class' => $schema->getModelClass(),
            'factory_class' => "{$modelName}Factory",
            'methods' => [
                'test_fillable_attributes',
                'test_casts_attributes',
                'test_dates_attributes',
                'test_hidden_attributes',
                'test_database_has_expected_columns',
                ...$this->getRelationshipTestMethods($schema),
                ...$this->getValidationTestMethods($schema),
            ],
            'fillable' => array_map(fn ($field) => $field->name, $schema->getFillableFields()),
            'casts' => $this->getModelCasts($schema),
            'dates' => $this->getDateFields($schema),
            'relationships' => $this->getTestableRelationships($schema),
        ];

        // Tests des relations
        foreach ($schema->relationships as $relationship) {
            if ($this->shouldTestRelationship($relationship)) {
                $tests[] = [
                    'name' => "{$modelName}{$relationship->name}RelationshipTest",
                    'namespace' => $options['unit_namespace'] ?? 'Tests\\Unit\\Relationships',
                    'type' => 'relationship',
                    'model_class' => $schema->getModelClass(),
                    'relationship_name' => $relationship->name,
                    'relationship_type' => $relationship->type,
                    'related_model' => $relationship->model,
                    'methods' => [
                        "test_{$relationship->name}_relationship_exists",
                        "test_{$relationship->name}_returns_correct_type",
                        "test_can_access_{$relationship->name}_via_relationship",
                    ],
                ];
            }
        }

        return $tests;
    }

    protected function getTestTraits(ModelSchema $schema): array
    {
        $traits = ['RefreshDatabase'];

        if ($schema->hasTimestamps()) {
            $traits[] = 'WithTimestamps';
        }

        if ($this->hasSoftDeletes($schema)) {
            $traits[] = 'WithSoftDeletes';
        }

        if ($this->hasFileUploads($schema)) {
            $traits[] = 'WithFileUploads';
        }

        return $traits;
    }

    protected function getFactoriesNeeded(ModelSchema $schema): array
    {
        $factories = [
            $schema->name => "{$schema->name}Factory",
        ];

        // Ajouter les factories des relations
        foreach ($schema->relationships as $relationship) {
            if ($relationship->model && $relationship->model !== $schema->name) {
                $factories[$relationship->model] = "{$relationship->model}Factory";
            }
        }

        return $factories;
    }

    protected function getTestableFields(ModelSchema $schema): array
    {
        $fields = [];

        foreach ($schema->getFillableFields() as $field) {
            $fields[] = [
                'name' => $field->name,
                'type' => $field->type,
                'required' => ! $field->nullable, // nullable false = required true
                'unique' => $field->unique,
                'validation_rules' => $this->getFieldValidationRules($field),
                'test_values' => $this->getTestValues($field),
            ];
        }

        return $fields;
    }

    protected function getTestableRelationships(ModelSchema $schema): array
    {
        $relationships = [];

        foreach ($schema->relationships as $relationship) {
            $relationships[] = [
                'name' => $relationship->name,
                'type' => $relationship->type,
                'model' => $relationship->model,
                'foreign_key' => $relationship->foreignKey ?? null,
                'local_key' => $relationship->localKey ?? null,
                'pivot_table' => $relationship->pivotTable ?? null,
            ];
        }

        return $relationships;
    }

    protected function getRelationshipTestMethods(ModelSchema $schema): array
    {
        $methods = [];

        foreach ($schema->relationships as $relationship) {
            $methods[] = "test_has_{$relationship->name}_relationship";
        }

        return $methods;
    }

    protected function getValidationTestMethods(ModelSchema $schema): array
    {
        $methods = [];

        foreach ($schema->getFillableFields() as $field) {
            if (! $field->nullable) { // nullable false = required true
                $methods[] = "test_requires_{$field->name}";
            }

            if ($field->unique) {
                $methods[] = "test_{$field->name}_must_be_unique";
            }

            if ($field->type === 'email') {
                $methods[] = "test_{$field->name}_must_be_valid_email";
            }
        }

        return $methods;
    }

    protected function getModelCasts(ModelSchema $schema): array
    {
        $casts = [];

        foreach ($schema->fields as $field) {
            switch ($field->type) {
                case 'boolean':
                    $casts[$field->name] = 'boolean';
                    break;
                case 'json':
                    $casts[$field->name] = 'array';
                    break;
                case 'date':
                    $casts[$field->name] = 'date';
                    break;
                case 'timestamp':
                case 'dateTime':
                    $casts[$field->name] = 'datetime';
                    break;
                case 'decimal':
                    $casts[$field->name] = 'decimal:2';
                    break;
            }
        }

        return $casts;
    }

    protected function getDateFields(ModelSchema $schema): array
    {
        $dates = [];

        foreach ($schema->fields as $field) {
            if (in_array($field->type, ['date', 'timestamp', 'dateTime'])) {
                $dates[] = $field->name;
            }
        }

        return $dates;
    }

    protected function shouldTestRelationship($relationship): bool
    {
        return ! empty($relationship->model) && $relationship->model !== 'morphTo';
    }

    protected function hasSoftDeletes(ModelSchema $schema): bool
    {
        foreach ($schema->fields as $field) {
            if ($field->name === 'deleted_at') {
                return true;
            }
        }

        return false;
    }

    protected function hasFileUploads(ModelSchema $schema): bool
    {
        foreach ($schema->fields as $field) {
            if (str_contains($field->name, 'file') || str_contains($field->name, 'image') || str_contains($field->name, 'photo')) {
                return true;
            }
        }

        return false;
    }

    protected function getFieldValidationRules($field): array
    {
        $rules = [];

        if (! $field->nullable) { // nullable false = required true
            $rules[] = 'required';
        }

        if ($field->unique) {
            $rules[] = 'unique';
        }

        switch ($field->type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'integer':
            case 'bigInteger':
                $rules[] = 'integer';
                break;
            case 'decimal':
            case 'float':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'json':
                $rules[] = 'array';
                break;
        }

        if ($field->length && in_array($field->type, ['string', 'text'])) {
            $rules[] = "max:{$field->length}";
        }

        return $rules;
    }

    protected function getTestValues($field): array
    {
        return [
            'valid' => $this->getValidTestValue($field),
            'invalid' => $this->getInvalidTestValues($field),
        ];
    }

    protected function getValidTestValue($field): mixed
    {
        return match ($field->type) {
            'string' => 'Test '.ucfirst($field->name),
            'email' => 'test@example.com',
            'text' => 'This is a test description for '.$field->name,
            'integer', 'bigInteger' => 42,
            'decimal', 'float' => 123.45,
            'boolean' => true,
            'date' => '2023-01-01',
            'timestamp', 'dateTime' => '2023-01-01 12:00:00',
            'json' => ['key' => 'value'],
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            default => 'test_value'
        };
    }

    protected function getInvalidTestValues($field): array
    {
        $invalid = [];

        if (! $field->nullable) { // nullable false = required true
            $invalid[] = ['value' => null, 'error' => 'required'];
            $invalid[] = ['value' => '', 'error' => 'required'];
        }

        switch ($field->type) {
            case 'email':
                $invalid[] = ['value' => 'invalid-email', 'error' => 'email'];
                $invalid[] = ['value' => '@example.com', 'error' => 'email'];
                break;
            case 'integer':
            case 'bigInteger':
                $invalid[] = ['value' => 'not-a-number', 'error' => 'integer'];
                $invalid[] = ['value' => 123.45, 'error' => 'integer'];
                break;
            case 'decimal':
            case 'float':
                $invalid[] = ['value' => 'not-a-number', 'error' => 'numeric'];
                break;
            case 'boolean':
                $invalid[] = ['value' => 'not-boolean', 'error' => 'boolean'];
                break;
            case 'date':
                $invalid[] = ['value' => 'not-a-date', 'error' => 'date'];
                $invalid[] = ['value' => '2023-13-01', 'error' => 'date'];
                break;
            case 'json':
                $invalid[] = ['value' => 'not-json', 'error' => 'array'];
                break;
        }

        if ($field->length && in_array($field->type, ['string', 'text'])) {
            $invalid[] = ['value' => str_repeat('a', $field->length + 1), 'error' => 'max'];
        }

        return $invalid;
    }
}
