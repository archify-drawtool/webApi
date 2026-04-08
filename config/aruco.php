<?php

return [
    'python_path' => env('ARUCO_PYTHON_PATH', 'python3'),
    'script_path' => env('ARUCO_SCRIPT_PATH', base_path('scripts/detect_aruco.py')),
    'dictionary' => env('ARUCO_DICTIONARY', 'DICT_ARUCO_MIP_36h12'),
    'timeout' => (int) env('ARUCO_TIMEOUT', 30),
    'edge_margin' => (int) env('ARUCO_EDGE_MARGIN', 20),
];
