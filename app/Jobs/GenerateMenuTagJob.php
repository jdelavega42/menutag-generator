<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\MenuTagEngineContract;
use App\DTOs\EngineRequest;
use App\Enums\MenuTagStatus;
use App\Enums\RenderMode;
use App\Exceptions\EngineFailureException;
use App\Exceptions\EngineValidationException;
use App\Models\MenuTag;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Asynchronous generation of a menu tag (spec §5.4, WS-3).
 *
 * State machine on the record: queued → processing → completed | failed.
 *
 * The payload carries ONLY the MenuTag id — never absolute paths, never an
 * UploadedFile (spec §7.1): app and worker are distinct containers sharing a
 * storage volume, so every absolute path (logo input, STL outputs) is
 * resolved INSIDE the job through Storage::disk(...)->path().
 *
 * Outcome partition (contract 03 §5, decisions §3):
 *  - success                   → completed: relative STL paths, full report
 *                                JSON, metric columns;
 *  - EngineValidationException → failed: the engine message IS the user
 *                                message, stored as-is (exit 2); no retry —
 *                                the engine is deterministic on parameters;
 *  - anything else             → failed with a GENERIC Italian message
 *                                (details only in the logs), stamped by
 *                                failed() once the retries are exhausted.
 */
final class GenerateMenuTagJob implements ShouldQueue
{
    use Queueable;

    /**
     * One retry, for TRANSIENT infrastructure failures only (worker killed
     * mid-run, process timeout under CPU contention, storage volume mounted
     * late): deterministic outcomes never rethrow, so they never retry —
     * re-running a 60 s process on the same parameters cannot change a
     * parametric error, and the record is already terminal.
     */
    public int $tries = 2;

    /**
     * Seconds between the two attempts: enough for a restarting worker or a
     * saturated host to recover, short enough that the UI polling every
     * 2.5 s (spec §7.4) does not sit on "processing" needlessly.
     */
    public int $backoff = 10;

    /**
     * Job timeout MUST stay greater than the Process timeout (120 s vs 60 s,
     * spec §7.4): the job has to survive the engine process to record the
     * failure on the record, otherwise it dies first and the record hangs in
     * "processing" until menutag:recover-stuck picks it up.
     */
    public int $timeout;

    /**
     * Generic user-facing message for internal errors: the technical detail
     * goes to the logs only (decisions §3 — internal errors are never
     * exposed).
     */
    private const string GENERIC_FAILURE_MESSAGE = 'Si è verificato un errore interno durante la generazione: '
        .'riprova tra qualche minuto. Se il problema persiste, contatta l’assistenza.';

    public function __construct(
        public readonly int $menuTagId,
    ) {
        $this->timeout = (int) config('product.engine.job_timeout_s');
    }

    /*
    |----------------------------------------------------------------------
    | STL path convention (single source, reused by menutag:prune-guests)
    |----------------------------------------------------------------------
    */

    /** Directory of every artifact of one record, RELATIVE to the 'stl' disk. */
    public static function stlDirectory(int $menuTagId): string
    {
        return sprintf('menu-tags/%d', $menuTagId);
    }

    /** Base body STL path, RELATIVE to the 'stl' disk (openapi part=base). */
    public static function stlPath(int $menuTagId): string
    {
        return self::stlDirectory($menuTagId).'/base.stl';
    }

    /** Accent STL path, RELATIVE to the 'stl' disk (openapi part=accent, inlay only). */
    public static function stlAccentPath(int $menuTagId): string
    {
        return self::stlDirectory($menuTagId).'/accent.stl';
    }

