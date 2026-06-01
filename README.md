# webApi

A Laravel API for Archify. Handelt authenticatie, projecten, foto-uploads en de verwerking van handgetekende IT-landschappen naar VueFlow-schetsen af.

## ⚠️ Belangrijk

Naast `php artisan serve` moet je in een aparte terminal ook **`php artisan queue:work`** draaien. Fotoverwerking (ArUco-detectie, OCR, edge detection) draait via `ProcessPhotoJob`. Zonder worker blijven geüploade foto's eindeloos op `processing` staan en worden er nooit schetsen aangemaakt.

## Getting Started

### 1. Prerequisites

- PHP 8.2+
- Composer
- SQLite (default) of een andere DB die Laravel ondersteunt
- Python + OpenCV (voor de ArUco-detectie, zie `scripts/`)

### 2. Install

```bash
cd webApi
composer install
cp .env.example .env
php artisan key:generate
```

### 3. Database

```bash
php artisan migrate
```

Optioneel met seeders:

```bash
php artisan migrate --seed
```

### 4. Run

In twee aparte terminals:

```bash
php artisan serve
php artisan queue:work
```

Voor toegang vanaf een fysiek apparaat (telefoon op hetzelfde Wi-Fi netwerk):

```bash
php artisan serve --host=0.0.0.0
```

## Tests

```bash
php artisan test
```

## Useful Commands

| Command | Description |
|---|---|
| `composer install` | Install dependencies |
| `php artisan migrate:fresh` | Drop everything en opnieuw migreren |
| `php artisan migrate:fresh --seed` | Idem, met seeders |
| `php artisan queue:work` | Start de queue worker (verplicht) |
| `php artisan queue:listen` | Queue worker die code-changes oppikt (handig in dev) |
| `php artisan tinker` | REPL voor de app |
| `php artisan route:list` | Toon alle geregistreerde routes |
| `php artisan test` | Run alle tests |

## Project Structure

```
app/
├── Enums/                              # Type-safe enums
│   ├── CornerPosition.php
│   ├── MarkerType.php
│   └── PhotoStatus.php
├── Http/
│   ├── Controllers/                    # API endpoints
│   │   ├── AuthController.php          # Login / logout / token
│   │   ├── PhotoController.php         # Upload + status van foto's
│   │   ├── ProjectController.php       # CRUD voor projecten
│   │   ├── SketchController.php        # CRUD voor schetsen
│   │   ├── NodeTypeController.php      # Lijst beschikbare node types
│   │   └── SharedLinkController.php    # Deel-links voor schetsen
│   └── Middleware/
│       └── PrometheusMetrics.php       # Metrics scraping endpoint
├── Jobs/
│   └── ProcessPhotoJob.php             # Async fotoverwerking pipeline
├── Models/                             # Eloquent models
│   ├── User.php
│   ├── Project.php
│   ├── Photo.php
│   ├── Sketch.php
│   ├── SharedLink.php
│   ├── DetectionResult.php             # Resultaat van één detectie-run
│   ├── ArucoMarker.php                 # Gedetecteerde marker
│   ├── ArucoMarkerCorner.php           # 4 hoekpunten per marker
│   └── DetectedEdge.php                # Edges tussen markers
├── Services/                           # Business logic
│   ├── PhotoService.php                # Orchestreert de hele pipeline
│   ├── ArucoService.php                # Python-bridge voor markerdetectie
│   ├── ImageSnippetService.php         # Snijdt marker-snippets uit foto
│   ├── OcrService.php                  # Google Vision OCR (batch)
│   ├── EdgeDetectionService.php        # Bepaalt verbindingen tussen markers
│   ├── VueFlowConversionService.php    # Detectie → VueFlow schets
│   └── MermaidExportService.php        # Schets → Mermaid diagram
└── Providers/
database/
├── migrations/
├── seeders/
└── factories/
routes/
├── api.php                             # Alle API routes
└── console.php                         # Artisan commands
scripts/                                # Python helpers (ArUco etc.)
```

## Fotoverwerkings-pipeline

Wanneer de mobile app een foto uploadt, gebeurt het volgende:

1. **`PhotoController@store`** — slaat de foto op disk op, maakt een `Photo` met status `processing`, dispatcht `ProcessPhotoJob`, en geeft direct response terug.
2. **`ProcessPhotoJob`** — wordt opgepakt door de queue worker en roept `PhotoService::process()` aan.
3. **`PhotoService::process`** — orchestreert de pipeline:
   - `ArucoService` detecteert markers
   - `ImageSnippetService` snijdt snippets uit
   - `OcrService` doet batched OCR via Vision
   - `EdgeDetectionService` bepaalt verbindingen
   - `VueFlowConversionService` maakt er een `Sketch` van
4. **Status update** — Photo wordt op `completed` (of `failed`) gezet met de `sketch_id`.

De mobile app pollt op de photo-status totdat die `completed` is.
