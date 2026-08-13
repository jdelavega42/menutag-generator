# Contratto — Configurazione (`config/printers.php`, `config/product.php`, dischi)

Tutte le costanti di dominio vivono qui: WS-1 (validazioni PHP), WS-2 (motore:
riceve gli stessi valori via CLI o li replica in `engine/` da un'unica tabella
documentata nel README) e WS-4 (messaggi UI) leggono queste chiavi. Nessuna
costante sparsa.

## `config/printers.php`

```php
return [
    'default' => 'a1mini',
    'profiles' => [
        'a1mini' => [
            'name' => 'Bambu Lab A1 mini',
            'bed_mm' => ['x' => 180, 'y' => 180, 'z' => 180],
            'bed_warn_mm' => 175,            // margine per brim e skirt
            'plate_spacing_mm' => 5.0,
            'plate_surface' => 'PEI testurizzato',
            'nozzles' => [
                '0.2' => ['layer_min' => 0.05, 'layer_max' => 0.15,
                          'layer_default' => 0.10, 'first_layer' => 0.15],
                '0.4' => ['layer_min' => 0.08, 'layer_max' => 0.30,
                          'layer_default' => 0.10, 'first_layer' => 0.20],
            ],
        ],
    ],
];
```

## `config/product.php`

```php
return [
    // Minimi/massimi di prodotto (moneta da 2 €)
    'size_min_mm' => 25.75, 'size_max_mm' => 200.0,
    'thickness_min_mm' => 2.20, 'thickness_max_mm' => 20.0,

    'qr' => [
        'min_pitch_mm' => 1.2,        // policy: indipendente dall'ugello
        'floor_version' => 6,         // v6 = 41 moduli → pavimenti di prodotto:
        'floor_square_mm' => 58.8,    //   min_pitch × (41+8)
        'floor_circle_mm' => 79.2,    //   min_pitch × (41×√2+8), arrotondato a 0.1
        'quiet_zone_modules' => 4,
        'default_ec' => 'H',
        'module_dilation_mm' => 0.005,
        'logo_channel_passes' => 1.2, // canale chiaro logo↔moduli, in passate
        'demo_url' => 'https://menu.example.it/demo',
        // Capacità byte-mode ISO/IEC 18004 per versione (v1..v20), per EC.
        // ATTENZIONE: capacità di CARATTERI, non codeword dati (l'header di
        // modo+contatore è già sottratto). Unica fonte per PHP, Python e JS
        // (replicata in engine/, test di parità con casi al confine:
        // URL da 64 e 65 byte → v7/v8, codificando con la libreria QR reale).
        'byte_capacity' => [
            'H' => [1=>7, 2=>14, 3=>24, 4=>34, 5=>44, 6=>58, 7=>64, 8=>84,
                    9=>98, 10=>119, 11=>137, 12=>155, 13=>177, 14=>194,
                    15=>220, 16=>250, 17=>280, 18=>310, 19=>338, 20=>382],
            'Q' => [1=>11, 2=>20, 3=>32, 4=>46, 5=>60, 6=>74, 7=>86, 8=>108,
                    9=>130, 10=>151, 11=>177, 12=>203, 13=>241, 14=>258,
                    15=>292, 16=>322, 17=>364, 18=>394, 19=>442, 20=>482],
            'M' => [1=>14, 2=>26, 3=>42, 4=>62, 5=>84, 6=>106, 7=>122, 8=>152,
                    9=>180, 10=>213, 11=>251, 12=>287, 13=>331, 14=>362,
                    15=>412, 16=>450, 17=>504, 18=>560, 19=>624, 20=>666],
            'L' => [1=>17, 2=>32, 3=>53, 4=>78, 5=>106, 6=>134, 7=>154, 8=>192,
                    9=>230, 10=>271, 11=>321, 12=>367, 13=>425, 14=>458,
                    15=>520, 16=>586, 17=>644, 18=>718, 19=>792, 20=>858],
        ],
    ],

    'nfc' => [
        'radial_clearance_mm' => 0.20, 'axial_clearance_mm' => 0.20,
        'radial_wall_min_mm' => 1.50, 'axial_wall_min_mm' => 0.40,
        'axial_wall_min_layers' => 2,
        'tag_thickness_default_mm' => 0.80,
        'tag_thickness_range_mm' => [0.30, 1.60],
    ],

    'graphics' => [
        'depth_min_mm' => 0.2, 'depth_max_mm' => 2.0,
        'core_min_mm' => 1.0, 'core_min_layers' => 4,
    ],

    'detail' => [   // moltiplicatori dell'ugello
        'exist_x' => 1, 'legible_x' => 2, 'full_x' => 3, 'inlay_x' => 4,
        'inlay_void_passes' => 2,
        'loss_warn_pct' => 2.0, 'loss_block_pct' => 10.0,
    ],

    'materials' => [
        'pla-matte' => ['density_g_cm3' => 1.24, 'dishwasher_safe' => false],
        'petg'      => ['density_g_cm3' => 1.27, 'dishwasher_safe' => true],
    ],

    // Proposto dalla UI quando l'utente passa a mode=inlay senza aver
    // toccato depth (§3.6: contenere i layer bicromatici). Consumatori:
    // Configurator (04) e Form Request (02).
    'inlay' => ['default_depth_mm' => 0.5],

    // Parametri slicer per la guida di stampa (§8.7). ASSUNZIONI dichiarate
    // da tarare: il motore produce solo geometria, questi valori non escono
    // da lui. Consumatore: generatore della guida (WS-6).
    'print_profiles' => [
        'pla-matte' => ['nozzle_temp_c' => 220, 'bed_temp_c' => 65,
                        'fan_pct' => 100],
        'petg'      => ['nozzle_temp_c' => 250, 'bed_temp_c' => 70,
                        'fan_pct' => 50],
        'common' => [
            'wall_loops' => 2,            // mai "only one wall" (§8.4)
            'top_bottom_shells' => 4,
            'infill_pct' => 15,
            'brim' => true, 'supports' => false,
            'rimmed_recess_solid_layers' => 6, 'rimmed_ironing' => true,
            'inlay_purge_generous' => true,
        ],
    ],

    'guests' => [
        'generations_per_hour' => 5,   // per IP
        'retention_hours' => 24,       // STL + loghi ospite; scadenza signed URL allineata
        'upload_max_kb' => 2048,
    ],
    'api' => ['generations_per_hour' => 30],  // autenticati, assunzione dichiarata

    'engine' => [
        'python' => env('MENUTAG_ENGINE_PYTHON', base_path('engine/.venv/bin/python3')),
        'script' => 'engine/menutag.py',
        'timeout_s' => 60,
        'job_timeout_s' => 120,        // sempre > timeout_s (§7.4)
        'stuck_after_minutes' => 15,   // comando di recupero record appesi
    ],

    'plate' => ['max_pieces' => 100],
    'xy_comp_range_mm' => [-0.30, 0.30],

    'presets' => [ /* tabella sotto, chiavi = campi DTO in snake_case */ ],
];
```

