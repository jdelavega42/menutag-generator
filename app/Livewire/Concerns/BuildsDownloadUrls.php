<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\MenuTag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Download URLs per contract 04: authenticated users get the Policy-protected
 * route (`menu-tags.download`, WS-5 authorizes 'download'); guests get a
 * TEMPORARY SIGNED URL toward `guest.menu-tags.download` whose expiry is
 * aligned to the guest retention window (capability-based access, spec §7.2 —
 * see the EnsureGuestToken docblock for the declared trade-off).
 */
trait BuildsDownloadUrls
{
    /**
     * URL to download one STL part ('base' or 'accent'), or null when the
     * part does not exist (accent is inlay-only).
     */
    protected function downloadUrl(MenuTag $menuTag, string $part = 'base'): ?string
    {
        if ($part === 'accent' && $menuTag->stl_accent_path === null) {
            return null;
        }

        if (Auth::check()) {
            return route('menu-tags.download', ['menuTag' => $menuTag->id, 'part' => $part]);
        }

        return URL::temporarySignedRoute(
            'guest.menu-tags.download',
            now()->addHours((int) config('product.guests.retention_hours')),
            ['menuTag' => $menuTag->id, 'part' => $part],
        );
    }

    /**
     * URL to the generated print guide (§8.7), same access model as the STL:
     * Policy route for authenticated users, temporary signed URL for guests.
     */
    protected function guideUrl(MenuTag $menuTag): string
    {
        if (Auth::check()) {
            return route('menu-tags.guide', ['menuTag' => $menuTag->id]);
        }

        return URL::temporarySignedRoute(
            'guest.menu-tags.guide',
            now()->addHours((int) config('product.guests.retention_hours')),
            ['menuTag' => $menuTag->id],
        );
    }
}
