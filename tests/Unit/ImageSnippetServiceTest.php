<?php

use App\Services\ImageSnippetService;

beforeEach(function () {
    $this->service = new ImageSnippetService;
});

describe('resolveHitbox', function () {
    test('all configured hitboxes in ocr_hitboxes.php have non-crossing boundaries', function () {
        $hitboxes = require base_path('config/ocr_hitboxes.php');
        config(['ocr_hitboxes' => $hitboxes]);

        $method = new ReflectionMethod(ImageSnippetService::class, 'resolveHitbox');

        foreach (array_keys($hitboxes) as $markerId) {
            expect(fn () => $method->invoke($this->service, $markerId))
                ->not->toThrow(InvalidArgumentException::class);
        }
    });

    test('throws when x boundaries cross', function () {
        config(['ocr_hitboxes' => [
            1 => ['xPos' => -0.8, 'xNeg' => -0.5, 'yPos' => 1.0, 'yNeg' => 1.0],
        ]]);

        $method = new ReflectionMethod(ImageSnippetService::class, 'resolveHitbox');

        expect(fn () => $method->invoke($this->service, 1))
            ->toThrow(InvalidArgumentException::class, 'xNeg');
    });

    test('throws when y boundaries cross', function () {
        config(['ocr_hitboxes' => [
            2 => ['xPos' => 1.0, 'xNeg' => 1.0, 'yPos' => -0.8, 'yNeg' => -0.5],
        ]]);

        $method = new ReflectionMethod(ImageSnippetService::class, 'resolveHitbox');

        expect(fn () => $method->invoke($this->service, 2))
            ->toThrow(InvalidArgumentException::class, 'yNeg');
    });
});

describe('mapCenterToRotatedCanvas', function () {
    test('returns unchanged center for 0 degree rotation', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'mapCenterToRotatedCanvas');

        // At 0°, rotated canvas size equals original, so the center should map to itself.
        [$x, $y] = $method->invoke($this->service, 200.0, 150.0, 400, 300, 0.0, 400, 300);

        expect($x)->toEqual(200.0)
            ->and($y)->toEqual(150.0);
    });

    test('maps exact image center to canvas center regardless of rotation', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'mapCenterToRotatedCanvas');

        // A point at the image center (rx=0, ry=0) should stay at the new canvas center.
        foreach ([0.0, 45.0, 90.0, 180.0] as $angle) {
            [$x, $y] = $method->invoke($this->service, 200.0, 150.0, 400, 300, $angle, 500, 400);

            expect($x)->toEqual(250.0, "x failed at {$angle}°")
                ->and($y)->toEqual(200.0, "y failed at {$angle}°");
        }
    });

    test('rotates a point 90 degrees correctly', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'mapCenterToRotatedCanvas');

        // Image 200×200, center at (100,100).
        // Point (150, 100) → rx=50, ry=0.
        // After 90° CCW: x'=cos(90)*50+sin(90)*0 = 0, y'=-sin(90)*50+cos(90)*0 = -50.
        // Mapped to new canvas (200×200): x = 0 + 100 = 100, y = -50 + 100 = 50.
        [$x, $y] = $method->invoke($this->service, 150.0, 100.0, 200, 200, 90.0, 200, 200);

        expect(round($x))->toEqual(100.0)
            ->and(round($y))->toEqual(50.0);
    });
});

describe('calculateSnippetBounds', function () {
    test('computes size and crop position from hitbox', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'calculateSnippetBounds');

        // Marker 100×50 at center (300, 200) on a 600×400 canvas, no hitbox expansion.
        $hitbox = ['xPos' => 0.0, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 0.0];
        [$cropX, $cropY, $w, $h] = $method->invoke($this->service, 300.0, 200.0, 100.0, 50.0, $hitbox, 600, 400);

        expect($cropX)->toBe(250)
            ->and($cropY)->toBe(175)
            ->and($w)->toBe(100)
            ->and($h)->toBe(50);
    });

    test('expands size by hitbox multipliers', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'calculateSnippetBounds');

        // Marker 100×100, hitbox expands by 1× on each vertical side → total factor 3×. hitbox expands 3× towards negative X, and -1× to positive X.
        $hitbox = ['xPos' => -1.0, 'xNeg' => 3.0, 'yPos' => 1.0, 'yNeg' => 1.0];
        [$cropX, $cropY, $snippetW, $snippetH] = $method->invoke($this->service, 500.0, 500.0, 100.0, 100.0, $hitbox, 1000, 1000);

        expect($cropX)->toBe(150)
            ->and($cropY)->toBe(350)
            ->and($snippetW)->toBe(300)
            ->and($snippetH)->toBe(300);
    });

    test('clamps crop position to canvas origin', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'calculateSnippetBounds');

        // Marker at top-left corner: center at (5,5) on a 100×100 canvas.
        $hitbox = ['xPos' => 1.0, 'xNeg' => 1.0, 'yPos' => 1.0, 'yNeg' => 1.0];
        [$cropX, $cropY] = $method->invoke($this->service, 5.0, 5.0, 10.0, 10.0, $hitbox, 100, 100);

        expect($cropX)->toBe(0)
            ->and($cropY)->toBe(0);
    });

    test('clamps snippet size to canvas edge', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'calculateSnippetBounds');

        // Marker at bottom-right corner so the snippet would overflow.
        $hitbox = ['xPos' => 1.0, 'xNeg' => 0.0, 'yPos' => 1.0, 'yNeg' => 0.0];
        [$cropX, $cropY, $w, $h] = $method->invoke($this->service, 95.0, 95.0, 10.0, 10.0, $hitbox, 100, 100);

        expect($cropX + $w)->toBeLessThanOrEqual(100)
            ->and($cropY + $h)->toBeLessThanOrEqual(100);
    });
});

describe('euclideanDistance', function () {
    test('returns 0 for identical points', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'euclideanDistance');

        expect($method->invoke($this->service, ['x' => 3.0, 'y' => 4.0], ['x' => 3.0, 'y' => 4.0]))->toBe(0.0);
    });

    test('returns correct distance for known points', function () {
        $method = new ReflectionMethod(ImageSnippetService::class, 'euclideanDistance');

        // 3-4-5 right triangle.
        expect($method->invoke($this->service, ['x' => 0.0, 'y' => 0.0], ['x' => 3.0, 'y' => 4.0]))->toBe(5.0);
    });
});

describe('extractSnippet', function () {
    test('returns valid JPEG data for a real image', function () {
        config(['ocr_hitboxes' => []]);

        $tmpImage = tempnam(sys_get_temp_dir(), 'snippet_test_').'.jpg';
        imagejpeg(imagecreatetruecolor(400, 300), $tmpImage);

        $marker = [
            'id' => 1,
            'center' => ['x' => 200.0, 'y' => 150.0],
            'corners' => [
                ['x' => 180.0, 'y' => 130.0], // TL
                ['x' => 220.0, 'y' => 130.0], // TR
                ['x' => 220.0, 'y' => 170.0], // BR
                ['x' => 180.0, 'y' => 170.0], // BL
            ],
            'rotation' => 0.0,
        ];

        try {
            $result = $this->service->extractSnippet($tmpImage, $marker);

            // JPEG files start with the SOI marker FF D8.
            expect(substr($result, 0, 2))->toBe("\xFF\xD8");
        } finally {
            @unlink($tmpImage);
        }
    });
});
