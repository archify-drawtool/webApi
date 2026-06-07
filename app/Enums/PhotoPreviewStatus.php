<?php

namespace App\Enums;

enum PhotoPreviewStatus: string
{
    case Detecting = 'detecting';
    case Detected = 'detected';
    case Failed = 'failed';
}
