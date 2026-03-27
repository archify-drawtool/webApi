<?php

use App\Services\ArucoService;

beforeEach(function () {
    $this->service = new ArucoService();
});

test('detectMarkers throws InvalidArgumentException for missing image', function () {
    expect(fn () => $this->service->detectMarkers('/nonexistent/image.jpg'))
        ->toThrow(InvalidArgumentException::class, 'Image file not found');
});

test('detectMarkers throws RuntimeException when script is missing', function () {
    config(['aruco.script_path' => '/nonexistent/detect_aruco.py']);
    $service = new ArucoService();

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_') . '.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        expect(fn () => $service->detectMarkers($tmpImage))
            ->toThrow(RuntimeException::class, 'ArUco detection script not found');
    } finally {
        @unlink($tmpImage);
    }
});

test('detectMarkers returns empty array when no markers are found', function () {
    $scriptPath = tempnam(sys_get_temp_dir(), 'aruco_script_') . '.py';
    file_put_contents($scriptPath, "import sys\nimport json\nprint(json.dumps({'markers': []}))\n");

    config([
        'aruco.script_path' => $scriptPath,
        'aruco.python_path' => 'python3',
    ]);
    $service = new ArucoService();

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_') . '.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        $result = $service->detectMarkers($tmpImage);
        expect($result)->toBe([]);
    } finally {
        @unlink($tmpImage);
        @unlink($scriptPath);
    }
});

test('detectMarkers returns structured marker array from script output', function () {
    $markerJson = json_encode([
        'markers' => [
            [
                'id'       => 3,
                'center'   => ['x' => 200.0, 'y' => 150.0],
                'corners'  => [
                    ['x' => 180.0, 'y' => 130.0],
                    ['x' => 220.0, 'y' => 130.0],
                    ['x' => 220.0, 'y' => 170.0],
                    ['x' => 180.0, 'y' => 170.0],
                ],
                'rotation' => 0.0,
            ],
        ],
    ]);

    $scriptPath = tempnam(sys_get_temp_dir(), 'aruco_script_') . '.py';
    file_put_contents($scriptPath, "import sys\nprint(" . var_export($markerJson, true) . ")\n");

    config([
        'aruco.script_path' => $scriptPath,
        'aruco.python_path' => 'python3',
    ]);
    $service = new ArucoService();

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_') . '.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        $result = $service->detectMarkers($tmpImage);

        expect($result)->toHaveCount(1)
            ->and($result[0]['id'])->toBe(3)
            ->and($result[0]['center'])->toBe(['x' => 200.0, 'y' => 150.0])
            ->and($result[0]['corners'])->toHaveCount(4)
            ->and($result[0]['rotation'])->toBe(0.0);
    } finally {
        @unlink($tmpImage);
        @unlink($scriptPath);
    }
});

test('detectMarkers throws RuntimeException on non-zero exit code', function () {
    $scriptPath = tempnam(sys_get_temp_dir(), 'aruco_script_') . '.py';
    file_put_contents($scriptPath, "import sys\nprint('error', file=sys.stderr)\nsys.exit(1)\n");

    config([
        'aruco.script_path' => $scriptPath,
        'aruco.python_path' => 'python3',
    ]);
    $service = new ArucoService();

    $tmpImage = tempnam(sys_get_temp_dir(), 'aruco_test_') . '.jpg';
    imagejpeg(imagecreatetruecolor(100, 100), $tmpImage);

    try {
        expect(fn () => $service->detectMarkers($tmpImage))
            ->toThrow(RuntimeException::class);
    } finally {
        @unlink($tmpImage);
        @unlink($scriptPath);
    }
});
