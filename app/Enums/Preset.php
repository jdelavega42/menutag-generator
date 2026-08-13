<?php

declare(strict_types=1);

namespace App\Enums;

enum Preset: string
{
    case MenuTag = 'menutag';
    case Coaster = 'coaster';
    case CoinCart = 'coin_cart';
}
