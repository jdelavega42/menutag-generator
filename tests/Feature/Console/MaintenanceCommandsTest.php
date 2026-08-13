<?php

declare(strict_types=1);

use App\Enums\MenuTagStatus;
use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Scheduled maintenance (spec §5.1/§7.4, WS-3 commands): guest retention of
 * 24 h with file cleanup, and recovery of records stuck in processing.
 */
beforeEach(function (): void {
    Storage::fake('stl');
    Storage::fake('assets');
});

it('prunes guest records and their STL files past the retention window', function (): void {
    $retention = (int) config('product.guests.retention_hours');

    $expired = completeWithRealisticReport(MenuTag::factory()->guest()->create());
    MenuTag::query()->whereKey($expired->id)->update(['created_at' => now()->subHours($retention + 1)]);

    $fresh = completeWithRealisticReport(MenuTag::factory()->guest()->create());

    $userOwned = completeWithRealisticReport(MenuTag::factory()->for(User::factory()->create())->create());
    MenuTag::query()->whereKey($userOwned->id)->update(['created_at' => now()->subHours($retention + 48)]);

    $this->artisan('menutag:prune-guests')->assertSuccessful();

    expect(MenuTag::query()->find($expired->id))->toBeNull()
        ->and(MenuTag::query()->find($fresh->id))->not->toBeNull()
        // Authenticated users are NEVER pruned: retention is a guest rule.
        ->and(MenuTag::query()->find($userOwned->id))->not->toBeNull();

    Storage::disk('stl')->assertMissing(GenerateMenuTagJob::stlPath($expired->id));
    Storage::disk('stl')->assertExists(GenerateMenuTagJob::stlPath($fresh->id));
});

it('prunes orphan guest logos past the retention window', function (): void {
    $retention = (int) config('product.guests.retention_hours');

    Storage::disk('assets')->put('logos/orphan.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $orphan = LogoAsset::factory()->guest()->create(['disk_path' => 'logos/orphan.svg']);
    LogoAsset::query()->whereKey($orphan->id)->update(['created_at' => now()->subHours($retention + 1)]);

    Storage::disk('assets')->put('logos/fresh.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $freshOrphan = LogoAsset::factory()->guest()->create(['disk_path' => 'logos/fresh.svg']);

    $this->artisan('menutag:prune-guests')->assertSuccessful();

    expect(LogoAsset::query()->find($orphan->id))->toBeNull()
        ->and(LogoAsset::query()->find($freshOrphan->id))->not->toBeNull();

    Storage::disk('assets')->assertMissing('logos/orphan.svg');
    Storage::disk('assets')->assertExists('logos/fresh.svg');
});

it('recovers records stuck in processing beyond the threshold', function (): void {
    $threshold = (int) config('product.engine.stuck_after_minutes');

    $stuck = MenuTag::factory()->guest()->processing()->create();
    MenuTag::query()->whereKey($stuck->id)->update(['updated_at' => now()->subMinutes($threshold + 5)]);

    $running = MenuTag::factory()->guest()->processing()->create();

    $this->artisan('menutag:recover-stuck')->assertSuccessful();

    $stuck->refresh();
    $running->refresh();

    expect($stuck->status)->toBe(MenuTagStatus::Failed)
        ->and((string) $stuck->error_message)->toContain('interrotta')
        ->and($running->status)->toBe(MenuTagStatus::Processing);
});

it('keeps a referenced guest logo until its record expires too', function (): void {
    $retention = (int) config('product.guests.retention_hours');
    $token = (string) Str::uuid();

    Storage::disk('assets')->put('logos/used.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $logo = LogoAsset::factory()->guest()->create(['guest_token' => $token, 'disk_path' => 'logos/used.svg']);
    LogoAsset::query()->whereKey($logo->id)->update(['created_at' => now()->subHours($retention + 1)]);

    // A FRESH guest record still references the old logo.
    MenuTag::factory()->guest()->queued()->create([
        'guest_token' => $token,
        'logo_asset_id' => $logo->id,
    ]);

    $this->artisan('menutag:prune-guests')->assertSuccessful();

    expect(LogoAsset::query()->find($logo->id))->not->toBeNull();
    Storage::disk('assets')->assertExists('logos/used.svg');
});
