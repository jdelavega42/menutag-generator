<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed values are the exact CLI tokens (--material pla-matte|petg).
 */
enum Material: string
{
    case PlaMatte = 'pla-matte';
    case Petg = 'petg';
}
