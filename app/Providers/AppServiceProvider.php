<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\MigrateGuestOwnershipToUser;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureGuestMigration();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Generation rate limiters (spec §5.1, WS-5). Both limits are product
     * values from config/product.php — never inline constants:
     *
     * - 'menutag-generate' (web configurator): guests are capped at
     *   `product.guests.generations_per_hour` (5/h) PER IP; an authenticated
     *   user on the same route falls back to the per-user cap so a shared
     *   office IP never throttles a logged-in customer.
     * - 'api-generate' (POST /api/v1/menu-tags, Sanctum): capped at
     *   `product.api.generations_per_hour` (30/h) PER USER.
     *
     * Usage (WS-4/WS-6): `->middleware('throttle:menutag-generate')` on the
     * web generation endpoint, `->middleware('throttle:api-generate')` on
     * the API one. Exceeding the limit returns 429 with an Italian message.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('menutag-generate', function (Request $request): Limit {
            $user = $request->user();

            if ($user !== null) {
                return Limit::perHour((int) config('product.api.generations_per_hour'))
                    ->by('generate|user|'.$user->getAuthIdentifier())
                    ->response(fn (Request $request, array $headers): Response => $this->rateLimitedResponse(
                        $request,
                        $headers,
                        sprintf(
                            'Hai raggiunto il limite di %d generazioni all\'ora. Riprova più tardi.',
                            (int) config('product.api.generations_per_hour'),
                        ),
                    ));
            }

            return Limit::perHour((int) config('product.guests.generations_per_hour'))
                ->by('generate|ip|'.$request->ip())
                ->response(fn (Request $request, array $headers): Response => $this->rateLimitedResponse(
                    $request,
                    $headers,
                    sprintf(
                        'Come ospite puoi avviare al massimo %d generazioni all\'ora. Riprova più tardi oppure registrati per continuare a lavorare.',
                        (int) config('product.guests.generations_per_hour'),
                    ),
                ));
        });

        RateLimiter::for('api-generate', function (Request $request): Limit {
            $user = $request->user();

            if ($user === null) {
                // The API is Sanctum-only; an unauthenticated request should
                // be stopped by auth:sanctum before ever reaching this
                // limiter. Guard by IP with the guest cap anyway.
                return Limit::perHour((int) config('product.guests.generations_per_hour'))
                    ->by('generate|ip|'.$request->ip());
            }

            return Limit::perHour((int) config('product.api.generations_per_hour'))
                ->by('generate|user|'.$user->getAuthIdentifier())
                ->response(fn (Request $request, array $headers): Response => $this->rateLimitedResponse(
                    $request,
                    $headers,
                    sprintf(
                        'Hai raggiunto il limite di %d generazioni all\'ora per questo account. Riprova più tardi.',
                        (int) config('product.api.generations_per_hour'),
                    ),
                ));
        });
    }

    /**
     * Guest → user ownership migration (spec §7.2): at login and at
     * registration the session guest_token's menu_tags and logo_assets move
     * to the authenticated user. Explicit registration on a method NOT named
     * handle/__invoke, so event auto-discovery cannot register it twice.
     */
    protected function configureGuestMigration(): void
    {
        Event::listen(
            [Login::class, Registered::class],
            [MigrateGuestOwnershipToUser::class, 'migrate'],
        );
    }

    /**
     * 429 response with a user-visible Italian message, JSON for API/AJAX
     * callers and plain text otherwise (rate-limit headers preserved).
     *
     * @param  array<string, mixed>  $headers
     */
    protected function rateLimitedResponse(Request $request, array $headers, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_TOO_MANY_REQUESTS, $headers);
        }

        return response($message, Response::HTTP_TOO_MANY_REQUESTS, $headers);
    }
}
