<?php

return [
    [
        'type' => 'rectangle',
        'name' => 'Rechthoek',
        'icon' => 'square',
        'aruco' => 1,
        'mermaid_shape' => 'rectangle',   // id["Label"]
        'drawio_shape' => 'rounded=0;whiteSpace=wrap;html=1;',
    ],
    [
        'type' => 'server',
        'name' => 'Server',
        'icon' => 'server',
        'aruco' => 2,
        'mermaid_shape' => 'subroutine',  // id[["Label"]]
        'drawio_shape' => 'rounded=1;whiteSpace=wrap;html=1;arcSize=10;',
    ],
    [
        'type' => 'database',
        'name' => 'Database',
        'icon' => 'database',
        'aruco' => 3,
        'mermaid_shape' => 'cylinder',    // id[("Label")]
        'drawio_shape' => 'shape=cylinder3;whiteSpace=wrap;html=1;boundedLbl=1;backgroundOutline=1;size=15;',
    ],
    [
        'type' => 'application',
        'name' => 'Applicatie',
        'icon' => 'layout-dashboard',
        'aruco' => 4,
        'mermaid_shape' => 'hexagon',     // id{{"Label"}}
        'drawio_shape' => 'shape=hexagon;perimeter=hexagonPerimeter2;whiteSpace=wrap;html=1;',
    ],
    [
        'type' => 'user',
        'name' => 'Gebruiker',
        'icon' => 'user',
        'aruco' => 5,
        'mermaid_shape' => 'circle',      // id(("Label"))
        'drawio_shape' => 'shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;html=1;',
    ],
    [
        'type' => 'cloud',
        'name' => 'Cloudomgeving',
        'icon' => 'cloud',
        'aruco' => 6,
        'mermaid_shape' => 'rectangle',      // id(("Label"))
        'drawio_shape' => 'ellipse;shape=cloud;whiteSpace=wrap;html=1;',
    ],
    [
        'type' => 'note',
        'name' => 'Notitie',
        'icon' => 'sticky-note',
        'aruco' => null,                  // Notes worden niet via ArUco gescand.
        'mermaid_shape' => 'note',        // N1["📝 Label"]
        'drawio_shape' => 'shape=note;whiteSpace=wrap;html=1;',
    ],
];
