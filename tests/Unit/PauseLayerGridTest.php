<?php

declare(strict_types=1);

use App\DTOs\EngineRequest;
use App\DTOs\EngineResult;
use App\DTOs\MenuTagParameters;
use App\Enums\Nozzle;
use App\Enums\Shape;
use App\Services\FakeMenuTagEngine;

/**
 * PAUSE_Z on the layer grid for BOTH nozzles (mandatory list, spec §6 WS-6):
 * the grid base is the FIRST LAYER of the selected nozzle — 0.15 mm with the
 * 0.2 nozzle, 0.20 mm with the 0.4 (spec §8.3: the 0.20 first layer is NOT
 * printable with the 0.2 nozzle, so the whole grid changes base). The
 * on-grid condition is (z − first_layer) / layer_height ∈ ℤ within the
 * explicit 1e-9 tolerance, and PAUSE_LAYER = 1 + that integer.
 *
 * The report comes from FakeMenuTagEngine (never Python, spec §11), which
 * derives the pause with the same grid formula from the printer profile.
 */
function pauseReportFor(Nozzle $nozzle, ?float $layerHeight = null, float $thickness = 3.0): EngineResult
{
    $parameters = new MenuTagParameters(
        shape: Shape::Square,
        size: 58.8,
        thickness: $thickness,
        nfc: true,
        nozzle: $nozzle,
        layerHeight: $layerHeight,
    );

    return FakeMenuTagEngine::realisticResultFor(new EngineRequest(
        parameters: $parameters,
        outPath: '/tmp/out.stl',
        outAccentPath: null,
        logoPath: null,
    ));
}

function expectOnGrid(EngineResult $result): void
{
    expect($result->pauseZ)->not->toBeNull()
        ->and($result->pauseLayer)->not->toBeNull();

    $steps = ((float) $result->pauseZ - $result->firstLayer) / $result->layerHeight;

    // (z − primo_layer) / layer_height must be an integer within 1e-9.
    expect(abs($steps - round($steps)))->toBeLessThan(1e-9)
        ->and($result->pauseLayer)->toBe(1 + (int) round($steps));
}

it('reproduces the validated reference with the 0.4 nozzle: PAUSE_Z 2.0, layer 19', function (): void {
    // 58.8 × 3.0, L=0.10, FL=0.20 → PAUSE_Z=2.0, PAUSE_LAYER=19 (contract 03 §3).
    $result = pauseReportFor(Nozzle::N04, 0.10);

    expect($result->firstLayer)->toBe(0.20)
        ->and($result->pauseZ)->toBe(2.0)
        ->and($result->pauseLayer)->toBe(19);

    expectOnGrid($result);
});

it('rebases the grid on the 0.15 first layer with the 0.2 nozzle', function (): void {
    $result = pauseReportFor(Nozzle::N02, 0.10);

    // FL=0.15 (config printers profile — NOT 0.20): the pocket top realigns
    // to 0.15 + 18 × 0.10 = 1.95 instead of the 0.4-nozzle 2.0.
    expect($result->firstLayer)->toBe(0.15)
        ->and($result->pauseZ)->toBe(1.95)
        ->and($result->pauseLayer)->toBe(19);

    expectOnGrid($result);
});

it('stays on the grid for a non-default layer height on both nozzles', function (Nozzle $nozzle, float $layerHeight): void {
    expectOnGrid(pauseReportFor($nozzle, $layerHeight, 4.0));
})->with([
    'nozzle 0.2, layer 0.15' => [Nozzle::N02, 0.15],
    'nozzle 0.2, layer 0.05' => [Nozzle::N02, 0.05],
    'nozzle 0.4, layer 0.20' => [Nozzle::N04, 0.20],
    'nozzle 0.4, layer 0.08' => [Nozzle::N04, 0.08],
]);

it('omits the pause keys without NFC', function (): void {
    $parameters = new MenuTagParameters(shape: Shape::Square, size: 58.8, thickness: 3.0);

    $result = FakeMenuTagEngine::realisticResultFor(new EngineRequest(
        parameters: $parameters,
        outPath: '/tmp/out.stl',
        outAccentPath: null,
        logoPath: null,
    ));

    expect($result->pauseZ)->toBeNull()
        ->and($result->pauseLayer)->toBeNull()
        ->and($result->raw)->not->toHaveKey('PAUSE_Z')
        ->not->toHaveKey('PAUSE_LAYER');
});
