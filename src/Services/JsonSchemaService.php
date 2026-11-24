<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Services;

use Grazulex\LaravelModelschema\Exceptions\SchemaException;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use Illuminate\Filesystem\Filesystem;

/**
 * Exports a JSON Schema (draft-07) describing the YAML schema files
 *
 * The exported document can be referenced by editors (YAML language server, PhpStorm,
 * VS Code) for autocompletion and inline validation, or used by any JSON Schema
 * validator in CI. Both the "core" structure and the legacy flat structure are covered.
 */
class JsonSchemaService
{
    public const DRAFT = 'http://json-schema.org/draft-07/schema#';

    public const DEFAULT_TITLE = 'Laravel ModelSchema';

    /**
     * Supported relationship types with their description
     */
    public const RELATIONSHIP_TYPES = [
        'belongsTo' => 'Belongs to a single related model',
        'hasOne' => 'Has one related model',
        'hasMany' => 'Has many related models',
        'belongsToMany' => 'Many-to-many relationship',
        'hasOneThrough' => 'Has one through intermediate model',
        'hasManyThrough' => 'Has many through intermediate model',
        'morphTo' => 'Polymorphic belongs to',
        'morphOne' => 'Polymorphic has one',
        'morphMany' => 'Polymorphic has many',
        'morphToMany' => 'Polymorphic many-to-many',
        'morphedByMany' => 'Inverse polymorphic many-to-many',
    ];

    /**
     * Keys accepted in the core section
     */
    public const CORE_KEYS = [
        'model', 'name', 'table', 'version', 'fields', 'relationships', 'relations', 'options', 'metadata',
    ];

    public function __construct(
        protected Filesystem $filesystem = new Filesystem(),
    ) {}

