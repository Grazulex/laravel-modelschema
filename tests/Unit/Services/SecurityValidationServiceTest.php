<?php

declare(strict_types=1);

use Grazulex\LaravelModelschema\Services\SecurityValidationService;

describe('SecurityValidationService', function () {
    beforeEach(function () {
        $this->securityService = new SecurityValidationService();
    });

    describe('validateClassName', function () {
        it('validates correct class names', function () {
            $result = $this->securityService->validateClassName('User');
            expect($result['errors'])->toBeEmpty();
            expect($result['warnings'])->toBeEmpty();
        });

        it('validates class names with numbers', function () {
            $result = $this->securityService->validateClassName('User123');
            expect($result['errors'])->toBeEmpty();
            expect($result['warnings'])->toContain('Class name ending with numbers might be problematic');
        });

        it('rejects empty class names', function () {
            $result = $this->securityService->validateClassName('');
            expect($result['errors'])->toContain('Class name cannot be empty');
        });

        it('rejects class names with dangerous characters', function () {
            $result = $this->securityService->validateClassName('User<script>');
            expect($result['errors'])->toContain("Class name contains dangerous character: '<'");
        });

        it('rejects PHP reserved words', function () {
            $result = $this->securityService->validateClassName('class');
            expect($result['errors'])->toContain("Class name 'class' is a reserved PHP keyword");
        });

        it('warns about magic method prefixes', function () {
            $result = $this->securityService->validateClassName('__User');
            expect($result['warnings'])->toContain('Class name starting with double underscore is reserved for PHP magic methods');
        });

        it('rejects too long class names', function () {
            $longName = str_repeat('A', 101);
            $result = $this->securityService->validateClassName($longName);
            expect($result['errors'])->toContain('Class name is too long (max 100 characters)');
        });

        it('rejects invalid PHP identifiers', function () {
            $result = $this->securityService->validateClassName('123User');
            expect($result['errors'])->toContain('Class name must be a valid PHP identifier');
        });
    });

    describe('validateNamespace', function () {
        it('validates correct namespaces', function () {
            $result = $this->securityService->validateNamespace('App\\Models');
            expect($result['errors'])->toBeEmpty();
        });

        it('allows empty namespaces', function () {
            $result = $this->securityService->validateNamespace('');
            expect($result['errors'])->toBeEmpty();
            expect($result['warnings'])->toBeEmpty();
        });

        it('rejects namespaces with dangerous characters', function () {
            $result = $this->securityService->validateNamespace('App\\Models<script>');
            expect($result['errors'])->toContain("Namespace contains dangerous character: '<'");
        });

        it('rejects excessive backslashes', function () {
            $result = $this->securityService->validateNamespace('App\\\\\\Models');
            expect($result['errors'])->toContain('Namespace contains excessive backslashes');
        });

        it('validates individual namespace parts', function () {
            $result = $this->securityService->validateNamespace('App\\class');
            expect($result['errors'])->toContain("Invalid namespace part 'class': Class name 'class' is a reserved PHP keyword");
        });

        it('rejects too long namespaces', function () {
            $longNamespace = str_repeat('VeryLongNamespacePart\\', 20).'Models';
            $result = $this->securityService->validateNamespace($longNamespace);
            expect($result['errors'])->toContain('Namespace is too long (max 255 characters)');
        });
    });

    describe('sanitizeContent', function () {
        it('allows safe content', function () {
            $content = 'This is safe content with {{placeholder}}';
            $result = $this->securityService->sanitizeContent($content);

            expect($result['errors'])->toBeEmpty();
            expect($result['is_safe'])->toBeTrue();
            expect($result['sanitized'])->toBe($content);
        });

        it('detects PHP injection attempts', function () {
            $content = '<?php eval($_GET["cmd"]); ?>';
            $result = $this->securityService->sanitizeContent($content);

            expect($result['errors'])->toContain('Content contains dangerous PHP pattern: /\<\?php/');
            expect($result['errors'])->toContain('Content contains dangerous PHP pattern: /eval\s*\(/');
            expect($result['is_safe'])->toBeFalse();
        });

        it('detects SQL injection attempts', function () {
            $content = "'; DROP TABLE users; --";
            $result = $this->securityService->sanitizeContent($content);

            expect($result['warnings'])->toContain('Content contains potential SQL injection pattern: /;\s*(DROP|DELETE|UPDATE|INSERT|CREATE|ALTER)\s+/i');
        });

        it('detects UNION SELECT attacks', function () {
            $content = "' UNION SELECT password FROM users";
            $result = $this->securityService->sanitizeContent($content);

            expect($result['warnings'])->toContain('Content contains potential SQL injection pattern: /UNION\s+SELECT/i');
        });

        it('warns about control characters', function () {
            $content = "Hello\x00World";
            $result = $this->securityService->sanitizeContent($content);

            expect($result['warnings'])->toContain('Content contains control characters');
            expect($result['sanitized'])->toBe('HelloWorld');
        });

        it('warns about escape sequences', function () {
            $content = 'Hello\\rWorld\\tTest\\"Quote';
            $result = $this->securityService->sanitizeContent($content);

            expect($result['warnings'])->toContain('Content contains escape sequences');
        });
        it('detects file system functions', function () {
            $content = 'file_get_contents("/etc/passwd")';
            $result = $this->securityService->sanitizeContent($content);

            expect($result['errors'])->toContain('Content contains dangerous PHP pattern: /file_get_contents\s*\(/');
        });

        it('detects variable variables', function () {
            $content = '$$variable_name';
            $result = $this->securityService->sanitizeContent($content);

            expect($result['errors'])->toContain('Content contains dangerous PHP pattern: /\$\$/');
        });
    });

    describe('validateStubPath', function () {
        it('validates correct stub paths', function () {
            $result = $this->securityService->validateStubPath('basic.schema.stub');
            expect($result['errors'])->toBeEmpty();
        });

        it('rejects empty paths', function () {
            $result = $this->securityService->validateStubPath('');
            expect($result['errors'])->toContain('Stub path cannot be empty');
        });

        it('detects path traversal attempts', function () {
            $result = $this->securityService->validateStubPath('../../../etc/passwd');
            expect($result['errors'])->toContain('Stub path contains path traversal attempts');
        });

        it('rejects dangerous characters', function () {
            $result = $this->securityService->validateStubPath('file<script>.stub');
            expect($result['errors'])->toContain("Stub path contains dangerous character: '<'");
        });

        it('validates file extensions', function () {
            $result = $this->securityService->validateStubPath('malicious.php');
            expect($result['errors'])->toContain("Stub file extension 'php' is not allowed. Allowed: stub, yaml, yml, json");
        });

        it('warns about long paths', function () {
            $longPath = str_repeat('a', 261).'.stub';
            $result = $this->securityService->validateStubPath($longPath);
            expect($result['warnings'])->toContain('Stub path is very long and might cause issues on some systems');
        });

        it('allows valid extensions', function () {
            foreach (['stub', 'yaml', 'yml', 'json'] as $ext) {
                $result = $this->securityService->validateStubPath("file.{$ext}");
                expect($result['errors'])->toBeEmpty();
            }
        });
    });

    describe('auditStubContent', function () {
        it('passes secure stub content', function () {
            $content = 'core:\n  model: {{MODEL_NAME}}\n  table: {{TABLE_NAME}}';
            $result = $this->securityService->auditStubContent($content);

            expect($result['is_secure'])->toBeTrue();
            expect($result['security_score'])->toBe(100);
            expect($result['recommendations'])->toContain('Stub content appears to be secure');
        });

        it('detects variable-like placeholders', function () {
            $content = 'core:\n  model: {{ $model_name }}';
            $result = $this->securityService->auditStubContent($content);

            expect($result['warnings'])->toContain('Stub contains variable-like placeholders that might be vulnerable');
        });

        it('detects TODO comments', function () {
            $content = '/* TODO: Fix security issue */';
            $result = $this->securityService->auditStubContent($content);

            expect($result['warnings'])->toContain('Stub contains TODO/FIXME comments that might indicate incomplete security measures');
        });

        it('detects external URLs', function () {
            $content = 'api_url: https://evil.com/api';
            $result = $this->securityService->auditStubContent($content);

            expect($result['warnings'])->toContain('Stub contains external URLs that might pose security risks');
        });

        it('calculates security scores correctly', function () {
            $content = '<?php eval($_GET["cmd"]); ?>';
            $result = $this->securityService->auditStubContent($content);

            expect($result['security_score'])->toBeLessThan(100);
            expect($result['is_secure'])->toBeFalse();
        });

        it('recommends splitting large stubs', function () {
            $content = str_repeat('line: content\n', 1000);
            $result = $this->securityService->auditStubContent($content);

            expect($result['recommendations'])->toContain('Consider splitting large stubs into smaller, more manageable pieces');
        });
    });

    describe('validateConfigValue', function () {
        it('validates safe configuration values', function () {
            $result = $this->securityService->validateConfigValue('cache_enabled', true);
            expect($result['errors'])->toBeEmpty();
            expect($result['warnings'])->toBeEmpty();
        });

        it('warns about sensitive configuration keys', function () {
            $result = $this->securityService->validateConfigValue('database_password', 'secret123');
            expect($result['warnings'])->toContain("Configuration key 'database_password' appears to contain sensitive information");
        });

        it('validates string configuration values', function () {
            $result = $this->securityService->validateConfigValue('dangerous_config', '<?php eval($_GET["cmd"]); ?>');
            expect($result['errors'])->toContain('Content contains dangerous PHP pattern: /\<\?php/');
        });

        it('handles non-string values', function () {
            $result = $this->securityService->validateConfigValue('numeric_config', 123);
            expect($result['errors'])->toBeEmpty();
        });

        it('detects sensitive keys case-insensitively', function () {
            foreach (['DATABASE', 'Password', 'SECRET', 'Key', 'TOKEN', 'api'] as $key) {
                $result = $this->securityService->validateConfigValue($key, 'value');
                expect($result['warnings'])->not->toBeEmpty();
            }
        });
    });

    describe('validateSchemaContent', function () {
        it('validates secure schema content', function () {
            $schema = [
                'core' => [
                    'model' => 'User',
                    'namespace' => 'App\\Models',
                    'table' => 'users',
                    'fields' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'email'],
                    ],
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);
            expect($result['is_secure'])->toBeTrue();
            expect($result['errors'])->toBeEmpty();
        });

        it('detects dangerous content in schema values', function () {
            $schema = [
                'core' => [
                    'model' => 'User',
                    'fields' => [
                        'evil' => ['default' => '<?php eval($_GET["cmd"]); ?>'],
                    ],
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);
            expect($result['is_secure'])->toBeFalse();

            $hasPhpError = false;
            foreach ($result['errors'] as $error) {
                if (str_contains($error, 'core.fields.evil.default') && str_contains($error, 'dangerous PHP pattern')) {
                    $hasPhpError = true;
                    break;
                }
            }
            expect($hasPhpError)->toBeTrue();
        });

        it('validates model names in schema', function () {
            $schema = [
                'core' => [
                    'model' => 'class',  // Reserved word
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);
            expect($result['errors'])->toContain("Class name 'class' is a reserved PHP keyword");
        });

        it('validates namespaces in schema', function () {
            $schema = [
                'core' => [
                    'namespace' => 'App\\Models<script>',
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);
            expect($result['errors'])->toContain("Namespace contains dangerous character: '<'");
        });

        it('handles nested arrays recursively', function () {
            $schema = [
                'core' => [
                    'model' => 'User',
                    'relations' => [
                        'posts' => [
                            'type' => 'hasMany',
                            'model' => '<?php eval($_GET["cmd"]); ?>',
                        ],
                    ],
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);

            $hasPhpError = false;
            foreach ($result['errors'] as $error) {
                if (str_contains($error, 'core.relations.posts.model') && str_contains($error, 'dangerous PHP pattern')) {
                    $hasPhpError = true;
                    break;
                }
            }
            expect($hasPhpError)->toBeTrue();
        });

        it('validates array keys for security issues', function () {
            $schema = [
                'core' => [
                    '<?php eval($_GET["cmd"]); ?>' => 'value',
                ],
            ];

            $result = $this->securityService->validateSchemaContent($schema);

            $hasKeyError = false;
            foreach ($result['errors'] as $error) {
                if (str_contains($error, 'schema key') && str_contains($error, 'dangerous PHP pattern')) {
                    $hasKeyError = true;
                    break;
                }
            }
            expect($hasKeyError)->toBeTrue();
        });
    });
});
