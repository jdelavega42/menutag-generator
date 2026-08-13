<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MenuTagStatus;
use App\Http\Controllers\Controller;
use App\Models\MenuTag;
use App\Services\PrintGuideGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * GET /api/v1/menu-tags/{id}/guide (docs/openapi.yaml): the Italian print
 * guide as text/markdown, generated ON DEMAND from the record parameters and
 * the engine report stored on it (decisions §4: no second artifact on disk,
 * always coherent with the metadata).
 *
 * 409 before `completed`: the guide quotes real report values (PAUSE_Z,
 * BICOLOR_LAYERS, CAPACITY_ML, warnings) that only exist after a successful
 * run.
 */
class MenuTagGuideController extends Controller
{
    public function __invoke(MenuTag $menuTag, PrintGuideGenerator $generator): Response
    {
        Gate::authorize('view', $menuTag);

        abort_unless(
            $menuTag->status === MenuTagStatus::Completed,
            409,
            'La guida di stampa è disponibile solo a generazione completata: interroga GET /api/v1/menu-tags/{id} e riprova quando lo stato è "completed".',
        );

        return response($generator->generate($menuTag), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="guida-stampa-%s-%d.md"',
                $menuTag->preset->value,
                $menuTag->id,
            ),
        ]);
    }
}
