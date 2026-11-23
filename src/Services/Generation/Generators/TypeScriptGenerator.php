<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services\Generation\Generators;

use Grazulex\LaravelModelschema\Contracts\FieldTypeInterface;
use Grazulex\LaravelModelschema\Schema\Field;
use Grazulex\LaravelModelschema\Schema\ModelSchema;
use Grazulex\LaravelModelschema\Schema\Relationship;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Generator for TypeScript interfaces or type aliases describing a model
 *
 * The generated type mirrors the JSON shape of the serialized Eloquent model, so it can be
 * used as-is for Inertia page props, Vue or React components and API clients.
 */
class TypeScriptGenerator extends AbstractGenerator
{
    public const STYLE_INTERFACE = 'interface';

    public const STYLE_TYPE = 'type';

    /**
     * TypeScript type used when a field type cannot be mapped
     */
    public const UNKNOWN_TYPE = 'unknown';

    /**
     * TypeScript type of a decoded JSON column
     */
    private const JSON_TYPE = 'Record<string, unknown> | unknown[]';

    /**
     * TypeScript types of the built-in field types, keyed by their canonical name
     * (aliases are resolved through the FieldTypeRegistry)
     */
    private const TYPE_MAP = [
        'string' => 'string',
        'text' => 'string',
        'longText' => 'string',
        'mediumText' => 'string',
        'email' => 'string',
        'uuid' => 'string',
        'binary' => 'string',
        'enum' => 'string',
        'integer' => 'number',
        'bigInteger' => 'number',
        'tinyInteger' => 'number',
        'smallInteger' => 'number',
        'mediumInteger' => 'number',
        'unsignedBigInteger' => 'number',
        'foreignId' => 'number',
        'float' => 'number',
        'double' => 'number',
        // Laravel serializes decimals as strings to keep their precision
        'decimal' => 'string',
        'boolean' => 'boolean',
        'date' => 'string',
        'datetime' => 'string',
        'time' => 'string',
        'timestamp' => 'string',
        'json' => self::JSON_TYPE,
        'set' => 'string[]',
    ];

    /**
     * TypeScript types of the Eloquent casts, used for field types without a built-in mapping
     * (custom field types and plugins)
     */
    private const CAST_MAP = [
        'int' => 'number',
        'integer' => 'number',
        'real' => 'number',
        'float' => 'number',
        'double' => 'number',
        'timestamp' => 'number',
        'decimal' => 'string',
        'string' => 'string',
        'bool' => 'boolean',
        'boolean' => 'boolean',
        'array' => self::JSON_TYPE,
        'json' => self::JSON_TYPE,
        'collection' => self::JSON_TYPE,
        'object' => 'Record<string, unknown>',
        'date' => 'string',
        'datetime' => 'string',
        'immutable_date' => 'string',
        'immutable_datetime' => 'string',
        'hashed' => 'string',
        'encrypted' => 'string',
    ];

    private const SINGLE_RELATIONS = ['belongsTo', 'hasOne', 'morphOne', 'hasOneThrough'];

    private const MANY_RELATIONS = ['hasMany', 'belongsToMany', 'morphMany', 'hasManyThrough', 'morphToMany', 'morphedByMany'];

    public function getGeneratorName(): string
    {
        return 'typescript';
    }

    public function getAvailableFormats(): array
    {
        return ['json', 'yaml', 'ts'];
    }

