<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Any anomalous engine outcome that is NOT a user error (contract 03 §5:
 * exit code other than 0 and 2, timeout, unparsable stdout, missing input).
 *
 * INTERNAL: the message is technical (English) and meant for logs — the
 * user only ever sees a generic Italian message chosen by the caller.
 */
final class EngineFailureException extends RuntimeException
{
    private const int STDERR_EXCERPT_CHARS = 4000;

    /**
     * Pre-check failure: the logo path was resolved but the file is not
     * there. Spec §7.1: when app and worker run as separate containers this
     * is the classic symptom of the worker NOT mounting the same storage
     * volume as the app — the record sits in "processing" and Python dies
     * with "file not found", which looks like an engine bug but is Docker
     * orchestration.
     */
    public static function becauseLogoIsMissing(string $logoPath): self
    {
        return new self(sprintf(
            'Logo file not found at "%s" before invoking the engine. '
            .'If app and worker are separate containers, this is the typical symptom of the worker '
            .'container not mounting the same storage volume as the app container (spec §7.1): '
            .'it looks like an engine "file not found" bug, but it is Docker orchestration. '
            .'Verify the worker volume mounts before debugging the engine.',
            $logoPath,
        ));
    }

    public static function becauseTimedOut(int $timeoutSeconds, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Engine process exceeded the %d s timeout (config product.engine.timeout_s) and was killed.', $timeoutSeconds),
            0,
            $previous,
        );
    }

    public static function becauseProcessCouldNotStart(Throwable $previous): self
    {
        return new self(
            sprintf(
                'Engine process could not start: %s. Check config product.engine.python and that the virtualenv exists.',
                $previous->getMessage(),
            ),
            0,
            $previous,
        );
    }

    public static function fromProcess(?int $exitCode, string $stderr): self
    {
        return new self(sprintf(
            'Engine exited with internal error code %s. stderr: %s',
            $exitCode === null ? 'unknown' : (string) $exitCode,
            self::excerpt($stderr),
        ));
    }

    public static function becauseStdoutIsMalformed(string $reason, string $stdout): self
    {
        return new self(sprintf(
            'Engine exited 0 but its stdout does not honour contract 03 §4 (%s). stdout: %s',
            $reason,
            self::excerpt($stdout),
        ));
    }

    private static function excerpt(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '(empty)';
        }

        if (mb_strlen($text) > self::STDERR_EXCERPT_CHARS) {
            return mb_substr($text, 0, self::STDERR_EXCERPT_CHARS).'… (truncated)';
        }

        return $text;
    }
}
