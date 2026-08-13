<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LogoAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogoAsset>
 */
class LogoAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_token' => null,
            'disk_path' => 'logos/'.fake()->uuid().'.png',
            'original_name' => fake()->word().'.png',
            'mime' => 'image/png',
            // Upload limit is 2 MB (product.guests.upload_max_kb).
            'size_bytes' => fake()->numberBetween(1_024, 2_048 * 1_024),
        ];
    }

    public function svg(): static
    {
        return $this->state(fn (array $attributes): array => [
            'disk_path' => 'logos/'.fake()->uuid().'.svg',
            'original_name' => fake()->word().'.svg',
            'mime' => 'image/svg+xml',
        ]);
    }

    /**
     * A guest upload: no user, identified by its guest token.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'guest_token' => fake()->uuid(),
        ]);
    }
}
