<?php

declare(strict_types=1);

namespace App\Enums;

enum RenderMode: string
{
    case Engrave = 'engrave';
    case Relief = 'relief';
    case Inlay = 'inlay';

    /**
     * Whether the graphic depth consumes the thickness budget (V6/V7):
     * engrave and inlay carve into the body, relief only adds on top.
     */
    public function consumesThickness(): bool
    {
        return $this !== self::Relief;
    }
}
