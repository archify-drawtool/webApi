<?php

namespace App\Enums;

enum MarkerType: string
{
    case Node = 'node';
    case Directionless = 'directionless';
    case Monodirectional = 'monodirectional';
    case Bidirectional = 'bidirectional';

    public static function fromConfig(int $markerId, array $config): self
    {
        return self::from($config[$markerId]['type'] ?? self::Node->value);
    }
}
