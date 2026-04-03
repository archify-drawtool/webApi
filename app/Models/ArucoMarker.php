<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArucoMarker extends Model
{
    protected $fillable = [
        'detection_result_id',
        'marker_id',
        'center_x',
        'center_y',
        'rotation',
        'ocr_text',
    ];

    public function detectionResult(): BelongsTo
    {
        return $this->belongsTo(DetectionResult::class);
    }

    public function corners(): HasMany
    {
        return $this->hasMany(ArucoMarkerCorner::class);
    }
}
