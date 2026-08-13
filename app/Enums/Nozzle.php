<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * String-backed on purpose — NEVER float: the values go to the CLI as exact
 * text ('0.2' / '0.4') and floats would reintroduce formatting drift.
 */
enum Nozzle: string
{
    case N02 = '0.2';
    case N04 = '0.4';

    public function mm(): float
    {
        return (float) $this->value;
    }
}
