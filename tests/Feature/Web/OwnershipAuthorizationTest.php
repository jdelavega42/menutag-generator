<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureGuestToken;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * WS-5 authorization rules (mandatory list, spec §6 WS-6): a user never
 * reaches another user's records, a guest never reaches another guest's,
 * and the guest signed URLs reject expiry and tampering.
 */

// --- user ↔ user -------------------------------------------------------------

it('lets a user view and download only their own records', function (): void {
    Storage::fake('stl');

    $me = User::factory()->create();
    $mine = completeWithRealisticReport(MenuTag::factory()->for($me)->create());

    $this->actingAs($me);

    $this->get(route('menu-tags.show', $mine))->assertOk();
    $this->get(route('menu-tags.download', $mine))->assertOk();
    $this->get(route('menu-tags.guide', $mine))->assertOk();
});

it('blocks a user from another user records with 403', function (): void {
    Storage::fake('stl');

    $me = User::factory()->create();
    $other = User::factory()->create();
    $theirs = completeWithRealisticReport(MenuTag::factory()->for($other)->create());

    $this->actingAs($me);

    $this->get(route('menu-tags.show', $theirs))->assertForbidden();
    $this->get(route('menu-tags.download', $theirs))->assertForbidden();
    $this->get(route('menu-tags.guide', $theirs))->assertForbidden();
});

// --- guest ↔ guest -----------------------------------------------------------

it('lets a guest view only records of their own session token', function (): void {
    $token = (string) Str::uuid();
    $record = MenuTag::factory()->guest()->create(['guest_token' => $token]);

    $this->withSession([EnsureGuestToken::SESSION_KEY => $token])
        ->get(route('menu-tags.show', $record))
        ->assertOk();
});

it('blocks a guest from another guest records with 403', function (): void {
    $record = MenuTag::factory()->guest()->create(['guest_token' => (string) Str::uuid()]);

    $this->withSession([EnsureGuestToken::SESSION_KEY => (string) Str::uuid()])
        ->get(route('menu-tags.show', $record))
        ->assertForbidden();
});

it('does not let an old guest token reach a record migrated to an account', function (): void {
    $token = (string) Str::uuid();
    $owner = User::factory()->create();

    // Migrated at registration (spec §7.2): user_id set, token cleared.
    $record = MenuTag::factory()->for($owner)->create(['guest_token' => null]);

    $this->withSession([EnsureGuestToken::SESSION_KEY => $token])
        ->get(route('menu-tags.show', $record))
        ->assertForbidden();
});

// --- guest signed URLs (capability-based, spec §7.2) --------------------------

it('serves the guest STL and guide through a valid temporary signed URL', function (): void {
    Storage::fake('stl');

    $record = completeWithRealisticReport(MenuTag::factory()->guest()->create());
    $expiry = now()->addHours((int) config('product.guests.retention_hours'));

    $downloadUrl = URL::temporarySignedRoute('guest.menu-tags.download', $expiry, ['menuTag' => $record->id]);
    $guideUrl = URL::temporarySignedRoute('guest.menu-tags.guide', $expiry, ['menuTag' => $record->id]);

    // Capability-based: no session/token needed, the signature IS the access.
    $this->get($downloadUrl)->assertOk()->assertHeader('Content-Type', 'model/stl');
    $this->get($guideUrl)->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
});

it('rejects an expired signed URL with 403', function (): void {
    Storage::fake('stl');

    $record = completeWithRealisticReport(MenuTag::factory()->guest()->create());

    $url = URL::temporarySignedRoute('guest.menu-tags.download', now()->addMinutes(5), ['menuTag' => $record->id]);

    $this->travel(6)->minutes();

    $this->get($url)->assertForbidden();
});

it('rejects a tampered signed URL with 403', function (): void {
    Storage::fake('stl');

    $record = completeWithRealisticReport(MenuTag::factory()->guest()->create());
    $decoy = completeWithRealisticReport(MenuTag::factory()->guest()->create());

    $url = URL::temporarySignedRoute(
        'guest.menu-tags.download',
        now()->addHour(),
        ['menuTag' => $record->id],
    );

    // Swap the record id inside the signed URL.
    $tampered = str_replace('/targhette/'.$record->id.'/', '/targhette/'.$decoy->id.'/', $url);

    $this->get($tampered)->assertForbidden();

    // Adding a parameter breaks the signature too.
    $this->get($url.'&part=accent')->assertForbidden();
});

it('answers 409 on a signed URL for a record that is not completed yet', function (): void {
    $record = MenuTag::factory()->guest()->processing()->create();

    $url = URL::temporarySignedRoute('guest.menu-tags.download', now()->addHour(), ['menuTag' => $record->id]);

    $this->get($url)->assertStatus(409);
});
