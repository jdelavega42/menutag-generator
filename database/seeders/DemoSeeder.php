<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\DTOs\EngineRequest;
use App\Enums\MenuTagStatus;
use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\QrPreset;
use App\Models\User;
use App\Rules\CleanImageUpload;
use App\Services\FakeMenuTagEngine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Local demo data (spec §6 WS-6): a demo user (credentials in the README,
 * local only), CODE-GENERATED example logos (simple SVGs written by PHP —
 * never real customer files), saved QR presets and a menu-tag history across
 * every status and product size band, so the whole UI can be explored
 * without generating anything.
 *
 * Completed records get a COHERENT engine report built by
 * FakeMenuTagEngine::realisticResultFor() from their own parameters (the
 * same code path the test suite uses) plus a stub STL on the 'stl' disk, so
 * downloads and print guides work out of the box. No real geometry is
 * produced: that stays in Python (spec §11).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@menutag.test'],
            [
                'name' => 'Demo MenuTag',
                'company_name' => 'Agenzia Demo S.r.l.',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $logos = $this->seedLogos($user);
        $this->seedQrPresets($user);
        $this->seedMenuTags($user, $logos);
    }

    /**
     * Three simple vector logos, generated in code (spec §6: never real
     * customer assets). High-contrast monochrome shapes on purpose — the UI
     * itself recommends exactly this kind of artwork for extrusion.
     *
     * @return list<LogoAsset>
     */
    private function seedLogos(User $user): array
    {
        $disk = Storage::disk('assets');
        $assets = [];

        foreach ($this->demoSvgs() as $name => $svg) {
            $fileName = CleanImageUpload::generatedFileName(CleanImageUpload::MIME_SVG);
            $diskPath = 'logos/'.$fileName;
            $disk->put($diskPath, $svg);

            $assets[] = LogoAsset::query()->create([
                'user_id' => $user->id,
                'guest_token' => null,
                'disk_path' => $diskPath,
                'original_name' => $name,
                'mime' => CleanImageUpload::MIME_SVG,
                'size_bytes' => strlen($svg),
            ]);
        }

        return $assets;
    }

    private function seedQrPresets(User $user): void
    {
        $presets = [
            'Menù IT' => 'https://menu.example.it/demo',
            'Menù EN' => 'https://menu.example.it/demo-en',
            'Recensioni' => 'https://menu.example.it/recensioni',
        ];

        foreach ($presets as $name => $data) {
            QrPreset::query()->firstOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                ['data' => $data],
            );
        }
    }

    /**
     * History across the three presets, the four statuses and the three
     * product size bands of spec §3.2.
     *
     * @param  list<LogoAsset>  $logos
     */
    private function seedMenuTags(User $user, array $logos): void
    {
        // 1. The main product: MenuTag preset, completed, with NFC — the
        //    print-validated reference (58.8 × 3.0, pause layer 19 of 29).
        $withNfc = MenuTag::factory()
            ->for($user)
            ->menuTagPreset()
            ->create([
                'label' => 'Targhetta menù — Trattoria (NFC)',
                'parameters' => [
                    ...$this->presetParameters('menutag'),
                    'nfc' => true,
                    'tag_diameter' => 25,
                ],
            ]);
        $this->complete($withNfc);

        // 2. MenuTag preset, completed, plain QR, plate of 4 (series).
        $plate = MenuTag::factory()
            ->for($user)
            ->menuTagPreset()
            ->create([
                'label' => 'Targhetta menù — piastra da 4',
                'parameters' => [
                    ...$this->presetParameters('menutag'),
                    'plate' => 4,
                ],
            ]);
        $this->complete($plate);

        // 3. Coaster preset with a logo in relief, completed (rimmed →
        //    capacity in the report, PETG in the guide).
        $coaster = MenuTag::factory()
            ->for($user)
            ->coasterPreset()
            ->create([
                'label' => 'Sottobicchiere — logo in rilievo',
                'logo_asset_id' => $logos[0]->id,
                'parameters' => [
                    ...$this->presetParameters('coaster'),
                    'front' => 'logo',
                    'logo_asset_id' => $logos[0]->id,
                ],
            ]);
        $this->complete($coaster);

        // 4. Coaster customized to inlay, completed: two coplanar STLs
        //    (base + accent) and BICOLOR_LAYERS in the report.
        $inlay = MenuTag::factory()
            ->for($user)
            ->coasterPreset()
            ->customized()
            ->create([
                'label' => 'Sottobicchiere — intarsio bicolore',
                'logo_asset_id' => $logos[1]->id,
                'parameters' => [
                    ...$this->presetParameters('coaster'),
                    'front' => 'logo',
                    'logo_asset_id' => $logos[1]->id,
                    'mode' => 'inlay',
                    'depth' => (float) config('product.inlay.default_depth_mm'),
                ],
            ]);
        $this->complete($inlay);

        // 5. Coin Cart, completed, plate of 25, NFC Ø22, negative XY comp
        //    (band 25.75–28.39 mm: the Ø25 tag would not fit).
        $coin = MenuTag::factory()
            ->for($user)
            ->coinCartPreset()
            ->create([
                'label' => 'Gettone carrello — serie da 25',
                'logo_asset_id' => $logos[2]->id,
                'parameters' => [
                    ...$this->presetParameters('coin_cart'),
                    'front' => 'logo',
                    'logo_asset_id' => $logos[2]->id,
                    'nfc' => true,
                    'tag_diameter' => 22,
                    'plate' => 25,
                ],
            ]);
        $this->complete($coin);

        // 6. Middle band (28.40–58.79 mm): NFC-only token, no QR — completed.
        $token = MenuTag::factory()
            ->for($user)
            ->bandMedium()
            ->create(['label' => 'Gettone NFC Ø40 — solo tap']);
        $this->complete($token);

        // 7. One record per non-completed status, to show the whole UI.
        MenuTag::factory()->for($user)->menuTagPreset()->queued()
            ->create(['label' => 'In coda — menù bar']);

        MenuTag::factory()->for($user)->menuTagPreset()->processing()
            ->create(['label' => 'In lavorazione — carta dei vini']);

        MenuTag::factory()->for($user)->menuTagPreset()->failed()->create([
            'label' => 'Fallita — URL troppo lungo',
            'error_message' => 'Con questo indirizzo il codice QR richiede almeno 63.6 mm di lato, '
                .'oppure 86.0 mm di diametro: aumenta la dimensione ad almeno 63.6 mm, '
                .'oppure accorcia l\'URL — un indirizzo breve o un redirect mantiene il formato base.',
        ]);
    }

    /**
     * Mark a record completed with a coherent engine report derived from its
     * own parameters, and write stub STL files so download links work.
     */
    private function complete(MenuTag $menuTag): void
    {
        $stlDisk = Storage::disk('stl');
        $parameters = $menuTag->parameters;

        $stlPath = GenerateMenuTagJob::stlPath($menuTag->id);
        $accentPath = $parameters->mode->value === 'inlay'
            ? GenerateMenuTagJob::stlAccentPath($menuTag->id)
            : null;

        $result = FakeMenuTagEngine::realisticResultFor(new EngineRequest(
            parameters: $parameters,
            outPath: $stlDisk->path($stlPath),
            outAccentPath: $accentPath !== null ? $stlDisk->path($accentPath) : null,
            logoPath: null,
        ));

        // Minimal valid binary STL (80-byte header + zero triangle count):
        // enough for the viewer/download demo, never committed (*.stl is
        // git-ignored).
        $stub = str_pad('MENUTAG-DEMO-STL', 80, "\0").pack('V', 0);
        $stlDisk->put($stlPath, $stub);

        if ($accentPath !== null) {
            $stlDisk->put($accentPath, $stub);
        }

        $menuTag->update([
            'status' => MenuTagStatus::Completed,
            'stl_path' => $stlPath,
            'stl_accent_path' => $accentPath,
            'report' => $result->raw,
            'triangles' => $result->triangles,
            'volume_mm3' => $result->volumeMm3,
            'weight_g' => $result->weightG,
            'pause_z' => $result->pauseZ,
            'pause_layer' => $result->pauseLayer,
            'printability' => $result->printability,
            'error_message' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presetParameters(string $preset): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('product.presets.'.$preset.'.defaults');

        if (($defaults['front'] ?? null) === 'qr') {
            $defaults['qr_data_front'] = (string) config('product.qr.demo_url');
        }

        return $defaults;
    }

    /**
     * Code-generated demo SVGs: simple, monochrome, high-contrast shapes.
     *
     * @return array<string, string>
     */
    private function demoSvgs(): array
    {
        $circleEmblem = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
              <circle cx="50" cy="50" r="46" fill="black"/>
              <circle cx="50" cy="50" r="34" fill="white"/>
              <circle cx="50" cy="50" r="20" fill="black"/>
            </svg>
            SVG;

        $forkAndKnife = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
              <rect x="24" y="10" width="8" height="80" fill="black"/>
              <rect x="14" y="10" width="6" height="30" fill="black"/>
              <rect x="36" y="10" width="6" height="30" fill="black"/>
              <path d="M64 10 h10 v40 l-6 8 v32 h-8 v-32 l4 -8 z" fill="black"/>
            </svg>
            SVG;

        $diamondMonogram = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
              <path d="M50 6 L94 50 L50 94 L6 50 Z" fill="black"/>
              <path d="M50 26 L74 50 L50 74 L26 50 Z" fill="white"/>
              <rect x="42" y="42" width="16" height="16" fill="black"/>
            </svg>
            SVG;

        return [
            'emblema-cerchi.svg' => $circleEmblem,
            'posate.svg' => $forkAndKnife,
            'monogramma-rombo.svg' => $diamondMonogram,
        ];
    }
}
