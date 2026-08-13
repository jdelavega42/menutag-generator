<?php

declare(strict_types=1);

use App\DTOs\EngineRequest;
use App\DTOs\MenuTagParameters;
use App\Enums\FaceContent;
use App\Enums\Nozzle;
use App\Enums\Printability;
use App\Enums\QrEcLevel;
use App\Enums\RenderMode;
use App\Enums\Shape;
use App\Services\PythonMenuTagEngine;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * THE ONLY test that invokes Python (contract 03 §1: at most one, marked
 * ->group('integration')). It runs the real engine on the print-validated
 * reference configuration (58.8 × 3.0, engrave 0.6, NFC, layer 0.10) and
 * checks the binding stdout contract plus the known real-world outcome:
 * PAUSE_Z=2.0 after layer 19 of 29.
 *
 * Skips itself when the engine virtualenv is absent, so the suite stays
 * green on machines without Python (spec §11, DoD §12).
 */
it('generates the validated reference STL through the real Python engine', function (): void {
    $python = (string) config('product.engine.python');

    if (! is_file($python)) {
        $this->markTestSkipped('engine/.venv is missing: run the Dockerfile venv step or create it locally.');
    }

    $workDir = storage_path('framework/testing/engine-'.Str::lower(Str::random(8)));
    File::ensureDirectoryExists($workDir);

    try {
        $parameters = new MenuTagParameters(
            shape: Shape::Square,
            size: 58.8,
            fillet: 4.0,
            thickness: 3.0,
            front: FaceContent::Qr,
            mode: RenderMode::Engrave,
            depth: 0.6,
            qrDataFront: 'https://menu.example.it/demo',
            qrEc: QrEcLevel::H,
            nfc: true,
            nozzle: Nozzle::N04,
            layerHeight: 0.10,
        );

        $result = new PythonMenuTagEngine()->generate(new EngineRequest(
            parameters: $parameters,
            outPath: $workDir.'/out.stl',
            outAccentPath: null,
            logoPath: null,
        ));

        expect($result->ok)->toBeTrue()
            ->and(is_file($workDir.'/out.stl'))->toBeTrue()
            ->and($result->triangles)->toBeGreaterThan(0)
            ->and($result->volumeMm3)->toBeGreaterThan(0.0)
            ->and(abs($result->volumeDeltaMm3))->toBeLessThan(1e-3)   // §8.2 mesh vs analytic
            ->and($result->qrDecoded)->toBeTrue()                     // §8.2 QR decodes from geometry
            ->and($result->pauseZ)->toBe(2.0)                         // validated reference
            ->and($result->pauseLayer)->toBe(19)
            ->and($result->firstLayer)->toBe(0.2)
            ->and($result->printability)->toBeInstanceOf(Printability::class)
            ->and($result->raw)->toHaveKeys(['WEIGHT_G', 'SIZE_MIN_FUNCTIONAL_MM', 'BBOX_X', 'FILE_SIZE_KB']);

        // Byte-mode parity at the 64/65 boundary (decisions §5): the version
        // predicted by the PHP ISO table must match what the engine encodes.
        $url64 = 'https://menu.example.it/'.str_repeat('a', 40);
        expect(MenuTagParameters::minQrVersion($url64, QrEcLevel::H))->toBe(7);

        $boundary = new PythonMenuTagEngine()->generate(new EngineRequest(
            parameters: new MenuTagParameters(
                shape: Shape::Square,
                size: 63.6,
                thickness: 3.0,
                front: FaceContent::Qr,
                mode: RenderMode::Engrave,
                depth: 0.6,
                qrDataFront: $url64,
                qrEc: QrEcLevel::H,
                layerHeight: 0.10,
            ),
            outPath: $workDir.'/boundary.stl',
            outAccentPath: null,
            logoPath: null,
        ));

        expect($boundary->qrVersion)->toBe(7)
            ->and($boundary->qrModules)->toBe(45);

        // Regression: a 119-byte URL (the §3.2 v10 indicative case) on a
        // square silhouette used to crash the sliver repair in meshing.py
        // (stale edge map when two slivers shared a neighbour face).
        $regression = new PythonMenuTagEngine()->generate(new EngineRequest(
            parameters: new MenuTagParameters(
                shape: Shape::Square,
                size: 78.0,
                thickness: 3.0,
                front: FaceContent::Qr,
                mode: RenderMode::Engrave,
                depth: 0.6,
                qrDataFront: 'https://menu.example.it/'.str_repeat('x', 95),
                qrEc: QrEcLevel::H,
                layerHeight: 0.10,
            ),
            outPath: $workDir.'/regression.stl',
            outAccentPath: null,
            logoPath: null,
        ));

        expect($regression->ok)->toBeTrue()
            ->and($regression->qrVersion)->toBe(10)
            ->and($regression->qrDecoded)->toBeTrue()
            ->and(abs($regression->volumeDeltaMm3))->toBeLessThan(1e-3);
    } finally {
        File::deleteDirectory($workDir);
    }
})->group('integration');
