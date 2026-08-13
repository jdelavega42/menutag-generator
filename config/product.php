<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Product domain constants (contract docs/contracts/05-configurazione.md)
|--------------------------------------------------------------------------
| Every domain constant lives here — never inline in code. Consumers:
| WS-1 (PHP validations: DTO invariants V1..V12 and the Form Request),
| WS-2 (the Python engine receives the same values via CLI or replicates
| them in engine/ from a single documented table) and WS-4 (UI messages).
*/

return [
    // Product minimums/maximums (2 € coin reference)
    'size_min_mm' => 25.75,
    'size_max_mm' => 200.0,
    'thickness_min_mm' => 2.20,
    'thickness_max_mm' => 20.0,

    'qr' => [
        'min_pitch_mm' => 1.2,        // policy: independent from the nozzle
        'floor_version' => 6,         // v6 = 41 modules -> product floors:
        'floor_square_mm' => 58.8,    //   min_pitch * (41+8)
        'floor_circle_mm' => 79.2,    //   min_pitch * (41*sqrt(2)+8), rounded to 0.1
        'quiet_zone_modules' => 4,
        'default_ec' => 'H',
        'module_dilation_mm' => 0.005,
        'logo_channel_passes' => 1.2, // light channel between logo and modules, in passes
        'demo_url' => 'https://menu.example.it/demo',
        // Byte-mode capacity per ISO/IEC 18004, per version (v1..v20), per EC
        // level. WARNING: capacity in CHARACTERS, not data codewords (the
        // mode+count header is already subtracted). Single source for PHP,
        // Python and JS (replicated in engine/, parity test with boundary
        // cases: URLs of 64 and 65 bytes -> v7/v8, encoding the payload with
        // the real QR library).
        'byte_capacity' => [
            'H' => [1 => 7, 2 => 14, 3 => 24, 4 => 34, 5 => 44, 6 => 58, 7 => 64, 8 => 84,
                9 => 98, 10 => 119, 11 => 137, 12 => 155, 13 => 177, 14 => 194,
                15 => 220, 16 => 250, 17 => 280, 18 => 310, 19 => 338, 20 => 382],
            'Q' => [1 => 11, 2 => 20, 3 => 32, 4 => 46, 5 => 60, 6 => 74, 7 => 86, 8 => 108,
                9 => 130, 10 => 151, 11 => 177, 12 => 203, 13 => 241, 14 => 258,
                15 => 292, 16 => 322, 17 => 364, 18 => 394, 19 => 442, 20 => 482],
            'M' => [1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84, 6 => 106, 7 => 122, 8 => 152,
                9 => 180, 10 => 213, 11 => 251, 12 => 287, 13 => 331, 14 => 362,
                15 => 412, 16 => 450, 17 => 504, 18 => 560, 19 => 624, 20 => 666],
            'L' => [1 => 17, 2 => 32, 3 => 53, 4 => 78, 5 => 106, 6 => 134, 7 => 154, 8 => 192,
                9 => 230, 10 => 271, 11 => 321, 12 => 367, 13 => 425, 14 => 458,
                15 => 520, 16 => 586, 17 => 644, 18 => 718, 19 => 792, 20 => 858],
        ],
    ],

    'nfc' => [
        // Declared axial-clearance choice (spec §3.3): the pocket is 0.20 mm
        // deeper than the tag, so the closing layer crosses 0.20 mm of air.
        // Cost: one irregular, invisible internal layer. The rejected
        // alternative — the nozzle slamming on a tag thicker than declared —
        // costs the whole print.
        'radial_clearance_mm' => 0.20,
        'axial_clearance_mm' => 0.20,
        'radial_wall_min_mm' => 1.50,
        'axial_wall_min_mm' => 0.40,
        'axial_wall_min_layers' => 2,
        'tag_thickness_default_mm' => 0.80,
        'tag_thickness_range_mm' => [0.30, 1.60],
    ],

    'graphics' => [
        'depth_min_mm' => 0.2,
        'depth_max_mm' => 2.0,
        'core_min_mm' => 1.0,
        'core_min_layers' => 4,
    ],

    'detail' => [   // nozzle multipliers
        'exist_x' => 1,
        'legible_x' => 2,
        'full_x' => 3,
        'inlay_x' => 4,
        'inlay_void_passes' => 2,
        'loss_warn_pct' => 2.0,
        'loss_block_pct' => 10.0,
    ],

    'materials' => [
        'pla-matte' => ['density_g_cm3' => 1.24, 'dishwasher_safe' => false],
        'petg' => ['density_g_cm3' => 1.27, 'dishwasher_safe' => true],
    ],

    // Proposed by the UI when the user switches to mode=inlay without having
    // touched depth (spec §3.6: keep the bichromatic layer count low).
    // Consumers: Configurator (contract 04) and Form Request (contract 02).
    'inlay' => ['default_depth_mm' => 0.5],

    // Slicer parameters for the print guide (spec §8.7). Declared ASSUMPTIONS
    // to be calibrated: the engine only produces geometry, these values never
    // come out of it. Consumer: the print-guide generator (WS-6).
    'print_profiles' => [
        'pla-matte' => ['nozzle_temp_c' => 220, 'bed_temp_c' => 65, 'fan_pct' => 100],
        'petg' => ['nozzle_temp_c' => 250, 'bed_temp_c' => 70, 'fan_pct' => 50],
        'common' => [
            'wall_loops' => 2,            // never "only one wall" (spec §8.4)
            'top_bottom_shells' => 4,
            'infill_pct' => 15,
            'brim' => true,
            'supports' => false,
            'rimmed_recess_solid_layers' => 6,
            'rimmed_ironing' => true,
            'inlay_purge_generous' => true,
        ],
    ],

    'guests' => [
        'generations_per_hour' => 5,   // per IP
        'retention_hours' => 24,       // guest STL + logos; signed URL expiry aligned
        'upload_max_kb' => 2048,
    ],
    'api' => ['generations_per_hour' => 30],  // authenticated, declared assumption

    'engine' => [
        'python' => env('MENUTAG_ENGINE_PYTHON', base_path('engine/.venv/bin/python3')),
        'script' => 'engine/menutag.py',
        'timeout_s' => 60,
        'job_timeout_s' => 120,        // always > timeout_s (spec §7.4)
        'stuck_after_minutes' => 15,   // recovery command for stuck records
    ],

    'plate' => ['max_pieces' => 100],
    'xy_comp_range_mm' => [-0.30, 0.30],

    /*
    |----------------------------------------------------------------------
    | Presets (binding values, contract 05). 'defaults' keys are the DTO
    | fields in snake_case.
    |----------------------------------------------------------------------
    | Print-validated reference — the ONLY configuration with a known
    | real-world outcome on the Bambu Lab A1 mini, reproduced by the
    | 'menutag' preset defaults: MenuTag 58.8 × 3.0 mm, engrave 0.6 mm,
    | QR module pitch exactly 1.200 mm; with NFC enabled, pocket
    | Ø25.4 × 1.0 mm and pause after layer 19 of 29 (PAUSE_Z=2.0).
    |
    | 'menutag' size is a DYNAMIC FLOOR, never a fixed constant: the
    | effective preset size is max(58.8, size_min_qr(url)) rounded up to
    | 0.1 mm, recomputed on the current URL and shape (spec §5.2).
    */
    'presets' => [
        'menutag' => [
            'defaults' => [
                'shape' => 'square',
                'size' => 58.8,       // dynamic floor, see note above
                'fillet' => 4.0,
                'thickness' => 3.0,
                'base_profile' => 'flat',
                'front' => 'qr',
                'back' => 'none',
                'mode' => 'engrave',  // validated in print; UI recommends inlay declaring the AMS requirement
                'depth' => 0.6,
                'qr_ec' => 'H',
                'nfc' => false,
                'tag_diameter' => 25,
                'nozzle' => '0.4',
                'layer_height' => 0.10,
                'material' => 'pla-matte',
                'plate' => 1,
                'xy_comp' => 0.0,
            ],
            'size_is_dynamic_floor' => true,
            'recommended_mode' => 'inlay',
            'rejected_modes' => [],
            'nfc_tag_diameters' => [22, 25],
            'plate_suggested' => 4,
        ],
        'coaster' => [
            'defaults' => [
                'shape' => 'circle',
                'size' => 85.0,
                'fillet' => 0.0,
                'thickness' => 4.0,
                'base_profile' => 'rimmed',
                'rim_width' => 5.0,
                'recess_depth' => 1.2,
                'front' => 'logo',
                'back' => 'none',
                'mode' => 'relief',   // engrave is REJECTED on this preset (holds liquid)
                'depth' => 0.6,       // relief: must stay below the rim
                'nfc' => false,
                'tag_diameter' => 25,
                'nozzle' => '0.4',
                'layer_height' => 0.10,
                'material' => 'petg', // dishwasher-safe; PLA (Tg ~60 °C) warps
                'plate' => 1,
                'xy_comp' => 0.0,
            ],
            'size_is_dynamic_floor' => false,
            'recommended_mode' => 'inlay', // recommended only when AMS is present
            'rejected_modes' => ['engrave'],
            'nfc_tag_diameters' => [22, 25],
            'plate_suggested' => 4,
        ],
        'coin_cart' => [
            'defaults' => [
                'shape' => 'circle',
                'size' => 25.75,
                'fillet' => 0.0,
                'thickness' => 2.20,
                'base_profile' => 'flat',
                'front' => 'logo',
                'back' => 'none',
                'mode' => 'relief',
                'depth' => 0.4,
                'nfc' => false,
                'tag_diameter' => 22, // Ø25 would leave a 0.175 mm radial wall
                'nozzle' => '0.4',
                'layer_height' => 0.10,
                'material' => 'pla-matte',
                'plate' => 1,
                // Per-side negative XY compensation: an FDM print of 25.75
                // nominal comes out at 25.85–25.95; with -0.10 per side the
                // nominal drops to 25.55 and the expected real size to
                // 25.65–25.75. To be calibrated with a caliper on the first
                // piece (mandatory step in the print guide). V1 product
                // limits apply to the NOMINAL size, so 25.75 stays valid.
                'xy_comp' => -0.10,
            ],
            'size_is_dynamic_floor' => false,
            'recommended_mode' => 'relief',
            'rejected_modes' => [],
            'nfc_tag_diameters' => [22],
            'plate_suggested' => 25,
        ],
    ],
];
