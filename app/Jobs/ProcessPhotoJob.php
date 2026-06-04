<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\PhotoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Photo $photo, public ?int $userId = null) {}

    public function handle(PhotoService $photoService): void
    {
        $photoService->process($this->photo, $this->userId);
    }
}
