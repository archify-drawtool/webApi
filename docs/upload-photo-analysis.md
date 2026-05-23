# Upload Photo – Pipeline Analysis

## Pipeline Overview

```
POST /photos/upload
  └─ PhotoService::store()        — save file, create Photo (status=processing), dispatch job
       └─ ProcessPhotoJob::handle()
            └─ PhotoService::process()
                 1. normalizeExifOrientation()   — in-place JPEG rotation fix
                 2. ArucoService::detectMarkers() — Python subprocess → JSON
                 3. extractSnippets()            — N × loadImage + rotate + crop → JPEG bytes
                 4. OcrService::recognizeTextBatch() — single HTTP request to Vision API
                 5. persist ArucoMarker + ArucoMarkerCorner rows (N + 4N inserts)
                 6. EdgeDetectionService::detectEdges() — geometric search in PHP
                 7. persist DetectedEdge rows
                 8. VueFlowConversionService::convert() — build canvas_state JSON
                 9. Photo.status = completed
```

---

## 1. Memory – Image Snippet Processing

### What happens per marker

`extractSnippets()` maps `ImageSnippetService::extractSnippet()` over every detected marker.
Inside each call, three GD image objects are created and kept alive simultaneously:

| Object | Approximate size (12 MP / 4032×3024 JPEG) |
|--------|--------------------------------------------|
| `$src` — full source image decoded by GD | `4032 × 3024 × 4 B ≈ 49 MB` |
| `$rotated` — `imagerotate()` result (bounding box grows) | up to `5040 × 5040 × 4 B ≈ 102 MB` |
| `$snippet` — cropped region | small, typically < 1 MB |

**Peak memory inside one `extractSnippet()` call: ~150 MB for a 12 MP photo.**

PHP 8 GdImage objects call `imagedestroy()` in their destructor and are freed via reference
counting when they leave scope. Practically this means each call's objects *are* freed before
the next one starts — no images accumulate across the loop. However, **`imagedestroy()` is
never called explicitly**, so deallocation relies entirely on refcount dropping to zero at the
end of each closure invocation inside `array_map`.

### Concrete risk

The source image is re-loaded from disk **inside every call** (`loadImage($imagePath)` is called
N times). This is wasteful I/O-wise but does not accumulate memory as long as the previous
`$src` is released before the next is loaded. For 36 markers this holds, but the single-call
peak (~150 MB) leaves little headroom if `memory_limit` is 256 MB (the PHP default), and a
larger or higher-resolution photo could push past the limit without a clean exception.

### What is missing

- No explicit `imagedestroy($src)` and `imagedestroy($rotated)` before returning from
  `extractSnippet()`. Safe under the current flow but fragile.
- The source image is loaded once per marker; a simple refactor passes the already-loaded
  `GdImage` into `extractSnippet()` and loads it once for all markers, halving peak memory.

---

## 2. OCR Batch Size – No Limit, No Timeout

`OcrService::recognizeTextBatch()` sends **all** N snippets in a single `Http::post()` call.

**Google Cloud Vision `batchAnnotateImages` REST limits:**
- Maximum **16 images per request**.
- Maximum total request body: ~20 MB.

For 36 nodes + 36 edge markers = **72 images in one request**, the API will likely return a
`400 Bad Request`. The `RequestException` handler wraps this in `HttpException(502)`, which
is caught by the outer `catch (Throwable $e)` in `PhotoService::process()` and marks the
photo as `failed`. So the job should *fail*, not hang — but only if the HTTP call itself
returns.

**There is no timeout on the HTTP call.** Laravel's `Http` facade has no default timeout. If
the Vision API stalls (connection open but no response), `Http::post()` will block the worker
process indefinitely. Combined with the queue configuration below, this is the most likely
cause of a job that appears to run forever.

---

## 3. Edge Detection – Scaling

The algorithm is O(E × N × R) where:

- **E** = edge markers  
- **N** = node markers  
- **R** = retry attempts (default: 0–3, so up to 4 passes)

For 36 edges × 36 nodes × 4 attempts = **5 184 iterations** of the inner
`findCandidateNodes` loop. Each iteration does fixed-cost math plus two calls through
`MarkerGeometry::markerHitboxCenter()`:

```
markerHitboxCenter(node)
  └─ markerDimensions(corners)    — 3× firstWhere on a 4-element collection
  └─ resolveHitbox(markerId)      — config('marker_config') read (cached)
  └─ hitboxCenter(...)            — pure trig
```

The scaling from 32→36 nodes/edges adds ~(36²×4 − 32²×4) = ~1 088 extra iterations.
This is **not** a performance concern at this scale and would not cause a hang.

