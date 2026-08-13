<?php

declare(strict_types=1);

use App\DTOs\MenuTagParameters;
use App\Enums\QrEcLevel;

/**
 * ISO/IEC 18004 byte-mode capacity table (config/product.php, single source
 * for PHP, JS preview and the Python engine — decisions §5). The boundary
 * pairs verify the table is read as CHARACTER capacity, not data codewords.
 * The engine-side parity (encoding the payload with the real QR library) is
 * covered by the integration test marked ->group('integration').
 */
it('holds the binding capacity values at EC H for versions 6..8', function (): void {
    /** @var array<int, int> $table */
    $table = config('product.qr.byte_capacity.H');

    expect($table[6])->toBe(58)
        ->and($table[7])->toBe(64)
        ->and($table[8])->toBe(84);
});

it('selects the minimal byte-mode version at every EC H boundary', function (int $bytes, int $expectedVersion): void {
    expect(MenuTagParameters::minQrVersion(str_repeat('x', $bytes), QrEcLevel::H))
        ->toBe($expectedVersion);
})->with([
    '58 bytes fit v6' => [58, 6],
    '59 bytes need v7' => [59, 7],
    '64 bytes fit v7' => [64, 7],
    '65 bytes need v8' => [65, 8],
    '84 bytes fit v8' => [84, 8],
    '85 bytes need v9' => [85, 9],
]);

it('returns null beyond the version 20 capacity', function (): void {
    /** @var array<int, int> $table */
    $table = config('product.qr.byte_capacity.H');

    expect(MenuTagParameters::minQrVersion(str_repeat('x', $table[20] + 1), QrEcLevel::H))
        ->toBeNull();
});

it('uses the requested EC level column', function (): void {
    // 64 bytes: H needs v7, L fits v4 (capacity 78).
    expect(MenuTagParameters::minQrVersion(str_repeat('x', 64), QrEcLevel::L))->toBe(4);
});
