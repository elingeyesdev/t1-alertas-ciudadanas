<?php

namespace Tests\Unit;

use App\Services\JwtService;
use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_haversine_distance_is_correct(): void
    {
        // ~111 km por grado de latitud
        $this->assertEqualsWithDelta(111195, Geo::distance(0, 0, 1, 0), 50);
        // Dos puntos en Santa Cruz separados ~1.6 km
        $d = Geo::distance(-17.7833, -63.1821, -17.7975, -63.1821);
        $this->assertEqualsWithDelta(1579, $d, 5);
    }

    public function test_distance_to_route(): void
    {
        $route = [['lat' => -17.78, 'lng' => -63.19], ['lat' => -17.78, 'lng' => -63.17]];
        // Punto 100 m al norte de la ruta
        $lat = -17.78 + rad2deg(100 / Geo::EARTH_RADIUS_M);
        $this->assertEqualsWithDelta(100, Geo::distanceToRoute($lat, -63.18, $route), 1);
        // Punto lejos del extremo
        $this->assertGreaterThan(1000, Geo::distanceToRoute(-17.78, -63.10, $route));
    }

    public function test_parse_duration(): void
    {
        $this->assertSame(3600, JwtService::parseDuration('1h'));
        $this->assertSame(259200, JwtService::parseDuration('72h'));
        $this->assertSame(1800, JwtService::parseDuration('30m'));
        $this->assertSame(604800, JwtService::parseDuration('7d'));
        $this->assertSame(45, JwtService::parseDuration('45'));
    }
}
