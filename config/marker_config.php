<?php

/**
 * Marker configuration per ArUco marker ID.
 *
 * Each entry has:
 *   type   – one of 'node', 'directionless', 'monodirectional', 'bidirectional'
 *   hitbox – OCR crop offsets in marker-size units, measured from the marker edges:
 *     xPos – extend in the ArUco +x direction (left on physical paper)
 *     xNeg – extend in the ArUco -x direction (right on physical paper)
 *     yPos – extend downward
 *     yNeg – extend upward
 *
 * Negative hitbox values pull the boundary inside the marker bounds.
 * All 4 hitbox values = 0 → scan exactly the marker area.
 * xPos + xNeg <= -1 → invalid (boundaries cross); will throw at runtime.
 *
 * The default (2, 2, 2, 2) reproduces the old 5× symmetric behaviour:
 *   snippetW = markerW × (1 + 2 + 2) = markerW × 5
 *
 * The 'default' key is used as fallback when a marker ID is not listed below.
 */
return [
    'default' => ['type' => 'node', 'hitbox' => ['xPos' => 2.0, 'xNeg' => 2.0, 'yPos' => 2.0, 'yNeg' => 2.0]],

    // --- Node markers ---
    1 => ['type' => 'node', 'hitbox' => ['xPos' => 1.6, 'xNeg' => 1.6, 'yPos' => 1.6, 'yNeg' => 1.6]],
    2 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.0, 'yPos' => 0.0, 'yNeg' => 4.0]],
    3 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.0, 'yPos' => 0.0, 'yNeg' => 4.0]],
    4 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.2, 'yPos' => 3.2, 'yNeg' => 0.0]],
    5 => ['type' => 'node', 'hitbox' => ['xPos' => 1.6, 'xNeg' => 1.6, 'yPos' => 1.4, 'yNeg' => 2.0]],
    6 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 5.0, 'yPos' => 0.0, 'yNeg' => 2.0]],
    // --- Edge markers ---
    21 => ['type' => 'directionless', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.0, 'yNeg' => 0.0]],
    22 => ['type' => 'monodirectional', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.0, 'yNeg' => 0.0]],
    23 => ['type' => 'bidirectional', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // --- Notities ---
    41 => ['type' => 'node', 'hitbox' => ['xPos' => 3.2, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 3.2]],
];
