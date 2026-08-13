<?php

declare(strict_types=1);

namespace App\Enums;

enum QrEcLevel: string
{
    case L = 'L';
    case M = 'M';
    case Q = 'Q';
    case H = 'H';
}
