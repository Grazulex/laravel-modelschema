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
        $fullMessage = ! in_array($filePath, [null, '', '0'], true)
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

    public static function invalidVersion(mixed $version): self
    {
        $described = is_scalar($version)
            ? "'".(is_bool($version) ? var_export($version, true) : (string) $version)."'"
            : 'of type '.get_debug_type($version);

        return new self(
            "Invalid schema version {$described}: expected 'major', 'major.minor' or 'major.minor.patch' with numeric segments (e.g. '1.2.0')"
        );
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
