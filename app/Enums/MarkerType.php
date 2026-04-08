<?php

namespace App\Enums;

enum MarkerType: string
{
    case Node = 'node';
    case Directionless = 'directionless';
    case Monodirectional = 'monodirectional';
    case Bidirectional = 'bidirectional';
}
