<?php

declare(strict_types=1);

use App\Contracts\MenuTagEngineContract;
use App\Enums\MenuTagStatus;
use App\Enums\Printability;
use App\Exceptions\EngineFailureException;
use App\Jobs\GenerateMenuTagJob;
use App\Models\MenuTag;
use App\Models\User;
use App\Services\FakeMenuTagEngine;
use Illuminate\Support\Facades\Storage;

/**
 * State transitions with the fake engine (mandatory list, spec §6 WS-6):
 * queued → processing → completed | failed across the three outcome classes
 * of decisions §3. The engine is ALWAYS FakeMenuTagEngine here (bound as a
 * singleton in the 'testing' environment by EngineServiceProvider): no test
 * invokes Python.
 */
function fakeEngine(): FakeMenuTagEngine
{
    $engine = app(MenuTagEngineContract::class);

    expect($engine)->toBeInstanceOf(FakeMenuTagEngine::class);

    /** @var FakeMenuTagEngine $engine */
    return $engine;
}

beforeEach(function (): void {
    Storage::fake('stl');
    Storage::fake('assets');
});

it('completes the record with STL paths, report and metric columns on success', function (): void {
    $engine = fakeEngine()->succeed()->writingStlStubs();

    $tag = MenuTag::factory()->for(User::factory()->create())->queued()->create([
        'parameters' => [
            ...(array) config('product.presets.menutag.defaults'),
            'qr_data_front' => 'https://menu.example.it/demo',
            'nfc' => true,
        ],
    ]);

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    $tag->refresh();

    expect($tag->status)->toBe(MenuTagStatus::Completed)
        ->and($tag->stl_path)->toBe(GenerateMenuTagJob::stlPath($tag->id))
        ->and($tag->stl_accent_path)->toBeNull()
        ->and($tag->report)->toBeArray()->toHaveKeys(['OK', 'TRIANGLES', 'WEIGHT_G', 'PRINTABILITY', 'PAUSE_Z', 'PAUSE_LAYER'])
        ->and($tag->triangles)->toBeGreaterThan(0)
        ->and($tag->pause_z)->not->toBeNull()
        ->and($tag->pause_layer)->not->toBeNull()
        ->and($tag->printability)->toBeInstanceOf(Printability::class)
        ->and($tag->error_message)->toBeNull();

    Storage::disk('stl')->assertExists(GenerateMenuTagJob::stlPath($tag->id));

    // The engine received the absolute output path inside the 'stl' disk.
    expect($engine->lastRequest()?->outPath)
        ->toBe(Storage::disk('stl')->path(GenerateMenuTagJob::stlPath($tag->id)));
});

it('marks the record failed with the engine message verbatim on a user error (exit 2)', function (): void {
    $message = 'Con questo indirizzo il codice QR richiede almeno 63.6 mm di lato: aumenta la dimensione.';
    $engine = fakeEngine()->failValidation($message);

    $tag = MenuTag::factory()->for(User::factory()->create())->queued()->create();

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    $tag->refresh();

    expect($tag->status)->toBe(MenuTagStatus::Failed)
        ->and($tag->error_message)->toBe($message)
        ->and($tag->stl_path)->toBeNull();
});

it('rethrows internal errors for the retry, then failed() stamps the generic Italian message', function (): void {
    $engine = fakeEngine()->failInternally('fake: not watertight');

    $tag = MenuTag::factory()->for(User::factory()->create())->queued()->create();
    $job = new GenerateMenuTagJob($tag->id);

    // handle() must rethrow so the queue retries transient failures.
    expect(fn () => $job->handle($engine))->toThrow(EngineFailureException::class);

    // Mid-retry the record is still processing, never silently failed.
    expect($tag->refresh()->status)->toBe(MenuTagStatus::Processing);

    // After the last attempt the queue calls failed(): generic message, the
    // technical stderr never reaches the user (decisions §3).
    $job->failed(new EngineFailureException('fake: not watertight'));

    $tag->refresh();

    expect($tag->status)->toBe(MenuTagStatus::Failed)
        ->and($tag->error_message)->not->toContain('watertight')
        ->and($tag->error_message)->toContain('errore interno');
});

it('produces both STL paths in inlay mode', function (): void {
    $engine = fakeEngine()->succeed()->writingStlStubs();

    $tag = MenuTag::factory()->for(User::factory()->create())->queued()->create([
        'parameters' => [
            ...(array) config('product.presets.coaster.defaults'),
            'front' => 'none',
            'mode' => 'inlay',
            'depth' => 0.5,
        ],
    ]);

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    $tag->refresh();

    expect($tag->status)->toBe(MenuTagStatus::Completed)
        ->and($tag->stl_accent_path)->toBe(GenerateMenuTagJob::stlAccentPath($tag->id));

    Storage::disk('stl')->assertExists(GenerateMenuTagJob::stlAccentPath($tag->id));
});

it('never flips a record that is already terminal', function (): void {
    $engine = fakeEngine()->succeed();

    $tag = MenuTag::factory()->for(User::factory()->create())->failed()->create();

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    expect($tag->refresh()->status)->toBe(MenuTagStatus::Failed)
        ->and($engine->timesGenerated())->toBe(0);
});
