<?php

namespace App\Models;

use App\Enums\PhotoPreviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoPreview extends Model
{
    protected $fillable = [
        'user_id',
        'file_path',
        'status',
        'nodes_count',
        'edges_count',
        'detection_result_id',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PhotoPreviewStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detectionResult(): BelongsTo
    {
        return $this->belongsTo(DetectionResult::class);
    }
}
