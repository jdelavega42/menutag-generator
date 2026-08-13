<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Printer profiles (contract docs/contracts/05-configurazione.md)
|--------------------------------------------------------------------------
| Single source for machine constants. Consumers: the MenuTagParameters DTO
| (V1 plate bounds pre-check, V11 layer-height ranges), the Python engine
| (binding bounding-box verification) and the UI messages. The structure is
| ready to receive profiles beyond the A1 mini (declared roadmap).
*/

return [
    'default' => 'a1mini',

    'profiles' => [
        'a1mini' => [
            'name' => 'Bambu Lab A1 mini',
            'bed_mm' => ['x' => 180, 'y' => 180, 'z' => 180],
            'bed_warn_mm' => 175, // margin for brim and skirt
            'plate_spacing_mm' => 5.0,
            'plate_surface' => 'PEI testurizzato',
            'nozzles' => [
                '0.2' => [
                    'layer_min' => 0.05,
                    'layer_max' => 0.15,
                    'layer_default' => 0.10,
                    'first_layer' => 0.15,
                ],
                '0.4' => [
                    'layer_min' => 0.08,
                    'layer_max' => 0.30,
                    'layer_default' => 0.10,
                    'first_layer' => 0.20,
                ],
            ],
        ],
    ],
];
