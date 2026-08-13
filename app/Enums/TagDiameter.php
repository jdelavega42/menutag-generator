<?php

declare(strict_types=1);

namespace App\Enums;

enum TagDiameter: int
{
    case D22 = 22;
    case D25 = 25;

    public function mm(): float
    {
        return (float) $this->value;
    }
}
