# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer setup                                          # First-time setup: install, .env, key:generate, migrate
composer dev                                            # Start API + queue worker + Pail logger + Vite concurrently
php artisan serve                                       # API only on http://localhost:8000 — NO queue worker, uploads will hang on "processing"
php artisan queue:listen --tries=1 --timeout=0          # Run the queue worker standalone (needed for photo uploads)
php artisan test                                        # Run all tests (clears config cache first)
php artisan test --filter=EdgeDetectionServiceTest      # Run a single test class
```

Tests use **Pest** (not PHPUnit directly). Feature tests use `RefreshDatabase` and hit a real in-memory SQLite database. Unit tests mock all external dependencies (Python subprocess, Google Vision).

## Architecture

### Photo processing pipeline (async)

Photo upload is split across two methods:

- `PhotoService::store()` — persists the upload to `storage/app/photos/`, creates a `Photo` row with `status = processing`, and dispatches `ProcessPhotoJob`. Returns immediately.
- `PhotoService::process()` — runs on the queue worker. Executes the actual pipeline:

```
EXIF normalize → ArUco detect (Python) → Extract snippets → Batch OCR → Store markers/edges → Edge detection → VueFlow conversion → Photo.status = completed (or failed)
```

`ProcessPhotoJob` is configured with `$tries = 1` and `$timeout = 120`. On exception: a `DetectionResult` with `detection_failed = true` is created, `Photo.status = failed`, and `Photo.error_message` is set. The exception is also reported via `report($e)`.

**Clients must poll** `GET /photos/{photo}/status` to know when the upload is done. Without a running queue worker, `status` never changes from `processing`.

### Service responsibilities

| Service | Responsibility |
|---------|---------------|
| `ArucoService` | Shells out to `scripts/detect_aruco.py`, parses JSON output (id, center, corners, rotation per marker) |
| `ImageSnippetService` | EXIF-corrects the source image, then crops a rotation-aligned region around each marker for OCR |
| `OcrService` | Sends all snippets in one batch to Google Cloud Vision (`DOCUMENT_TEXT_DETECTION`), returns text per snippet |
| `EdgeDetectionService` | Projects node centers onto each edge marker's x-axis using vector math; closest node on the negative-x side = source, positive-x side = target |
| `VueFlowConversionService` | Maps markers → VueFlow nodes (position scaling + centering to 1400×800), detected edges → VueFlow edges |
| `MermaidExportService` | Converts a saved VueFlow canvas state to Mermaid syntax using `config/mermaid.php` arrow mappings |

### Data model

```
Photo (status: processing|completed|failed) ── sketch_id ──▶ Sketch (canvas_state JSON)
                                            ▲
DetectionResult ── filename ────────────────┘ (matched by filename, not FK)
 ├─ ArucoMarker (marker_id, center_x/y, rotation, ocr_text)
 │   └─ ArucoMarkerCorner (4 per marker, position enum)
 └─ DetectedEdge (edge_marker, source_marker, target_marker, edge_type string)

Sketch ── HasOne ──▶ SharedLink (token, is_active)
```

`Sketch::booted()` auto-fills `title` on creation with `"Schets {day} {dutch-month} {HH:mm}"` when no title is provided.

**Dead code:** `App\Models\Schets` (table `schetsen`) exists but is not referenced by any controller, service, or route. Treat it as legacy — do not extend it. The active model is `App\Models\Sketch`.

### Routes

All routes are prefixed with `/api`. Auth is `auth:sanctum` unless marked **public**.

| Method | Path | Controller | Notes |
|--------|------|------------|-------|
| GET | `/health` | inline | **public** |
| GET | `/metrics` | `PrometheusMetricsController` | **public** (Prometheus) |
| POST | `/login` | `AuthController@login` | **public**, returns bearer token |
| GET | `/shared/node-types` | `SharedLinkController@nodesTypes` | **public** |
| GET | `/shared/{token}` | `SharedLinkController@show` | **public** — read-only sketch view |
| GET | `/user` | `AuthController@user` | |
| POST | `/logout` | `AuthController@logout` | |
| GET | `/node-types` | `NodeTypeController@index` | |
| GET / POST | `/projects`, `/projects/{project}` | `ProjectController` | |
| POST | `/photos/upload` | `PhotoController@upload` | Dispatches `ProcessPhotoJob`, returns `photo_id` + initial `status` |
| GET | `/photos/{photo}/status` | `PhotoController@status` | Poll endpoint — returns `status`, `sketch_id`, `nodes_count`, `edges_count`, `error_message` |
| GET | `/photos/{filename}/aruco` | `PhotoController@getArucoResults` | Raw detection result (markers + edges) |
| GET / POST / PUT / PATCH / DELETE | `/sketches`, `/sketches/{sketch}`, `/sketches/{sketch}/rename` | `SketchController` | `PUT` updates `canvas_state` |
| GET | `/sketches/{sketch}/export/mermaid` | `SketchController@exportMermaidSketch` | |
| POST | `/export/mermaid` | `SketchController@exportMermaidFromState` | Export from arbitrary canvas state |
| GET | `/projects/{project}/sketches` | `SketchController@index` | |
| GET / POST | `/projects/{project}/sketches/{sketch}/share` | `SharedLinkController` | Toggle = create-or-flip `is_active`; token is `Str::random(64)` |

### Configuration

Marker behaviour is fully config-driven — no code changes needed to add or change marker types.

**`config/marker_config.php`** — maps ArUco IDs to edge types and OCR hitbox sizes (in marker-width units):
- IDs 21–23 = edge markers with a large forward hitbox (`xPos: 4`) for the text label area
- All other IDs fall through to a default: `type = node`, hitbox `[2, 2, 2, 2]`

**`config/node_types.php`** — maps ArUco IDs to VueFlow node types, Dutch display names, icons, and Mermaid shapes:
- IDs 1–5 = physical marker cards (rectangle, server, database, application, user)
- `aruco: null` = note node (created manually in the web editor, never scanned)

**`config/aruco.php`** — runtime tuning: Python binary, script path, dictionary, timeout, and the two geometric tolerances (`edge_margin`, `edge_angle_margin`).

### Env variables

| Variable | Default | Purpose |
|----------|---------|---------|
| `ARUCO_PYTHON_PATH` | `python3` | Python binary |
| `ARUCO_SCRIPT_PATH` | `scripts/detect_aruco.py` | Detection script (resolved via `base_path()`) |
| `ARUCO_DICTIONARY` | `DICT_ARUCO_MIP_36h12` | ArUco dictionary |
| `ARUCO_TIMEOUT` | `30` | Script timeout (seconds) |
| `ARUCO_EDGE_MARGIN` | `0.5` | Perpendicular tolerance (× marker size) |
| `ARUCO_EDGE_ANGLE_MARGIN` | `5.0` | Angle tolerance (degrees) |
| `GOOGLE_CLOUD_VISION_API_KEY` | — | Required for OCR; without it the OCR step throws |
| `QUEUE_CONNECTION` | `database` | Default uses the DB driver — migrations create the `jobs` table |

### MarkerType enum

`App\Enums\MarkerType` (`Node`, `Directionless`, `Monodirectional`, `Bidirectional`) is the canonical type system shared between `EdgeDetectionService` and `VueFlowConversionService`. Resolved via `MarkerType::fromConfig($markerId, $config)` — unknown IDs default to `Node`.

### Auth

Laravel Sanctum. Token is issued on `POST /login` and stored by the webapp as an `auth_token` cookie / by the mobile app in `SharedPreferences`. Public routes are listed in the route table above.
