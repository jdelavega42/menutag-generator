<?php

declare(strict_types=1);

use App\DTOs\EngineRequest;
use App\Enums\MenuTagStatus;
use App\Enums\RenderMode;
use App\Jobs\GenerateMenuTagJob;
use App\Models\MenuTag;
use App\Services\FakeMenuTagEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest configuration (WS-6)
|--------------------------------------------------------------------------
| Feature tests get the Laravel TestCase plus RefreshDatabase (sqlite
| in-memory, phpunit.xml). Unit tests get the Laravel TestCase too — the
| domain rules under test read config/product.php and config/printers.php,
| so the app must boot — but no database.
|
| NO test invokes Python (spec §11): the engine is FakeMenuTagEngine, bound
| in the 'testing' environment by EngineServiceProvider. The single
| exception is the integration test marked ->group('integration'), which
| skips itself when engine/.venv is missing.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared helpers
|--------------------------------------------------------------------------
*/

/**
 * Flip a record to `completed` with a COHERENT engine report derived from
 * its own parameters (FakeMenuTagEngine::realisticResultFor — same code path
 * as the DemoSeeder) and, by default, stub STL files on the current 'stl'
 * disk so download endpoints work. Call Storage::fake('stl') first when the
 * test asserts on files.
 */
function completeWithRealisticReport(MenuTag $menuTag, bool $writeStl = true): MenuTag
{
    $stlDisk = Storage::disk('stl');
    $parameters = $menuTag->parameters;

    $stlPath = GenerateMenuTagJob::stlPath($menuTag->id);
    $accentPath = $parameters->mode === RenderMode::Inlay
        ? GenerateMenuTagJob::stlAccentPath($menuTag->id)
        : null;

    $result = FakeMenuTagEngine::realisticResultFor(new EngineRequest(
        parameters: $parameters,
        outPath: $stlDisk->path($stlPath),
        outAccentPath: $accentPath !== null ? $stlDisk->path($accentPath) : null,
        logoPath: null,
    ));

    if ($writeStl) {
        $stub = str_pad('TEST-MENUTAG-STL', 80, "\0").pack('V', 0);
        $stlDisk->put($stlPath, $stub);

        if ($accentPath !== null) {
            $stlDisk->put($accentPath, $stub);
        }
    }

    $menuTag->update([
        'status' => MenuTagStatus::Completed,
        'stl_path' => $stlPath,
        'stl_accent_path' => $accentPath,
        'report' => $result->raw,
        'triangles' => $result->triangles,
        'volume_mm3' => $result->volumeMm3,
        'weight_g' => $result->weightG,
        'pause_z' => $result->pauseZ,
        'pause_layer' => $result->pauseLayer,
        'printability' => $result->printability,
        'error_message' => null,
    ]);

    return $menuTag->refresh();
}

/**
 * A minimal REAL png (1×1, transparent), for content-based upload tests
 * without requiring the GD extension.
 */
function tinyPngBytes(): string
{
    return (string) base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        true,
    );
}
