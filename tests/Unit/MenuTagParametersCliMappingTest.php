<?php

declare(strict_types=1);

use App\DTOs\MenuTagParameters;
use App\Enums\BaseProfile;
use App\Enums\FaceContent;
use App\Enums\Material;
use App\Enums\Nozzle;
use App\Enums\QrEcLevel;
use App\Enums\RenderMode;
use App\Enums\Shape;
use App\Enums\TagDiameter;

/**
 * DTO → CLI argv mapping (contract 02): deterministic emission with a single
 * expected value, compared against the contract's own example (MenuTag
 * preset, NFC active).
 *
 * Declared divergence (WS-1/WS-2b reports, followed here): the contract 02
 * example prints `--tag-thickness 0.80`, but the DOCUMENTED emission rule —
 * trailing zeros trimmed down to at least one decimal — produces "0.8"
 * (a PHP float cannot distinguish 0.80 from 0.8). Semantically identical
 * for argparse; the rule in the toCliArguments() docblock is authoritative.
 */
it('maps the contract 02 example (MenuTag preset, NFC active) to the exact argv', function (): void {
    $parameters = new MenuTagParameters(
        shape: Shape::Square,
        size: 58.8,
        fillet: 4.0,
        thickness: 3.0,
        baseProfile: BaseProfile::Flat,
        front: FaceContent::Qr,
        back: FaceContent::None,
        mode: RenderMode::Engrave,
        depth: 0.6,
        qrDataFront: 'https://menu.example.it/demo',
        qrEc: QrEcLevel::H,
        nfc: true,
        tagDiameter: TagDiameter::D25,
        tagThickness: 0.80,
        nozzle: Nozzle::N04,
        layerHeight: 0.1,
    );

    expect($parameters->toCliArguments('/abs/path/out.stl'))->toBe([
        '--shape', 'square',
        '--size', '58.8',
        '--fillet', '4.0',
        '--thickness', '3.0',
        '--base-profile', 'flat',
        '--front', 'qr',
        '--back', 'none',
        '--mode', 'engrave',
        '--depth', '0.6',
        '--margin', 'auto',
        '--qr-data-front', 'https://menu.example.it/demo',
        '--qr-ec', 'H',
        '--nfc',
        '--tag', '25',
        '--tag-thickness', '0.8', // declared divergence from the example's cosmetic "0.80"
        '--nozzle', '0.4',
        '--layer-height', '0.1',
        '--printer', 'a1mini',
        '--material', 'pla-matte',
        '--plate', '1',
        '--xy-comp', '0.0',
        '--out', '/abs/path/out.stl',
    ]);
});

it('omits conditional flags when their condition is inactive', function (): void {
    $args = new MenuTagParameters(
        shape: Shape::Circle,
        size: 85.0,
    )->toCliArguments('/abs/out.stl');

    expect($args)
        ->not->toContain('--fillet')       // circle
        ->not->toContain('--rim-width')    // flat
        ->not->toContain('--recess-depth')
        ->not->toContain('--logo')         // no logo path
        ->not->toContain('--logo-rotate')
        ->not->toContain('--qr-data-front') // no QR faces
        ->not->toContain('--qr-data-back')
        ->not->toContain('--qr-data')      // NEVER emitted, per-face only
        ->not->toContain('--nfc')
        ->not->toContain('--tag')
        ->not->toContain('--tag-thickness')
        ->not->toContain('--layer-height') // null → engine default
        ->not->toContain('--out-accent');  // not inlay
});

it('emits rimmed, logo and accent flags when active', function (): void {
    $args = new MenuTagParameters(
        shape: Shape::Circle,
        size: 85.0,
        baseProfile: BaseProfile::Rimmed,
        rimWidth: 5.0,
        recessDepth: 1.2,
        front: FaceContent::Logo,
        mode: RenderMode::Inlay,
        depth: 0.5,
        logoAssetId: 7,
        logoRotate: 15.0,
        material: Material::Petg,
    )->toCliArguments('/abs/out.stl', '/abs/accent.stl', '/abs/logo.svg');

    expect(implode(' ', $args))
        ->toContain('--rim-width 5.0')
        ->toContain('--recess-depth 1.2')
        ->toContain('--logo /abs/logo.svg')
        ->toContain('--logo-rotate 15.0')
        ->toContain('--material petg')
        ->toContain('--out /abs/out.stl')
        ->toContain('--out-accent /abs/accent.stl');
});

it('requires an accent path in inlay mode and a logo path for logo faces', function (): void {
    $inlay = new MenuTagParameters(
        shape: Shape::Square,
        size: 60.0,
        front: FaceContent::Logo,
        mode: RenderMode::Inlay,
        depth: 0.5,
        logoAssetId: 1,
    );

    expect(fn (): array => $inlay->toCliArguments('/abs/out.stl', null, '/abs/logo.svg'))
        ->toThrow(LogicException::class)
        ->and(fn (): array => $inlay->toCliArguments('/abs/out.stl', '/abs/accent.stl', null))
        ->toThrow(LogicException::class);
});

it('emits both per-face QR payloads for the bilingual two-QR case', function (): void {
    $args = new MenuTagParameters(
        shape: Shape::Square,
        size: 58.8,
        thickness: 3.0,
        front: FaceContent::Qr,
        back: FaceContent::Qr,
        mode: RenderMode::Engrave,
        depth: 0.4,
        qrDataFront: 'https://menu.example.it/it',
        qrDataBack: 'https://menu.example.it/en',
    )->toCliArguments('/abs/out.stl');

    expect(implode(' ', $args))
        ->toContain('--qr-data-front https://menu.example.it/it')
        ->toContain('--qr-data-back https://menu.example.it/en');
});
