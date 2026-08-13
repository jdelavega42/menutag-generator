<?php

declare(strict_types=1);

namespace App\Policies;

use App\Http\Middleware\EnsureGuestToken;
use App\Models\MenuTag;
use App\Models\User;

/**
 * Ownership rules for menu tags (spec §5.1/§7.2, WS-5 acceptance):
 * a user never sees another user's tags, and a guest with a different
 * `guest_token` never sees another guest's tags.
 *
 * The `?User` parameter makes every ability guest-callable: Laravel passes
 * null for unauthenticated visitors instead of denying outright, and the
 * guest branch matches the session `guest_token` (see EnsureGuestToken).
 * Guest-owned records (`user_id` null) are only reachable through their
 * token; records migrated to an account stop matching the old token because
 * migration nulls `guest_token` and the guest branch requires a null
 * `user_id`.
 */
final class MenuTagPolicy
{
    /**
     * View the record: status, metadata, printability report, print guide.
     */
    public function view(?User $user, MenuTag $menuTag): bool
    {
        return $this->owns($user, $menuTag);
    }

    /**
     * Download the generated STL (base or accent part).
     *
     * For AUTHENTICATED users this Policy is the download gate (spec §7.2:
     * "per gli utenti autenticati usa la Policy, non il signed URL").
     * For GUESTS the web download route (`guest.menu-tags.download`, WS-4)
     * is additionally reachable through a capability-based signed URL
     * (`URL::temporarySignedRoute()` + `signed` middleware) whose expiry is
     * aligned to `config('product.guests.retention_hours')`; the signature
     * proves URL integrity, not the identity of whoever opens it — see the
     * class docblock of {@see EnsureGuestToken}. "Download only when
     * completed" is a controller/route concern (409), not an ownership rule.
     */
    public function download(?User $user, MenuTag $menuTag): bool
    {
        return $this->owns($user, $menuTag);
    }

    /**
     * Delete the record (dashboard history hygiene). Same ownership rule.
     */
    public function delete(?User $user, MenuTag $menuTag): bool
    {
        return $this->owns($user, $menuTag);
    }

    /**
     * Owner check: `user_id` for authenticated users OR the session
     * `guest_token` for guests — never both (contract 01 invariant).
     */
    private function owns(?User $user, MenuTag $menuTag): bool
    {
        if ($user instanceof User) {
            return $menuTag->user_id === $user->id;
        }

        $guestToken = EnsureGuestToken::token();

        return $guestToken !== null
            && $menuTag->user_id === null
            && $menuTag->guest_token === $guestToken;
    }
}
