<?php

return [
    'python_path' => env('ARUCO_PYTHON_PATH', 'python3'),
    'script_path' => base_path(env('ARUCO_SCRIPT_PATH', 'scripts/detect_aruco.py')),
    'dictionary' => env('ARUCO_DICTIONARY', 'DICT_ARUCO_MIP_36h12'),
    'timeout' => (int) env('ARUCO_TIMEOUT', 30),
    'edge_margin' => (float) env('ARUCO_EDGE_MARGIN', 0.5),
    'edge_angle_margin' => (float) env('ARUCO_EDGE_ANGLE_MARGIN', 5.0),
];
