<?php

namespace App\Enums;

enum PhotoStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
