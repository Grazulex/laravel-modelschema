<?php

declare(strict_types=1);

namespace Tests\Unit\FieldTypes;

use Grazulex\LaravelModelschema\FieldTypes\PointFieldType;
use PHPUnit\Framework\TestCase;

class PointFieldTypeTest extends TestCase
{
    private PointFieldType $fieldType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldType = new PointFieldType();
    }

    /** @test */
    public function it_can_transform_array_coordinates()
    {
        $input = [12.5, 34.7];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(12.5 34.7)', $result);
    }

    /** @test */
    public function it_can_transform_associative_array_coordinates()
    {
        $input = ['lat' => 48.8566, 'lng' => 2.3522];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(2.3522 48.8566)', $result);
    }

    /** @test */
    public function it_can_transform_latitude_longitude_array()
    {
        $input = ['latitude' => 40.7128, 'longitude' => -74.0060];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(-74.006 40.7128)', $result);
    }

    /** @test */
    public function it_can_transform_x_y_coordinates()
    {
        $input = ['x' => 100.5, 'y' => 200.8];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(100.5 200.8)', $result);
    }

    /** @test */
    public function it_preserves_wkt_format()
    {
        $input = 'POINT(12.5 34.7)';
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(12.5 34.7)', $result);
    }

    /** @test */
    public function it_can_handle_3d_coordinates()
    {
        $input = [12.5, 34.7, 100.0];
        $result = $this->fieldType->transformValue($input);

        $this->assertEquals('POINT(12.5 34.7 100)', $result);
    }

    /** @test */
    public function it_validates_point_format_correctly()
    {
        $this->assertTrue($this->fieldType->isValidPointFormat([12.5, 34.7]));
        $this->assertTrue($this->fieldType->isValidPointFormat(['lat' => 48.8566, 'lng' => 2.3522]));
        $this->assertTrue($this->fieldType->isValidPointFormat('POINT(12.5 34.7)'));

        $this->assertFalse($this->fieldType->isValidPointFormat([12.5]));
        $this->assertFalse($this->fieldType->isValidPointFormat(['lat' => 48.8566]));
        $this->assertFalse($this->fieldType->isValidPointFormat('INVALID'));
        $this->assertFalse($this->fieldType->isValidPointFormat(123));
    }

    /** @test */
    public function it_extracts_coordinates_from_wkt()
    {
        $wkt = 'POINT(12.5 34.7)';
        $coordinates = $this->fieldType->toCoordinates($wkt);

        $this->assertEquals([12.5, 34.7], $coordinates);
    }

    /** @test */
    public function it_extracts_3d_coordinates_from_wkt()
    {
        $wkt = 'POINT(12.5 34.7 100.0)';
        $coordinates = $this->fieldType->toCoordinates($wkt);

        $this->assertEquals([12.5, 34.7, 100.0], $coordinates);
    }

    /** @test */
    public function it_validates_numeric_coordinates()
    {
        $this->assertTrue($this->fieldType->validateValue([12.5, 34.7]));
        $this->assertTrue($this->fieldType->validateValue(['lat' => 48.8566, 'lng' => 2.3522]));

        // Invalid coordinates
        $this->assertFalse($this->fieldType->validateValue(['lat' => 'invalid', 'lng' => 2.3522]));
        $this->assertFalse($this->fieldType->validateValue([12.5, 'invalid']));
    }

    /** @test */
    public function it_validates_latitude_longitude_ranges()
    {
        // Valid latitude/longitude ranges
        $this->assertTrue($this->fieldType->validateValue(['lat' => 45.0, 'lng' => 90.0]));
        $this->assertTrue($this->fieldType->validateValue(['latitude' => -45.0, 'longitude' => -90.0]));

        // Invalid latitude (outside -90 to 90)
        $this->assertFalse($this->fieldType->validateValue(['lat' => 95.0, 'lng' => 2.3522]));
        $this->assertFalse($this->fieldType->validateValue(['latitude' => -95.0, 'longitude' => 2.3522]));

        // Invalid longitude (outside -180 to 180)
        $this->assertFalse($this->fieldType->validateValue(['lat' => 48.8566, 'lng' => 185.0]));
        $this->assertFalse($this->fieldType->validateValue(['latitude' => 48.8566, 'longitude' => -185.0]));
    }

    /** @test */
    public function it_handles_srid_configuration()
    {
        $fieldType = new PointFieldType(['srid' => 4326]);

        $input = [12.5, 34.7];
        $result = $fieldType->transformValue($input);

        $this->assertEquals('SRID=4326;POINT(12.5 34.7)', $result);
    }

    /** @test */
    public function it_handles_dimension_configuration()
    {
        $fieldType = new PointFieldType(['dimensions' => 3]);

        $input = [12.5, 34.7];
        $result = $fieldType->transformValue($input);

        // Should enforce 3D format
        $this->assertEquals('POINT(12.5 34.7 0)', $result);
    }

    /** @test */
    public function it_returns_default_value_when_configured()
    {
        $fieldType = new PointFieldType(['default' => 'POINT(0 0)']);

        $this->assertEquals('POINT(0 0)', $fieldType->getDefaultValue());
    }

    /** @test */
    public function it_returns_mysql_column_definition()
    {
        $definition = $this->fieldType->getMySQLColumnDefinition();

        $this->assertEquals('POINT', $definition);
    }

    /** @test */
    public function it_returns_postgresql_column_definition()
    {
        $definition = $this->fieldType->getPostgreSQLColumnDefinition();

        $this->assertEquals('POINT', $definition);
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
        $this->assertTrue($this->fieldType->validateValue(null));
    }

    /** @test */
    public function it_handles_empty_string()
    {
        $this->assertNull($this->fieldType->transformValue(''));
        $this->assertTrue($this->fieldType->validateValue(''));
    }
}
