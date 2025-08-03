<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\FieldTypes;

/**
 * Field type for polygon geometric data
 * Specialized field type for polygon areas, boundaries, etc.
 */
final class PolygonFieldType extends AbstractFieldType
{
    protected array $specificAttributes = [
        'srid', // Spatial Reference System Identifier
        'dimension', // 2D or 3D
        'allow_holes', // Whether to allow holes in polygons
    ];

    public function getType(): string
    {
        return 'polygon';
    }

    public function getAliases(): array
    {
        return ['area', 'boundary', 'region'];
    }

    public function getCastType(array $config = []): string
    {
        return 'string'; // Polygons are typically stored as WKT strings in Laravel
    }

    public function getMigrationParameters(array $config): array
    {
        $params = [];

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

        // Custom validation for polygon format
        $rules[] = function ($attribute, $value, $fail): void {
            if ($value !== null && ! $this->isValidPolygonFormat($value)) {
                $fail("The {$attribute} must be a valid polygon format.");
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
        if (! isset($transformed['dimension'])) {
            $transformed['dimension'] = '2D';
        }

        // Set default allow_holes if not specified
        if (! isset($transformed['allow_holes'])) {
            $transformed['allow_holes'] = true;
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

        // Validate allow_holes
        if (isset($config['allow_holes']) && ! is_bool($config['allow_holes'])) {
            $errors[] = 'allow_holes must be a boolean value';
        }

        return $errors;
    }

    /**
     * Transform value to proper polygon format
     */
    public function transformValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        // If already in WKT format, return as is
        if (is_string($value) && str_starts_with(mb_strtoupper($value), 'POLYGON')) {
            return $value;
        }

        // Handle array format for polygon coordinates
        if (is_array($value)) {
            return $this->arrayToPolygonWkt($value);
        }

        return (string) $value;
    }

    /**
     * Extract coordinates from polygon WKT
     */
    public function extractCoordinates(string $polygonWkt): ?array
    {
        if (in_array(preg_match('/^POLYGON\s*\(\s*(.+)\s*\)$/i', $polygonWkt, $matches), [0, false], true)) {
            return null;
        }

        $ringsText = $matches[1];
        $rings = [];

        // Split rings by top-level commas (outside parentheses)
        preg_match_all('/\(([^)]+)\)/', $ringsText, $ringMatches);

        foreach ($ringMatches[1] as $ringCoords) {
            $coords = [];
            $coordPairs = explode(',', $ringCoords);

            foreach ($coordPairs as $pair) {
                $parts = preg_split('/\s+/', mb_trim($pair));
                if (count($parts) >= 2) {
                    $coords[] = [(float) $parts[0], (float) $parts[1]];
                }
            }

            if ($coords !== []) {
                $rings[] = $coords;
            }
        }

        return $rings;
    }

    /**
     * Calculate polygon area (simplified for 2D polygons)
     * Note: This is a basic calculation and doesn't account for Earth's curvature
     */
    public function calculateArea(string $polygonWkt): ?float
    {
        $coordinates = $this->extractCoordinates($polygonWkt);

        if ($coordinates === null || $coordinates === [] || empty($coordinates[0])) {
            return null;
        }

        // Use shoelace formula for the exterior ring
        $ring = $coordinates[0];
        $area = 0;
        $n = count($ring);

        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += $ring[$i][0] * $ring[$j][1];
            $area -= $ring[$j][0] * $ring[$i][1];
        }

        $area = abs($area) / 2;
        // Subtract areas of holes if any
        $counter = count($coordinates);

        // Subtract areas of holes if any
        for ($h = 1; $h < $counter; $h++) {
            $hole = $coordinates[$h];
            $holeArea = 0;
            $hn = count($hole);

            for ($i = 0; $i < $hn; $i++) {
                $j = ($i + 1) % $hn;
                $holeArea += $hole[$i][0] * $hole[$j][1];
                $holeArea -= $hole[$j][0] * $hole[$i][1];
            }

            $area -= abs($holeArea) / 2;
        }

        return $area;
    }

    /**
     * Check if a point is inside the polygon
     * Uses ray casting algorithm
     */
    public function containsPoint(string $polygonWkt, array $point): bool
    {
        $coordinates = $this->extractCoordinates($polygonWkt);

        if ($coordinates === null || $coordinates === [] || empty($coordinates[0])) {
            return false;
        }

        // Check exterior ring
        $ring = $coordinates[0];
        $inside = $this->pointInRing($point, $ring);

        // If inside exterior ring, check if it's in any holes
        if ($inside) {
            $counter = count($coordinates);
            for ($h = 1; $h < $counter; $h++) {
                if ($this->pointInRing($point, $coordinates[$h])) {
                    return false; // Point is in a hole
                }
            }
        }

        return $inside;
    }

    protected function getMigrationMethod(): string
    {
        return 'polygon';
    }

    /**
     * Check if the value is in a valid polygon format
     */
    private function isValidPolygonFormat($value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Check if it's a valid polygon WKT format
        if (in_array(preg_match('/^POLYGON\s*\(/i', $value), [0, false], true)) {
            return false;
        }

        // Basic validation for polygon structure
        // This is a simplified check - real-world usage might need more robust validation
        return (bool) preg_match('/^POLYGON\s*\(\s*\([^)]+\)(\s*,\s*\([^)]+\))*\s*\)$/i', $value);
    }

    /**
     * Convert array representation to polygon WKT
     */
    private function arrayToPolygonWkt(array $data): string
    {
        // Handle simple coordinate array [[[lng1, lat1], [lng2, lat2], ...]]
        if (is_array($data[0]) && is_array($data[0][0])) {
            $rings = [];

            foreach ($data as $ring) {
                $coordinates = array_map(fn ($coord): string => "{$coord[0]} {$coord[1]}", $ring);
                $rings[] = '('.implode(', ', $coordinates).')';
            }

            return 'POLYGON('.implode(', ', $rings).')';
        }

        // Handle simple ring [[[lng1, lat1], [lng2, lat2], ...]]
        if (is_array($data[0]) && count($data[0]) === 2 && is_numeric($data[0][0])) {
            $coordinates = array_map(fn ($coord): string => "{$coord[0]} {$coord[1]}", $data);

            return 'POLYGON(('.implode(', ', $coordinates).'))';
        }

        // Default: return as JSON string
        return json_encode($data);
    }

    /**
     * Point-in-polygon test for a single ring
     */
    private function pointInRing(array $point, array $ring): bool
    {
        $x = $point[0];
        $y = $point[1];
        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            if ((($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
