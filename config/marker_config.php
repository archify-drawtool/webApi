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
 * Default when marker ID is absent: type = 'node', hitbox = [2.0, 2.0, 2.0, 2.0].
 */
return [
    // --- Node markers ---
    // 0 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // 1 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // 2 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // 3 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // 4 => ['type' => 'node', 'hitbox' => ['xPos' => 0.0, 'xNeg' => 3.5, 'yPos' => 0.0, 'yNeg' => 0.0]],
    // --- Edge markers ---
    21 => ['type' => 'directionless',   'hitbox' => ['xPos' => 4, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 0.0]],
    22 => ['type' => 'monodirectional', 'hitbox' => ['xPos' => 4, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 0.0]],
    23 => ['type' => 'bidirectional',   'hitbox' => ['xPos' => 4, 'xNeg' => 0.0, 'yPos' => 0.0, 'yNeg' => 0.0]],
];
