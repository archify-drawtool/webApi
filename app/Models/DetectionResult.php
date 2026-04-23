<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetectionResult extends Model
{
    protected $fillable = [
        'filename',
        'image_path',
        'detection_failed',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'detection_failed' => 'boolean',
            'detected_at' => 'datetime',
        ];
    }

    public function markers(): HasMany
    {
        return $this->hasMany(ArucoMarker::class);
    }

    public function edges(): HasMany
    {
        return $this->hasMany(DetectedEdge::class);
    }
}
