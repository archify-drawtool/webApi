<?php

use App\Helpers\MarkerGeometry;

describe('euclideanDistance', function () {
    test('returns 0 for identical points', function () {
        expect(MarkerGeometry::euclideanDistance(3.0, 4.0, 3.0, 4.0))->toBe(0.0);
    });

    test('returns correct distance for axis-aligned points', function () {
        expect(MarkerGeometry::euclideanDistance(0.0, 0.0, 5.0, 0.0))->toBe(5.0);
        expect(MarkerGeometry::euclideanDistance(0.0, 0.0, 0.0, 5.0))->toBe(5.0);
    });

    test('returns correct distance for a 3-4-5 triangle', function () {
        expect(MarkerGeometry::euclideanDistance(0.0, 0.0, 3.0, 4.0))->toBe(5.0);
    });
});

describe('resolveHitbox', function () {
    test('all configured hitboxes in marker_config.php have non-crossing boundaries', function () {
        $markerConfig = require base_path('config/marker_config.php');
        config(['marker_config' => $markerConfig]);

        foreach (array_keys($markerConfig) as $markerId) {
            expect(fn () => MarkerGeometry::resolveHitbox($markerId))
                ->not->toThrow(InvalidArgumentException::class);
        }
    });

    test('returns default hitbox for unknown marker ID', function () {
        config(['marker_config' => [
            'default' => ['type' => 'node', 'hitbox' => ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0]],
        ]]);

        $hitbox = MarkerGeometry::resolveHitbox(999);

        expect($hitbox)->toBe(['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0]);
    });

    test('throws when x boundaries cross', function () {
        config(['marker_config' => [
            1 => ['type' => 'node', 'hitbox' => ['xPos' => -0.8, 'xNeg' => -0.5, 'yPos' => 1.0, 'yNeg' => 1.0]],
        ]]);

        expect(fn () => MarkerGeometry::resolveHitbox(1))
            ->toThrow(InvalidArgumentException::class, 'xNeg');
    });

    test('throws when y boundaries cross', function () {
        config(['marker_config' => [
            2 => ['type' => 'node', 'hitbox' => ['xPos' => 1.0, 'xNeg' => 1.0, 'yPos' => -0.8, 'yNeg' => -0.5]],
        ]]);

        expect(fn () => MarkerGeometry::resolveHitbox(2))
            ->toThrow(InvalidArgumentException::class, 'yNeg');
    });
});

describe('hitboxCenter', function () {
    test('symmetric hitbox returns the marker center unchanged', function () {
        $hitbox = ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0];
        $center = MarkerGeometry::hitboxCenter(100.0, 200.0, 40.0, 40.0, $hitbox, 0.0);

        expect($center['x'])->toBe(100.0)
            ->and($center['y'])->toBe(200.0);
    });

    test('asymmetric hitbox with 0° rotation offsets along the x axis', function () {
        // xPos=3, xNeg=1 → dxLocal = (3-1)/2 * 50 = 50 (to the right)
        $hitbox = ['xPos' => 3.0, 'xNeg' => 1.0, 'yPos' => 2.0, 'yNeg' => 2.0];
        $center = MarkerGeometry::hitboxCenter(100.0, 100.0, 50.0, 50.0, $hitbox, 0.0);

        expect(round($center['x'], 6))->toBe(150.0)
            ->and(round($center['y'], 6))->toBe(100.0);
    });

    test('asymmetric hitbox with 90° rotation rotates the offset vector', function () {
        // xPos=3, xNeg=1 → dxLocal=50, dyLocal=0; at 90° (cos=0, sin=1):
        // world dx = cos(90)*50 - sin(90)*0 = 0
        // world dy = sin(90)*50 + cos(90)*0 = 50
        $hitbox = ['xPos' => 3.0, 'xNeg' => 1.0, 'yPos' => 2.0, 'yNeg' => 2.0];
        $center = MarkerGeometry::hitboxCenter(100.0, 100.0, 50.0, 50.0, $hitbox, 90.0);

        expect(round($center['x'], 6))->toBe(100.0)
            ->and(round($center['y'], 6))->toBe(150.0);
    });
});
