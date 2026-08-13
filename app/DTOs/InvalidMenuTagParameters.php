<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

/**
 * Thrown by MenuTagParameters when one or more invariants (V1..V12) fail.
 *
 * Carries a per-field error map keyed by the snake_case field names used in
 * the API payload (contract 02), so the Form Request can surface the same
 * messages as `parameters.<field>` validation errors. Messages are in
 * Italian (user-facing) and always explain how to get back within limits.
 *
 * Lives in App\DTOs next to the DTO it guards: app/Exceptions/ belongs to
 * other workstreams (declared file boundary).
 */
final class InvalidMenuTagParameters extends InvalidArgumentException
{
    /**
     * @param  array<string, list<string>>  $errors  snake_case field => messages
     */
    private function __construct(
        public readonly array $errors,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, list<string>>  $errors  snake_case field => messages
     */
    public static function withErrors(array $errors): self
    {
        $flat = [];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $fieldMessage) {
                $flat[] = sprintf('%s: %s', $field, $fieldMessage);
            }
        }

        return new self($errors, implode(' | ', $flat));
    }
}