One minor inefficiency: `MarkerGeometry::resolveHitbox()` calls `config('marker_config')`
on every node evaluation inside the loop. Config is cached in memory after the first call,
so the cost is negligible, but the marker config is already loaded into `$markerConfig` in
`detectEdges()` and could be passed down rather than re-read.

---

## 4. Queue Timeout – Why the Job Can Run Forever

The job declares `public int $timeout = 120`, but **this value is only honoured by
`queue:work`**. The CLAUDE.md default command is:

```
php artisan queue:listen --tries=1 --timeout=0
```

`queue:listen` spawns a fresh `queue:work` process per job and forwards `--timeout` to it.
With `--timeout=0`, the spawned worker has **unlimited execution time**. If the job blocks
(e.g. on an HTTP call with no timeout), it will hang indefinitely without ever being killed
or marked failed.

---

## 5. Debugging Guide

### Step 1 – Run the job synchronously in a console command

This gives you immediate stdout/stderr and a visible stack trace without queue machinery:

```php
// In a one-off artisan command or tinker:
$photo = Photo::find($id);
app(App\Services\PhotoService::class)->process($photo);
```

Or add a temporary artisan command that calls `process()` directly.

### Step 2 – Add timing checkpoints to `PhotoService::process()`

Insert `Log::info()` calls (or `dump()` in tinker) between each stage:

```php
$t = microtime(true);

$this->imageSnippetService->normalizeExifOrientation($absolutePath);
Log::info('[photo] exif done', ['ms' => round((microtime(true) - $t) * 1000)]);  $t = microtime(true);

$markers = $this->arucoService->detectMarkers($absolutePath);
Log::info('[photo] aruco done', ['count' => count($markers), 'ms' => round((microtime(true) - $t) * 1000)]);  $t = microtime(true);

// …and so on for snippets, OCR, edge detection, DB writes
```

Run `php artisan pail` or `tail -f storage/logs/laravel.log` alongside the worker.
The step that produces no log entry is where execution is stuck.

### Step 3 – Identify the OCR request as the likely hang point

Add a timeout to the Vision API call in `OcrService`:

```php
$response = Http::timeout(30)->post(self::VISION_API_URL.'?key='.$apiKey, [
    'requests' => $requests,
]);
```

Also log how many images are being sent:

```php
Log::info('[ocr] batch', ['count' => count($base64Contents)]);
```

If count > 16, the API will reject it. The fix is to chunk the batch:

```php
// recognizeTextBatch() — split into pages of 16
$chunks = array_chunk($imageDataItems, 16);
$results = [];
foreach ($chunks as $chunk) {
    $results = array_merge($results, $this->callVisionApi(array_map('base64_encode', $chunk)));
}
return $results;
```

### Step 4 – Check memory at peak

Add before and after the snippet loop:

```php
Log::info('[photo] before snippets', ['mem_mb' => round(memory_get_usage(true) / 1048576, 1)]);
$snippets = $this->extractSnippets($absolutePath, $markers);
Log::info('[photo] after snippets', ['mem_mb' => round(memory_get_usage(true) / 1048576, 1), 'count' => count($markers)]);
```

If memory spikes past the PHP `memory_limit`, the worker process is killed by the OS and
the job is silently abandoned (no exception, no `failed` status).

### Step 5 – Switch from `queue:listen` to `queue:work` with an enforced timeout

```bash
php artisan queue:work --tries=1 --timeout=150 --memory=512
```

This runs the same worker process across jobs (more efficient), and **kills and fails the
job** if it exceeds 150 seconds. `queue:listen --timeout=0` lets a hung job run forever and
also restarts the entire PHP process between jobs, which hides memory growth but is slower.

### Step 6 – Enable Laravel Telescope or add a failed-job table

Ensure `QUEUE_FAILED_DRIVER=database` and `php artisan queue:failed-table && php artisan
migrate` are in place. A job killed by OOM or timeout will land in `failed_jobs` with a
traceable exception. Without this table, a killed worker leaves the photo stuck in
`processing` permanently.

---

## Summary of Risks by Severity

| # | Issue | Impact | Likely trigger at 36×36 |
|---|-------|--------|--------------------------|
| 1 | No timeout on `Http::post()` to Vision API | Hang indefinitely | ✓ primary suspect |
| 2 | Batch size not capped (Vision API limit: 16) | 400 error or slow response | ✓ at 72 markers |
| 3 | `queue:listen --timeout=0` ignores job timeout | Job can never be killed by queue | ✓ makes hang permanent |
| 4 | No `imagedestroy()` calls | Fragile GC dependency; OOM on large photos | possible on high-res input |
| 5 | Source image loaded N times inside snippet loop | Wasted disk I/O, high per-call GD peak | minor at 36 markers |
| 6 | Edge detection config re-read inside inner loop | Negligible (config is cached) | not a concern |
