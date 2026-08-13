<?php

declare(strict_types=1);

use App\Enums\Printability;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use App\Services\PrintGuideGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * Print guide (spec §8.7): Italian Markdown generated from the REAL values
 * of record + report — nozzle/layer/first layer from the engine report,
 * slicer profile from config product.print_profiles per material, PEI plate,
 * brim, NO supports; rimmed → solid recess layers + ironing; inlay →
 * filament assignment + BICOLOR_LAYERS + generous purge; NFC → pause at
 * PAUSE_Z AND PAUSE_LAYER with the caliper instruction; XY compensation
 * check on the first piece; WARNING= lines included.
 */
beforeEach(function (): void {
    Storage::fake('stl');
});

it('quotes the real slicer parameters for the PLA MenuTag preset', function (): void {
    $tag = completeWithRealisticReport(MenuTag::factory()->for(User::factory()->create())->create([
        'parameters' => [
            ...(array) config('product.presets.menutag.defaults'),
            'qr_data_front' => 'https://menu.example.it/demo',
        ],
    ]));

    $guide = app(PrintGuideGenerator::class)->generate($tag);

    $common = (array) config('product.print_profiles.common');
    $pla = (array) config('product.print_profiles.pla-matte');

    expect($guide)
        ->toContain('# Guida di stampa')
        ->toContain('**0.4 mm**')                                        // nozzle from the report
        ->toContain('| Altezza layer | **0.10 mm** |')                   // layer from the report (verbatim)
        ->toContain('| Primo layer | **0.20 mm** |')                     // first layer from the report
        ->toContain((string) $common['wall_loops'])
        ->toContain('only one wall')                                     // spec §8.4 operational warning
        ->toContain(sprintf('%d %%', (int) $common['infill_pct']))
        ->toContain(sprintf('%d °C (PLA matte)', (int) $pla['nozzle_temp_c']))
        ->toContain(sprintf('%d °C', (int) $pla['bed_temp_c']))
        ->toContain('PEI')                                               // plate surface
        ->toContain('| Brim | sì |')
        ->toContain('| Supporti | **NO**')
        ->toContain('limite superiore');                                  // WEIGHT_G honesty note
});

it('adds pause instructions with PAUSE_Z and PAUSE_LAYER for NFC records', function (): void {
    $tag = completeWithRealisticReport(MenuTag::factory()->for(User::factory()->create())->create([
        'parameters' => [
            ...(array) config('product.presets.menutag.defaults'),
            'qr_data_front' => 'https://menu.example.it/demo',
            'nfc' => true,
            'tag_diameter' => 25,
        ],
    ]));

    $guide = app(PrintGuideGenerator::class)->generate($tag);
    $report = (array) $tag->report;

    // The validated reference: 58.8 × 3.0 → PAUSE_Z=2.0 after layer 19.
    expect($guide)
        ->toContain('Tag NFC — pausa a metà stampa')
        ->toContain(sprintf('Z = %s mm', $report['PAUSE_Z']))
        ->toContain(sprintf('dopo il layer %s', $report['PAUSE_LAYER']))
        ->toContain('Ø25 mm')
        ->toContain('calibro');                                           // measure the REAL tag thickness

    expect($report['PAUSE_Z'])->toBe('2.00')
        ->and($report['PAUSE_LAYER'])->toBe('19');
});

it('covers rimmed, PETG and inlay specifics for a customized Coaster', function (): void {
    $user = User::factory()->create();
    $logo = LogoAsset::factory()->for($user)->create();

    $tag = completeWithRealisticReport(MenuTag::factory()->for($user)->coasterPreset()->create([
        'logo_asset_id' => $logo->id,
        'parameters' => [
            ...(array) config('product.presets.coaster.defaults'),
            'front' => 'logo',
            'logo_asset_id' => $logo->id,
            'mode' => 'inlay',
            'depth' => 0.5,
        ],
    ]));

    $guide = app(PrintGuideGenerator::class)->generate($tag);
    $report = (array) $tag->report;
    $common = (array) config('product.print_profiles.common');
    $petg = (array) config('product.print_profiles.petg');

    expect($guide)
        // PETG per preset (dishwasher) with its own temperatures.
        ->toContain('PETG')
        ->toContain(sprintf('%d °C (PETG)', (int) $petg['nozzle_temp_c']))
        // Rimmed: solid layers on the recess bottom + ironing + capacity.
        ->toContain(sprintf('almeno %d', (int) $common['rimmed_recess_solid_layers']))
        ->toContain('Ironing')
        ->toContain(sprintf('%s ml', $report['CAPACITY_ML']))
        // Inlay: two files, filament assignment, bicolor layers, purge.
        ->toContain('due file STL complanari')
        ->toContain('colore principale')
        ->toContain('colore di contrasto')
        ->toContain((string) $report['BICOLOR_LAYERS'])
        ->toContain('spurgo');
});

it('explains the applied negative XY compensation on the Coin Cart', function (): void {
    $user = User::factory()->create();
    $logo = LogoAsset::factory()->for($user)->create();

    $tag = completeWithRealisticReport(MenuTag::factory()->for($user)->coinCartPreset()->create([
        'logo_asset_id' => $logo->id,
        'parameters' => [
            ...(array) config('product.presets.coin_cart.defaults'),
            'front' => 'logo',
            'logo_asset_id' => $logo->id,
        ],
    ]));

    $guide = app(PrintGuideGenerator::class)->generate($tag);

    expect($guide)
        ->toContain('compensazione XY di -0.1 mm per lato')
        ->toContain('Misura col calibro il primo pezzo')
        ->toContain('gettone');
});

it('includes the WARNING lines of the engine report', function (): void {
    $tag = completeWithRealisticReport(MenuTag::factory()->for(User::factory()->create())->create([
        'parameters' => [
            ...(array) config('product.presets.menutag.defaults'),
            'qr_data_front' => 'https://menu.example.it/demo',
        ],
    ]));

    // Append a warning the way the parser stores it (raw['WARNING']).
    $report = (array) $tag->report;
    $report['WARNING'] = "La piastra occupa 176.4 × 176.4 mm e supera il margine consigliato di 175 mm.\nSecondo avviso di prova.";
    $tag->update(['report' => $report, 'printability' => Printability::Warn]);

    $guide = app(PrintGuideGenerator::class)->generate($tag->refresh());

    expect($guide)
        ->toContain('Avvisi del controllo di stampabilità')
        ->toContain('La piastra occupa 176.4 × 176.4 mm')
        ->toContain('Secondo avviso di prova');
});

it('refuses to generate a guide without an engine report', function (): void {
    $tag = MenuTag::factory()->for(User::factory()->create())->queued()->create();

    expect(fn (): string => app(PrintGuideGenerator::class)->generate($tag))
        ->toThrow(LogicException::class);
});
