<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\QrPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrPreset>
 */
class QrPresetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // (user_id, name) is unique — keep names unique per run.
            'name' => 'Menù '.fake()->unique()->numerify('###'),
            'data' => 'https://menu.example.it/'.fake()->slug(2),
        ];
    }
}
