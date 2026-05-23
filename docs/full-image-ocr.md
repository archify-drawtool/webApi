# Full-Image OCR – Architecture and Debugging

## Why This Was Changed

The original OCR pipeline cropped a separate image snippet for every detected ArUco marker,
then batched those snippets into a single Vision API request. This had several compounding problems:

| Problem | Detail |
|---------|--------|
| GD memory peak | ~150 MB per marker (source + rotated canvas + crop) |
| API batch limit | Vision rejects batches larger than 16 images |
| Scales with marker count | N markers → N GD crop operations → N images in the batch |
| Pipeline complexity | Two extra steps (`extractSnippets` + `batchOcr`) with their own error handling |

The replacement makes **one `DOCUMENT_TEXT_DETECTION` call on the full normalized image**, then
matches each word-level text block back to the marker it belongs to using existing hitbox geometry.

---

## Pipeline (updated)

```
POST /photos/upload
  └─ PhotoService::store()         — save file, create Photo (status=processing), dispatch job
       └─ ProcessPhotoJob::handle()
            └─ PhotoService::process()
                 1. normalizeExifOrientation()         — in-place JPEG rotation fix
                 2. ArucoService::detectMarkers()      — Python subprocess → JSON
                 3. OcrService::recognizeTextInRegions() ← replaces extractSnippets + batchOcr
                      a. blankMarkers()               — draw white polygons over marker squares (GD, in-memory)
                      b. one DOCUMENT_TEXT_DETECTION call on the blanked image
                      c. matchTextToMarkers()         — hitbox polygon containment per marker
                 4. persist ArucoMarker + ArucoMarkerCorner rows (N + 4N inserts)
                 5. EdgeDetectionService::detectEdges() — geometric search in PHP
                 6. persist DetectedEdge rows
                 7. VueFlowConversionService::convert() — build canvas_state JSON
                 8. Photo.status = completed
```

---

## Marker Blanking

### The problem

ArUco markers are square QR-like patterns. Google Vision reads their inner cells as characters
(`H`, `X`, `☐`, `■`, etc.) — these fragments appear inside the OCR hitbox of whatever marker
they come from, polluting the `ocr_text` stored on the `ArucoMarker` row.

### The fix

Before sending the image to Vision, `OcrService::blankMarkers()` loads the full image with GD
and draws a **filled white polygon** over every marker's detected corner quadrilateral:

```php
imagefilledpolygon($img, [$c0x, $c0y, $c1x, $c1y, $c2x, $c2y, $c3x, $c3y], $white);
```

The four corner points come directly from `ArucoService::detectMarkers()` — they are in the
same normalized image coordinate space (EXIF correction has already been applied). The blanked
image is JPEG-encoded in memory (`ob_start` / `ob_get_clean`) and sent to Vision; it is never
written to disk.

---

## Hitbox Matching

After Vision returns the full list of word-level text blocks (`textAnnotations[1..]`), each block's
bounding polygon centroid is tested against every marker's hitbox rectangle:

```
centroid = avg(boundingPoly.vertices)

for each marker:
    width = euclideanDistance(corners[TL], corners[TR])
    hitbox_config = marker_config.php[marker_id]   (xPos/xNeg/yPos/yNeg in marker-width units)
    hitbox_corners = MarkerGeometry::hitboxCorners(cx, cy, width, hitbox, rotation)

    if MarkerGeometry::pointInHitbox(centroid, hitbox_corners):
        append word to marker's ocr_text
```

`pointInHitbox` uses two dot-product projections onto the hitbox's own axis vectors — a
standard point-in-rotated-rectangle test, O(1) per (word, marker) pair.

### Key classes

| Class / method | File | Role |
|----------------|------|------|
| `OcrService::recognizeTextInRegions()` | `app/Services/OcrService.php` | Public entry point |
| `OcrService::blankMarkers()` | `app/Services/OcrService.php` | Ephemeral GD masking |
| `OcrService::recognizeFullImage()` | `app/Services/OcrService.php` | Single Vision API call |
| `OcrService::matchTextToMarkers()` | `app/Services/OcrService.php` | Per-marker hitbox matching |
| `MarkerGeometry::hitboxCorners()` | `app/Helpers/MarkerGeometry.php` | Rotated rectangle corners |
| `MarkerGeometry::pointInHitbox()` | `app/Helpers/MarkerGeometry.php` | Containment test |
| `MarkerGeometry::resolveHitbox()` | `app/Helpers/MarkerGeometry.php` | Config lookup per marker ID |

Hitbox sizes per marker ID are configured in `config/marker_config.php` (in marker-width units).
Edge markers 21–23 have a large `xPos` hitbox to capture the text label placed beside them.

---

## Performance Comparison

| | Snippet approach (old) | Full-image approach (new) |
|-|------------------------|---------------------------|
| Vision API requests | 1 per 16 markers (ceil) | **1 per photo** |
| GD peak memory | ~150 MB × N markers | ~50 MB (load once, paint, encode) |
| Vision batch size limit | hits at >16 markers | irrelevant |
| Scales with marker count | linearly worse | flat |
| Pipeline steps | `extractSnippets` + `batchOcr` | `recognizeTextInRegions` |

---

## Trade-offs

- **Accuracy on small markers:** Vision may miss tiny text in a wide-angle photo that the
  snippet approach enlarged by cropping. In practice this matters only when physical markers
  cover a very small fraction of the total image area.
- **All markers blanked:** Every detected marker is masked regardless of type (node or edge).
  The hitbox config already excludes the marker square itself (`xPos`/`xNeg` offsets start from
  the marker edge), so blanking the square does not remove any text that would have been
  captured anyway.

---

## How to Debug

### Check what Vision returned vs. what was matched

Every call to `recognizeTextInRegions()` logs at `debug` level:

```
local.DEBUG: OCR hitbox matches [
  {"marker_id": 1,  "ocr_text": "web server"},
  {"marker_id": 22, "ocr_text": "HTTP"},
  {"marker_id": 3,  "ocr_text": ""}
]
```

Empty `ocr_text` means no word centroids fell inside that marker's hitbox. Check:
1. Is the hitbox large enough? Inspect `config/marker_config.php` for that marker ID.
2. Did the marker appear in the blanked region? The blanked area is exactly the detected corners — if the text label is outside the physical marker but inside the hitbox, it will not be blanked.
3. Is the marker very small relative to the image? Vision may not detect small text at full resolution.

### Inspect the raw Vision response

Temporarily dump the raw annotations before matching:

```php
// in OcrService::recognizeFullImage() before return array_slice(...)
\Log::debug('Vision raw textAnnotations', $annotations);
```

Each entry looks like:

```json
{
  "description": "server",
  "boundingPoly": {
    "vertices": [{"x": 420, "y": 310}, {"x": 580, "y": 310}, {"x": 580, "y": 370}, {"x": 420, "y": 370}]
  }
}
```

Compare the centroid `(avg_x, avg_y)` against the hitbox corners logged by adding a temporary
`\Log::debug('hitboxCorners', $hitboxCorners)` line inside `matchTextToMarkers()`.
