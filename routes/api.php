<?php

declare(strict_types=1);

use App\Http\Controllers\Api\LogoController;
use App\Http\Controllers\Api\MenuTagController;
use App\Http\Controllers\Api\MenuTagDownloadController;
use App\Http\Controllers\Api\MenuTagGuideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (WS-6) — docs/openapi.yaml is the binding contract
|--------------------------------------------------------------------------
| Sanctum bearer-token API for B2B automation. The guest flow exists only on
| the web UI (session guest_token + signed URLs, spec §7.2): here every
| request is authenticated and ownership goes through the Policies.
|
| POST /menu-tags answers 202 (async generation, spec §5.4) and is throttled
| by the 'api-generate' limiter (30/h per user, registered by WS-5 in
| AppServiceProvider from config product.api.generations_per_hour).
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::post('menu-tags', [MenuTagController::class, 'store'])
            ->middleware('throttle:api-generate')
            ->name('menu-tags.store');

        Route::get('menu-tags', [MenuTagController::class, 'index'])
            ->name('menu-tags.index');

        Route::get('menu-tags/{menuTag}', [MenuTagController::class, 'show'])
            ->whereNumber('menuTag')
            ->name('menu-tags.show');

        Route::get('menu-tags/{menuTag}/download', MenuTagDownloadController::class)
            ->whereNumber('menuTag')
            ->name('menu-tags.download');

        Route::get('menu-tags/{menuTag}/guide', MenuTagGuideController::class)
            ->whereNumber('menuTag')
            ->name('menu-tags.guide');

        Route::post('logos', [LogoController::class, 'store'])
            ->name('logos.store');
    });
