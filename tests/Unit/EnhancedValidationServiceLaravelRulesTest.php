<?php

declare(strict_types=1);

namespace Tests\Unit;

use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Services\Validation\EnhancedValidationService;
use Tests\TestCase;

class EnhancedValidationServiceLaravelRulesTest extends TestCase
{
    private EnhancedValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new EnhancedValidationService();
    }

    public function test_validates_exists_rule_successfully(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'email'],
            ],
        ]);

        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'user_id' => ['type' => 'foreignId', 'validation' => ['exists:users,id']],
                'title' => ['type' => 'string'],
            ],
        ]);

        $schemas = [$userSchema, $postSchema];
        $result = $this->validationService->validateLaravelRules($schemas);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['statistics']['total_rules']);
        $this->assertEquals(1, $result['statistics']['valid_rules']);
    }

    public function test_detects_invalid_exists_rule_missing_table(): void
    {
        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'user_id' => ['type' => 'foreignId', 'validation' => ['exists:non_existent_table,id']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$postSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('non-existent table', $result['errors'][0]);
    }

    public function test_detects_invalid_exists_rule_missing_column(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string'],
            ],
        ]);

        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'user_id' => ['type' => 'foreignId', 'validation' => ['exists:users,non_existent_column']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema, $postSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('non-existent column', $result['errors'][0]);
    }

    public function test_validates_unique_rule_successfully(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'email' => ['type' => 'email', 'validation' => ['unique:users,email']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_warns_about_unique_on_text_fields(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'bio' => ['type' => 'text', 'validation' => ['unique:users,bio']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('performance issues', $result['warnings'][0]);
    }

    public function test_validates_in_rule_successfully(): void
    {
        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'status' => ['type' => 'string', 'validation' => ['in:draft,published,archived']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$postSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_detects_invalid_in_rule_empty_values(): void
    {
        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'status' => ['type' => 'string', 'validation' => ['in:']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$postSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Empty values list', $result['errors'][0]);
    }

    public function test_warns_about_large_in_rule_values(): void
    {
        $values = implode(',', range(1, 60)); // 60 values
        $postSchema = ModelSchema::fromArray('Post', [
            'table' => 'posts',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'category' => ['type' => 'integer', 'validation' => ["in:{$values}"]],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$postSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Large number of values', $result['warnings'][0]);
    }

    public function test_validates_regex_rule_successfully(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'username' => ['type' => 'string', 'validation' => ['regex:/^[a-zA-Z0-9_]+$/']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_detects_invalid_regex_pattern(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'username' => ['type' => 'string', 'validation' => ['regex:/[invalid-regex']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Invalid regex pattern', $result['errors'][0]);
    }

    public function test_validates_size_constraint_rules(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'validation' => ['min:2', 'max:50']],
                'age' => ['type' => 'integer', 'validation' => ['between:18,100']],
                'phone' => ['type' => 'string', 'validation' => ['digits:10']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_detects_invalid_size_constraint_parameters(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'validation' => ['min:invalid']],
                'age' => ['type' => 'integer', 'validation' => ['between:100,18']], // max < min
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Invalid parameter', $result['errors'][0]);
        $this->assertStringContainsString('Invalid range', $result['errors'][1]);
    }

    public function test_validates_conditional_rules(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'is_company' => ['type' => 'boolean'],
                'company_name' => ['type' => 'string', 'validation' => ['required_if:is_company,true']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_detects_conditional_rule_missing_field(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'company_name' => ['type' => 'string', 'validation' => ['required_if:non_existent_field,true']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('references non-existent field', $result['errors'][0]);
    }

    public function test_handles_basic_rules_without_errors(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'validation' => ['required', 'string']],
                'email' => ['type' => 'email', 'validation' => ['required', 'email']],
                'age' => ['type' => 'integer', 'validation' => ['nullable', 'integer']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_warns_about_unknown_rules(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'name' => ['type' => 'string', 'validation' => ['unknown_custom_rule']],
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertTrue($result['is_valid']); // Unknown rules don't make it invalid
        $this->assertEmpty($result['errors']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('Unknown validation rule', $result['warnings'][0]);
    }

    public function test_provides_accurate_statistics(): void
    {
        $userSchema = ModelSchema::fromArray('User', [
            'table' => 'users',
            'fields' => [
                'id' => ['type' => 'bigInteger'],
                'email' => ['type' => 'email', 'validation' => ['unique:users,email', 'email']],
                'name' => ['type' => 'string', 'validation' => ['min:invalid']], // This will be invalid
            ],
        ]);

        $result = $this->validationService->validateLaravelRules([$userSchema]);

        $this->assertFalse($result['is_valid']);
        $this->assertEquals(3, $result['statistics']['total_rules']);
        $this->assertEquals(2, $result['statistics']['valid_rules']);
        $this->assertEquals(1, $result['statistics']['invalid_rules']);
        $this->assertEquals(3, $result['statistics']['custom_rules']); // All rules are custom
    }
}
