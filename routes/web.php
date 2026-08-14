<?php

declare(strict_types=1);

use App\Enums\MenuTagStatus;
use App\Http\Controllers\MenuTagDownloadController;
use App\Models\MenuTag;
use App\Services\PrintGuideGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes (WS-4)
|--------------------------------------------------------------------------
| Home: PresetPicker + Configurator (guests included — EnsureGuestToken is
| appended to the whole web group in bootstrap/app.php).
| Downloads: Policy-gated for authenticated users, temporary signed URL for
| guests (WS-5 provides Policy and middleware; referenced here by name).
| The print-guide route belongs to WS-6.
*/

Route::view('/', 'menu-tags.create')->name('home');

// Studio promo page (restyle §5.3, flussi.md §3): the target of every
// contextual registration CTA and of the guest nav item.
Route::view('studio', 'studio-promo')->name('studio-promo');

Route::get('targhette/{menuTag}', function (MenuTag $menuTag) {
    Gate::authorize('view', $menuTag);

    return view('menu-tags.show', ['menuTag' => $menuTag]);
})->name('menu-tags.show');

Route::get('targhette/{menuTag}/download', [MenuTagDownloadController::class, 'download'])
    ->middleware('auth')
    ->name('menu-tags.download');

Route::get('ospite/targhette/{menuTag}/download', [MenuTagDownloadController::class, 'guest'])
    ->middleware('signed')
    ->name('guest.menu-tags.download');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';

/*
|--------------------------------------------------------------------------
| Print guide (WS-6, spec §8.7) — appended, same dual access as downloads
|--------------------------------------------------------------------------
| Generated on demand from the record parameters + engine report (decisions
| §4), as text/markdown, only when status is `completed` (409 otherwise).
| - Authenticated users: MenuTagPolicy 'view' (Policy, not signed URL).
| - Guests: temporary signed URL toward guest.menu-tags.guide. Like the STL
|   download, the signed URL is CAPABILITY-BASED access (spec §7.2): the
|   signature proves the URL is untampered and unexpired, NOT the identity
|   of whoever opens it — declared trade-off, see EnsureGuestToken; expiry
|   must be aligned to product.guests.retention_hours when generating it.
*/

$printGuideResponse = static function (MenuTag $menuTag) {
    abort_unless(
        $menuTag->status === MenuTagStatus::Completed,
        409,
        'La guida di stampa è disponibile solo a generazione completata: attendi il termine del lavoro e riprova.',
    );

    return response(app(PrintGuideGenerator::class)->generate($menuTag), 200, [
        'Content-Type' => 'text/markdown; charset=UTF-8',
        'Content-Disposition' => sprintf(
            'attachment; filename="guida-stampa-%s-%d.md"',
            $menuTag->preset->value,
            $menuTag->id,
        ),
    ]);
};

Route::get('targhette/{menuTag}/guida', function (MenuTag $menuTag) use ($printGuideResponse) {
    Gate::authorize('view', $menuTag);

    return $printGuideResponse($menuTag);
})->middleware('auth')->name('menu-tags.guide');

// Signed middleware IS the whole authorization here (capability-based, see
// the block comment above): no Policy call, like guest.menu-tags.download.
Route::get('ospite/targhette/{menuTag}/guida', function (MenuTag $menuTag) use ($printGuideResponse) {
    return $printGuideResponse($menuTag);
})->middleware('signed')->name('guest.menu-tags.guide');
