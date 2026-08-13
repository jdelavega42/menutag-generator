<?php

declare(strict_types=1);

use App\Enums\MenuTagStatus;
use App\Jobs\GenerateMenuTagJob;
use App\Models\LogoAsset;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * /api/v1/menu-tags conformance against docs/openapi.yaml: 202 on POST with
 * the record queued and the job dispatched, paginated index scoped to the
 * owner, 403/404 partition on show, 409 on premature download, ?part=accent,
 * guide as text/markdown, coherent JSON errors, api-generate rate limiter.
 */

/**
 * @return array<string, mixed>
 */
function validMenuTagPayload(): array
{
    return [
        'preset' => 'menutag',
        'label' => 'Targhetta di prova',
        'parameters' => [
            'shape' => 'square',
            'size' => 58.8,
            'fillet' => 4.0,
            'thickness' => 3.0,
            'base_profile' => 'flat',
            'front' => 'qr',
            'back' => 'none',
            'mode' => 'engrave',
            'depth' => 0.6,
            'qr_data_front' => 'https://menu.example.it/demo',
            'qr_ec' => 'H',
            'nfc' => false,
            'tag_diameter' => 25,
            'nozzle' => '0.4',
            'layer_height' => 0.10,
            'material' => 'pla-matte',
            'plate' => 1,
            'xy_comp' => 0.0,
        ],
    ];
}

it('rejects unauthenticated requests with 401 JSON', function (): void {
    $this->getJson('/api/v1/menu-tags')->assertUnauthorized()->assertJsonStructure(['message']);
    $this->postJson('/api/v1/menu-tags', validMenuTagPayload())->assertUnauthorized();
});

it('answers 202 on POST, creates the queued record and dispatches the job', function (): void {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/menu-tags', validMenuTagPayload());

    $response->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.preset', 'menutag')
        ->assertJsonPath('data.customized', false)
        ->assertJsonPath('data.links.download', null)
        ->assertJsonPath('data.parameters.size', 58.8);

    $id = $response->json('data.id');

    expect(MenuTag::query()->find($id))
        ->not->toBeNull()
        ->status->toBe(MenuTagStatus::Queued)
        ->guest_token->toBeNull();

    Queue::assertPushed(
        GenerateMenuTagJob::class,
        fn (GenerateMenuTagJob $job): bool => $job->menuTagId === $id,
    );
});

it('marks API records customized when a parameter deviates from the preset defaults', function (): void {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $payload = validMenuTagPayload();
    $payload['parameters']['thickness'] = 5.0;

    $this->postJson('/api/v1/menu-tags', $payload)
        ->assertStatus(202)
        ->assertJsonPath('data.customized', true);
});

it('returns 422 with per-field Italian errors on invalid parameters', function (): void {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $payload = validMenuTagPayload();
    $payload['parameters']['size'] = 25.0; // below the 2 € coin minimum

    $this->postJson('/api/v1/menu-tags', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parameters.size']);

    Queue::assertNothingPushed();
});

it('blocks a QR below the shape floor through the API too', function (): void {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());

    $payload = validMenuTagPayload();
    $payload['parameters']['size'] = 40.0; // QR floor is 58.8 for squares
    $payload['parameters']['fillet'] = 0.0;

    $response = $this->postJson('/api/v1/menu-tags', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parameters.size']);

    $errors = (array) $response->json('errors');
    expect((string) $errors['parameters.size'][0])->toContain('58.8');
    Queue::assertNothingPushed();
});

it('rejects a foreign logo_asset_id with a 422 on the same field', function (): void {
    Queue::fake();
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $logo = LogoAsset::factory()->for($stranger)->create();

    Sanctum::actingAs($owner);

    $payload = validMenuTagPayload();
    $payload['parameters']['front'] = 'qr_logo';
    $payload['parameters']['logo_asset_id'] = $logo->id;

    $this->postJson('/api/v1/menu-tags', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parameters.logo_asset_id']);

    Queue::assertNothingPushed();
});

it('protects POST /menu-tags with the api-generate rate limiter', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r): bool => $r->getName() === 'api.v1.menu-tags.store');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:api-generate');
});

