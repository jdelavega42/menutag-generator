<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Http\Middleware\EnsureGuestToken;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Migrates guest-owned records to the account at login/registration
 * (spec §7.2: "alla registrazione di un ospite, migra le sue menu_tags dal
 * guest_token al nuovo user_id"; logo assets follow the same rule so the
 * uploaded logo survives the account creation too).
 *
 * The guest token lives in the session (see EnsureGuestToken) and survives
 * the session ID regeneration Fortify performs at login — that is exactly
 * why the token, and never the session ID, keys guest records. The listener
 * is synchronous (it needs the session of the current request) and
 * idempotent: migrated rows get `user_id` set and `guest_token` nulled, so
 * the follow-up Login event after a registration matches nothing.
 *
 * Registered explicitly in AppServiceProvider::boot() — the method name
 * `migrate` is deliberately NOT `handle`/`__invoke` so Laravel's event
 * auto-discovery does not register it a second time.
 */
final class MigrateGuestOwnershipToUser
{
    public function migrate(Login|Registered $event): void
    {
        $guestToken = EnsureGuestToken::token();

        if ($guestToken === null) {
            return;
        }

        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        DB::transaction(function () use ($guestToken, $user): void {
            MenuTag::query()
                ->whereNull('user_id')
                ->where('guest_token', $guestToken)
                ->update(['user_id' => $user->id, 'guest_token' => null]);

            LogoAsset::query()
                ->whereNull('user_id')
                ->where('guest_token', $guestToken)
                ->update(['user_id' => $user->id, 'guest_token' => null]);
        });
    }
}