    /**
     * Build the structured description of the TypeScript type
     *
     * Options:
     *  - style:            'interface' (default) or 'type'
     *  - type_name:        name of the generated type (default: the model name)
     *  - relations:        include the relationships as optional properties (default: true)
     *  - imports:          import the related types (default: true)
     *  - import_path:      directory of the related type files (default: './')
     *  - implicit_columns: add id, timestamps and soft delete columns that the schema implies
     *                      without declaring them as fields (default: true)
     *  - exclude:          names of the properties to leave out (e.g. ['password'])
     *  - type_map:         array<string, string> overriding the TypeScript type of a field type
     *
     * @return array{name: string, style: string, file_name: string, model_class: string, properties: list<array{name: string, type: string, optional: bool, nullable: bool, source: string, comment: ?string}>, imports: list<array{name: string, path: string}>, code: string}
     */
    public function buildTypeData(ModelSchema $schema, array $options = []): array
    {
        $style = $options['style'] ?? self::STYLE_INTERFACE;
        if (! in_array($style, [self::STYLE_INTERFACE, self::STYLE_TYPE], true)) {
            throw new InvalidArgumentException(
                'Unsupported TypeScript style: '.(is_scalar($style) ? (string) $style : gettype($style)).". Use 'interface' or 'type'."
            );
        }

        $typeName = $this->toIdentifier((string) ($options['type_name'] ?? $schema->name));
        if ($typeName === '') {
            throw new InvalidArgumentException("Cannot build a TypeScript type name from '{$schema->name}'.");
        }

        $exclude = array_map('strval', (array) ($options['exclude'] ?? []));

        $properties = $this->buildFieldProperties($schema, $options);

        $imports = [];
        if ($options['relations'] ?? true) {
            $taken = array_column($properties, 'name');
            foreach ($schema->relationships as $relationship) {
                $property = $this->buildRelationProperty($relationship, $schema, $typeName);
                if (in_array($property['name'], $taken, true)) {
                    continue;
                }

                $taken[] = $property['name'];
                $related = $property['related'];
                unset($property['related']);
                $properties[] = $property;

                if ($related !== null && $related !== $typeName && ! in_array($property['name'], $exclude, true)) {
                    $imports[$related] = [
                        'name' => $related,
                        'path' => $this->importPath((string) ($options['import_path'] ?? './'), $related),
                    ];
                }
            }
        }

        $properties = array_values(array_filter(
            $properties,
            fn (array $property): bool => ! in_array($property['name'], $exclude, true)
        ));

        ksort($imports);
        $imports = ($options['imports'] ?? true) ? array_values($imports) : [];

        return [
            'name' => $typeName,
            'style' => $style,
            'file_name' => $typeName.'.ts',
            'model_class' => $schema->getModelClass(),
            'properties' => $properties,
            'imports' => $imports,
            'code' => $this->renderCode($typeName, $style, $properties, $imports),
        ];
    }

    /**
     * Resolve the TypeScript type of a field, without the "| null" part
     */
    public function resolveFieldType(Field $field, array $options = []): string
    {
        $typeMap = (array) ($options['type_map'] ?? []);

        if (isset($typeMap[$field->type]) && is_string($typeMap[$field->type])) {
            return $typeMap[$field->type];
        }

        if (! FieldTypeRegistry::has($field->type)) {
            return self::TYPE_MAP[$field->type] ?? self::UNKNOWN_TYPE;
        }

        $fieldType = FieldTypeRegistry::get($field->type);
        $canonical = $fieldType->getType();

        if (isset($typeMap[$canonical]) && is_string($typeMap[$canonical])) {
            return $typeMap[$canonical];
        }

        $config = array_merge($field->attributes, $field->toArray());

        // Custom field types can describe themselves with a getTypeScriptType() method
        if (method_exists($fieldType, 'getTypeScriptType')) {
            $custom = $fieldType->getTypeScriptType($config);
            if (is_string($custom) && mb_trim($custom) !== '') {
                return mb_trim($custom);
            }
        }

        if ($canonical === 'enum' || $canonical === 'set') {
            $union = $this->literalUnion($field);
            if ($union !== null) {
                return $canonical === 'set' ? "({$union})[]" : $union;
            }
        }

        return self::TYPE_MAP[$canonical] ?? $this->typeFromCast($fieldType, $config);
    }

