# Security Features Documentation

## Overview

Laravel ModelSchema implements comprehensive security validation to protect against code injection, path traversal attacks, and other security vulnerabilities. The security system validates all user inputs including schema content, class names, namespaces, and file paths.

## SecurityValidationService

The `SecurityValidationService` is the core component responsible for security validation throughout the package.

### Features

- **Code Injection Prevention**: Detects and blocks PHP, SQL, and script injection attempts
- **Safe Naming Validation**: Ensures class and namespace names follow secure PHP standards
- **Path Traversal Protection**: Validates file paths to prevent directory traversal attacks
- **Content Sanitization**: Automatically sanitizes dangerous content while preserving functionality
- **Security Scoring**: Provides quantitative security assessment with recommendations

## Security Validations

### 1. Class Name Validation

```php
$result = $securityService->validateClassName('User');
// Returns: ['errors' => [], 'warnings' => []]

$result = $securityService->validateClassName('class'); // Reserved word
// Returns: ['errors' => ["Class name 'class' is a reserved PHP keyword"], 'warnings' => []]
```

**Checks performed:**
- Valid PHP identifier format
- No dangerous characters (`<`, `>`, `?`, `:`, `*`, `|`, `"`, `'`, `` ` ``)
- Not a PHP reserved word
- Length restrictions (max 100 characters)
- Warning for magic method prefixes (`__`)
- Warning for names ending with numbers

### 2. Namespace Validation

```php
$result = $securityService->validateNamespace('App\\Models');
// Returns: ['errors' => [], 'warnings' => []]

$result = $securityService->validateNamespace('App\\Models<script>');
// Returns: ['errors' => ["Namespace contains dangerous character: '<'"], 'warnings' => []]
```

**Checks performed:**
- Valid namespace format with proper backslash usage
- Each namespace part validates as a valid class name
- No excessive backslashes
- Length restrictions (max 255 characters)

### 3. Content Sanitization

```php
$result = $securityService->sanitizeContent('Safe content {{placeholder}}');
// Returns: ['errors' => [], 'warnings' => [], 'sanitized' => 'Safe content {{placeholder}}', 'is_safe' => true]

$result = $securityService->sanitizeContent('<?php eval($_GET["cmd"]); ?>');
// Returns: ['errors' => ['Content contains dangerous PHP pattern: ...'], 'is_safe' => false]
```

**Detects:**
- **PHP Injection**: `<?php`, `eval()`, `exec()`, `system()`, `file_get_contents()`, etc.
- **SQL Injection**: `DROP TABLE`, `UNION SELECT`, `' OR '`, SQL comments
- **Variable Variables**: `$$variable`, `${variable}`
- **Control Characters**: Non-printable characters
- **Escape Sequences**: `\n`, `\r`, `\t`, `\'`, `\"`

### 4. Stub Path Validation

```php
$result = $securityService->validateStubPath('basic.schema.stub');
// Returns: ['errors' => [], 'warnings' => []]

$result = $securityService->validateStubPath('../../../etc/passwd');
// Returns: ['errors' => ['Stub path contains path traversal attempts'], 'warnings' => []]
```

**Checks performed:**
- Path traversal detection (`../`, `..\\`)
- Dangerous characters in file paths
- Allowed file extensions (`.stub`, `.yaml`, `.yml`, `.json`)
- File existence and readability
- Path length restrictions

### 5. Stub Content Audit

```php
$result = $securityService->auditStubContent($stubContent);
// Returns comprehensive security analysis with score
```

**Audit includes:**
- All content sanitization checks
- Suspicious placeholder patterns
- TODO/FIXME comments indicating incomplete security
- External URL detection
- Complex regex pattern analysis
- Security score calculation (0-100)
- Specific recommendations

## Integration with SchemaService

The security validation is automatically integrated into the main `SchemaService`:

### Automatic Schema Validation

```php
$errors = $schemaService->validateCoreSchema($yamlContent);
// Includes automatic security validation
```

### Manual Security Validation

```php
// Validate stub path
$result = $schemaService->validateStubPath('/path/to/file.stub');

// Audit stub content
$audit = $schemaService->auditStubContent($stubContent);

// Validate naming
$naming = $schemaService->validateSecureNaming('UserModel', 'App\\Models');
```

## Security Configuration

Security validation can be configured through the package configuration:

```php
// config/modelschema.php
return [
    'security' => [
        'strict_validation' => true,
        'allowed_stub_extensions' => ['stub', 'yaml', 'yml', 'json'],
        'max_class_name_length' => 100,
        'max_namespace_length' => 255,
        'security_score_threshold' => 80,
    ],
];
```

## Security Best Practices

### 1. Input Validation
Always validate user inputs before processing:

```php
// Validate schema content before parsing
$securityResult = $schemaService->validateSchemaContent($userInput);
if (!$securityResult['is_secure']) {
    throw new SchemaException('Security validation failed: ' . implode(', ', $securityResult['errors']));
}
```

### 2. Stub File Security
When working with stub files:

```php
// Validate stub path
$pathResult = $schemaService->validateStubPath($stubPath);
if (!empty($pathResult['errors'])) {
    throw new SchemaException('Invalid stub path: ' . implode(', ', $pathResult['errors']));
}

// Audit stub content
$auditResult = $schemaService->auditStubContent($stubContent);
if ($auditResult['security_score'] < 80) {
    $logger->warning('Low security score for stub', $auditResult);
}
```

### 3. Naming Conventions
Ensure secure naming for generated classes:

```php
$namingResult = $schemaService->validateSecureNaming($className, $namespace);
if (!$namingResult['is_valid']) {
    throw new SchemaException('Insecure naming: ' . implode(', ', $namingResult['errors']));
}
```

## Security Scoring

The security system provides quantitative scoring:

- **100**: Perfect security, no issues detected
- **80-99**: Good security with minor warnings
- **60-79**: Moderate security concerns
- **40-59**: Significant security issues
- **0-39**: Critical security vulnerabilities

## Error Handling

Security errors are categorized as:

- **Errors**: Critical security issues that must be fixed
- **Warnings**: Potential security concerns that should be reviewed
- **Recommendations**: Best practices for improved security

## Logging

All security validations are automatically logged through the `LoggingService`:

```php
// Security validation events are logged with context
$this->logger->logValidation('security', $isSecure, $errors, $warnings, $context);

// Security warnings are logged separately
$this->logger->logWarning('Security warning detected', $context, $recommendation);
```

## Testing Security

The package includes comprehensive security tests:

```bash
# Run security-specific tests
./vendor/bin/pest tests/Unit/Services/SecurityValidationServiceTest.php

# Run all security-related tests
./vendor/bin/pest --filter="Security"
```

## Common Security Issues

### 1. Code Injection
- **Issue**: User input contains PHP code
- **Detection**: PHP opening tags, dangerous functions
- **Solution**: Content sanitization and validation

### 2. Path Traversal
- **Issue**: File paths contain `../` sequences
- **Detection**: Path analysis and normalization
- **Solution**: Path validation and restriction

### 3. Reserved Keywords
- **Issue**: Class names use PHP reserved words
- **Detection**: Keyword blacklist checking
- **Solution**: Name validation and suggestions

### 4. Dangerous Characters
- **Issue**: Names contain special characters
- **Detection**: Character pattern matching
- **Solution**: Character filtering and validation

## Advanced Security Features

### Custom Validation Rules

You can extend the security validation with custom rules:

```php
class CustomSecurityValidator extends SecurityValidationService
{
    protected function validateCustomPatterns(string $content): array
    {
        // Add your custom security patterns
        return parent::sanitizeContent($content);
    }
}
```

### Security Plugins

The security system can be extended with plugins for specific validation needs:

```php
// Register custom security validators
$securityService->registerValidator('custom', new CustomValidator());
```

## Migration and Upgrades

When upgrading to security-enabled versions:

1. **Audit existing schemas** for security issues
2. **Update stub files** to meet security standards
3. **Review generated code** for potential vulnerabilities
4. **Update configuration** to enable strict security mode

## Performance Considerations

Security validation adds minimal overhead:

- **Schema validation**: ~1-5ms additional time
- **Content sanitization**: ~0.1-1ms per content block
- **Path validation**: ~0.1ms per path
- **Logging**: Configurable verbosity levels

The security system is optimized for production use with minimal performance impact.
