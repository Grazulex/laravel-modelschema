<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Field type for geographic point data (coordinates)
 * Supports various input formats and WKT output for database storage
 */
class PointFieldType extends AbstractFieldType
{
    /**
     * Configuration for this field type instance
     */
    protected array $config = [];

    /**
     * Additional attributes specific to point fields
     */
    protected array $specificAttributes = [
        'srid',       // Spatial Reference System Identifier
        'dimensions', // 2D or 3D coordinates
    ];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function getType(): string
    {
        return 'point';
    }

    public function getAliases(): array
    {
        return ['geopoint', 'coordinates', 'latlng'];
    }

    public function transformConfig(array $config): array
    {
        $transformed = parent::transformConfig($config);

        // Ensure SRID is integer if provided
        if (isset($config['srid'])) {
            $transformed['srid'] = (int) $config['srid'];
        }

        // Set default dimensions
        if (! isset($config['dimensions'])) {
            $transformed['dimensions'] = 2;
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
        if (isset($config['dimension']) && ! in_array($config['dimension'], ['2D', '3D'])) {
            $errors[] = "Dimension must be either '2D' or '3D'";
        }

        return $errors;
    }

    /**
     * Transform value to proper point format
     */
    public function transformValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Handle different input formats
        if (is_string($value)) {
            // If already in WKT format, return as is
            if (str_starts_with(mb_strtoupper($value), 'POINT')) {
                return $value;
            }

            // Handle "lat,lng" format
            if (str_contains($value, ',')) {
                $parts = array_map('trim', explode(',', $value));
                if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $point = "POINT({$parts[1]} {$parts[0]})"; // lng lat format

                    return $this->addSridIfConfigured($point);
                }
            }

            return null;
        }

        if (is_array($value)) {
            // Handle numeric array [lng, lat] or [lng, lat, elevation]
            if (array_is_list($value) && count($value) >= 2) {
                $coordinates = implode(' ', array_slice($value, 0, 3)); // Max 3 dimensions

                // Force 3D if configured but only 2D provided
                if (($this->config['dimensions'] ?? 2) === 3 && count($value) === 2) {
                    $coordinates .= ' 0'; // Default elevation
                }

                $point = "POINT({$coordinates})";

                return $this->addSridIfConfigured($point);
            }

            // Handle associative arrays
            $lng = $value['lng'] ?? $value['longitude'] ?? $value['x'] ?? null;
            $lat = $value['lat'] ?? $value['latitude'] ?? $value['y'] ?? null;
            $elevation = $value['elevation'] ?? $value['z'] ?? null;

            if ($lng !== null && $lat !== null) {
                $coordinates = "{$lng} {$lat}";
                if ($elevation !== null) {
                    $coordinates .= " {$elevation}";
                } elseif (($this->config['dimensions'] ?? 2) === 3) {
                    $coordinates .= ' 0'; // Default elevation
                }

                $point = "POINT({$coordinates})";

                return $this->addSridIfConfigured($point);
            }
        }

        return null;
    }

    /**
     * Check if the value is in a valid point format
     */
    public function isValidPointFormat(mixed $value): bool
    {
        if (is_array($value)) {
            // Check for numeric array [x, y] or [x, y, z]
            if (array_is_list($value) && count($value) >= 2 && count($value) <= 3) {
                return array_reduce($value, fn ($carry, $coord): bool => $carry && is_numeric($coord), true);
            }

            // Check for associative arrays with coordinate keys
            $hasLatLng = isset($value['lat']) && isset($value['lng']);
            $hasLatitudeLongitude = isset($value['latitude']) && isset($value['longitude']);
            $hasXY = isset($value['x']) && isset($value['y']);

            if ($hasLatLng || $hasLatitudeLongitude || $hasXY) {
                foreach ($value as $coord) {
                    if (! is_numeric($coord)) {
                        return false;
                    }
                }

                return true;
            }

            return false;
        }

        if (! is_string($value)) {
            return false;
        }

        // Check WKT format: POINT(x y) or POINT(x y z)
        if (preg_match('/^POINT\s*\(\s*-?\d+(\.\d+)?\s+-?\d+(\.\d+)?(\s+-?\d+(\.\d+)?)?\s*\)$/i', $value)) {
            return true;
        }

        // Check "lat,lng" format
        return (bool) preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/', $value);
    }

    /**
     * Convert point to coordinate array for easier handling
     */
    public function toCoordinates(string $pointWkt): ?array
    {
        if (preg_match('/^POINT\s*\(\s*(-?\d+(?:\.\d+)?)\s+(-?\d+(?:\.\d+)?)(?:\s+(-?\d+(?:\.\d+)?))?\s*\)$/i', $pointWkt, $matches)) {
            $coords = [
                (float) $matches[1], // longitude/x
                (float) $matches[2], // latitude/y
            ];

            // Add Z coordinate if present (elevation)
            if (isset($matches[3])) {
                $coords[] = (float) $matches[3];
            }

            return $coords;
        }

        return null;
    }

    /**
     * Validate if the value is valid for this field type
     */
    public function validateValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true; // Allow null/empty values
        }

        if (! $this->isValidPointFormat($value)) {
            return false;
        }

        // Additional validation for lat/lng ranges
        if (is_array($value)) {
            if (isset($value['lat']) || isset($value['latitude'])) {
                $lat = $value['lat'] ?? $value['latitude'];
                if (! is_numeric($lat) || $lat < -90 || $lat > 90) {
                    return false;
                }
            }

            if (isset($value['lng']) || isset($value['longitude'])) {
                $lng = $value['lng'] ?? $value['longitude'];
                if (! is_numeric($lng) || $lng < -180 || $lng > 180) {
                    return false;
                }
            }
        }

        return true;
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
        return 'POINT';
    }

    /**
     * Get PostgreSQL column definition
     */
    public function getPostgreSQLColumnDefinition(): string
    {
        return 'POINT';
    }

    /**
     * Get SQLite column definition
     */
    public function getSQLiteColumnDefinition(): string
    {
        return 'TEXT'; // SQLite doesn't have native point type
    }

    protected function getMigrationMethod(): string
    {
        return 'point';
    }

    /**
     * Add SRID prefix if configured
     */
    private function addSridIfConfigured(string $point): string
    {
        if (isset($this->config['srid'])) {
            return "SRID={$this->config['srid']};{$point}";
        }

        return $point;
    }
}
