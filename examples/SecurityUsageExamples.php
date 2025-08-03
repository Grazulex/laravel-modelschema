<?php

declare(strict_types=1);

/**
 * Laravel ModelSchema - Security Usage Examples
 *
 * This file demonstrates how to use the security validation features
 * to protect your application from common security vulnerabilities.
 */

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Services\SchemaService;
use Grazulex\LaravelModelschema\Services\SecurityValidationService;

// Example 1: Basic Security Validation
function demonstrateBasicSecurity()
{
    $securityService = new SecurityValidationService();

    echo "=== Basic Security Validation ===\n";

    // Validate class names
    $classNames = ['User', 'class', 'User<script>', '__MagicUser', 'User123'];

    foreach ($classNames as $className) {
        $result = $securityService->validateClassName($className);
        echo "Class '{$className}': ";

        if (empty($result['errors'])) {
            echo '✅ Valid';
            if (! empty($result['warnings'])) {
                echo ' (⚠️  '.implode(', ', $result['warnings']).')';
            }
        } else {
            echo '❌ Invalid - '.implode(', ', $result['errors']);
        }
        echo "\n";
    }

    echo "\n";
}

// Example 2: Content Sanitization
function demonstrateContentSanitization()
{
    $securityService = new SecurityValidationService();

    echo "=== Content Sanitization ===\n";

    $testContents = [
        'Safe content with {{placeholder}}',
        '<?php eval($_GET["cmd"]); ?>',
        "'; DROP TABLE users; --",
        'Normal text with\x00control chars',
        'file_get_contents("/etc/passwd")',
        '$variable and $$dangerous_variable',
    ];

    foreach ($testContents as $content) {
        $result = $securityService->sanitizeContent($content);
        echo 'Content: '.mb_substr($content, 0, 30)."...\n";
        echo '  Safe: '.($result['is_safe'] ? '✅' : '❌')."\n";

        if (! empty($result['errors'])) {
            echo '  Errors: '.implode(', ', $result['errors'])."\n";
        }

        if (! empty($result['warnings'])) {
            echo '  Warnings: '.implode(', ', $result['warnings'])."\n";
        }

        echo '  Sanitized: '.mb_substr($result['sanitized'], 0, 30)."...\n\n";
    }
}

// Example 3: Stub Path Validation
function demonstrateStubPathValidation()
{
    $securityService = new SecurityValidationService();

    echo "=== Stub Path Validation ===\n";

    $stubPaths = [
        'basic.schema.stub',
        '../../../etc/passwd',
        'user<script>.stub',
        'valid-file.yaml',
        'malicious.php',
        str_repeat('a', 300).'.stub', // Too long
    ];

    foreach ($stubPaths as $path) {
        $result = $securityService->validateStubPath($path);
        echo "Path '{$path}': ";

        if (empty($result['errors'])) {
            echo '✅ Valid';
            if (! empty($result['warnings'])) {
                echo ' (⚠️  '.implode(', ', $result['warnings']).')';
            }
        } else {
            echo '❌ Invalid - '.implode(', ', $result['errors']);
        }
        echo "\n";
    }

    echo "\n";
}

// Example 4: Comprehensive Stub Audit
function demonstrateStubAudit()
{
    $securityService = new SecurityValidationService();

    echo "=== Comprehensive Stub Audit ===\n";

    $stubContents = [
        // Safe stub
        'core:
  model: {{MODEL_NAME}}
  table: {{TABLE_NAME}}
  fields:
    name:
      type: string
      nullable: false',

        // Dangerous stub
        'core:
  model: {{MODEL_NAME}}
  # TODO: Fix security issue
  dangerous: "<?php eval($_GET[\"cmd\"]); ?>"
  url: "https://evil.com/api"',

        // Suspicious stub
        'core:
  model: {{ $model_name }}
  complex_regex: "/.*[.*+?^${}()|[\]\\\\].*/"',
    ];

    foreach ($stubContents as $index => $content) {
        $result = $securityService->auditStubContent($content);
        echo 'Stub #'.($index + 1).":\n";
        echo "  Security Score: {$result['security_score']}/100\n";
        echo '  Is Secure: '.($result['is_secure'] ? '✅' : '❌')."\n";

        if (! empty($result['errors'])) {
            echo "  Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "    - {$error}\n";
            }
        }

        if (! empty($result['warnings'])) {
            echo "  Warnings:\n";
            foreach ($result['warnings'] as $warning) {
                echo "    - {$warning}\n";
            }
        }

        if (! empty($result['recommendations'])) {
            echo "  Recommendations:\n";
            foreach ($result['recommendations'] as $recommendation) {
                echo "    - {$recommendation}\n";
            }
        }

        echo "\n";
    }
}

// Example 5: Schema Security Validation
function demonstrateSchemaSecurityValidation()
{
    $securityService = new SecurityValidationService();

    echo "=== Schema Security Validation ===\n";

    $schemas = [
        // Safe schema
        [
            'core' => [
                'model' => 'User',
                'namespace' => 'App\\Models',
                'table' => 'users',
                'fields' => [
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'email'],
                ],
            ],
        ],

        // Dangerous schema
        [
            'core' => [
                'model' => 'class', // Reserved word
                'namespace' => 'App\\Models<script>',
                'fields' => [
                    'evil' => ['default' => '<?php eval($_GET["cmd"]); ?>'],
                ],
            ],
        ],
    ];

    foreach ($schemas as $index => $schema) {
        $result = $securityService->validateSchemaContent($schema);
        echo 'Schema #'.($index + 1).":\n";
        echo '  Is Secure: '.($result['is_secure'] ? '✅' : '❌')."\n";
        echo "  Security Score: {$result['security_score']}/100\n";

        if (! empty($result['errors'])) {
            echo "  Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "    - {$error}\n";
            }
        }

        if (! empty($result['warnings'])) {
            echo "  Warnings:\n";
            foreach ($result['warnings'] as $warning) {
                echo "    - {$warning}\n";
            }
        }

        echo "\n";
    }
}

