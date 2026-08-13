<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Middleware\EnsureGuestToken;
use App\Models\LogoAsset;
use App\Models\User;

/**
 * Ownership rules for uploaded logos: same dual mechanism as MenuTagPolicy —
 * `user_id` for authenticated users, session `guest_token` for guests
 * (spec §7.2). Guests own their uploads only for the retention window; the
 * retention command removes them afterwards.
 */
final class LogoAssetPolicy
{
    /**
     * View/preview the logo (the file is on the private 'assets' disk and is
     * only ever streamed through an authorized route, never from public/).
     */
    public function view(?User $user, LogoAsset $logoAsset): bool
    {
        return $this->owns($user, $logoAsset);
    }

    /**
     * Attach the logo to a menu tag being configured (used by the
     * Configurator and the Form Request when validating `logo_asset_id`).
     */
    public function use(?User $user, LogoAsset $logoAsset): bool
    {
        return $this->owns($user, $logoAsset);
    }

    /**
     * Delete the logo from the library.
     */
    public function delete(?User $user, LogoAsset $logoAsset): bool
    {
        return $this->owns($user, $logoAsset);
    }

    /**
     * Owner check: `user_id` OR session `guest_token`, never both
     * (contract 01 invariant).
     */
    private function owns(?User $user, LogoAsset $logoAsset): bool
    {
        if ($user instanceof User) {
            return $logoAsset->user_id === $user->id;
        }

        $guestToken = EnsureGuestToken::token();

        return $guestToken !== null
            && $logoAsset->user_id === null
            && $logoAsset->guest_token === $guestToken;
    }
}
