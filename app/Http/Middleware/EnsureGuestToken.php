<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Listeners\MigrateGuestOwnershipToUser;
use App\Policies\MenuTagPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues a `guest_token` UUID in the session on the first visit (spec §7.2).
 *
 * The token — NEVER the session ID — is the guest ownership key stored on
 * `menu_tags.guest_token` / `logo_assets.guest_token`. The session ID rotates
 * on `session()->regenerate()` and on login, so keying records on it would
 * silently orphan the guest's history; a UUID stored *inside* the session
 * survives ID regeneration (regenerate keeps the payload) and is migrated to
 * `user_id` at login/registration by
 * {@see MigrateGuestOwnershipToUser}.
 *
 * Two distinct mechanisms protect guest resources (spec §7.2):
 *
 * - LISTING / STATUS: the session `guest_token` matched by the Policies
 *   ({@see MenuTagPolicy}) and by the `forOwner()` scopes.
 * - DOWNLOAD: a signed URL created with `URL::temporarySignedRoute()` toward
 *   the `guest.menu-tags.download` route (WS-4), protected by the `signed`
 *   middleware. That signed URL is CAPABILITY-BASED access: the signature
 *   only guarantees the URL was produced by this application and has not
 *   been tampered with or expired — it does NOT authenticate whoever opens
 *   it. Anyone holding the link can download the STL until it expires. This
 *   is a deliberate, documented trade-off, acceptable for a guest's STL;
 *   the expiry MUST be aligned to the guest retention window:
 *   `now()->addHours((int) config('product.guests.retention_hours'))` (24 h),
 *   after which the scheduled retention command deletes the artifact anyway.
 *   Authenticated users never rely on signed URLs: their downloads go
 *   through Policy-protected routes.
 *
 * Registered on the whole `web` group (bootstrap/app.php) so guest ownership
 * works on any page, and aliased as `guest.token` for explicit use.
 */
final class EnsureGuestToken
{
    /**
     * Session key holding the guest token. Shared contract with WS-4/WS-6:
     * read it through {@see self::token()}, never through the session ID.
     */
    public const string SESSION_KEY = 'guest_token';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            $request->session()->put(self::SESSION_KEY, (string) Str::uuid());
        }

        return $next($request);
    }

    /**
     * The guest token of the current session, or null when the request has
     * no session (API/console contexts). Single accessor for Policies,
     * listeners and Livewire components — keeps the session key in one place.
     */
    public static function token(): ?string
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $token = $request->session()->get(self::SESSION_KEY);

        return is_string($token) && $token !== '' ? $token : null;
    }
}
