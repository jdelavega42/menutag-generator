<?php

declare(strict_types=1);

use App\Enums\MenuTagStatus;
use App\Http\Middleware\EnsureGuestToken;
use App\Jobs\GenerateMenuTagJob;
use App\Livewire\Configurator;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Web submit flow (mandatory list, spec §6 WS-6): the Configurator submit
 * creates the record with status `queued` and dispatches GenerateMenuTagJob
 * — for guests (session guest_token as owner) AND authenticated users.
 * Queue::fake() everywhere: no job actually runs here.
 */
it('creates a queued record and dispatches the job for a guest', function (): void {
    Queue::fake();

    // Livewire::test() sends kernel requests WITHOUT middleware, so no
    // session ever gets attached to them (StartSession is skipped). Attach
    // the array session store to every request the harness binds — in the
    // browser the web group middleware does this before EnsureGuestToken
    // issues the token on the first visit.
    $token = (string) Str::uuid();
    $this->app->rebinding('request', function ($app, $request): void {
        $request->setLaravelSession($app['session.store']);
    });
    $this->app['request']->setLaravelSession($this->app['session.store']);
    session()->put(EnsureGuestToken::SESSION_KEY, $token);

    Livewire::test(Configurator::class)
        ->set('qrDataFront', 'https://menu.example.it/demo')
        ->call('submit')
        ->assertHasNoErrors();

    $record = MenuTag::query()->latest('id')->first();

    expect($record)->not->toBeNull()
        ->status->toBe(MenuTagStatus::Queued)
        ->user_id->toBeNull()
        ->guest_token->toBe($token);

    Queue::assertPushed(
        GenerateMenuTagJob::class,
        fn (GenerateMenuTagJob $job): bool => $job->menuTagId === $record->id,
    );
});

it('creates a queued record owned by the authenticated user', function (): void {
    Queue::fake();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Configurator::class)
        ->set('qrDataFront', 'https://menu.example.it/demo')
        ->set('label', 'La mia targhetta')
        ->call('submit')
        ->assertHasNoErrors();

    $record = MenuTag::query()->latest('id')->first();

    expect($record)->not->toBeNull()
        ->status->toBe(MenuTagStatus::Queued)
        ->user_id->toBe($user->id)
        ->guest_token->toBeNull()
        ->label->toBe('La mia targhetta');

    Queue::assertPushed(GenerateMenuTagJob::class);
});

it('blocks the submit with an explicit error when the QR payload is removed', function (): void {
    Queue::fake();

    // The Configurator opens with a REAL scannable demo QR (DoD §12), so the
    // payload must be cleared explicitly to exercise the V4 invariant.
    Livewire::test(Configurator::class)
        ->set('qrDataFront', null)
        ->call('submit')
        ->assertHasErrors(['parameters.qr_data_front']);

    expect(MenuTag::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('enforces the guest quota of 5 generations per hour per IP', function (): void {
    Queue::fake();

    $this->app['request']->setLaravelSession($this->app['session.store']);
    session()->put(EnsureGuestToken::SESSION_KEY, (string) Str::uuid());

    $max = (int) config('product.guests.generations_per_hour');
    $key = 'generate|ip|127.0.0.1';

    // Same key convention as the 'menutag-generate' limiter (WS-5).
    for ($i = 0; $i < $max; $i++) {
        RateLimiter::hit($key, 3600);
    }

    Livewire::test(Configurator::class)
        ->set('qrDataFront', 'https://menu.example.it/demo')
        ->call('submit')
        ->assertHasErrors(['submit']);

    expect(MenuTag::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
