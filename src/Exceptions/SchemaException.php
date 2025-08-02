<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Exceptions;

use Exception;

/**
 * Exception thrown when schema parsing or validation fails
 */
final class SchemaException extends Exception
{
    public static function invalidYaml(string $message, ?string $filePath = null): self
    {
        $fullMessage = $filePath !== null && $filePath !== '' && $filePath !== '0'
            ? "Invalid YAML in file '{$filePath}': {$message}"
            : "Invalid YAML: {$message}";

        return new self($fullMessage);
    }

    public static function fileNotFound(string $filePath): self
    {
        return new self("Schema file not found: {$filePath}");
    }

    public static function validationFailed(array $errors): self
    {
        $message = "Schema validation failed:\n".implode("\n", $errors);

        return new self($message);
    }

    public static function missingYamlExtension(): self
    {
        return new self('YAML extension is required to parse YAML files. Please install php-yaml extension.');
    }

    public static function invalidFieldType(string $fieldName, string $type): self
    {
        return new self("Invalid field type '{$type}' for field '{$fieldName}'");
    }

    public static function invalidRelationshipType(string $relationshipName, string $type): self
    {
        return new self("Invalid relationship type '{$type}' for relationship '{$relationshipName}'");
    }
}
