<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Engine exit code 2 — parametric/dimensional USER error (contract 03 §5).
 *
 * The message IS the user-facing text: the engine writes a human-readable
 * Italian explanation on stderr (including how to get back within limits)
 * and the UI shows it as-is. Never log it as an application failure — it is
 * an expected outcome, not a bug.
 */
final class EngineValidationException extends RuntimeException
{
    public static function fromStderr(string $stderr): self
    {
        $message = trim($stderr);

        if ($message === '') {
            // The engine should always explain itself on exit 2; if it did
            // not, still give the user an actionable Italian message.
            $message = 'La configurazione non è generabile con questi parametri: '
                .'modifica dimensione, spessore o contenuti e riprova.';
        }

        return new self($message);
    }
}