    protected function generateFormat(ModelSchema $schema, string $format, array $options): string
    {
        $data = $this->buildTypeData($schema, $options);

        return match ($format) {
            'json' => $this->toJsonFormat(['typescript' => $data]),
            'yaml' => Yaml::dump(['typescript' => $data], 6, 2),
            'ts' => $data['code'],
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    /**
     * Build the properties that come from the columns of the table
     *
     * @return list<array{name: string, type: string, optional: bool, nullable: bool, source: string, comment: ?string}>
     */
    protected function buildFieldProperties(ModelSchema $schema, array $options): array
    {
        /** @var array<string, Field> $fields */
        $fields = [];
        foreach ($schema->getAllFields() as $field) {
            // Index by name: schemas built by hand may use a plain list of fields
            $fields[$field->name] ??= $field;
        }

        $implicit = (bool) ($options['implicit_columns'] ?? true);
        $properties = [];

        if ($implicit && ! isset($fields['id'])) {
            $properties['id'] = $this->property('id', 'number', false, 'field');
        }

        foreach ($fields as $field) {
            if ($this->isMorphs($field)) {
                $properties[$field->name.'_id'] ??= $this->property($field->name.'_id', 'number', $field->nullable, 'field', $field->comment);
                $properties[$field->name.'_type'] ??= $this->property($field->name.'_type', 'string', $field->nullable, 'field', $field->comment);

                continue;
            }

            $properties[$field->name] = $this->property(
                $field->name,
                $this->resolveFieldType($field, $options),
                $field->nullable,
                'field',
                $field->comment
            );
        }

        if ($implicit) {
            if ($schema->hasTimestamps()) {
                $properties['created_at'] ??= $this->property('created_at', 'string', true, 'field');
                $properties['updated_at'] ??= $this->property('updated_at', 'string', true, 'field');
            }

            if ($schema->hasSoftDeletes()) {
                $properties['deleted_at'] ??= $this->property('deleted_at', 'string', true, 'field');
            }
        }

        return array_values($properties);
    }

    /**
     * Build the optional property of a relationship
     *
     * Relationships are optional because they are only serialized when they are loaded. They use
     * the snake_case name Eloquent gives them when a model is converted to an array.
     *
     * @return array{name: string, type: string, optional: bool, nullable: bool, source: string, comment: ?string, related: ?string}
     */
    protected function buildRelationProperty(Relationship $relationship, ModelSchema $schema, string $typeName): array
    {
        $related = $this->relatedTypeName($relationship->model);
        if ($related === $this->toIdentifier($schema->name)) {
            // Self-referencing relationship: follow a custom type name
            $related = $typeName;
        }

        $isMany = in_array($relationship->type, self::MANY_RELATIONS, true);
        $isSingle = in_array($relationship->type, self::SINGLE_RELATIONS, true);

        if ($related === null || (! $isMany && ! $isSingle)) {
            // morphTo and unknown relationship types cannot be narrowed to one model
            $type = $isMany ? self::UNKNOWN_TYPE.'[]' : self::UNKNOWN_TYPE;
            $related = null;
            $nullable = false;
        } else {
            $type = $isMany ? $related.'[]' : $related;
            $nullable = ! $isMany;
        }

        $property = $this->property(Str::snake($relationship->name), $type, $nullable, 'relation', "{$relationship->type} relationship");
        $property['optional'] = true;
        $property['related'] = $related;

        return $property;
    }

    /**
     * @return array{name: string, type: string, optional: bool, nullable: bool, source: string, comment: ?string}
     */
    protected function property(string $name, string $type, bool $nullable, string $source, ?string $comment = null): array
    {
        return [
            'name' => $name,
            'type' => $nullable ? $type.' | null' : $type,
            'optional' => false,
            'nullable' => $nullable,
            'source' => $source,
            'comment' => $comment === null || mb_trim($comment) === '' ? null : mb_trim($comment),
        ];
    }

    /**
     * Render the TypeScript source code
     *
     * @param  list<array{name: string, type: string, optional: bool, nullable: bool, source: string, comment: ?string}>  $properties
     * @param  list<array{name: string, path: string}>  $imports
     */
    protected function renderCode(string $typeName, string $style, array $properties, array $imports): string
    {
        $lines = [];

        foreach ($imports as $import) {
            $lines[] = "import type { {$import['name']} } from '".$this->escapeString($import['path'])."';";
        }

        if ($imports !== []) {
            $lines[] = '';
        }

        $lines[] = $style === self::STYLE_TYPE
            ? "export type {$typeName} = {"
            : "export interface {$typeName} {";

        foreach ($properties as $property) {
            if ($property['comment'] !== null) {
                $lines[] = '    /** '.$this->escapeComment($property['comment']).' */';
            }

            $lines[] = '    '.$this->propertyKey($property['name']).($property['optional'] ? '?' : '').': '.$property['type'].';';
        }

        $lines[] = $style === self::STYLE_TYPE ? '};' : '}';

        return implode("\n", $lines)."\n";
    }

    /**
     * Build a union of literal types from the allowed values of an enum or set field
     */
    protected function literalUnion(Field $field): ?string
    {
        $values = $field->attributes['values'] ?? $field->attributes['options'] ?? null;
        if (! is_array($values)) {
            return null;
        }

        $literals = [];
        foreach ($values as $value) {
            if (is_int($value) || is_float($value)) {
                $literals[] = (string) $value;
            } elseif (is_string($value)) {
                $literals[] = "'".$this->escapeString($value)."'";
            }
        }

        $literals = array_values(array_unique($literals));

        return $literals === [] ? null : implode(' | ', $literals);
    }

    /**
     * Map the Eloquent cast of a field type to a TypeScript type
     */
    protected function typeFromCast(FieldTypeInterface $fieldType, array $config): string
    {
        $cast = $fieldType->getCastType($config);
        if ($cast === null || $cast === '') {
            return self::UNKNOWN_TYPE;
        }

        // Drop cast parameters such as "decimal:2" or "datetime:Y-m-d"
        $cast = mb_strtolower(explode(':', $cast)[0]);

        return self::CAST_MAP[$cast] ?? self::UNKNOWN_TYPE;
    }

    protected function isMorphs(Field $field): bool
    {
        return FieldTypeRegistry::has($field->type)
            && FieldTypeRegistry::get($field->type)->getType() === 'morphs';
    }

    /**
     * Get the TypeScript type name of a related model, null when the model is unknown
     */
    protected function relatedTypeName(string $model): ?string
    {
        $segments = explode('\\', mb_trim(str_replace('/', '\\', $model), '\\ '));
        $name = $this->toIdentifier(end($segments));

        return $name === '' ? null : $name;
    }

    /**
     * Turn a name into a valid TypeScript identifier
     */
    protected function toIdentifier(string $name): string
    {
        $identifier = (string) preg_replace('/[^A-Za-z0-9_$]/', '', $name);

        return preg_match('/^\d/', $identifier) === 1 ? '_'.$identifier : $identifier;
    }

    protected function importPath(string $directory, string $typeName): string
    {
        $directory = mb_rtrim($directory, '/');

        return ($directory === '' ? '.' : $directory).'/'.$typeName;
    }

    /**
     * Quote property names that are not valid identifiers
     */
    protected function propertyKey(string $name): string
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name) === 1
            ? $name
            : "'".$this->escapeString($name)."'";
    }

    protected function escapeString(string $value): string
    {
        return str_replace(
            ['\\', "'", "\n", "\r"],
            ['\\\\', "\\'", '\\n', '\\r'],
            $value
        );
    }

    protected function escapeComment(string $comment): string
    {
        return str_replace('*/', '*\\/', (string) preg_replace('/\s+/', ' ', $comment));
    }
}