    public function handle(MenuTagEngineContract $engine): void
    {
        $menuTag = MenuTag::query()->find($this->menuTagId);

        if ($menuTag === null) {
            // Pruned by the guest retention while queued: nothing to do.
            Log::info('GenerateMenuTagJob skipped: record no longer exists.', [
                'menu_tag_id' => $this->menuTagId,
            ]);

            return;
        }

        if ($menuTag->status->isTerminal()) {
            // Duplicate delivery, or menutag:recover-stuck already marked it
            // failed: never flip a state the UI has already shown as final.
            Log::info('GenerateMenuTagJob skipped: record already in a terminal state.', [
                'menu_tag_id' => $menuTag->id,
                'status' => $menuTag->status->value,
            ]);

            return;
        }

        $menuTag->update(['status' => MenuTagStatus::Processing]);

        try {
            $request = $this->buildEngineRequest($menuTag);
            $result = $engine->generate($request);
        } catch (EngineValidationException $exception) {
            // Exit 2 — user error: the stderr message is written FOR the
            // user and is stored as-is (contract 03 §5). Expected outcome,
            // deterministic on the parameters: no retry, no error logging.
            $this->markFailed($menuTag, $exception->getMessage());
            $this->deleteGuestLogo($menuTag);

            return;
        } catch (EngineFailureException $exception) {
            // Internal error: log the detail with the record id, then
            // rethrow so the queue retries once (transient failures) and
            // failed() stamps the generic message after the last attempt.
            Log::error('MenuTag generation failed with an internal engine error.', [
                'menu_tag_id' => $menuTag->id,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $menuTag->update([
            'status' => MenuTagStatus::Completed,
            'stl_path' => self::stlPath($menuTag->id),
            'stl_accent_path' => $menuTag->parameters->mode === RenderMode::Inlay
                ? self::stlAccentPath($menuTag->id)
                : null,
            // Full engine report: every stdout key verbatim (contract 01).
            'report' => $result->raw,
            'triangles' => $result->triangles,
            'volume_mm3' => $result->volumeMm3,
            'weight_g' => $result->weightG,
            'pause_z' => $result->pauseZ,
            'pause_layer' => $result->pauseLayer,
            'printability' => $result->printability,
            'error_message' => null,
        ]);

        $this->deleteGuestLogo($menuTag);
    }

    /**
     * Runs after the LAST failed attempt (internal errors, job timeout,
     * unexpected throwables): ALWAYS leaves a readable Italian error on the
     * record so the UI never shows a dead "processing" (WS-3 constraint).
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateMenuTagJob exhausted its attempts.', [
            'menu_tag_id' => $this->menuTagId,
            'error' => $exception?->getMessage(),
        ]);

        $menuTag = MenuTag::query()->find($this->menuTagId);

        if ($menuTag === null || $menuTag->status->isTerminal()) {
            return;
        }

        // Defensive: a validation exception normally never reaches failed()
        // (handle() absorbs it), but if it ever does, its message is the
        // user-facing one and must not be hidden behind the generic text.
        $message = $exception instanceof EngineValidationException
            ? $exception->getMessage()
            : self::GENERIC_FAILURE_MESSAGE;

        $this->markFailed($menuTag, $message);
        $this->deleteGuestLogo($menuTag);
    }

    /**
     * Resolve every absolute path INSIDE the worker (spec §7.1) and build
     * the engine input. The logo file existence is verified here, before
     * spending engine time: a missing file is the classic symptom of the
     * worker container not mounting the app storage volume.
     */
    private function buildEngineRequest(MenuTag $menuTag): EngineRequest
    {
        $parameters = $menuTag->parameters;
        $stlDisk = Storage::disk('stl');

        // The engine writes into this directory; create it up front so a
        // missing parent never masquerades as an engine failure.
        $stlDisk->makeDirectory(self::stlDirectory($menuTag->id));

        $logoPath = null;

        if ($parameters->front->hasLogo() || $parameters->back->hasLogo()) {
            $logoPath = $this->resolveLogoPath($menuTag);
        }

        return new EngineRequest(
            parameters: $parameters,
            outPath: $stlDisk->path(self::stlPath($menuTag->id)),
            outAccentPath: $parameters->mode === RenderMode::Inlay
                ? $stlDisk->path(self::stlAccentPath($menuTag->id))
                : null,
            logoPath: $logoPath,
        );
    }

    /**
     * @throws EngineFailureException when the logo record or its file is
     *                                missing — internal error: the user gets the generic message,
     *                                the logs get the Docker-volume diagnosis (spec §7.1).
     */
    private function resolveLogoPath(MenuTag $menuTag): string
    {
        $logo = $menuTag->logoAsset;

        if ($logo === null) {
            throw new EngineFailureException(sprintf(
                'MenuTag %d requires a logo on a face but has no usable logo asset '
                .'(logo_asset_id: %s) — the asset row was deleted after the record was queued.',
                $menuTag->id,
                $menuTag->logo_asset_id === null ? 'null' : (string) $menuTag->logo_asset_id,
            ));
        }

        $assetsDisk = Storage::disk('assets');

        if (! $assetsDisk->exists($logo->disk_path)) {
            throw EngineFailureException::becauseLogoIsMissing($assetsDisk->path($logo->disk_path));
        }

        return $assetsDisk->path($logo->disk_path);
    }

    private function markFailed(MenuTag $menuTag, string $message): void
    {
        $menuTag->update([
            'status' => MenuTagStatus::Failed,
            'error_message' => $message,
        ]);
    }

    /**
     * Guest logos are temporary: remove file and row once the job is
     * concluded (spec §7.1) — on every terminal outcome. Kept only while
     * ANOTHER non-terminal record still references the same asset (a guest
     * can queue several generations with one logo); the last concluded job
     * removes it, and menutag:prune-guests catches any leftover.
     */
    private function deleteGuestLogo(MenuTag $menuTag): void
    {
        $logo = $menuTag->logoAsset;

        if ($logo === null || $logo->user_id !== null) {
            return;
        }

        $stillReferenced = MenuTag::query()
            ->where('logo_asset_id', $logo->id)
            ->whereKeyNot($menuTag->id)
            ->whereIn('status', [MenuTagStatus::Queued, MenuTagStatus::Processing])
            ->exists();

        if ($stillReferenced) {
            return;
        }

        Storage::disk('assets')->delete($logo->disk_path);
        // menu_tags.logo_asset_id is nullOnDelete: the record keeps its
        // parameter snapshot, only the FK is cleared.
        $logo->delete();
    }
}
