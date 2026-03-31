<?php

namespace App\Services;

use GdImage;
use RuntimeException;

class ImageSnippetService
{
    /**
     * Extract a rotation-corrected image snippet around an ArUco marker.
     *
     * The snippet is 5× the marker width by 5× the marker height, centered on
     * the marker center, with the marker's clockwise rotation undone so that
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

        $cx = (float) $marker['center']['x'];
        $cy = (float) $marker['center']['y'];

        // corners: TL=0, TR=1, BR=2, BL=3
        $corners = $marker['corners'];
        $markerW = $this->euclideanDistance($corners[0], $corners[1]); // TL → TR
        $markerH = $this->euclideanDistance($corners[0], $corners[3]); // TL → BL

        $snippetW = (int) round($markerW * 5);
        $snippetH = (int) round($markerH * 5);

        // imagerotate() rotates counter-clockwise for positive angles.
        // The marker rotation is clockwise, so passing it directly undoes the tilt.
        $ccwDeg = (float) $marker['rotation'];
        $rotated = imagerotate($src, $ccwDeg, 0);
        imagedestroy($src);

        if ($rotated === false) {
            throw new RuntimeException('imagerotate() failed.');
        }

        $newW = imagesx($rotated);
        $newH = imagesy($rotated);

        // Map the original marker center to its position in the rotated canvas.
        // imagerotate() rotates visually CCW (Y-down space), whose forward transform is:
        //   x' =  cos(θ)·rx + sin(θ)·ry
        //   y' = −sin(θ)·rx + cos(θ)·ry
        $radians = deg2rad($ccwDeg);
        $cx_rot = cos($radians) * ($cx - $originalW / 2)
            + sin($radians) * ($cy - $originalH / 2)
            + $newW / 2;
        $cy_rot = -sin($radians) * ($cx - $originalW / 2)
            + cos($radians) * ($cy - $originalH / 2)
            + $newH / 2;

        // Crop rectangle centered on the transformed marker center.
        $cropX = (int) round($cx_rot - $snippetW / 2);
        $cropY = (int) round($cy_rot - $snippetH / 2);

        // Clamp to canvas bounds.
        $cropX = max(0, min($cropX, $newW - 1));
        $cropY = max(0, min($cropY, $newH - 1));
        $snippetW = min($snippetW, $newW - $cropX);
        $snippetH = min($snippetH, $newH - $cropY);

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
            throw new RuntimeException("Cannot read image: {$path}");
        }

        $image = match ($imageInfo[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => throw new RuntimeException(
                "Unsupported image type (IMAGETYPE constant {$imageInfo[2]}): {$path}"
            ),
        };

        if ($image === false) {
            throw new RuntimeException("GD failed to load image: {$path}");
        }

        return $image;
    }

    private function euclideanDistance(array $pointA, array $pointB): float
    {
        return sqrt(($pointB['x'] - $pointA['x']) ** 2 + ($pointB['y'] - $pointA['y']) ** 2);
    }
}
