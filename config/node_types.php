<?php

return [
    [
        'type' => 'rectangle',
        'name' => 'Rechthoek',
        'icon' => 'square',
        'aruco' => 1,
        'mermaid_shape' => 'rectangle',   // id["Label"]
    ],
    [
        'type' => 'server',
        'name' => 'Server',
        'icon' => 'server',
        'aruco' => 2,
        'mermaid_shape' => 'subroutine',  // id[["Label"]]
    ],
    [
        'type' => 'database',
        'name' => 'Database',
        'icon' => 'database',
        'aruco' => 3,
        'mermaid_shape' => 'cylinder',    // id[("Label")]
    ],
    [
        'type' => 'application',
        'name' => 'Applicatie',
        'icon' => 'layout-dashboard',
        'aruco' => 4,
        'mermaid_shape' => 'hexagon',     // id{{"Label"}}
    ],
    [
        'type' => 'user',
        'name' => 'Gebruiker',
        'icon' => 'user',
        'aruco' => 5,
        'mermaid_shape' => 'circle',      // id(("Label"))
    ],
];
