<?php

namespace App\Jobs;

use App\Models\PhotoPreview;
use App\Services\PhotoPreviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPhotoPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public PhotoPreview $preview) {}

    public function handle(PhotoPreviewService $service): void
    {
        $service->runDetection($this->preview);
    }
}
