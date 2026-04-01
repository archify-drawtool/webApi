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
     * The snippet's size is based on values configured in `ocr_hitboxes.php` with the marker's clockwise rotation undone so that
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
        // TODO dit is niet perfect representatief van de W en H, omdat de markers een hoek kunnen hebben. Maar aangezien het grotendeels orthogonaal is is het verwaarloosbaar.
        $corners = $marker['corners'];
        $markerW = $this->euclideanDistance($corners[0], $corners[1]); // TL → TR
        $markerH = $this->euclideanDistance($corners[0], $corners[3]); // TL → BL

        $hitbox = $this->resolveHitbox((int) $marker['id']);
        $snippetW = (int) round($markerW * (1.0 + $hitbox['xPos'] + $hitbox['xNeg']));
        $snippetH = (int) round($markerH * (1.0 + $hitbox['yPos'] + $hitbox['yNeg']));

        // imagerotate() rotates counter-clockwise (ccw) for positive angles.
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
        $centerXRotated = cos($radians) * ($centerX - $originalW / 2)
            + sin($radians) * ($centerY - $originalH / 2)
            + $newW / 2;
        $centerYRotated = -sin($radians) * ($centerX - $originalW / 2)
            + cos($radians) * ($centerY - $originalH / 2)
            + $newH / 2;

        // Crop rectangle anchored to the marker edges, offset by the hitbox.
        $cropX = (int) round($centerXRotated - $markerW / 2 - $hitbox['xNeg'] * $markerW);
        $cropY = (int) round($centerYRotated - $markerH / 2 - $hitbox['yNeg'] * $markerH);

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

    /**
     * Look up and validate the OCR hitbox for the given marker ID.
     *
     * @throws InvalidArgumentException When the hitbox boundaries cross each other. //TODO dit kan ook toegestaan worden, maar dan krijg je potentieel ongewenst gedrag.
     */
    private function resolveHitbox(int $markerId): array
    {
        $config = config('ocr_hitboxes', []);
        $hitbox = $config[$markerId] ?? ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0];

        if (($hitbox['xPos'] + $hitbox['xNeg']) <= -1.0) {
            throw new InvalidArgumentException(
                "OCR hitbox for marker {$markerId}: xNeg ({$hitbox['xNeg']}) surpasses xPos ({$hitbox['xPos']})."
            );
        }
        if (($hitbox['yPos'] + $hitbox['yNeg']) <= -1.0) {
            throw new InvalidArgumentException(
                "OCR hitbox for marker {$markerId}: yNeg ({$hitbox['yNeg']}) surpasses yPos ({$hitbox['yPos']})."
            );
        }

        return $hitbox;
    }
}
