<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sketch extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'project_id',
        'created_by',
        'canvas_state',
    ];

    protected $casts = [
        'canvas_state' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sharedLink(): HasOne
    {
        return $this->hasOne(SharedLink::class);
    }
}
