<?php

return [
    'python_path' => env('ARUCO_PYTHON_PATH', 'python3'),
    'script_path' => env('ARUCO_SCRIPT_PATH', base_path('scripts/detect_aruco.py')),
    'dictionary'  => env('ARUCO_DICTIONARY', 'DICT_4X4_50'),
    'timeout'     => (int) env('ARUCO_TIMEOUT', 30),
];
