<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FaceContent;
use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Enums\Printability;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuTag>
 *
 * Parameter snapshots are built from config('product.presets.*.defaults'),
 * so they always satisfy the DTO invariants enforced by the parameters cast.
 * Faces that would require a logo asset default to 'none' to keep records
 * self-contained; use withLogo() (as the LAST state) to attach one.
 */
class MenuTagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'guest_token' => null,
            'label' => fake()->words(2, true),
            'preset' => Preset::MenuTag,
            'customized' => false,
            'logo_asset_id' => null,
            'parameters' => self::presetParameters(Preset::MenuTag),
            'status' => MenuTagStatus::Queued,
        ];
    }

    /*
    |----------------------------------------------------------------------
    | Preset states
    |----------------------------------------------------------------------
    */

    public function menuTagPreset(): static
    {
        return $this->forPreset(Preset::MenuTag);
    }

    public function coasterPreset(): static
    {
        return $this->forPreset(Preset::Coaster);
    }

    public function coinCartPreset(): static
    {
        return $this->forPreset(Preset::CoinCart);
    }

    public function forPreset(Preset $preset): static
    {
        return $this->state(fn (array $attributes): array => [
            'preset' => $preset,
            'customized' => false,
            'parameters' => self::presetParameters($preset),
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Status states
    |----------------------------------------------------------------------
    */

    public function queued(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MenuTagStatus::Queued,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MenuTagStatus::Processing,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $triangles = fake()->numberBetween(5_000, 80_000);
            $volume = fake()->randomFloat(3, 5_000, 25_000);
            // Full-solid weight upper bound, PLA density (config materials).
            $weight = round($volume / 1_000 * 1.24, 2);

            return [
                'status' => MenuTagStatus::Completed,
                'stl_path' => 'menu-tags/'.fake()->uuid().'.stl',
                'triangles' => $triangles,
                'volume_mm3' => $volume,
                'weight_g' => $weight,
                'printability' => Printability::Ok,
                // Engine stdout keys as parsed by WS-3 (contract 03 §4).
                'report' => [
                    'OK' => '1',
                    'TRIANGLES' => (string) $triangles,
                    'VOLUME_MM3' => (string) $volume,
                    'WEIGHT_G' => (string) $weight,
                    'NOZZLE' => '0.4',
                    'LAYER_HEIGHT' => '0.1',
                    'FIRST_LAYER' => '0.2',
                    'BBOX_X' => '58.8',
                    'BBOX_Y' => '58.8',
                    'BBOX_Z' => '3.0',
                    'FILE_SIZE_KB' => (string) fake()->numberBetween(100, 4_000),
                    'VOLUME_DELTA_MM3' => '0.0001',
                    'SIZE_MIN_FUNCTIONAL_MM' => '58.8',
                    'RENDER_MODE' => 'engrave',
                    'MATERIAL' => 'pla-matte',
                    'PLATE' => '1',
                    'XY_COMP_MM' => '0.0',
                    'PRINTABILITY' => 'ok',
                ],
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => MenuTagStatus::Failed,
            'error_message' => 'La generazione non è andata a buon fine: riprova o contatta l’assistenza.',
        ]);
    }

    /*
    |----------------------------------------------------------------------
    | Size-band states (product catalog tiers, spec §3.2)
    |----------------------------------------------------------------------
    */

    /**
     * 25.75 – 28.39 mm: logo + NFC Ø22 only (a Ø25 tag does not fit).
     */
    public function bandSmall(): static
    {
        return $this->state(fn (array $attributes): array => [
            'preset' => Preset::CoinCart,
            'customized' => true,
            'parameters' => [
                ...self::presetParameters(Preset::CoinCart),
                'size' => 26.0,
                'nfc' => true,
                'tag_diameter' => 22,
            ],
        ]);
    }

    /**
     * 28.40 – 58.79 mm: the "token" tier — logo + NFC Ø22/Ø25, no QR yet.
     */
    public function bandMedium(): static
    {
        return $this->state(fn (array $attributes): array => [
            'preset' => Preset::MenuTag,
            'customized' => true,
            'parameters' => [
                ...self::presetParameters(Preset::MenuTag),
                'shape' => 'circle',
                'fillet' => 0.0,
                'size' => 40.0,
                'front' => 'none',
                'qr_data_front' => null,
                'nfc' => true,
                'tag_diameter' => 25,
            ],
        ]);
    }

    /**
     * ≥ 58.80 mm (square): the complete tier with a scannable QR — the main
     * product, i.e. the MenuTag preset defaults.
     */
    public function bandLarge(): static
    {
        return $this->forPreset(Preset::MenuTag);
    }

    /*
    |----------------------------------------------------------------------
    | Ownership and content states
    |----------------------------------------------------------------------
    */

    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'guest_token' => fake()->uuid(),
        ]);
    }

    public function customized(): static
    {
        return $this->state(fn (array $attributes): array => [
            'customized' => true,
        ]);
    }

    /**
     * Attach a logo asset and put it on the front face (qr_logo when a QR is
     * already there). Apply AFTER preset/band states: they replace the whole
     * parameters array. Pass a logo owned by the same user when ownership
     * matters to the test.
     */
    public function withLogo(?LogoAsset $logo = null): static
    {
        return $this->state(function (array $attributes) use ($logo): array {
            $logo ??= LogoAsset::factory()->create();

            /** @var array<string, mixed> $parameters */
            $parameters = $attributes['parameters'];
            $front = FaceContent::tryFrom((string) ($parameters['front'] ?? 'none')) ?? FaceContent::None;
            $parameters['front'] = $front->hasQr() ? FaceContent::QrLogo->value : FaceContent::Logo->value;
            $parameters['logo_asset_id'] = $logo->id;

            return [
                'logo_asset_id' => $logo->id,
                'parameters' => $parameters,
            ];
        });
    }

    /**
     * Preset defaults from config, made self-contained for factories: faces
     * that would require a logo upload fall back to 'none', and the MenuTag
     * QR face gets the demo URL from config.
     *
     * @return array<string, mixed>
     */
    private static function presetParameters(Preset $preset): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('product.presets.'.$preset->value.'.defaults');

        foreach (['front', 'back'] as $face) {
            if (($defaults[$face] ?? 'none') === FaceContent::Logo->value) {
                $defaults[$face] = FaceContent::None->value;
            }
        }

        if (($defaults['front'] ?? null) === FaceContent::Qr->value) {
            $defaults['qr_data_front'] = config('product.qr.demo_url');
        }

        return $defaults;
    }
}
