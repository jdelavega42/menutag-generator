<?php

declare(strict_types=1);

namespace App\Enums;

enum Printability: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Blocked = 'blocked';
}
