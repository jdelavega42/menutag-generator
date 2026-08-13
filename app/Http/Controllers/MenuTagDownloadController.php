<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MenuTagStatus;
use App\Models\MenuTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Thin STL download controller (WS-4 mandate): authorization only, then a
 * streamed download from the private 'stl' disk — never from public/.
 *
 * - `menu-tags.download` (auth): the MenuTagPolicy 'download' ability is the
 *   gate for authenticated users (WS-5).
 * - `guest.menu-tags.download` (signed): the 'signed' middleware on the
 *   route validates the temporary signed URL — capability-based access,
 *   expiry aligned to the guest retention window (spec §7.2, see
 *   EnsureGuestToken).
 *
 * Download requires status `completed` (409 otherwise, like the API); the
 * `part=accent` STL exists only in inlay mode (409 otherwise).
 */
class MenuTagDownloadController extends Controller
{
    /** Authenticated download, Policy-gated. */
    public function download(Request $request, MenuTag $menuTag): StreamedResponse
    {
        Gate::authorize('download', $menuTag);

        return $this->stream($menuTag, (string) $request->query('part', 'base'));
    }

    /**
     * Guest download: the URL signature (validated by the 'signed' route
     * middleware) is the whole authorization — see the declared trade-off in
     * EnsureGuestToken. The part travels inside the signed parameters, so it
     * cannot be tampered with.
     */
    public function guest(Request $request, MenuTag $menuTag): StreamedResponse
    {
        return $this->stream($menuTag, (string) $request->query('part', 'base'));
    }

    private function stream(MenuTag $menuTag, string $part): StreamedResponse
    {
        abort_unless(in_array($part, ['base', 'accent'], true), 404);

        abort_unless(
            $menuTag->status === MenuTagStatus::Completed,
            409,
            'La generazione non è ancora completata: attendi il termine del lavoro e riprova.',
        );

        $path = $part === 'accent' ? $menuTag->stl_accent_path : $menuTag->stl_path;

        abort_if(
            $path === null,
            409,
            $part === 'accent'
                ? 'Questa targhetta non ha una parte accento: il secondo STL esiste solo in modalità intarsio (inlay).'
                : 'Il file STL non è disponibile per questa targhetta.',
        );

        $disk = Storage::disk('stl');

        abort_unless($disk->exists($path), 404, 'Il file STL non esiste più (retention scaduta).');

        $filename = sprintf(
            '%s-%d%s.stl',
            $menuTag->preset->value,
            $menuTag->id,
            $part === 'accent' ? '-accento' : '',
        );

        return response()->streamDownload(
            static function () use ($disk, $path): void {
                $stream = $disk->readStream($path);

                if ($stream !== null) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            $filename,
            ['Content-Type' => 'model/stl'],
        );
    }
}
