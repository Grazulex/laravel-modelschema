<?php

declare(strict_types=1);

namespace Tests\Unit\FieldTypes;

use Grazulex\LaravelModelschema\FieldTypes\GeometryFieldType;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class GeometryFieldTypeTest extends TestCase
{
    private GeometryFieldType $fieldType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldType = new GeometryFieldType();
    }

    /** @test */
    public function it_has_correct_type()
    {
        $this->assertEquals('geometry', $this->fieldType->getType());
    }

    /** @test */
    public function it_has_correct_aliases()
    {
        $aliases = $this->fieldType->getAliases();
        $this->assertContains('geom', $aliases);
        $this->assertContains('spatial', $aliases);
        $this->assertContains('geo', $aliases);
    }

    /** @test */
    public function it_supports_geometry_specific_attributes()
    {
        $reflection = new ReflectionClass($this->fieldType);
        $property = $reflection->getProperty('specificAttributes');
        $property->setAccessible(true);
        $attributes = $property->getValue($this->fieldType);

        $this->assertContains('geometry_type', $attributes);
        $this->assertContains('srid', $attributes);
        $this->assertContains('dimensions', $attributes);
    }

    /** @test */
    public function it_can_transform_point_geometry()
    {
        $input = ['type' => 'POINT', 'coordinates' => [12.5, 34.7]];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(12.5 34.7)', $result);
    }

    /** @test */
    public function it_can_transform_linestring_geometry()
    {
        $input = [
            'type' => 'LINESTRING',
            'coordinates' => [[12.5, 34.7], [56.8, 90.1]],
        ];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('LINESTRING(12.5 34.7, 56.8 90.1)', $result);
    }

    /** @test */
    public function it_can_transform_polygon_geometry()
    {
        $input = [
            'type' => 'POLYGON',
            'coordinates' => [
                [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
            ],
        ];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POLYGON((0 0, 10 0, 10 10, 0 10, 0 0))', $result);
    }

    /** @test */
    public function it_preserves_wkt_strings()
    {
        $wkt = 'POINT(12.5 34.7)';
        $result = $this->fieldType->transformValue($wkt);

        $this->assertEquals($wkt, $result);
    }

    /** @test */
    public function it_handles_srid_configuration()
    {
        $fieldType = new GeometryFieldType(['srid' => 4326]);

        $input = ['type' => 'POINT', 'coordinates' => [12.5, 34.7]];
        $result = $fieldType->transformValue($input);

        $this->assertEquals('SRID=4326;POINT(12.5 34.7)', $result);
    }

    /** @test */
    public function it_validates_geometry_types()
    {
        $supportedTypes = array_keys(GeometryFieldType::getSupportedGeometryTypes());

        $expectedTypes = [
            'POINT', 'LINESTRING', 'POLYGON', 'MULTIPOINT',
            'MULTILINESTRING', 'MULTIPOLYGON', 'GEOMETRYCOLLECTION',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $supportedTypes);
        }
    }

    /** @test */
    public function it_validates_configuration()
    {
        $validConfig = [
            'geometry_type' => 'POINT',
            'srid' => 4326,
            'dimensions' => 2,
        ];

        $errors = $this->fieldType->validate($validConfig);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_rejects_invalid_geometry_type()
    {
        $invalidConfig = ['geometry_type' => 'INVALID_TYPE'];

        $errors = $this->fieldType->validate($invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Invalid geometry type', $errors[0]);
    }

    /** @test */
    public function it_returns_mysql_column_definition()
    {
        $definition = $this->fieldType->getMySQLColumnDefinition();
        $this->assertEquals('GEOMETRY', $definition);
    }

    /** @test */
    public function it_returns_postgresql_column_definition()
    {
        $definition = $this->fieldType->getPostgreSQLColumnDefinition();
        $this->assertEquals('GEOMETRY', $definition);
    }

    /** @test */
    public function it_returns_sqlite_column_definition()
    {
        $definition = $this->fieldType->getSQLiteColumnDefinition();
        $this->assertEquals('TEXT', $definition);
    }

    /** @test */
    public function it_handles_null_values()
    {
        $this->assertNull($this->fieldType->transformValue(null));
        $this->assertNull($this->fieldType->transformValue(''));
    }

    /** @test */
    public function it_handles_multipoint_geometry()
    {
        $input = [
            'type' => 'MULTIPOINT',
            'coordinates' => [[10, 40], [40, 30], [20, 20], [30, 10]],
        ];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('MULTIPOINT((10 40), (40 30), (20 20), (30 10))', $result);
    }

    /** @test */
    public function it_handles_geometry_collection()
    {
        $input = [
            'type' => 'GEOMETRYCOLLECTION',
            'geometries' => [
                ['type' => 'POINT', 'coordinates' => [12.5, 34.7]],
                ['type' => 'LINESTRING', 'coordinates' => [[0, 0], [1, 1]]],
            ],
        ];
        $result = $this->fieldType->transformValue($input);

        $this->assertStringContainsString('GEOMETRYCOLLECTION', $result);
        $this->assertStringContainsString('POINT(12.5 34.7)', $result);
        $this->assertStringContainsString('LINESTRING(0 0, 1 1)', $result);
    }
}
