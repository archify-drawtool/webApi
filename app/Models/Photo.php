<?php

namespace App\Models;

use App\Enums\PhotoStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $fillable = [
        'project_id',
        'filename',
        'path',
        'status',
        'error_message',
        'sketch_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => PhotoStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sketch(): BelongsTo
    {
        return $this->belongsTo(Sketch::class);
    }
}