## Preset (valori vincolanti)

| Campo | `menutag` | `coaster` | `coin_cart` |
|---|---|---|---|
| shape | square | circle | circle |
| size | **58.8 = pavimento dinamico**: `max(58.8, size_min_qr(URL))`, arrotondato per eccesso a 0.1 | 85.0 | 25.75 |
| fillet | 4.0 | — | — |
| thickness | 3.0 | 4.0 | 2.20 |
| base_profile | flat | **rimmed** (rim 5.0, recess 1.2) | flat |
| front / back | qr / none | logo / none | logo / none |
| mode (default) | engrave (0.6) — UI raccomanda `inlay` dichiarando il requisito AMS | relief (0.6, **sotto il bordo**) — `engrave` **rifiutato** | relief (0.4) |
| qr_ec | H | — | — |
| nfc | opzionale Ø22/Ø25 | opzionale Ø22/Ø25 | opzionale **solo Ø22** |
| nozzle / layer | 0.4 / 0.10 | 0.4 / 0.10 | 0.4 / 0.10 |
| material | pla-matte | **petg** (lavastoviglie; PLA Tg ~60 °C si imbarca) | pla-matte |
| xy_comp | 0.0 | 0.0 | **−0.10** (taratura col calibro in guida) |
| plate suggerito | 4 | 4 | 25 |
| avvertenze UI | consiglio URL breve dove si scrive l'URL | capacità 5.3 ml mostrata; trattiene condensa, non impermeabile | normativa Reg. CE 2182/2004 in chiaro; «verificare con un legale per uso commerciale — non è consulenza legale» |

Riferimento validato in stampa (documentare in config come commento): MenuTag
58.8 × 3.0, engrave 0.6, passo modulo 1.200 mm, con NFC tasca Ø25.4 × 1.0 e
pausa dopo il layer 19 di 29 (`PAUSE_Z=2.0`), Bambu Lab A1 mini.

## `config/filesystems.php` — dischi dedicati (root dichiarate, mai il default)

```php
'assets' => ['driver' => 'local', 'root' => storage_path('app/assets'),
             'serve' => false, 'throw' => true],
'stl'    => ['driver' => 'local', 'root' => storage_path('app/stl'),
             'serve' => false, 'throw' => true],
```

Mai servire da `public/`. Il payload dei Job trasporta **ID o path relativi**;
i path assoluti si risolvono solo dentro il job (`Storage::disk(...)->path()`).
Il container worker monta lo stesso volume di storage del container app.
