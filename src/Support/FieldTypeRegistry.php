<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Support;

use Grazulex\LaravelModelschema\Contracts\FieldTypeInterface;
use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\FieldTypes\BigIntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\BinaryFieldType;
use Grazulex\LaravelModelschema\FieldTypes\BooleanFieldType;
use Grazulex\LaravelModelschema\FieldTypes\DateFieldType;
use Grazulex\LaravelModelschema\FieldTypes\DateTimeFieldType;
use Grazulex\LaravelModelschema\FieldTypes\DecimalFieldType;
use Grazulex\LaravelModelschema\FieldTypes\DoubleFieldType;
use Grazulex\LaravelModelschema\FieldTypes\EmailFieldType;
use Grazulex\LaravelModelschema\FieldTypes\EnumFieldType;
use Grazulex\LaravelModelschema\FieldTypes\FloatFieldType;
use Grazulex\LaravelModelschema\FieldTypes\ForeignIdFieldType;
use Grazulex\LaravelModelschema\FieldTypes\IntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\JsonFieldType;
use Grazulex\LaravelModelschema\FieldTypes\LongTextFieldType;
use Grazulex\LaravelModelschema\FieldTypes\MediumIntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\MediumTextFieldType;
use Grazulex\LaravelModelschema\FieldTypes\MorphsFieldType;
use Grazulex\LaravelModelschema\FieldTypes\SetFieldType;
use Grazulex\LaravelModelschema\FieldTypes\SmallIntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\StringFieldType;
use Grazulex\LaravelModelschema\FieldTypes\TextFieldType;
use Grazulex\LaravelModelschema\FieldTypes\TimeFieldType;
use Grazulex\LaravelModelschema\FieldTypes\TimestampFieldType;
use Grazulex\LaravelModelschema\FieldTypes\TinyIntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\UnsignedBigIntegerFieldType;
use Grazulex\LaravelModelschema\FieldTypes\UuidFieldType;
use InvalidArgumentException;

/**
 * Registry for managing field types
 */
class FieldTypeRegistry
{
    /**
     * Registered field types
     */
    private static array $fieldTypes = [];

    /**
     * Field type instances cache
     */
    private static array $instances = [];

    /**
     * Initialize default field types
     */
    public static function initialize(): void
    {
        if (self::$fieldTypes !== []) {
            return;
        }

        // Register built-in field types
        self::register('string', StringFieldType::class);
        self::register('integer', IntegerFieldType::class);
        self::register('bigInteger', BigIntegerFieldType::class);
        self::register('tinyInteger', TinyIntegerFieldType::class);
        self::register('smallInteger', SmallIntegerFieldType::class);
        self::register('mediumInteger', MediumIntegerFieldType::class);
        self::register('unsignedBigInteger', UnsignedBigIntegerFieldType::class);
        self::register('decimal', DecimalFieldType::class);
        self::register('float', FloatFieldType::class);
        self::register('double', DoubleFieldType::class);
        self::register('boolean', BooleanFieldType::class);
        self::register('text', TextFieldType::class);
        self::register('longText', LongTextFieldType::class);
        self::register('mediumText', MediumTextFieldType::class);
        self::register('date', DateFieldType::class);
        self::register('datetime', DateTimeFieldType::class);
        self::register('time', TimeFieldType::class);
        self::register('timestamp', TimestampFieldType::class);
        self::register('json', JsonFieldType::class);
        self::register('uuid', UuidFieldType::class);
        self::register('binary', BinaryFieldType::class);
        self::register('email', EmailFieldType::class);
        self::register('enum', EnumFieldType::class);
        self::register('set', SetFieldType::class);
        self::register('foreignId', ForeignIdFieldType::class);
        self::register('morphs', MorphsFieldType::class);

        // Register aliases
        self::registerAlias('varchar', 'string');
        self::registerAlias('char', 'string');
        self::registerAlias('int', 'integer');
        self::registerAlias('bigint', 'bigInteger');
        self::registerAlias('long', 'bigInteger');
        self::registerAlias('tinyint', 'tinyInteger');
        self::registerAlias('smallint', 'smallInteger');
        self::registerAlias('mediumint', 'mediumInteger');
        self::registerAlias('unsigned_big_integer', 'unsignedBigInteger');
        self::registerAlias('unsigned_bigint', 'unsignedBigInteger');
        self::registerAlias('numeric', 'decimal');
        self::registerAlias('money', 'decimal');
        self::registerAlias('real', 'float');
        self::registerAlias('double_precision', 'double');
        self::registerAlias('bool', 'boolean');
        self::registerAlias('longtext', 'longText');
        self::registerAlias('mediumtext', 'mediumText');
        self::registerAlias('jsonb', 'json');
        self::registerAlias('guid', 'uuid');
        self::registerAlias('blob', 'binary');
        self::registerAlias('email_address', 'email');
        self::registerAlias('enumeration', 'enum');
        self::registerAlias('multi_select', 'set');
        self::registerAlias('multiple_choice', 'set');
        self::registerAlias('foreign_id', 'foreignId');
        self::registerAlias('fk', 'foreignId');
        self::registerAlias('polymorphic', 'morphs');
    }

