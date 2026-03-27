<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class ArucoService
{
    private string $pythonPath;

    private string $scriptPath;

    private string $dictionary;

    private int $timeout;

    public function __construct()
    {
        $this->pythonPath = config('aruco.python_path', 'python3');
        $this->scriptPath = config('aruco.script_path', base_path('scripts/detect_aruco.py'));
        $this->dictionary = config('aruco.dictionary', 'DICT_4X4_50');
        $this->timeout = (int) config('aruco.timeout', 30);
    }

    /**
     * Detect ArUco markers in an image.
     *
     * Returns an array of detected markers, each containing:
     *   - id (int): the ArUco marker ID
     *   - center (array): ['x' => float, 'y' => float] in image pixels
     *   - corners (array): 4 points [['x' => float, 'y' => float], ...] in TL, TR, BR, BL order
     *   - rotation (float): 2D clockwise angle in degrees relative to horizontal, range (-180, 180]
     *
     * Returns an empty array when no markers are detected (not an error).
     *
     * @throws InvalidArgumentException When the image file does not exist.
     * @throws RuntimeException When the script is missing, Python is unavailable, or detection fails.
     */
    public function detectMarkers(string $imagePath): array
    {
        if (! file_exists($imagePath)) {
            throw new InvalidArgumentException("Image file not found: {$imagePath}");
        }

        if (! file_exists($this->scriptPath)) {
            throw new RuntimeException("ArUco detection script not found: {$this->scriptPath}");
        }

        $output = $this->executeScript($this->buildCommand($imagePath));

        return $this->parseOutput($output);
    }

    private function buildCommand(string $imagePath): string
    {
        return sprintf(
            '%s %s --image %s --dictionary %s',
            escapeshellcmd($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($imagePath),
            escapeshellarg($this->dictionary),
        );
    }

    private function executeScript(string $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start ArUco detection process. Is Python available?');
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (! feof($pipes[1]) || ! feof($pipes[2])) {
            if ((time() - $start) > $this->timeout) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                throw new RuntimeException("ArUco detection timed out after {$this->timeout} seconds.");
            }

            $read = array_filter([$pipes[1], $pipes[2]], fn ($p) => ! feof($p));
            $write = null;
            $except = null;

            if (empty($read) || stream_select($read, $write, $except, 1) === false) {
                break;
            }

            if (! feof($pipes[1])) {
                $stdout .= fread($pipes[1], 8192);
            }
            if (! feof($pipes[2])) {
                $stderr .= fread($pipes[2], 8192);
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            Log::error('ArUco detection script failed', [
                'exit_code' => $exitCode,
                'stderr' => $stderr,
            ]);

            throw new RuntimeException("ArUco detection script exited with code {$exitCode}: {$stderr}");
        }

        return $stdout;
    }

    private function parseOutput(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('ArUco script returned invalid JSON: '.json_last_error_msg());
        }

        if (! isset($decoded['markers']) || ! is_array($decoded['markers'])) {
            throw new RuntimeException('ArUco script JSON missing expected "markers" key.');
        }

        return $decoded['markers'];
    }
}
