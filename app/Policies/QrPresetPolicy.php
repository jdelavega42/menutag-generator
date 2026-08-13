<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QrPreset;
use App\Models\User;

/**
 * QR presets are a dashboard-only resource (contract 01): guests have no
 * library, so the non-nullable User parameter makes Laravel deny every
 * ability to unauthenticated visitors automatically.
 */
final class QrPresetPolicy
{
    public function view(User $user, QrPreset $qrPreset): bool
    {
        return $this->owns($user, $qrPreset);
    }

    public function update(User $user, QrPreset $qrPreset): bool
    {
        return $this->owns($user, $qrPreset);
    }

    public function delete(User $user, QrPreset $qrPreset): bool
    {
        return $this->owns($user, $qrPreset);
    }

    private function owns(User $user, QrPreset $qrPreset): bool
    {
        return $qrPreset->user_id === $user->id;
    }
}
