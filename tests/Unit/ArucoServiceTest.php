<?php

use App\Services\ArucoService;

beforeEach(function () {
    $this->service = new ArucoService;
});

test('detectMarkers throws InvalidArgumentException for missing image', function () {
    expect(fn () => $this->service->detectMarkers('/nonexistent/image.jpg'))
        ->toThrow(InvalidArgumentException::class, 'Image file not found');
});

test('detectMarkers throws RuntimeException when script is missing', function () {
    config(['aruco.script_path' => '/nonexistent/detect_aruco.py']);
    $service = new ArucoService;

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_').'.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        expect(fn () => $service->detectMarkers($tmpImage))
            ->toThrow(RuntimeException::class, 'ArUco detection script not found');
    } finally {
        @unlink($tmpImage);
    }
});

test('detectMarkers throws RuntimeException on non-zero exit code', function () {
    $scriptPath = tempnam(sys_get_temp_dir(), 'aruco_script_').'.py';
    file_put_contents($scriptPath, "import sys\nprint('error', file=sys.stderr)\nsys.exit(1)\n");

    config([
        'aruco.script_path' => $scriptPath,
        'aruco.python_path' => pythonBinary(),
    ]);
    $service = new ArucoService;

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_').'.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        expect(fn () => $service->detectMarkers($tmpImage))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($tmpImage);
        @unlink($scriptPath);
    }
});

describe('with real images', function () {
    beforeEach(function () {
        config(['aruco.python_path' => pythonBinary()]);
        $this->service = new ArucoService;
    });

    test('detects zero markers in an image with no markers', function () {
        $result = $this->service->detectMarkers(fixturesPath('aruco/0_markers.jpg'));
        expect($result)->toBe([]);
    });

    test('detects all 3 unique markers', function () {
        $result = $this->service->detectMarkers(fixturesPath('aruco/3_markers.jpg'));
        expect($result)->toHaveCount(3);
        $ids = array_column($result, 'id');
        expect(array_unique($ids))->toHaveCount(3);
    });

    test('detects 3 markers including 2 instances of a duplicate ID', function () {
        $result = $this->service->detectMarkers(fixturesPath('aruco/3_markers_2_duplicate.jpg'));
        expect($result)->toHaveCount(3);
        $counts = array_count_values(array_column($result, 'id'));
        expect(max($counts))->toBe(2);
    });

    test('detects all 13 markers in a slightly angled image', function () {
        $result = $this->service->detectMarkers(fixturesPath('aruco/13_markers_angle.jpg'));
        expect($result)->toHaveCount(13);
    });
})->skip(fn () => ! file_exists(config('aruco.script_path')), 'ArUco detection script not available');
