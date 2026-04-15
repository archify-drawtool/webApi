<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Mermaid Arrow Types
     |--------------------------------------------------------------------------
     |
     | Defines the Mermaid arrow syntax used per detected edge direction.
     | Direction is determined by the presence of markerStart / markerEnd on
     | a VueFlow edge:
     |
     |   'none' — no markerStart, no markerEnd  → plain line
     |   'mono' — only markerEnd present        → one-directional arrow
     |   'bi'   — markerStart and markerEnd     → bidirectional arrow
     |
     | You can extend this map or change individual entries to customise how
     | edges are rendered in Mermaid without touching any service code.
     |
     | Mermaid arrow reference:
     |   ---    plain line (no arrow)
     |   -->    normal arrow
     |   <-->   bidirectional arrow
     |   -.->   dotted arrow
     |   ==>    thick arrow
     |
     */
    'arrows' => [
        'none' => '---',
        'mono' => '-->',
        'bi'   => '<-->',
    ],

    /*
     |--------------------------------------------------------------------------
     | Default Arrow
     |--------------------------------------------------------------------------
     |
     | Fallback arrow syntax used when the detected direction has no matching
     | entry in the 'arrows' map above.
     |
     */
    'default_arrow' => '-->',

];
