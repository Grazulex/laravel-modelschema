<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

use InvalidArgumentException;

/**
 * Field type for general geometric data
 * Supports various geometric types like POLYGON, LINESTRING, etc.
 */
final class GeometryFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'geometry_type', // POINT, LINESTRING, POLYGON, MULTIPOINT, etc.
        'srid', // Spatial Reference System Identifier
        'dimensions', // 2D, 3D
    ];

    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get supported geometry types
     */
    public static function getSupportedGeometryTypes(): array
    {
        return [
            'GEOMETRY' => 'Generic geometry type',
            'POINT' => 'Single point (longitude, latitude)',
            'LINESTRING' => 'Series of connected points',
            'POLYGON' => 'Closed area with possible holes',
            'MULTIPOINT' => 'Collection of points',
            'MULTILINESTRING' => 'Collection of linestrings',
            'MULTIPOLYGON' => 'Collection of polygons',
            'GEOMETRYCOLLECTION' => 'Collection of mixed geometries',
        ];
    }

    public function getType(): string
    {
        return 'geometry';
    }

    public function getAliases(): array
    {
        return ['geom', 'spatial', 'geo'];
    }

    public function getCastType(array $config = []): string
    {
        return 'string'; // Geometries are typically stored as WKT strings in Laravel
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

        // Add geometry type if specified
        if (isset($config['geometry_type'])) {
            $params[] = "'{$config['geometry_type']}'";
        }

        // Add SRID if specified
        if (isset($config['srid'])) {
            $params[] = $config['srid'];
        }

        return $params;
    }

    public function getValidationRules(array $config = []): array
    {
        $rules = parent::getValidationRules($config);

        $rules[] = 'string';

        // Custom validation for geometry format
        $rules[] = function ($attribute, $value, $fail) use ($config): void {
            if ($value !== null && ! $this->isValidGeometryFormat($value, $config)) {
                $geometryType = $config['geometry_type'] ?? 'geometry';
                $fail("The {$attribute} must be a valid {$geometryType} format.");
            }
        };

        return $rules;
    }

    public function transformConfig(array $config): array
    {
        $transformed = parent::transformConfig($config);

        // Set default SRID if not specified
        if (! isset($transformed['srid'])) {
            $transformed['srid'] = 4326; // WGS84 - most common coordinate system
        }

        // Set default dimension if not specified
        if (! isset($transformed['dimensions'])) {
            $transformed['dimensions'] = 2;
        }

        // Set default geometry type if not specified
        if (! isset($transformed['geometry_type'])) {
            $transformed['geometry_type'] = 'GEOMETRY';
        }

        return $transformed;
    }

    public function validate(array $config): array
    {
        $errors = parent::validate($config);

        // Validate SRID if provided
        if (isset($config['srid']) && (! is_int($config['srid']) || $config['srid'] < 0)) {
            $errors[] = 'SRID must be a positive integer';
        }

        // Validate dimension
        if (isset($config['dimensions']) && (! is_int($config['dimensions']) || ! in_array($config['dimensions'], [2, 3]))) {
            $errors[] = 'Dimensions must be either 2 or 3';
        }

        // Validate geometry type
        if (isset($config['geometry_type'])) {
            $validTypes = [
                'GEOMETRY', 'POINT', 'LINESTRING', 'POLYGON',
                'MULTIPOINT', 'MULTILINESTRING', 'MULTIPOLYGON',
                'GEOMETRYCOLLECTION',
            ];

            if (! in_array(mb_strtoupper($config['geometry_type']), $validTypes)) {
                $errors[] = 'Invalid geometry type. Must be one of: '.implode(', ', $validTypes);
            }
        }

        return $errors;
    }

    /**
     * Transform value to proper geometry format
     */
    public function transformValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If already in WKT format, return as is
        if (is_string($value) && $this->isWktFormat($value)) {
            return $value;
        }

        // Handle array format for simple geometries or GeoJSON
        if (is_array($value)) {
            // Check if it's GeoJSON format
            if (isset($value['type']) && (isset($value['coordinates']) || isset($value['geometries']))) {
                $wkt = $this->geoJsonToWkt($value);

                // Add SRID if configured
                if (isset($this->config['srid'])) {
                    return "SRID={$this->config['srid']};{$wkt}";
                }

                return $wkt;
            }

            return $this->arrayToWkt($value);
        }

        return (string) $value;
    }

    /**
     * Get the default value for this field type
     */
    public function getDefaultValue(): mixed
    {
        return $this->config['default'] ?? null;
    }

    /**
     * Get MySQL column definition
     */
    public function getMySQLColumnDefinition(): string
    {
        return 'GEOMETRY';
    }

    /**
     * Get PostgreSQL column definition
     */
    public function getPostgreSQLColumnDefinition(): string
    {
        return 'GEOMETRY';
    }

    /**
     * Get SQLite column definition
     */
    public function getSQLiteColumnDefinition(): string
    {
        return 'TEXT'; // SQLite doesn't have native geometry type
    }

    protected function getMigrationMethod(): string
    {
        return 'geometry';
    }

    /**
     * Convert GeoJSON format to WKT format
     */
    private function geoJsonToWkt(array $geoJson): string
    {
        $type = mb_strtoupper($geoJson['type']);

        // Handle GEOMETRYCOLLECTION separately as it doesn't have coordinates
        if ($type === 'GEOMETRYCOLLECTION') {
            if (! isset($geoJson['geometries'])) {
                return 'GEOMETRYCOLLECTION EMPTY';
            }
            $geometries = array_map([$this, 'geoJsonToWkt'], $geoJson['geometries']);

            return 'GEOMETRYCOLLECTION('.implode(', ', $geometries).')';
        }

        $coordinates = $geoJson['coordinates'];

        switch ($type) {
            case 'POINT':
                return 'POINT('.implode(' ', $coordinates).')';

            case 'LINESTRING':
                $points = array_map(fn ($coord): string => implode(' ', $coord), $coordinates);

                return 'LINESTRING('.implode(', ', $points).')';

            case 'POLYGON':
                $rings = array_map(function ($ring): string {
                    $points = array_map(fn ($coord): string => implode(' ', $coord), $ring);

                    return '('.implode(', ', $points).')';
                }, $coordinates);

                return 'POLYGON('.implode(', ', $rings).')';

            case 'MULTIPOINT':
                $points = array_map(fn ($coord): string => '('.implode(' ', $coord).')', $coordinates);

                return 'MULTIPOINT('.implode(', ', $points).')';

            case 'MULTILINESTRING':
                $lines = array_map(function ($line): string {
                    $points = array_map(fn ($coord): string => implode(' ', $coord), $line);

                    return '('.implode(', ', $points).')';
                }, $coordinates);

                return 'MULTILINESTRING('.implode(', ', $lines).')';

            case 'MULTIPOLYGON':
                $polygons = array_map(function ($polygon): string {
                    $rings = array_map(function ($ring): string {
                        $points = array_map(fn ($coord): string => implode(' ', $coord), $ring);

                        return '('.implode(', ', $points).')';
                    }, $polygon);

                    return '('.implode(', ', $rings).')';
                }, $coordinates);

                return 'MULTIPOLYGON('.implode(', ', $polygons).')';

            default:
                throw new InvalidArgumentException("Unsupported GeoJSON type: {$type}");
        }
    }

    /**
     * Check if the value is in a valid geometry format
     */
    private function isValidGeometryFormat($value, array $config = []): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Check if it's a valid WKT format
        if (! $this->isWktFormat($value)) {
            return false;
        }

        // Check if it matches the specified geometry type
        if (isset($config['geometry_type'])) {
            $expectedType = mb_strtoupper($config['geometry_type']);
            if ($expectedType !== 'GEOMETRY' && ! str_starts_with(mb_strtoupper($value), $expectedType)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if value is in Well-Known Text format
     */
    private function isWktFormat(string $value): bool
    {
        $geometryTypes = [
            'POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT',
            'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION',
        ];

        foreach ($geometryTypes as $type) {
            if (str_starts_with(mb_strtoupper($value), $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert array representation to WKT
     */
    private function arrayToWkt(array $data): string
    {
        // Handle simple point array [lng, lat]
        if (count($data) === 2 && is_numeric($data[0]) && is_numeric($data[1])) {
            return "POINT({$data[0]} {$data[1]})";
        }

        // Handle coordinate pairs array [[lng1, lat1], [lng2, lat2], ...]
        if (is_array($data[0]) && count($data[0]) === 2) {
            $coordinates = array_map(fn ($coord): string => "{$coord[0]} {$coord[1]}", $data);

            if (count($data) === 1) {
                return "POINT({$coordinates[0]})";
            }
            $coordString = implode(', ', $coordinates);

            return "LINESTRING({$coordString})";

        }

        // Default: return as JSON string
        return json_encode($data);
    }
}
