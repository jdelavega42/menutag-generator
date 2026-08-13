<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\EngineRequest;
use App\DTOs\EngineResult;
use App\Exceptions\EngineFailureException;
use App\Exceptions\EngineValidationException;

/**
 * Boundary to the geometry engine (contract 03 §1).
 *
 * Laravel orchestrates, Python computes: no geometry in PHP and no Process
 * call outside PythonMenuTagEngine (spec §11). Implementations:
 * PythonMenuTagEngine (production) and FakeMenuTagEngine (tests — no test
 * ever invokes Python; at most one integration test in group 'integration').
 */
interface MenuTagEngineContract
{
    /**
     * @throws EngineValidationException exit code 2 — user error: human-readable
     *                                   message from stderr, to be shown to the user as-is
     * @throws EngineFailureException any other anomalous outcome — internal
     *                                error: stderr is logged, the user only sees a generic message
     */
    public function generate(EngineRequest $request): EngineResult;
}
