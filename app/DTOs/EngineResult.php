<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Printability;
use App\Exceptions\EngineFailureException;

/**
 * Typed engine report, built by the KEY=VALUE stdout parser (contract 03
 * §2 and §4): one pair per line, free order, unknown keys preserved.
 *
 * `raw` is the COMPLETE key → value map as printed by the engine (known and
 * unknown keys alike) and is what gets saved verbatim into
 * `menu_tags.report` (contract 01). The repeatable `WARNING` key is typed in
 * `$warnings` and kept in `raw['WARNING']` joined by newlines, so the stored
 * report stays integral.
 */
final readonly class EngineResult
{
    /** Keys the engine must always print on exit 0 (contract 03 §4). */
    private const array REQUIRED_KEYS = [
        'OK', 'TRIANGLES', 'VOLUME_MM3', 'WEIGHT_G', 'NOZZLE', 'LAYER_HEIGHT',
        'FIRST_LAYER', 'BBOX_X', 'BBOX_Y', 'BBOX_Z', 'FILE_SIZE_KB',
        'VOLUME_DELTA_MM3', 'SIZE_MIN_FUNCTIONAL_MM', 'RENDER_MODE',
        'MATERIAL', 'PLATE', 'XY_COMP_MM', 'PRINTABILITY',
    ];

    /**
     * Field order follows contract 03 §2.
     *
     * @param  list<string>  $warnings  repeatable WARNING=<text> lines, in stdout order
     * @param  array<string, string>  $raw  full stdout map, saved verbatim in menu_tags.report
     */
    public function __construct(
        public bool $ok,
        public int $triangles,
        public float $volumeMm3,
        public float $weightG,
        public ?float $pauseZ,
        public ?int $pauseLayer,
        public string $nozzle,
        public float $layerHeight,
        public float $firstLayer,
        public float $bboxX,
        public float $bboxY,
        public float $bboxZ,
        public int $fileSizeKb,
        public ?int $qrVersion,
        public ?string $qrEc,
        public ?int $qrModules,
        public ?float $qrPitchMm,
        public ?bool $qrDecoded,
        public ?float $featureMinMm,
        public ?float $featureLossPct,
        public ?float $voidMinMm,
        public ?float $perimeterResiduePct,
        public ?float $perimeterResidueWidthMm,
        public float $volumeDeltaMm3,
        public float $sizeMinFunctionalMm,
        public string $renderMode,
        public ?int $accentTriangles,
        public ?float $accentVolumeMm3,
        public ?int $bicolorLayers,
        public ?float $rimWidth,
        public ?float $recessDepth,
        public ?float $capacityMl,
        public string $material,
        public int $plate,
        public float $xyCompMm,
        public Printability $printability,
        public array $warnings,
        public array $raw,
    ) {}

    /**
     * Robust KEY=VALUE parser (contract 03 §4). Lines without '=' are
     * skipped, unknown keys land in `raw` untouched, WARNING is repeatable.
     *
     * @throws EngineFailureException when a mandatory key is missing or a
     *                                typed value cannot be cast — the engine exited 0 but broke its
     *                                own contract, which is an internal error, never a user error.
     */
    public static function fromStdout(string $stdout): self
    {
        $raw = [];
        $warnings = [];

        foreach (preg_split('/\r\n|\r|\n/', $stdout) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ($key === 'WARNING') {
                $warnings[] = $value;

                continue;
            }

            $raw[$key] = $value;
        }

        if ($warnings !== []) {
            $raw['WARNING'] = implode("\n", $warnings);
        }

        $missing = array_values(array_filter(
            self::REQUIRED_KEYS,
            static fn (string $key): bool => ! array_key_exists($key, $raw),
        ));

        if ($missing !== []) {
            throw EngineFailureException::becauseStdoutIsMalformed(
                'missing mandatory keys: '.implode(', ', $missing),
                $stdout,
            );
        }

        $printability = Printability::tryFrom(strtolower($raw['PRINTABILITY']));

        if ($printability === null) {
            throw EngineFailureException::becauseStdoutIsMalformed(
                sprintf("PRINTABILITY has unexpected value '%s' (expected ok|warn|blocked)", $raw['PRINTABILITY']),
                $stdout,
            );
        }

        return new self(
            ok: $raw['OK'] === '1',
            triangles: self::intValue($raw, 'TRIANGLES', $stdout),
            volumeMm3: self::floatValue($raw, 'VOLUME_MM3', $stdout),
            weightG: self::floatValue($raw, 'WEIGHT_G', $stdout),
            pauseZ: self::optionalFloat($raw, 'PAUSE_Z', $stdout),
            pauseLayer: self::optionalInt($raw, 'PAUSE_LAYER', $stdout),
            nozzle: $raw['NOZZLE'],
            layerHeight: self::floatValue($raw, 'LAYER_HEIGHT', $stdout),
            firstLayer: self::floatValue($raw, 'FIRST_LAYER', $stdout),
            bboxX: self::floatValue($raw, 'BBOX_X', $stdout),
            bboxY: self::floatValue($raw, 'BBOX_Y', $stdout),
            bboxZ: self::floatValue($raw, 'BBOX_Z', $stdout),
            fileSizeKb: self::intValue($raw, 'FILE_SIZE_KB', $stdout),
            qrVersion: self::optionalInt($raw, 'QR_VERSION', $stdout),
            qrEc: $raw['QR_EC'] ?? null,
            qrModules: self::optionalInt($raw, 'QR_MODULES', $stdout),
            qrPitchMm: self::optionalFloat($raw, 'QR_PITCH_MM', $stdout),
            qrDecoded: self::optionalYesNo($raw, 'QR_DECODED'),
            featureMinMm: self::optionalFloat($raw, 'FEATURE_MIN_MM', $stdout),
            featureLossPct: self::optionalFloat($raw, 'FEATURE_LOSS_PCT', $stdout),
            voidMinMm: self::optionalFloat($raw, 'VOID_MIN_MM', $stdout),
            perimeterResiduePct: self::optionalFloat($raw, 'PERIMETER_RESIDUE_PCT', $stdout),
            perimeterResidueWidthMm: self::optionalFloat($raw, 'PERIMETER_RESIDUE_WIDTH_MM', $stdout),
            volumeDeltaMm3: self::floatValue($raw, 'VOLUME_DELTA_MM3', $stdout),
            sizeMinFunctionalMm: self::floatValue($raw, 'SIZE_MIN_FUNCTIONAL_MM', $stdout),
            renderMode: $raw['RENDER_MODE'],
            accentTriangles: self::optionalInt($raw, 'ACCENT_TRIANGLES', $stdout),
            accentVolumeMm3: self::optionalFloat($raw, 'ACCENT_VOLUME_MM3', $stdout),
            bicolorLayers: self::optionalInt($raw, 'BICOLOR_LAYERS', $stdout),
            rimWidth: self::optionalFloat($raw, 'RIM_WIDTH', $stdout),
            recessDepth: self::optionalFloat($raw, 'RECESS_DEPTH', $stdout),
            capacityMl: self::optionalFloat($raw, 'CAPACITY_ML', $stdout),
            material: $raw['MATERIAL'],
            plate: self::intValue($raw, 'PLATE', $stdout),
            xyCompMm: self::floatValue($raw, 'XY_COMP_MM', $stdout),
            printability: $printability,
            warnings: $warnings,
            raw: $raw,
        );
    }

    /**
     * @param  array<string, string>  $raw
     */
    private static function intValue(array $raw, string $key, string $stdout): int
    {
        if (! is_numeric($raw[$key])) {
            throw EngineFailureException::becauseStdoutIsMalformed(
                sprintf("%s should be an integer, got '%s'", $key, $raw[$key]),
                $stdout,
            );
        }

        return (int) $raw[$key];
    }

    /**
     * @param  array<string, string>  $raw
     */
    private static function floatValue(array $raw, string $key, string $stdout): float
    {
        if (! is_numeric($raw[$key])) {
            throw EngineFailureException::becauseStdoutIsMalformed(
                sprintf("%s should be a float, got '%s'", $key, $raw[$key]),
                $stdout,
            );
        }

        return (float) $raw[$key];
    }

    /**
     * @param  array<string, string>  $raw
     */
    private static function optionalInt(array $raw, string $key, string $stdout): ?int
    {
        return array_key_exists($key, $raw) ? self::intValue($raw, $key, $stdout) : null;
    }

    /**
     * @param  array<string, string>  $raw
     */
    private static function optionalFloat(array $raw, string $key, string $stdout): ?float
    {
        return array_key_exists($key, $raw) ? self::floatValue($raw, $key, $stdout) : null;
    }

    /**
     * QR_DECODED=yes|no (contract 03 §4). Tolerant on unexpected values:
     * the raw string stays available in `raw` for the saved report.
     *
     * @param  array<string, string>  $raw
     */
    private static function optionalYesNo(array $raw, string $key): ?bool
    {
        return match (strtolower($raw[$key] ?? '')) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }
}
