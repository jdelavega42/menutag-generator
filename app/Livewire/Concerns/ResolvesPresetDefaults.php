<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\DTOs\MenuTagParameters;
use App\Enums\Preset;

/**
 * Maps the binding preset tables of config/product.php (contract 05) onto the
 * camelCase component/viewer state (PreviewParams, contract 04). Single
 * source: the Configurator, the PreviewViewer and the status page all read
 * the same defaults — never re-declared constants.
 */
trait ResolvesPresetDefaults
{
    /**
     * Component state for a preset, straight from config/product.php.
     * For QR faces the demo URL from config is pre-filled so the very first
     * preview shows a REAL, scannable QR (contract 04).
     *
     * @return array<string, mixed>
     */
    public static function presetDefaults(Preset $preset): array
    {
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('product.presets.'.$preset->value.'.defaults');
        $demoUrl = (string) config('product.qr.demo_url');

        $front = (string) ($defaults['front'] ?? 'none');
        $back = (string) ($defaults['back'] ?? 'none');

        return [
            'shape' => (string) ($defaults['shape'] ?? 'square'),
            'size' => (float) ($defaults['size'] ?? config('product.size_min_mm')),
            'fillet' => (float) ($defaults['fillet'] ?? 0.0),
            'thickness' => (float) ($defaults['thickness'] ?? 4.0),
            'baseProfile' => (string) ($defaults['base_profile'] ?? 'flat'),
            'rimWidth' => (float) ($defaults['rim_width'] ?? 5.0),
            'recessDepth' => (float) ($defaults['recess_depth'] ?? 1.2),
            'front' => $front,
            'back' => $back,
            'mode' => (string) ($defaults['mode'] ?? 'engrave'),
            'depth' => (float) ($defaults['depth'] ?? 0.8),
            'qrDataFront' => str_contains($front, 'qr') ? $demoUrl : null,
            'qrDataBack' => str_contains($back, 'qr') ? $demoUrl : null,
            'qrEc' => (string) ($defaults['qr_ec'] ?? config('product.qr.default_ec')),
            'nfc' => (bool) ($defaults['nfc'] ?? false),
            'tagDiameter' => (int) ($defaults['tag_diameter'] ?? 25),
            'tagThickness' => (float) config('product.nfc.tag_thickness_default_mm'),
            'nozzle' => (string) ($defaults['nozzle'] ?? '0.4'),
            'layerHeight' => isset($defaults['layer_height']) ? (float) $defaults['layer_height'] : null,
            'printer' => (string) config('printers.default'),
            'material' => (string) ($defaults['material'] ?? 'pla-matte'),
            'plate' => (int) ($defaults['plate'] ?? 1),
            'xyComp' => (float) ($defaults['xy_comp'] ?? 0.0),
        ];
    }

    /**
     * The exact PreviewParams shape of contract 04 (plus the extra
     * logoPreviewUrl hint) from a stored parameters snapshot.
     *
     * @return array<string, mixed>
     */
    public static function previewParamsFromDto(MenuTagParameters $parameters, ?string $logoPreviewUrl = null): array
    {
        return [
            'shape' => $parameters->shape->value,
            'size' => $parameters->size,
            'fillet' => $parameters->fillet,
            'thickness' => $parameters->thickness,
            'baseProfile' => $parameters->baseProfile->value,
            'rimWidth' => $parameters->rimWidth,
            'recessDepth' => $parameters->recessDepth,
            'front' => $parameters->front->value,
            'back' => $parameters->back->value,
            'mode' => $parameters->mode->value,
            'depth' => $parameters->depth,
            'qrDataFront' => $parameters->qrDataFront,
            'qrDataBack' => $parameters->qrDataBack,
            'qrEc' => $parameters->qrEc->value,
            'nfc' => $parameters->nfc,
            'tagDiameter' => $parameters->tagDiameter->value,
            'logoPreviewUrl' => $logoPreviewUrl,
        ];
    }
}
