<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureGuestToken;
use App\Livewire\Configurator;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Guest gating of the parametric mode (restyle §5.4, flussi.md §1/§4): the
 * gate lives in the SERVER, not in the CSS. A guest gets the 3-step wizard
 * — preset, essential input, download — and every route into the parametric
 * mode (unlock, property mutation, duplication) is refused with a 403 or
 * redirected to the registration CTA. Registered users keep the full studio.
 */

/**
 * Livewire::test() sends kernel requests WITHOUT middleware, so no session
 * gets attached (StartSession is skipped). Attach the array session store to
 * every request the harness binds, with a stable guest token — in the
 * browser the web group middleware does this before EnsureGuestToken issues
 * the token on the first visit.
 */
function bindGuestSession(string $token): void
{
    app()->rebinding('request', function ($app, $request): void {
        $request->setLaravelSession($app['session.store']);
    });
    app('request')->setLaravelSession(app('session.store'));
    session()->put(EnsureGuestToken::SESSION_KEY, $token);
}

// --- The parametric mode is unreachable server-side ---------------------------

it('refuses the parametric unlock for guests with a 403', function (): void {
    Livewire::test(Configurator::class)
        ->call('unlockCustomization')
        ->assertForbidden();
});

it('refuses parametric property mutations for guests with a 403', function (string $property, mixed $value): void {
    Livewire::test(Configurator::class)
        ->set($property, $value)
        ->assertForbidden();
})->with([
    'size' => ['size', 80.0],
    'shape' => ['shape', 'circle'],
    'mode' => ['mode', 'inlay'],
    'nfc' => ['nfc', true],
    'thickness' => ['thickness', 5.0],
    'back face' => ['back', 'qr'],
    'customized flag itself' => ['customized', true],
    'plate' => ['plate', 10],
    'error correction' => ['qrEc', 'L'],
]);

it('lets a guest update the essential input: the menu URL', function (): void {
    Livewire::test(Configurator::class)
        ->set('qrDataFront', 'https://menu.example.it/mio')
        ->assertSet('qrDataFront', 'https://menu.example.it/mio');
});

it('refuses the logo upload for guests on the MenuTag preset (customization, not essential input)', function (): void {
    Storage::fake('assets');

    Livewire::test(Configurator::class)
        ->set('logoUpload', UploadedFile::fake()->createWithContent('logo.png', tinyPngBytes()))
        ->assertForbidden();
});

it('accepts the logo upload for guests on the Coaster preset (essential input)', function (): void {
    Storage::fake('assets');
    bindGuestSession((string) Str::uuid());

    Livewire::test(Configurator::class)
        ->call('onPresetSelected', 'coaster')
        ->assertSet('preset', 'coaster')
        ->set('logoUpload', UploadedFile::fake()->createWithContent('logo.png', tinyPngBytes()))
        ->assertHasNoErrors()
        // storeLogoUpload() ran: the stored asset id travels on the event
        // (in the browser the same component consumes it via #[On]).
        ->assertDispatched('logo-uploaded');
});

it('keeps the full parametric mode for registered users', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(Configurator::class)
        ->call('unlockCustomization')
        ->assertSet('customized', true)
        ->set('size', 80.0)
        ->assertSet('size', 80.0);
});

// --- Existing guest records: duplication → registration CTA, never an error ---

it('shows the registration CTA instead of prefilling when a guest follows a duplicate link', function (): void {
    $token = (string) Str::uuid();
    $record = MenuTag::factory()->guest()->create(['guest_token' => $token, 'label' => 'La mia targhetta']);

    bindGuestSession($token);

    Livewire::withQueryParams(['duplica' => $record->id])
        ->test(Configurator::class)
        ->assertSet('duplicateRequiresAccount', true)
        ->assertSet('customized', false)
        ->assertSet('label', null)
        ->assertSee('Registrati')
        ->assertSee('Per riaprire e modificare un modello serve');
});

it('keeps duplication working for the registered owner', function (): void {
    $user = User::factory()->create();
    $record = MenuTag::factory()->for($user)->create(['label' => 'Originale']);

    Livewire::actingAs($user)
        ->withQueryParams(['duplica' => $record->id])
        ->test(Configurator::class)
        ->assertSet('duplicateRequiresAccount', false)
        ->assertSet('customized', true)
        ->assertSet('label', 'Originale (copia)');
});

it('shows the registration CTA on a guest record page instead of the duplicate action', function (): void {
    Storage::fake('stl');

    $token = (string) Str::uuid();
    $record = completeWithRealisticReport(MenuTag::factory()->guest()->create(['guest_token' => $token]));

    $this->withSession([EnsureGuestToken::SESSION_KEY => $token])
        ->get(route('menu-tags.show', $record))
        ->assertOk()
        ->assertDontSee('Duplica e modifica')
        ->assertSee('Registrati')
        ->assertSee('Verifica di stampa');
});

it('keeps the duplicate action on the record page for the registered owner', function (): void {
    Storage::fake('stl');

    $user = User::factory()->create();
    $record = completeWithRealisticReport(MenuTag::factory()->for($user)->create());

    $this->actingAs($user)
        ->get(route('menu-tags.show', $record))
        ->assertOk()
        ->assertSee('Duplica e modifica');
});

// --- The guest wizard walks 1 → 2 → 3 and stores ONLY preset + essential input

it('walks the guest wizard and pins the record to the preset values', function (): void {
    Queue::fake();
    bindGuestSession((string) Str::uuid());

    Livewire::test(Configurator::class)
        ->assertSet('step', 1)
        ->assertSee('Sblocca lo Studio completo')
        ->call('continueToInput')
        ->assertSet('step', 2)
        ->assertSee('Collega il tuo menù')
        ->set('qrDataFront', 'https://menu.example.it/demo')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    $record = MenuTag::query()->latest('id')->first();
    $defaults = (array) config('product.presets.menutag.defaults');

    expect($record)->not->toBeNull()
        ->customized->toBeFalse()
        ->and($record->parameters->size)->toBe((float) $defaults['size'])
        ->and($record->parameters->mode->value)->toBe($defaults['mode'])
        ->and($record->parameters->nfc)->toBe($defaults['nfc'])
        ->and($record->parameters->qrDataFront)->toBe('https://menu.example.it/demo');
});

it('steps back from the essential input to the format cards', function (): void {
    Livewire::test(Configurator::class)
        ->call('continueToInput')
        ->assertSet('step', 2)
        ->call('backToFormat')
        ->assertSet('step', 1);
});

// --- Registration promo route (placeholder now, filled by R-4) ----------------

it('serves the studio promo placeholder page', function (): void {
    $this->get(route('studio-promo'))
        ->assertOk()
        ->assertSee('Lo Studio completo');
});