// Example 6: Integration with SchemaService
function demonstrateSchemaServiceIntegration()
{
    $schemaService = new SchemaService();

    echo "=== SchemaService Security Integration ===\n";

    // Validate naming
    $namingResult = $schemaService->validateSecureNaming('UserModel', 'App\\Models');
    echo 'Naming validation: '.($namingResult['is_valid'] ? '✅ Valid' : '❌ Invalid')."\n";

    if (! empty($namingResult['errors'])) {
        echo '  Errors: '.implode(', ', $namingResult['errors'])."\n";
    }

    // Validate stub path
    $stubResult = $schemaService->validateStubPath('basic.schema.stub');
    echo 'Stub path validation: '.(empty($stubResult['errors']) ? '✅ Valid' : '❌ Invalid')."\n";

    // Example of safe YAML content
    $safeYaml = 'core:
  model: User
  table: users
  namespace: App\\Models
  fields:
    name:
      type: string
      nullable: false
    email:
      type: email
      unique: true';

    try {
        $errors = $schemaService->validateCoreSchema($safeYaml);
        echo 'Schema validation: '.(empty($errors) ? '✅ Valid' : '❌ Invalid')."\n";

        if (! empty($errors)) {
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }
    } catch (Exception $e) {
        echo "Schema validation failed: {$e->getMessage()}\n";
    }

    echo "\n";
}

// Example 7: Error Handling and Logging
function demonstrateErrorHandling()
{
    echo "=== Security Error Handling ===\n";

    $schemaService = new SchemaService();

    // Example of handling security errors
    $dangerousYaml = 'core:
  model: "<?php eval($_GET[\"cmd\"]); ?>"
  table: users';

    try {
        $errors = $schemaService->validateCoreSchema($dangerousYaml);

        if (! empty($errors)) {
            $securityErrors = array_filter($errors, fn ($error) => str_contains($error, 'Security issue'));

            if (! empty($securityErrors)) {
                echo "❌ Security validation failed:\n";
                foreach ($securityErrors as $error) {
                    echo "  - {$error}\n";
                }

                // In a real application, you would:
                // 1. Log the security incident
                // 2. Block the request
                // 3. Notify administrators
                // 4. Return a safe error message to user

                throw new SchemaException('Schema contains security vulnerabilities');
            }
        }
    } catch (SchemaException $e) {
        echo "Caught security exception: {$e->getMessage()}\n";
    } catch (Exception $e) {
        echo "Unexpected error: {$e->getMessage()}\n";
    }

    echo "\n";
}

// Example 8: Configuration and Best Practices
function demonstrateBestPractices()
{
    echo "=== Security Best Practices ===\n";

    $securityService = new SecurityValidationService();

    // Best practice: Always validate user input
    function validateUserInput(string $userInput, SecurityValidationService $securityService): bool
    {
        $result = $securityService->sanitizeContent($userInput);

        if (! $result['is_safe']) {
            // Log security incident
            error_log('Security violation detected: '.implode(', ', $result['errors']));

            return false;
        }

        if (! empty($result['warnings'])) {
            // Log warnings for review
            error_log('Security warnings: '.implode(', ', $result['warnings']));
        }

        return true;
    }

    // Best practice: Validate file paths before use
    function validateFilePath(string $filePath, SecurityValidationService $securityService): bool
    {
        $result = $securityService->validateStubPath($filePath);

        if (! empty($result['errors'])) {
            error_log('Invalid file path: '.implode(', ', $result['errors']));

            return false;
        }

        return true;
    }

    // Best practice: Regular security audits
    function performSecurityAudit(array $stubFiles, SecurityValidationService $securityService): array
    {
        $auditResults = [];

        foreach ($stubFiles as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $audit = $securityService->auditStubContent($content);

                $auditResults[$file] = [
                    'security_score' => $audit['security_score'],
                    'is_secure' => $audit['is_secure'],
                    'issues' => array_merge($audit['errors'], $audit['warnings']),
                ];

                if ($audit['security_score'] < 80) {
                    echo "⚠️  File '{$file}' has low security score: {$audit['security_score']}/100\n";
                }
            }
        }

        return $auditResults;
    }

    // Example usage
    $testInputs = ['safe input', '<?php evil(); ?>'];
    foreach ($testInputs as $input) {
        $isSafe = validateUserInput($input, $securityService);
        echo "Input validation for '{$input}': ".($isSafe ? '✅' : '❌')."\n";
    }

    echo "\n";
}

// Run all examples
function runAllSecurityExamples()
{
    echo "Laravel ModelSchema - Security Usage Examples\n";
    echo "=============================================\n\n";

    demonstrateBasicSecurity();
    demonstrateContentSanitization();
    demonstrateStubPathValidation();
    demonstrateStubAudit();
    demonstrateSchemaSecurityValidation();
    demonstrateSchemaServiceIntegration();
    demonstrateErrorHandling();
    demonstrateBestPractices();

    echo "All security examples completed! ✅\n";
}

// Uncomment to run examples
// runAllSecurityExamples();
