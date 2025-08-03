<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Grazulex\LaravelModelschema\FieldTypes\GeometryFieldType;
use Grazulex\LaravelModelschema\FieldTypes\PointFieldType;
use Grazulex\LaravelModelschema\FieldTypes\PolygonFieldType;
use Grazulex\LaravelModelschema\Support\FieldTypeRegistry;
use PHPUnit\Framework\TestCase;

class GeometricFieldTypeRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FieldTypeRegistry::initialize();
    }

    /** @test */
    public function it_registers_point_field_type()
    {
        $this->assertTrue(FieldTypeRegistry::has('point'));
        $this->assertInstanceOf(PointFieldType::class, FieldTypeRegistry::get('point'));
    }

    /** @test */
    public function it_registers_geometry_field_type()
    {
        $this->assertTrue(FieldTypeRegistry::has('geometry'));
        $this->assertInstanceOf(GeometryFieldType::class, FieldTypeRegistry::get('geometry'));
    }

    /** @test */
    public function it_registers_polygon_field_type()
    {
        $this->assertTrue(FieldTypeRegistry::has('polygon'));
        $this->assertInstanceOf(PolygonFieldType::class, FieldTypeRegistry::get('polygon'));
    }

    /** @test */
    public function it_registers_geometric_aliases()
    {
        // Point aliases
        $this->assertTrue(FieldTypeRegistry::has('geopoint'));
        $this->assertTrue(FieldTypeRegistry::has('coordinates'));
        $this->assertTrue(FieldTypeRegistry::has('latlng'));

        // Geometry aliases
        $this->assertTrue(FieldTypeRegistry::has('geom'));
        $this->assertTrue(FieldTypeRegistry::has('spatial'));
        $this->assertTrue(FieldTypeRegistry::has('geo'));

        // Polygon aliases
        $this->assertTrue(FieldTypeRegistry::has('area'));
        $this->assertTrue(FieldTypeRegistry::has('boundary'));
        $this->assertTrue(FieldTypeRegistry::has('region'));
    }

    /** @test */
    public function aliases_return_correct_field_types()
    {
        $this->assertInstanceOf(PointFieldType::class, FieldTypeRegistry::get('geopoint'));
        $this->assertInstanceOf(PointFieldType::class, FieldTypeRegistry::get('coordinates'));
        $this->assertInstanceOf(GeometryFieldType::class, FieldTypeRegistry::get('geom'));
        $this->assertInstanceOf(PolygonFieldType::class, FieldTypeRegistry::get('area'));
    }
}
