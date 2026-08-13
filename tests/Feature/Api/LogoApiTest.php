<?php

declare(strict_types=1);

use App\Models\LogoAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * POST /api/v1/logos (docs/openapi.yaml): 201 with the LogoAsset schema,
 * content-based MIME verification (WS-5 CleanImageUpload), server-side file
 * names on the private 'assets' disk, 2 MB cap from config.
 */
it('uploads a real PNG and answers 201 with the LogoAsset schema', function (): void {
    Storage::fake('assets');
    Sanctum::actingAs($user = User::factory()->create());

    $response = $this->postJson('/api/v1/logos', [
        'file' => UploadedFile::fake()->createWithContent('logo cliente.png', tinyPngBytes()),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.mime', 'image/png')
        ->assertJsonPath('data.original_name', 'logo cliente.png')
        ->assertJsonStructure(['data' => ['id', 'original_name', 'mime', 'size_bytes', 'created_at']]);

    $asset = LogoAsset::query()->findOrFail($response->json('data.id'));

    expect($asset->user_id)->toBe($user->id)
        ->and($asset->guest_token)->toBeNull()
        // Server-generated file name: never the client name on disk.
        ->and($asset->disk_path)->not->toContain('logo cliente')
        ->and($asset->disk_path)->toEndWith('.png');

    Storage::disk('assets')->assertExists($asset->disk_path);
});

it('accepts a real SVG detected from its content', function (): void {
    Storage::fake('assets');
    Sanctum::actingAs(User::factory()->create());

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

    $this->postJson('/api/v1/logos', [
        'file' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
    ])->assertCreated()->assertJsonPath('data.mime', 'image/svg+xml');
});

it('rejects a PHP script renamed .png (content-based check)', function (): void {
    Storage::fake('assets');
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/logos', [
        'file' => UploadedFile::fake()->createWithContent('evil.png', '<?php echo "pwn";'),
    ])->assertStatus(422)->assertJsonValidationErrors(['file']);
});

it('rejects uploads above the 2 MB product cap', function (): void {
    Storage::fake('assets');
    Sanctum::actingAs(User::factory()->create());

    $maxKb = (int) config('product.guests.upload_max_kb');

    $this->postJson('/api/v1/logos', [
        'file' => UploadedFile::fake()->create('big.png', $maxKb + 1),
    ])->assertStatus(422)->assertJsonValidationErrors(['file']);
});

it('requires authentication', function (): void {
    $this->postJson('/api/v1/logos', [])->assertUnauthorized();
});