    /**
     * Register a field type
     */
    public static function register(string $type, string $className): void
    {
        if (! class_exists($className)) {
            throw new InvalidArgumentException("Field type class '{$className}' does not exist");
        }

        if (! is_subclass_of($className, FieldTypeInterface::class)) {
            throw new InvalidArgumentException("Field type class '{$className}' must implement FieldTypeInterface");
        }

        self::$fieldTypes[$type] = $className;

        // Clear cached instance if it exists
        unset(self::$instances[$type]);
    }

    /**
     * Register an alias for an existing field type
     */
    public static function registerAlias(string $alias, string $baseType): void
    {
        if (! isset(self::$fieldTypes[$baseType])) {
            throw new InvalidArgumentException("Base field type '{$baseType}' is not registered");
        }

        self::$fieldTypes[$alias] = self::$fieldTypes[$baseType];
    }

    /**
     * Get a field type instance
     */
    public static function get(string $type): FieldTypeInterface
    {
        self::initialize();

        if (! isset(self::$fieldTypes[$type])) {
            throw SchemaException::invalidFieldType('unknown', $type);
        }

        if (! isset(self::$instances[$type])) {
            $className = self::$fieldTypes[$type];
            self::$instances[$type] = new $className();
        }

        return self::$instances[$type];
    }

    /**
     * Check if a field type is registered
     */
    public static function has(string $type): bool
    {
        self::initialize();

        return isset(self::$fieldTypes[$type]);
    }

    /**
     * Get all registered field types
     */
    public static function all(): array
    {
        self::initialize();

        return array_keys(self::$fieldTypes);
    }

    /**
     * Get field types by their base type (excluding aliases)
     */
    public static function getBaseTypes(): array
    {
        self::initialize();

        $baseTypes = [];
        foreach (array_keys(self::$fieldTypes) as $type) {
            // Check if this is a base type (not an alias)
            $instance = self::get($type);
            if ($instance->getType() === $type) {
                $baseTypes[] = $type;
            }
        }

        return $baseTypes;
    }

    /**
     * Discover and register field types from a directory
     */
    public static function discoverFieldTypes(string $directory, string $namespace): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $files = glob($directory.'/*FieldType.php');

        foreach ($files as $file) {
            $className = $namespace.'\\'.basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, FieldTypeInterface::class)) {
                $instance = new $className();
                self::register($instance->getType(), $className);

                // Register aliases
                foreach ($instance->getAliases() as $alias) {
                    self::registerAlias($alias, $instance->getType());
                }
            }
        }
    }

    /**
     * Clear all registered field types (mainly for testing)
     */
    public static function clear(): void
    {
        self::$fieldTypes = [];
        self::$instances = [];
    }
}
