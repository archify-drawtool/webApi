<?php

namespace App\Services;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

class ImageSnippetService
{
    /**
     * Extract a rotation-corrected image snippet around an ArUco marker.
     *
     * The snippet's size is based on values configured in `marker_config.php` with the marker's clockwise rotation undone so that
     * any surrounding text is axis-aligned before being sent to OCR.
     *
     * @param  string  $imagePath  Absolute path to the source image.
     * @param  array  $marker  Marker array from ArucoService::detectMarkers().
     * @return string Raw JPEG binary data.
     *
     * @throws RuntimeException When the image cannot be loaded or GD operations fail.
     */
    public function extractSnippet(string $imagePath, array $marker): string
    {
        $src = $this->loadImage($imagePath);
        $originalW = imagesx($src);
        $originalH = imagesy($src);

        $centerX = (float) $marker['center']['x'];
        $centerY = (float) $marker['center']['y'];

        // corners: TL=0, TR=1, BR=2, BL=3 (OpenCV)
        $corners = $marker['corners'];
        $markerW = $this->euclideanDistance($corners[0], $corners[1]); // TL → TR
        $markerH = $this->euclideanDistance($corners[0], $corners[3]); // TL → BL

        $hitbox = $this->resolveHitbox((int) $marker['id']);

        // imagerotate() rotates counter-clockwise (ccw) for positive angles.
        $ccwDeg = (float) $marker['rotation'];
        $rotated = $this->rotateImage($src, $ccwDeg);

        $newW = imagesx($rotated);
        $newH = imagesy($rotated);

        [$centerXRotated, $centerYRotated] = $this->mapCenterToRotatedCanvas(
            $centerX, $centerY, $originalW, $originalH, $ccwDeg, $newW, $newH
        );

        [$cropX, $cropY, $snippetW, $snippetH] = $this->calculateSnippetBounds(
            $centerXRotated, $centerYRotated, $markerW, $markerH, $hitbox, $newW, $newH
        );

        return $this->cropAndEncode($rotated, $cropX, $cropY, $snippetW, $snippetH);
    }

    private function rotateImage(GdImage $src, float $ccwDeg): GdImage
    {
        $rotated = imagerotate($src, $ccwDeg, 0);
        imagedestroy($src);

        if ($rotated === false) {
            throw new RuntimeException('imagerotate() failed.');
        }

        return $rotated;
    }

    /**
     * Map the original marker center to its position in the rotated canvas.
     * imagerotate() rotates visually CCW (Y-down space), whose forward transform is:
     *   x' =  cos(θ)·rx + sin(θ)·ry
     *   y' = −sin(θ)·rx + cos(θ)·ry
     *
     * @return array{float, float}
     */
    private function mapCenterToRotatedCanvas(
        float $centerX, float $centerY,
        int $originalW, int $originalH,
        float $ccwDeg,
        int $newW, int $newH
    ): array {
        $radians = deg2rad($ccwDeg);
        $rx = $centerX - $originalW / 2;
        $ry = $centerY - $originalH / 2;

        return [
            cos($radians) * $rx + sin($radians) * $ry + $newW / 2,
            -sin($radians) * $rx + cos($radians) * $ry + $newH / 2,
        ];
    }

    /**
     * Calculate the crop rectangle (anchored to marker edges, offset by hitbox) and clamp to canvas.
     *
     * @return array{int, int, int, int} [$cropX, $cropY, $snippetW, $snippetH]
     */
    private function calculateSnippetBounds(
        float $centerXRotated, float $centerYRotated,
        float $markerW, float $markerH,
        array $hitbox,
        int $canvasW, int $canvasH
    ): array {
        $snippetW = (int) round($markerW * (1.0 + $hitbox['xPos'] + $hitbox['xNeg']));
        $snippetH = (int) round($markerH * (1.0 + $hitbox['yPos'] + $hitbox['yNeg']));

        $cropX = (int) round($centerXRotated - $markerW / 2 - $hitbox['xNeg'] * $markerW);
        $cropY = (int) round($centerYRotated - $markerH / 2 - $hitbox['yNeg'] * $markerH);

        // Clamp to canvas bounds.
        $cropX = max(0, min($cropX, $canvasW - 1));
        $cropY = max(0, min($cropY, $canvasH - 1));
        $snippetW = min($snippetW, $canvasW - $cropX);
        $snippetH = min($snippetH, $canvasH - $cropY);

        return [$cropX, $cropY, $snippetW, $snippetH];
    }

    private function cropAndEncode(GdImage $rotated, int $cropX, int $cropY, int $snippetW, int $snippetH): string
    {
        $snippet = imagecreatetruecolor($snippetW, $snippetH);
        if ($snippet === false) {
            imagedestroy($rotated);
            throw new RuntimeException('imagecreatetruecolor() failed.');
        }

        $copied = imagecopy($snippet, $rotated, 0, 0, $cropX, $cropY, $snippetW, $snippetH);
        imagedestroy($rotated);

        if (! $copied) {
            imagedestroy($snippet);
            throw new RuntimeException('imagecopy() failed.');
        }

        ob_start();
        imagejpeg($snippet, null, 95);
        $imageData = ob_get_clean();
        imagedestroy($snippet);

        if ($imageData === false || $imageData === '') {
            throw new RuntimeException('imagejpeg() produced no output.');
        }

        return $imageData;
    }

    private function loadImage(string $path): GdImage
    {
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new RuntimeException("Cannot read image: $path");
        }

        $image = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => throw new RuntimeException(
                "Unsupported image type (IMAGETYPE constant $imageInfo[2]): $path"
            ),
        };

        if ($image === false) {
            throw new RuntimeException("GD failed to load image: $path");
        }

        return $image;
    }

    private function euclideanDistance(array $pointA, array $pointB): float
    {
        return sqrt(($pointB['x'] - $pointA['x']) ** 2 + ($pointB['y'] - $pointA['y']) ** 2);
    }

    /**
     * Look up and validate the OCR hitbox for the given marker ID.
     *
     * @throws InvalidArgumentException When the hitbox boundaries cross each other.
     */
    private function resolveHitbox(int $markerId): array
    {
        $config = config('marker_config', []);
        $hitbox = $config[$markerId]['hitbox'] ?? ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0];

        if (($hitbox['xPos'] + $hitbox['xNeg']) <= -1.0) {
            throw new InvalidArgumentException(
                "OCR hitbox for marker $markerId: xNeg ({$hitbox['xNeg']}) surpasses xPos ({$hitbox['xPos']})."
            );
        }
        if (($hitbox['yPos'] + $hitbox['yNeg']) <= -1.0) {
            throw new InvalidArgumentException(
                "OCR hitbox for marker $markerId: yNeg ({$hitbox['yNeg']}) surpasses yPos ({$hitbox['yPos']})."
            );
        }

        return $hitbox;
    }
}
