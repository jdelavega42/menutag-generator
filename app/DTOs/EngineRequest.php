<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Input of a single engine run (contract 03 §1).
 *
 * Every path here is ABSOLUTE and must be resolved inside the worker by the
 * Job (`Storage::disk(...)->path()`): the queue payload only carries IDs or
 * relative paths, because app and worker are distinct containers sharing a
 * storage volume (spec §7.1).
 *
 * Coherence rules (`outAccentPath` required iff mode=inlay, `logoPath`
 * required when a face has a logo) are enforced by
 * MenuTagParameters::toCliArguments(), the single DTO → CLI translator.
 */
final readonly class EngineRequest
{
    public function __construct(
        public MenuTagParameters $parameters,
        /** Absolute path of the base body STL, inside the 'stl' disk. */
        public string $outPath,
        /** Absolute path of the accent STL — required ⇔ mode=inlay. */
        public ?string $outAccentPath,
        /** Absolute path of the logo inside the 'assets' disk, null when no face has a logo. */
        public ?string $logoPath,
    ) {}
}
