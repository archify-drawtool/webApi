<?php

namespace App\Models;

use App\Enums\CornerPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArucoMarkerCorner extends Model
{
    protected $fillable = [
        'aruco_marker_id',
        'position',
        'x',
        'y',
    ];

    protected function casts(): array
    {
        return [
            'position' => CornerPosition::class,
        ];
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(ArucoMarker::class);
    }
}
