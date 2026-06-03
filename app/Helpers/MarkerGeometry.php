<?php

namespace App\Helpers;

use App\Enums\CornerPosition;
use InvalidArgumentException;

final class MarkerGeometry
{
    public static function euclideanDistance(float $x1, float $y1, float $x2, float $y2): float
    {
        return sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2);
    }

    /**
     * Return the pixel dimensions of a marker from its corner models.
     *
     * @param  iterable  $corners  ArucoMarkerCorner models (or plain objects) with position, x, y.
     * @return array{width: float, height: float}
     */
    public static function markerDimensions(iterable $corners): array
    {
        $corners = collect($corners);
        $tl = $corners->firstWhere('position', CornerPosition::TopLeft);
        $tr = $corners->firstWhere('position', CornerPosition::TopRight);
        $bl = $corners->firstWhere('position', CornerPosition::BottomLeft);

        return [
            'width' => ($tl && $tr) ? self::euclideanDistance($tl->x, $tl->y, $tr->x, $tr->y) : 0.0,
            'height' => ($tl && $bl) ? self::euclideanDistance($tl->x, $tl->y, $bl->x, $bl->y) : 0.0,
        ];
    }

    /**
     * Average of a marker's width and height in pixels.
     * Returns 0.0 when the required corners are missing.
     *
     * @param  iterable  $corners  ArucoMarkerCorner models with position, x, y.
     */
    public static function markerSize(iterable $corners): float
    {
        $dims = self::markerDimensions($corners);

        return ($dims['width'] + $dims['height']) / 2.0;
    }

    /**
     * Effective size of a marker including its OCR hitbox, in pixels.
     * The hitbox extends the physical marker by xPos/xNeg marker-widths horizontally
     * and yPos/yNeg marker-heights vertically (from config/marker_config.php).
     * Returns 0.0 when corners are missing.
     */
    public static function markerEffectiveSize(object $marker): float
    {
        $dims = self::markerDimensions($marker->corners);
        $hitbox = self::resolveHitbox((int) $marker->marker_id);

        $effectiveWidth = $dims['width'] * (1 + $hitbox['xPos'] + $hitbox['xNeg']);
        $effectiveHeight = $dims['height'] * (1 + $hitbox['yPos'] + $hitbox['yNeg']);

        return ($effectiveWidth + $effectiveHeight) / 2.0;
    }

    /**
     * World-coordinate center of the OCR hitbox area surrounding a marker.
     *
     * Hitbox offsets are in the marker's local frame (xPos = forward/right, xNeg = back/left).
     * The offset vector is rotated by $rotationDeg to produce world-space coordinates.
     * For a symmetric hitbox (xPos == xNeg, yPos == yNeg) this equals the marker center.
     *
     * @param  array{xPos: float, xNeg: float, yPos: float, yNeg: float}  $hitbox
     * @return array{x: float, y: float}
     */
    public static function hitboxCenter(
        float $cx, float $cy,
        float $markerW, float $markerH,
        array $hitbox,
        float $rotationDeg
    ): array {
        $dxLocal = ($hitbox['xPos'] - $hitbox['xNeg']) / 2.0 * $markerW;
        $dyLocal = ($hitbox['yPos'] - $hitbox['yNeg']) / 2.0 * $markerH;

        $rad = deg2rad($rotationDeg);

        return [
            'x' => $cx + cos($rad) * $dxLocal - sin($rad) * $dyLocal,
            'y' => $cy + sin($rad) * $dxLocal + cos($rad) * $dyLocal,
        ];
    }

    /**
     * Compute the world-coordinate hitbox center directly from an ArucoMarker model.
     * Convenience wrapper around markerDimensions() + hitboxCenter() + resolveHitbox().
     *
     * @return array{x: float, y: float}
     */
    public static function markerHitboxCenter(object $marker): array
    {
        $dims = self::markerDimensions($marker->corners);

        return self::hitboxCenter(
            (float) $marker->center_x, (float) $marker->center_y,
            $dims['width'], $dims['height'],
            self::resolveHitbox((int) $marker->marker_id),
            (float) $marker->rotation
        );
    }

    /**
     * World-coordinate corners of the hitbox rectangle around a marker.
     *
     * @param  array{xPos: float, xNeg: float, yPos: float, yNeg: float}  $hitbox
     * @return array<array{x: float, y: float}> Four corners in local TL→TR→BR→BL order.
     */
    public static function hitboxCorners(
        float $cx, float $cy,
        float $markerW,
        array $hitbox,
        float $rotationDeg
    ): array {
        $rRad = deg2rad($rotationDeg);
        $cosR = cos($rRad);
        $sinR = sin($rRad);

        $local = [
            [-(0.5 + $hitbox['xNeg']) * $markerW, -(0.5 + $hitbox['yNeg']) * $markerW],
            [(0.5 + $hitbox['xPos']) * $markerW, -(0.5 + $hitbox['yNeg']) * $markerW],
            [(0.5 + $hitbox['xPos']) * $markerW,  (0.5 + $hitbox['yPos']) * $markerW],
            [-(0.5 + $hitbox['xNeg']) * $markerW,  (0.5 + $hitbox['yPos']) * $markerW],
        ];

        return array_map(fn ($lc) => [
            'x' => round($cx + $lc[0] * $cosR - $lc[1] * $sinR, 2),
            'y' => round($cy + $lc[0] * $sinR + $lc[1] * $cosR, 2),
        ], $local);
    }

    /**
     * Test whether a point (px, py) lies inside a rotated rectangle defined by its four corners.
     *
     * $corners must be in TL→TR→BR→BL order, as returned by hitboxCorners().
     * Uses two dot-product projections onto the rectangle's own axes — no AABB needed.
     */
    public static function pointInHitbox(float $px, float $py, array $corners): bool
    {
        $tl = $corners[0];
        $tr = $corners[1];
        $bl = $corners[3];

        $ux = $tr['x'] - $tl['x'];
        $uy = $tr['y'] - $tl['y'];
        $vx = $bl['x'] - $tl['x'];
        $vy = $bl['y'] - $tl['y'];
        $dx = $px - $tl['x'];
        $dy = $py - $tl['y'];

        $dotUU = $ux * $ux + $uy * $uy;
        $dotVV = $vx * $vx + $vy * $vy;

        if ($dotUU === 0.0 || $dotVV === 0.0) {
            return false;
        }

        $projU = ($dx * $ux + $dy * $uy) / $dotUU;
        $projV = ($dx * $vx + $dy * $vy) / $dotVV;

        return $projU >= 0.0 && $projU <= 1.0 && $projV >= 0.0 && $projV <= 1.0;
    }

    /**
     * Look up and validate the OCR hitbox for the given marker ID.
     *
     * @throws InvalidArgumentException When the hitbox boundaries cross each other.
     */
    public static function resolveHitbox(int $markerId): array
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
