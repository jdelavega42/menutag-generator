<?php

declare(strict_types=1);

namespace App\Enums;

enum FaceContent: string
{
    case None = 'none';
    case Logo = 'logo';
    case Qr = 'qr';
    case QrLogo = 'qr_logo';

    public function hasQr(): bool
    {
        return $this === self::Qr || $this === self::QrLogo;
    }

    public function hasLogo(): bool
    {
        return $this === self::Logo || $this === self::QrLogo;
    }
}
