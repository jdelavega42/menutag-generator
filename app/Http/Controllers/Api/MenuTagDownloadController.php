<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MenuTagStatus;
use App\Http\Controllers\Controller;
use App\Models\MenuTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /api/v1/menu-tags/{id}/download (docs/openapi.yaml): the binary STL,
 * streamed from the private 'stl' disk — never from public/.
 *
 * Contract: 409 when the generation is not `completed` (or when
 * ?part=accent is requested outside inlay mode), 403 when the record belongs
 * to another user (MenuTagPolicy::download), 404 when it does not exist.
 * PRINTABILITY=blocked does NOT stop the download (spec §8.8): the report in
 * GET /menu-tags/{id} carries the explicit warning, the user decides.
 */
class MenuTagDownloadController extends Controller
{
    public function __invoke(Request $request, MenuTag $menuTag): StreamedResponse
    {
        Gate::authorize('download', $menuTag);

        /** @var array{part?: string} $validated */
        $validated = $request->validate(
            ['part' => ['sometimes', Rule::in(['base', 'accent'])]],
            ['part.in' => 'Parte non valida: usa "base" oppure "accent" (solo in modalità inlay).'],
        );

        $part = $validated['part'] ?? 'base';

        abort_unless(
            $menuTag->status === MenuTagStatus::Completed,
            409,
            'La generazione non è ancora completata: interroga GET /api/v1/menu-tags/{id} e riprova quando lo stato è "completed".',
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

        abort_unless($disk->exists($path), 404, 'Il file STL non esiste più (retention scaduta): rigenera la targhetta.');

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
