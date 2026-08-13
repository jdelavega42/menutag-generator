<?php

declare(strict_types=1);

namespace App\Casts;

use App\DTOs\MenuTagParameters;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts the `menu_tags.parameters` JSON column to the MenuTagParameters DTO
 * (contract 01). Serialization goes through fromArray()/toArray(), so every
 * value read from or written to the database passes the V1..V12 invariants.
 *
 * @implements CastsAttributes<MenuTagParameters, mixed>
 */
final class MenuTagParametersCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MenuTagParameters
    {
        if ($value === null) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return MenuTagParameters::fromArray($decoded);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = MenuTagParameters::fromArray($value);
        }

        if (! $value instanceof MenuTagParameters) {
            throw new InvalidArgumentException(
                sprintf('The %s attribute must be a MenuTagParameters instance or an array.', $key),
            );
        }

        return json_encode(
            $value->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
