<?php

namespace App\Models;

use App\Enums\MarkerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetectedEdge extends Model
{
    protected $fillable = [
        'detection_result_id',
        'edge_marker_id',
        'source_marker_id',
        'target_marker_id',
        'edge_type',
    ];

    protected function casts(): array
    {
        return [
            'edge_type' => MarkerType::class,
        ];
    }

    public function detectionResult(): BelongsTo
    {
        return $this->belongsTo(DetectionResult::class);
    }

    public function edgeMarker(): BelongsTo
    {
        return $this->belongsTo(ArucoMarker::class, 'edge_marker_id');
    }

    public function sourceMarker(): BelongsTo
    {
        return $this->belongsTo(ArucoMarker::class, 'source_marker_id');
    }

    public function targetMarker(): BelongsTo
    {
        return $this->belongsTo(ArucoMarker::class, 'target_marker_id');
    }
}
