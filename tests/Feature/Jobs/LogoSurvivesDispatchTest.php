<?php

declare(strict_types=1);

use App\Contracts\MenuTagEngineContract;
use App\Enums\MenuTagStatus;
use App\Exceptions\EngineFailureException;
use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use App\Services\FakeMenuTagEngine;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Spec §7.1 (mandatory list, spec §6 WS-6): the logo file is persisted on
 * the private 'assets' disk BEFORE the dispatch, the job payload carries
 * only the record id, and the absolute path is resolved INSIDE the job —
 * so the logo survives the request that uploaded it and is readable when
 * the job runs. Storage::fake() everywhere.
 */
beforeEach(function (): void {
    Storage::fake('assets');
    Storage::fake('stl');
});

function menuTagWithLogo(?User $user, ?string $guestToken = null): MenuTag
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
    $diskPath = 'logos/'.Str::uuid()->toString().'.svg';
    Storage::disk('assets')->put($diskPath, $svg);

    $logo = LogoAsset::factory()->create([
        'user_id' => $user?->id,
        'guest_token' => $guestToken,
        'disk_path' => $diskPath,
        'mime' => 'image/svg+xml',
        'size_bytes' => strlen($svg),
    ]);

    return MenuTag::factory()->create([
        'user_id' => $user?->id,
        'guest_token' => $guestToken,
        'logo_asset_id' => $logo->id,
        'status' => MenuTagStatus::Queued,
        'parameters' => [
            ...(array) config('product.presets.coaster.defaults'),
            'front' => 'logo',
            'logo_asset_id' => $logo->id,
        ],
    ]);
}

it('keeps the job payload to the record id only', function (): void {
    Queue::fake();

    $tag = menuTagWithLogo(User::factory()->create());

    GenerateMenuTagJob::dispatch($tag->id);

    Queue::assertPushed(GenerateMenuTagJob::class, function (GenerateMenuTagJob $job) use ($tag): bool {
        // The payload is an int id: no UploadedFile, no absolute path
        // (app and worker are different containers, spec §7.1).
        return $job->menuTagId === $tag->id;
    });
});

it('resolves the logo to an existing absolute file inside the job', function (): void {
    /** @var FakeMenuTagEngine $engine */
    $engine = app(MenuTagEngineContract::class);
    $engine->succeed()->writingStlStubs();

    $user = User::factory()->create();
    $tag = menuTagWithLogo($user);
    $expectedPath = Storage::disk('assets')->path($tag->logoAsset?->disk_path ?? '');

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    expect($tag->refresh()->status)->toBe(MenuTagStatus::Completed)
        ->and($engine->lastRequest()?->logoPath)->toBe($expectedPath)
        // The logo of an AUTHENTICATED user survives the job: only guest
        // logos are temporary (spec §7.1).
        ->and(is_file($expectedPath))->toBeTrue();
});

it('fails with the generic message when the logo file is missing (unmounted volume symptom)', function (): void {
    /** @var FakeMenuTagEngine $engine */
    $engine = app(MenuTagEngineContract::class);
    $engine->succeed();

    $tag = menuTagWithLogo(User::factory()->create());

    // Simulate the worker container not mounting the storage volume.
    Storage::disk('assets')->delete($tag->logoAsset?->disk_path ?? '');

    $job = new GenerateMenuTagJob($tag->id);

    expect(fn () => $job->handle($engine))->toThrow(EngineFailureException::class)
        ->and($engine->timesGenerated())->toBe(0);
});

it('removes the guest logo (row and file) once the job concludes', function (): void {
    /** @var FakeMenuTagEngine $engine */
    $engine = app(MenuTagEngineContract::class);
    $engine->succeed()->writingStlStubs();

    $token = (string) Str::uuid();
    $tag = menuTagWithLogo(null, $token);
    $diskPath = $tag->logoAsset?->disk_path ?? '';

    (new GenerateMenuTagJob($tag->id))->handle($engine);

    expect($tag->refresh()->status)->toBe(MenuTagStatus::Completed)
        ->and(LogoAsset::query()->where('disk_path', $diskPath)->exists())->toBeFalse();

    Storage::disk('assets')->assertMissing($diskPath);
});
