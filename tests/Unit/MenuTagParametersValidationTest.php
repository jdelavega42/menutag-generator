<?php

declare(strict_types=1);

use App\DTOs\InvalidMenuTagParameters;
use App\DTOs\MenuTagParameters;
use App\Enums\QrEcLevel;
use App\Enums\Shape;

/**
 * Spec §3 domain limits (mandatory list, spec §6 WS-6): 2 € coin minimums on
 * both shapes and on thickness, QR blocked below the shape-dependent floor,
 * functional minimum growing with the URL (ISO byte-mode table: 64 bytes →
 * v7 → 63.6 mm, 65 bytes → v8 → 68.4 mm), Ø25 tag rejected below 28.4 mm,
 * NFC minimum thickness COMPUTED — all enforced by the DTO invariants
 * V1..V12 with the constants from config/product.php.
 */

/**
 * @param  array<string, mixed>  $overrides
 */
function makeParameters(array $overrides = []): MenuTagParameters
{
    return MenuTagParameters::fromArray([
        'shape' => 'square',
        'size' => 60.0,
        'thickness' => 4.0,
        ...$overrides,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, list<string>>
 */
function parameterErrors(array $overrides): array
{
    try {
        makeParameters($overrides);
    } catch (InvalidMenuTagParameters $exception) {
        return $exception->errors;
    }

    return [];
}

// --- V1: 2 € coin minimums -------------------------------------------------

it('rejects a square below the 2 € coin side of 25.75 mm', function (): void {
    expect(parameterErrors(['shape' => 'square', 'size' => 25.74]))->toHaveKey('size');
});

it('rejects a circle below the 2 € coin diameter of 25.75 mm', function (): void {
    expect(parameterErrors(['shape' => 'circle', 'size' => 25.74]))->toHaveKey('size');
});

it('accepts exactly 25.75 mm on both shapes', function (string $shape): void {
    expect(makeParameters(['shape' => $shape, 'size' => 25.75]))
        ->toBeInstanceOf(MenuTagParameters::class);
})->with(['square', 'circle']);

it('rejects a thickness below the 2 € coin thickness of 2.20 mm', function (): void {
    expect(parameterErrors(['thickness' => 2.19]))->toHaveKey('thickness');
});

it('accepts exactly 2.20 mm of thickness', function (): void {
    expect(makeParameters(['thickness' => 2.20]))->toBeInstanceOf(MenuTagParameters::class);
});

it('rejects sizes and thicknesses above the product maximums', function (): void {
    expect(parameterErrors(['size' => 200.01]))->toHaveKey('size')
        ->and(parameterErrors(['thickness' => 20.01]))->toHaveKey('thickness');
});

// --- V5: QR floor depending on shape ----------------------------------------

it('blocks a QR square below the 58.8 mm product floor', function (): void {
    $errors = parameterErrors([
        'size' => 58.7,
        'front' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
    ]);

    expect($errors)->toHaveKey('size')
        ->and($errors['size'][0])->toContain('58.8')->toContain('79.2');
});

it('blocks a QR circle below the 79.2 mm product floor', function (): void {
    $errors = parameterErrors([
        'shape' => 'circle',
        'size' => 79.1,
        'front' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
    ]);

    expect($errors)->toHaveKey('size');
});

it('accepts a QR exactly at the shape floor', function (string $shape, float $size): void {
    expect(makeParameters([
        'shape' => $shape,
        'size' => $size,
        'front' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
    ]))->toBeInstanceOf(MenuTagParameters::class);
})->with([
    'square at 58.8' => ['square', 58.8],
    'circle at 79.2' => ['circle', 79.2],
]);

// --- V5: functional minimum grows with the URL ------------------------------
// ISO byte-mode capacity at EC H: v7 holds 64 bytes, 65 bytes need v8
// (boundary pair required by decisions §5).

it('maps the 64/65 byte boundary to versions 7 and 8', function (): void {
    $url64 = 'https://menu.example.it/'.str_repeat('a', 40);
    $url65 = 'https://menu.example.it/'.str_repeat('a', 41);

    expect(strlen($url64))->toBe(64)
        ->and(strlen($url65))->toBe(65)
        ->and(MenuTagParameters::minQrVersion($url64, QrEcLevel::H))->toBe(7)
        ->and(MenuTagParameters::minQrVersion($url65, QrEcLevel::H))->toBe(8);
});

it('raises the square functional minimum to 63.6 mm for a 64 byte URL and 68.4 mm for 65 bytes', function (): void {
    $url64 = 'https://menu.example.it/'.str_repeat('a', 40);
    $url65 = 'https://menu.example.it/'.str_repeat('a', 41);

    // v7 → 45 modules → 1.2 × (45+8) = 63.6; v8 → 49 → 1.2 × (49+8) = 68.4.
    expect(MenuTagParameters::minSizeForQr($url64, QrEcLevel::H, Shape::Square))->toBe(63.6)
        ->and(MenuTagParameters::minSizeForQr($url65, QrEcLevel::H, Shape::Square))->toBe(68.4)
        // Circle for v7: 1.2 × (45·√2 + 8) rounded up to 0.1 → 86.0 (spec §3.2 table).
        ->and(MenuTagParameters::minSizeForQr($url64, QrEcLevel::H, Shape::Circle))->toBe(86.0);
});

it('rejects a 64 byte URL below 63.6 mm and accepts it at 63.6 mm', function (): void {
    $url64 = 'https://menu.example.it/'.str_repeat('a', 40);

    $errors = parameterErrors([
        'size' => 63.5,
        'front' => 'qr',
        'qr_data_front' => $url64,
    ]);

    expect($errors)->toHaveKey('size')
        ->and($errors['size'][0])->toContain('63.6')
        ->and(makeParameters(['size' => 63.6, 'front' => 'qr', 'qr_data_front' => $url64]))
        ->toBeInstanceOf(MenuTagParameters::class);
});

it('rejects a 65 byte URL at 63.6 mm because the floor moved to 68.4 mm', function (): void {
    $url65 = 'https://menu.example.it/'.str_repeat('a', 41);

    $errors = parameterErrors([
        'size' => 63.6,
        'front' => 'qr',
        'qr_data_front' => $url65,
    ]);

    expect($errors)->toHaveKey('size')
        ->and($errors['size'][0])->toContain('68.4');
});

// --- V8: NFC pocket plan minimum --------------------------------------------

it('rejects the Ø25 tag below the computed 28.4 mm plan on both shapes', function (string $shape): void {
    $errors = parameterErrors([
        'shape' => $shape,
        'size' => 28.3,
        'nfc' => true,
        'tag_diameter' => 25,
    ]);

    expect($errors)->toHaveKey('size')
        ->and($errors['size'][0])->toContain('28.4');
})->with(['square', 'circle']);

it('accepts the Ø25 tag at exactly 28.4 mm', function (): void {
    expect(makeParameters(['size' => 28.4, 'nfc' => true, 'tag_diameter' => 25]))
        ->toBeInstanceOf(MenuTagParameters::class);
});

it('accepts the Ø22 tag at the 25.75 mm product minimum (plan floor 25.4 mm)', function (): void {
    expect(makeParameters(['size' => 25.75, 'nfc' => true, 'tag_diameter' => 22]))
        ->toBeInstanceOf(MenuTagParameters::class);
});

it('applies the NFC plan check to the EFFECTIVE size (nominal + 2 × xy_comp)', function (): void {
    // 28.4 nominal with −0.10 per side prints at 28.2: below the plan floor.
    $errors = parameterErrors([
        'size' => 28.4,
        'nfc' => true,
        'tag_diameter' => 25,
        'xy_comp' => -0.10,
    ]);

    expect($errors)->toHaveKey('size');
});

// --- V7: NFC minimum thickness is computed, never a constant -----------------

it('computes the NFC minimum thickness from tag, clearance, walls and engraved faces', function (): void {
    // Spec §3.3 worked example: 0.80 tag + 0.20 clearance + 2 × 0.40 walls
    // + two engraved faces at 0.6 = 3.00 mm.
    $base = [
        'size' => 58.8,
        'front' => 'qr',
        'back' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
        'qr_data_back' => 'https://menu.example.it/en',
        'mode' => 'engrave',
        'depth' => 0.6,
        'nfc' => true,
        'tag_thickness' => 0.80,
    ];

    expect(parameterErrors([...$base, 'thickness' => 2.99]))->toHaveKey('thickness')
        ->and(makeParameters([...$base, 'thickness' => 3.0]))->toBeInstanceOf(MenuTagParameters::class);
});

it('does not charge relief graphics against the NFC thickness budget', function (): void {
    // Relief adds on top (contract 02 V6/V7): min is 0.80+0.20+0.80 = 1.80,
    // so the 2.20 product minimum passes even with graphics on both faces.
    expect(makeParameters([
        'size' => 58.8,
        'thickness' => 2.20,
        'front' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
        'mode' => 'relief',
        'depth' => 0.6,
        'nfc' => true,
    ]))->toBeInstanceOf(MenuTagParameters::class);
});

it('reports a computed minimum that grows with the declared tag thickness', function (): void {
    // 1.60 tag + 0.20 + 0.80 + 1.2 engraved = 3.80 → 3.7 fails, 3.8 passes.
    $base = [
        'size' => 58.8,
        'front' => 'qr',
        'back' => 'qr',
        'qr_data_front' => 'https://menu.example.it/demo',
        'qr_data_back' => 'https://menu.example.it/en',
        'mode' => 'engrave',
        'depth' => 0.6,
        'nfc' => true,
        'tag_thickness' => 1.60,
    ];

    expect(parameterErrors([...$base, 'thickness' => 3.7]))->toHaveKey('thickness')
        ->and(makeParameters([...$base, 'thickness' => 3.8]))->toBeInstanceOf(MenuTagParameters::class);
});
