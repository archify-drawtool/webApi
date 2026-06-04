<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Sketch extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Sketch $sketch) {
            if (empty($sketch->title)) {
                $sketch->title = self::generateTitle();
            }
        });
    }

    private static function generateTitle(): string
    {
        $maanden = [
            'januari', 'februari', 'maart', 'april', 'mei', 'juni',
            'juli', 'augustus', 'september', 'oktober', 'november', 'december',
        ];

        $now = Carbon::now();

        return sprintf('Schets %d %s %s', $now->day, $maanden[$now->month - 1], $now->format('H:i'));
    }

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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function photo(): HasOne
    {
        return $this->hasOne(Photo::class);
    }
}