it('lists only the caller records, filterable and paginated', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();

    MenuTag::factory()->for($me)->count(2)->create();
    MenuTag::factory()->for($me)->failed()->create();
    MenuTag::factory()->for($other)->count(3)->create();

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/menu-tags')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);

    $this->getJson('/api/v1/menu-tags?filter[status]=failed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'failed');

    $this->getJson('/api/v1/menu-tags?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->getJson('/api/v1/menu-tags?per_page=101')->assertStatus(422);
    $this->getJson('/api/v1/menu-tags?filter[status]=bogus')->assertStatus(422);
});

it('partitions show into 200 own / 403 foreign / 404 missing', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $mine = MenuTag::factory()->for($me)->create();
    $theirs = MenuTag::factory()->for($other)->create();

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/menu-tags/'.$mine->id)
        ->assertOk()
        ->assertJsonPath('data.id', $mine->id)
        ->assertJsonStructure(['data' => [
            'id', 'label', 'preset', 'customized', 'status', 'parameters',
            'printability', 'report', 'error_message', 'links', 'created_at', 'updated_at',
        ]]);

    $this->getJson('/api/v1/menu-tags/'.$theirs->id)->assertForbidden();
    $this->getJson('/api/v1/menu-tags/999999')->assertNotFound();
});

it('answers 409 on download until the generation is completed', function (): void {
    $me = User::factory()->create();
    $tag = MenuTag::factory()->for($me)->queued()->create();

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/menu-tags/'.$tag->id.'/download')
        ->assertStatus(409)
        ->assertJsonStructure(['message']);
});

it('streams the STL of a completed record and handles ?part=accent', function (): void {
    Storage::fake('stl');

    $me = User::factory()->create();

    $plain = completeWithRealisticReport(MenuTag::factory()->for($me)->create());

    Sanctum::actingAs($me);

    $this->get('/api/v1/menu-tags/'.$plain->id.'/download')
        ->assertOk()
        ->assertHeader('Content-Type', 'model/stl');

    // part=accent without inlay → 409 (openapi download contract).
    $this->getJson('/api/v1/menu-tags/'.$plain->id.'/download?part=accent')->assertStatus(409);

    // Invalid part value → coherent 422 validation error.
    $this->getJson('/api/v1/menu-tags/'.$plain->id.'/download?part=nope')->assertStatus(422);

    // Inlay record: both parts downloadable.
    $inlay = completeWithRealisticReport(
        MenuTag::factory()->for($me)->coasterPreset()->create([
            'parameters' => [
                ...(array) config('product.presets.coaster.defaults'),
                'front' => 'none',
                'mode' => 'inlay',
                'depth' => 0.5,
            ],
        ]),
    );

    $this->get('/api/v1/menu-tags/'.$inlay->id.'/download?part=accent')
        ->assertOk()
        ->assertHeader('Content-Type', 'model/stl');
});

it('serves the print guide as markdown only when completed', function (): void {
    Storage::fake('stl');

    $me = User::factory()->create();
    $queued = MenuTag::factory()->for($me)->queued()->create();

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/menu-tags/'.$queued->id.'/guide')->assertStatus(409);

    $done = completeWithRealisticReport(MenuTag::factory()->for($me)->create());

    $response = $this->get('/api/v1/menu-tags/'.$done->id.'/guide');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    expect($response->getContent())->toContain('# Guida di stampa');
});

it('exposes working download and guide links on a completed record', function (): void {
    Storage::fake('stl');

    $me = User::factory()->create();
    $done = completeWithRealisticReport(MenuTag::factory()->for($me)->create());

    Sanctum::actingAs($me);

    $links = $this->getJson('/api/v1/menu-tags/'.$done->id)->json('data.links');

    expect($links['download'])->toContain('/api/v1/menu-tags/'.$done->id.'/download')
        ->and($links['guide'])->toContain('/api/v1/menu-tags/'.$done->id.'/guide')
        ->and($links['download_accent'])->toBeNull();
});
