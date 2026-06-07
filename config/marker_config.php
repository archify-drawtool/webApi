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
    'default' => ['type' => 'node', 'hitbox' => ['xPos' => 0.4, 'xNeg' => 4.8, 'yPos' => 3.4, 'yNeg' => -1.0]],

    // --- Node markers ---

    // --- Edge markers ---
    21 => ['type' => 'directionless', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.8, 'yNeg' => -0.5]],
    22 => ['type' => 'monodirectional', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.8, 'yNeg' => -0.5]],
    23 => ['type' => 'bidirectional', 'hitbox' => ['xPos' => 2.7, 'xNeg' => 2.7, 'yPos' => 0.8, 'yNeg' => -0.5]],
    // --- Notities ---
    41 => ['type' => 'node', 'hitbox' => ['xPos' => 3.2, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 3.2]],
];