    /**
     * Build the JSON Schema as an array
     *
     * Options:
     * - id (string): value of "$id" (omitted by default)
     * - title (string): value of "title"
     * - strict (bool, default false): reject unknown keys inside the "core" section
     * - field_types (string[]): extra field types accepted in addition to the registry
     * - allow_unknown_field_types (bool, default false): suggest the known field types without rejecting other values
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(array $options = []): array
    {
        $strict = (bool) ($options['strict'] ?? false);

        $schema = [
            '$schema' => self::DRAFT,
        ];

        if (isset($options['id']) && is_string($options['id']) && $options['id'] !== '') {
            $schema['$id'] = $options['id'];
        }

        $schema['title'] = isset($options['title']) && is_string($options['title']) && $options['title'] !== ''
            ? $options['title']
            : self::DEFAULT_TITLE;
        $schema['description'] = 'YAML model schema. Declare the model under a "core" key (other root keys are extension data for parent applications) or use the flat format with the core keys at the root.';
        $schema['type'] = 'object';
        $schema['properties'] = [
            'core' => [
                'description' => 'Core schema data managed by Laravel ModelSchema',
            ],
        ];
        $schema['if'] = [
            'required' => ['core'],
        ];
        $schema['then'] = [
            'properties' => [
                'core' => ['$ref' => $strict ? '#/definitions/strictCore' : '#/definitions/core'],
            ],
        ];
        $schema['else'] = [
            '$ref' => '#/definitions/core',
        ];
        $schema['definitions'] = [
            'core' => $this->coreDefinition(false),
            'strictCore' => $this->coreDefinition(true),
            'field' => $this->fieldDefinition($options),
            'relationship' => $this->relationshipDefinition(),
            'version' => $this->versionDefinition(),
            'stringList' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ];

        return $schema;
    }

    /**
     * Build the JSON Schema as a JSON string
     *
     * @param  array<string, mixed>  $options  See generate()
     */
    public function toJson(array $options = [], int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->generate($options), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Write the JSON Schema to a file and return the file path
     *
     * @param  array<string, mixed>  $options  See generate()
     *
     * @throws SchemaException When the file cannot be written
     */
    public function exportToFile(string $filePath, array $options = []): string
    {
        $directory = dirname($filePath);

        if (! $this->filesystem->isDirectory($directory)) {
            $this->filesystem->makeDirectory($directory, 0755, true);
        }

        if ($this->filesystem->put($filePath, $this->toJson($options).PHP_EOL) === false) {
            throw new SchemaException("Unable to write the JSON Schema to: {$filePath}");
        }

        return $filePath;
    }

    /**
     * Field types accepted by the exported schema (registry types, aliases and extra types)
     *
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    public function getFieldTypes(array $options = []): array
    {
        $types = FieldTypeRegistry::all();

        foreach ((array) ($options['field_types'] ?? []) as $extraType) {
            if (is_string($extraType) && $extraType !== '') {
                $types[] = $extraType;
            }
        }

        $types = array_values(array_unique($types));
        sort($types);

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    protected function coreDefinition(bool $strict): array
    {
        return [
            'type' => 'object',
            'description' => 'Model definition: name, table, fields, relationships and options',
            'required' => ['fields'],
            'properties' => [
                'model' => [
                    'type' => 'string',
                    'description' => 'Model class name (e.g. User)',
                    'pattern' => '^[A-Za-z_][A-Za-z0-9_]*$',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Model name, used when "model" is missing',
                ],
                'table' => [
                    'type' => 'string',
                    'description' => 'Database table name (defaults to the snake_case plural of the model)',
                ],
                'version' => ['$ref' => '#/definitions/version'],
                'fields' => [
                    'type' => 'object',
                    'description' => 'Field definitions indexed by column name',
                    'minProperties' => 1,
                    'additionalProperties' => ['$ref' => '#/definitions/field'],
                ],
                'relationships' => [
                    'type' => ['object', 'null'],
                    'description' => 'Relationship definitions indexed by relation name',
                    'additionalProperties' => ['$ref' => '#/definitions/relationship'],
                ],
                'relations' => [
                    'type' => ['object', 'null'],
                    'description' => 'Alias of "relationships"',
                    'additionalProperties' => ['$ref' => '#/definitions/relationship'],
                ],
                'options' => [
                    'type' => ['object', 'null'],
                    'description' => 'Model options',
                    'properties' => [
                        'timestamps' => [
                            'type' => 'boolean',
                            'description' => 'Add created_at and updated_at (default: true)',
                        ],
                        'soft_deletes' => [
                            'type' => 'boolean',
                            'description' => 'Add deleted_at and enable soft deletes',
                        ],
                        'namespace' => [
                            'type' => 'string',
                            'description' => 'Model namespace (default: App\\Models)',
                        ],
                        'table' => [
                            'type' => 'string',
                            'description' => 'Table name, used when "table" is missing',
                        ],
                    ],
                    'additionalProperties' => true,
                ],
                'metadata' => [
                    'type' => ['object', 'null'],
                    'description' => 'Free-form metadata',
                ],
            ],
            'additionalProperties' => ! $strict,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function fieldDefinition(array $options): array
    {
        $fieldTypes = $this->getFieldTypes($options);

        $typeDefinition = (bool) ($options['allow_unknown_field_types'] ?? false)
            ? [
                'description' => 'Field type (default: string)',
                'anyOf' => [
                    ['type' => 'string', 'enum' => $fieldTypes],
                    ['type' => 'string'],
                ],
            ]
            : [
                'type' => 'string',
                'description' => 'Field type (default: string)',
                'enum' => $fieldTypes,
            ];

        return [
            'type' => 'object',
            'description' => 'Field definition. Additional keys are kept as field type specific attributes.',
            'properties' => [
                'type' => $typeDefinition,
                'nullable' => [
                    'type' => 'boolean',
                    'description' => 'Allow NULL values (default: false)',
                ],
                'unique' => [
                    'type' => 'boolean',
                    'description' => 'Add a unique index (default: false)',
                ],
                'index' => [
                    'type' => 'boolean',
                    'description' => 'Add an index (default: false)',
                ],
                'default' => [
                    'description' => 'Default value of the column',
                ],
                'length' => [
                    'type' => ['integer', 'null'],
                    'minimum' => 0,
                    'description' => 'Column length (string types)',
                ],
                'precision' => [
                    'type' => ['integer', 'null'],
                    'minimum' => 0,
                    'description' => 'Total number of digits (decimal types)',
                ],
                'scale' => [
                    'type' => ['integer', 'null'],
                    'minimum' => 0,
                    'description' => 'Number of digits after the decimal point (decimal types)',
                ],
                'values' => [
                    'type' => 'array',
                    'description' => 'Allowed values (enum and set types)',
                    'items' => ['type' => ['string', 'number', 'integer', 'boolean']],
                ],
                'rules' => [
                    '$ref' => '#/definitions/stringList',
                    'description' => 'Laravel validation rules',
                ],
                'validation' => [
                    '$ref' => '#/definitions/stringList',
                    'description' => 'Additional Laravel validation rules',
                ],
                'comment' => [
                    'type' => ['string', 'null'],
                    'description' => 'Column comment',
                ],
                'attributes' => [
                    'type' => ['object', 'null'],
                    'description' => 'Custom attributes',
                ],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function relationshipDefinition(): array
    {
        $descriptions = [];
        foreach (self::RELATIONSHIP_TYPES as $type => $description) {
            $descriptions[] = "{$type}: {$description}";
        }

        $nullableString = static fn (string $description): array => [
            'type' => ['string', 'null'],
            'description' => $description,
        ];

        return [
            'type' => 'object',
            'description' => 'Relationship definition',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'description' => 'Relationship type (default: belongsTo). '.implode('; ', $descriptions),
                    'enum' => array_keys(self::RELATIONSHIP_TYPES),
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'Related model class (e.g. App\\Models\\Post)',
                ],
                'foreign_key' => $nullableString('Foreign key column'),
                'foreignKey' => $nullableString('Alias of "foreign_key"'),
                'local_key' => $nullableString('Local key column'),
                'localKey' => $nullableString('Alias of "local_key"'),
                'pivot_table' => $nullableString('Pivot table (belongsToMany)'),
                'pivotTable' => $nullableString('Alias of "pivot_table"'),
                'pivot_fields' => [
                    '$ref' => '#/definitions/stringList',
                    'description' => 'Extra pivot columns (belongsToMany)',
                ],
                'pivotFields' => [
                    '$ref' => '#/definitions/stringList',
                    'description' => 'Alias of "pivot_fields"',
                ],
                'with_timestamps' => [
                    'type' => 'boolean',
                    'description' => 'Maintain pivot timestamps (belongsToMany)',
                ],
                'withTimestamps' => [
                    'type' => 'boolean',
                    'description' => 'Alias of "with_timestamps"',
                ],
                'attributes' => [
                    'type' => ['object', 'null'],
                    'description' => 'Custom attributes',
                ],
            ],
            'additionalProperties' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function versionDefinition(): array
    {
        return [
            'description' => 'Schema version: "major", "major.minor" or "major.minor.patch" (normalized to major.minor.patch)',
            'oneOf' => [
                [
                    'type' => 'string',
                    'pattern' => '^\s*(0|[1-9]\d{0,8})(\.(0|[1-9]\d{0,8})){0,2}\s*$',
                ],
                [
                    'type' => 'number',
                    'minimum' => 0,
                ],
            ],
        ];
    }
}
