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
     * @return array<array{x: float, y: float}>  Four corners in local TL→TR→BR→BL order.
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
            [ (0.5 + $hitbox['xPos']) * $markerW, -(0.5 + $hitbox['yNeg']) * $markerW],
            [ (0.5 + $hitbox['xPos']) * $markerW,  (0.5 + $hitbox['yPos']) * $markerW],
            [-(0.5 + $hitbox['xNeg']) * $markerW,  (0.5 + $hitbox['yPos']) * $markerW],
        ];

        return array_map(fn ($lc) => [
            'x' => round($cx + $lc[0] * $cosR - $lc[1] * $sinR, 2),
            'y' => round($cy + $lc[0] * $sinR + $lc[1] * $cosR, 2),
        ], $local);
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
