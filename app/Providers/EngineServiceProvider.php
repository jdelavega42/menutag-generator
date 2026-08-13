<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MenuTagEngineContract;
use App\Services\FakeMenuTagEngine;
use App\Services\PythonMenuTagEngine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the geometry engine boundary (contract 03 §1): the contract resolves
 * to PythonMenuTagEngine in production and to FakeMenuTagEngine in the
 * 'testing' environment — no test ever invokes Python, and the engine stays
 * swappable without touching the Job (WS-2 acceptance).
 *
 * Singletons on purpose: tests resolve FakeMenuTagEngine::class and get the
 * SAME instance the contract consumers used, so the recorded EngineRequest
 * list is available for assertions.
 */
final class EngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PythonMenuTagEngine::class);
        $this->app->singleton(FakeMenuTagEngine::class);

        $this->app->singleton(
            MenuTagEngineContract::class,
            static fn (Application $app): MenuTagEngineContract => $app->environment('testing')
                ? $app->make(FakeMenuTagEngine::class)
                : $app->make(PythonMenuTagEngine::class),
        );
    }
}
