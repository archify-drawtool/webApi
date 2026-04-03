<?php

/**
 * OCR hitbox configuration per ArUco marker ID.
 *
 * Values are in marker-size units, measured from the marker edges:
 *   xPos – extend in the ArUco +x direction (left on physical paper)
 *   xNeg – extend in the ArUco -x direction (right on physical paper)
 *   yPos – extend downward
 *   yNeg – extend upward
 *
 * Negative values pull the boundary inside the marker bounds, e.g. xNeg=-1
 * shifts the right boundary one marker-width inward (excluding the marker).
 *
 * All 4 values = 0  → scan exactly the marker area.
 * xPos + xNeg <= -1 → invalid (boundaries cross); will throw at runtime.
 *
 * The default (2, 2, 2, 2) reproduces the old 5× symmetric behaviour:
 *   snippetW = markerW × (1 + 2 + 2) = markerW × 5
 */
return [
    0 => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0],
    1 => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0],
    2 => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0],
    3 => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0],
    4 => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0],
];
