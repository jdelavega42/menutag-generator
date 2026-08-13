<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MenuTagEngineContract;
use App\DTOs\EngineRequest;
use App\DTOs\EngineResult;
use App\Exceptions\EngineFailureException;
use App\Exceptions\EngineValidationException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ExceptionInterface as SymfonyProcessException;

/**
 * Production engine: runs `engine/menutag.py` through the Process facade.
 *
 * THE ONLY class in the codebase allowed to touch Process (spec §11, WS-2
 * constraint): everything else talks to MenuTagEngineContract. Arguments are
 * always an ARRAY built by MenuTagParameters::toCliArguments() — never a
 * concatenated string, so user input can never be shell-interpreted.
 *
 * Timeouts: the Process runs with config('product.engine.timeout_s') (60 s);
 * the Job that calls this service uses config('product.engine.job_timeout_s')
 * (120 s), which MUST stay greater so the job survives the process and can
 * record the failure on the record (spec §7.4).
 */
final class PythonMenuTagEngine implements MenuTagEngineContract
{
    /** Exit code reserved for user-facing validation errors (contract 03 §5). */
    private const int USER_ERROR_EXIT_CODE = 2;

    public function generate(EngineRequest $request): EngineResult
    {
        $this->assertLogoExists($request);

        $command = $this->command($request);
        $timeout = (int) config('product.engine.timeout_s');

        try {
            $result = Process::path(base_path())
                ->timeout($timeout)
                ->run($command);
        } catch (ProcessTimedOutException $exception) {
            Log::error('MenuTag engine timed out.', [
                'timeout_s' => $timeout,
                'command' => $command,
            ]);

            throw EngineFailureException::becauseTimedOut($timeout, $exception);
        } catch (SymfonyProcessException $exception) {
            Log::error('MenuTag engine process could not start.', [
                'error' => $exception->getMessage(),
                'command' => $command,
            ]);

            throw EngineFailureException::becauseProcessCouldNotStart($exception);
        }

        if ($result->exitCode() === self::USER_ERROR_EXIT_CODE) {
            // Expected outcome, not a bug: the message on stderr is written
            // for the user and shown as-is. No error logging.
            throw EngineValidationException::fromStderr($result->errorOutput());
        }

        if (! $result->successful()) {
            Log::error('MenuTag engine failed with an internal error.', [
                'exit_code' => $result->exitCode(),
                'stderr' => $result->errorOutput(),
                'command' => $command,
            ]);

            throw EngineFailureException::fromProcess($result->exitCode(), $result->errorOutput());
        }

        try {
            return EngineResult::fromStdout($result->output());
        } catch (EngineFailureException $exception) {
            Log::error('MenuTag engine stdout violates contract 03 §4.', [
                'error' => $exception->getMessage(),
                'command' => $command,
            ]);

            throw $exception;
        }
    }

    /**
     * Pre-check (spec §7.1): the Job resolved an absolute logo path, but if
     * the file is not visible from THIS container the run is doomed. Failing
     * here with an explicit message beats a cryptic Python "file not found"
     * — the classic symptom of the worker container not mounting the same
     * storage volume as the app container.
     */
    private function assertLogoExists(EngineRequest $request): void
    {
        if ($request->logoPath !== null && ! is_file($request->logoPath)) {
            Log::error('MenuTag engine pre-check failed: logo file not found.', [
                'logo_path' => $request->logoPath,
            ]);

            throw EngineFailureException::becauseLogoIsMissing($request->logoPath);
        }
    }

    /**
     * Full argv: python binary, engine script, then the deterministic
     * argument list from the single DTO → CLI translator (contract 02).
     *
     * @return list<string>
     */
    private function command(EngineRequest $request): array
    {
        return [
            (string) config('product.engine.python'),
            (string) config('product.engine.script'),
            ...$request->parameters->toCliArguments(
                outPath: $request->outPath,
                outAccentPath: $request->outAccentPath,
                logoPath: $request->logoPath,
            ),
        ];
    }
}
