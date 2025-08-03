<?php

declare(strict_types=1);

namespace Tests\Feature;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use Tests\TestCase;

class LaravelRulesValidationIntegrationTest extends TestCase
{
    private EnhancedValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new EnhancedValidationService();
    }

    public function test_comprehensive_laravel_rules_validation_with_real_world_scenario(): void
    {
        // E-commerce scenario with multiple models and complex validation rules
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'username' => [
                    'type' => 'string',
                    'validation' => [
                        'unique:users,username',
                        'regex:/^[a-zA-Z0-9_]+$/',
                        'min:3',
                        'max:20',
                    ],
                ],
                'email' => [
                    'type' => 'email',
                    'validation' => ['unique:users,email'],
                ],
                'age' => [
                    'type' => 'integer',
                    'validation' => ['between:13,120'],
                ],
                'account_type' => [
                    'type' => 'string',
                    'validation' => ['in:individual,business,premium'],
                ],
                'company_name' => [
                    'type' => 'string',
                    'validation' => ['required_if:account_type,business'],
                ],
            ],
        ]);

        $categorySchema = ModelSchema::fromArray('Category', [
            'table' => 'categories',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'validation' => ['unique:categories,name']],
                'parent_id' => [
                    'type' => 'foreignId',
                    'nullable' => true,
                    'validation' => ['exists:categories,id'],
                ],
            ],
        ]);

        $productSchema = ModelSchema::fromArray('Product', [
            'table' => 'products',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
                'sku' => [
                    'type' => 'string',
                    'validation' => [
                        'unique:products,sku',
                        'regex:/^[A-Z0-9]{6,12}$/',
                    ],
                ],
                'price' => [
                    'type' => 'decimal',
                    'validation' => ['min:0.01', 'max:999999.99'],
                ],
                'category_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:categories,id'],
                ],
                'user_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:users,id'],
                ],
                'status' => [
                    'type' => 'string',
                    'validation' => ['in:draft,active,inactive,discontinued'],
                ],
                'tags' => [
                    'type' => 'json',
                    'validation' => ['json'],
                ],
            ],
        ]);

        $schemas = [$userSchema, $categorySchema, $productSchema];
        $result = $this->validationService->validateLaravelRules($schemas);

        // All validation rules should be valid
        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);

        // Check statistics
        $this->assertGreaterThan(15, $result['statistics']['total_rules']);
        $this->assertEquals($result['statistics']['total_rules'], $result['statistics']['valid_rules']);
        $this->assertEquals(0, $result['statistics']['invalid_rules']);

        // Verify specific rule types are detected
        $ruleTypes = array_column($result['validated_rules'], 'rule_type');
        $this->assertContains('exists', $ruleTypes);
        $this->assertContains('unique', $ruleTypes);
        $this->assertContains('in', $ruleTypes);
        $this->assertContains('regex', $ruleTypes);
        $this->assertContains('size_constraint', $ruleTypes);
        $this->assertContains('conditional', $ruleTypes);
        $this->assertContains('basic', $ruleTypes);
    }

    public function test_detects_multiple_validation_errors_in_complex_scenario(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'email' => ['type' => 'email'],
            ],
        ]);

        $brokenSchema = ModelSchema::fromArray('BrokenModel', [
            'table' => 'broken_models',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'user_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:non_existent_table,id'], // Error 1: table doesn't exist
                ],
                'category_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:users,non_existent_column'], // Error 2: column doesn't exist
                ],
                'status' => [
                    'type' => 'string',
                    'validation' => ['in:'], // Error 3: empty in rule
                ],
                'pattern' => [
                    'type' => 'string',
                    'validation' => ['regex:/[invalid-regex'], // Error 4: invalid regex
                ],
                'age' => [
                    'type' => 'integer',
                    'validation' => ['between:100,18'], // Error 5: invalid range
                ],
                'dependent_field' => [
                    'type' => 'string',
                    'validation' => ['required_if:non_existent_field,value'], // Error 6: missing field reference
                ],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema, $brokenSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertGreaterThanOrEqual(6, count($result['errors']));

        // Check that each type of error is detected
        $errorMessages = implode(' ', $result['errors']);
        $this->assertStringContainsString('non-existent table', $errorMessages);
        $this->assertStringContainsString('non-existent column', $errorMessages);
        $this->assertStringContainsString('Empty values list', $errorMessages);
        $this->assertStringContainsString('Invalid regex pattern', $errorMessages);
        $this->assertStringContainsString('Invalid range', $errorMessages);
        $this->assertStringContainsString('references non-existent field', $errorMessages);
    }

    public function test_provides_warnings_for_potential_issues(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'bio' => [
                    'type' => 'text',
                    'validation' => ['unique:users,bio'], // Warning: unique on text field
                ],
                'category' => [
                    'type' => 'integer',
                    'validation' => ['in:'.implode(',', range(1, 60))], // Warning: too many values
                ],
                'unknown_field' => [
                    'type' => 'string',
                    'validation' => ['some_unknown_rule'], // Warning: unknown rule
                ],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']); // Warnings don't make it invalid
        $this->assertCount(3, $result['warnings']);

        $warningMessages = implode(' ', $result['warnings']);
        $this->assertStringContainsString('performance issues', $warningMessages);
        $this->assertStringContainsString('Large number of values', $warningMessages);
        $this->assertStringContainsString('Unknown validation rule', $warningMessages);
    }

    public function test_integration_with_enhanced_validation_service(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'email' => [
                    'type' => 'email',
                    'validation' => ['unique:users,email'],
                ],
                'name' => ['type' => 'string'],
            ],
        ]);

        // Test that Laravel rules validation is integrated in the comprehensive schema validation
        $schemaValidation = $this->validationService->validateSchema($userSchema);

        $this->assertTrue($schemaValidation['is_valid']);
        $this->assertArrayHasKey('laravel_rules_validation', $schemaValidation);
        $this->assertTrue($schemaValidation['laravel_rules_validation']['is_valid']);
        $this->assertArrayHasKey('statistics', $schemaValidation['laravel_rules_validation']);

        // Test comprehensive report
        $report = $this->validationService->generateComprehensiveReport($userSchema);

        $this->assertArrayHasKey('laravel_rules_validation', $report);
        $this->assertTrue($report['laravel_rules_validation']['is_valid']);
    }

    public function test_validates_cross_table_references_correctly(): void
    {
        // Complex scenario with multiple cross-references
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'email' => ['type' => 'email'],
            ],
        ]);

        $roleSchema = ModelSchema::fromArray('Role', [
            'table' => 'roles',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
            ],
        ]);

        $userRoleSchema = ModelSchema::fromArray('UserRole', [
            'table' => 'user_roles',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'user_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:users,id'],
                ],
                'role_id' => [
                    'type' => 'foreignId',
                    'validation' => ['exists:roles,id'],
                ],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([
            $userSchema,
            $roleSchema,
            $userRoleSchema,
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);

        // Check that cross-references are properly validated
        $existsRules = array_filter($result['validated_rules'], fn ($rule) => $rule['rule_type'] === 'exists');
        $this->assertCount(2, $existsRules);
    }
}
