<?php

return [
    'python_path' => env('ARUCO_PYTHON_PATH', 'python3'),
    'script_path' => base_path(env('ARUCO_SCRIPT_PATH', 'scripts/detect_aruco.py')),
    'dictionary' => env('ARUCO_DICTIONARY', 'DICT_ARUCO_MIP_36h12'),
    'timeout' => (int) env('ARUCO_TIMEOUT', 30),
    'edge_margin' => (float) env('ARUCO_EDGE_MARGIN', 0.5),
    'edge_angle_margin' => (float) env('ARUCO_EDGE_ANGLE_MARGIN', 5.0),

    // Iterative widening when an edge marker finds no source or target node.
    // On each retry attempt the angle margin is widened by edge_retry_angle_step degrees
    // and the base margin factor is increased by edge_retry_margin_step.
    // Set edge_retry_max_attempts to 0 to disable retry behaviour entirely.
    'edge_retry_max_attempts' => (int) env('ARUCO_EDGE_RETRY_MAX_ATTEMPTS', 3),
    'edge_retry_angle_step' => (float) env('ARUCO_EDGE_RETRY_ANGLE_STEP', 5.0),
    'edge_retry_margin_step' => (float) env('ARUCO_EDGE_RETRY_MARGIN_STEP', 0.25),
];
