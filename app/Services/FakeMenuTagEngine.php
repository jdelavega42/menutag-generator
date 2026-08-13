<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MenuTagEngineContract;
use App\DTOs\EngineRequest;
use App\DTOs\EngineResult;
use App\DTOs\MenuTagParameters;
use App\Enums\BaseProfile;
use App\Enums\FaceContent;
use App\Enums\RenderMode;
use App\Enums\Shape;
use App\Exceptions\EngineFailureException;
use App\Exceptions\EngineValidationException;

/**
 * Test double for the geometry engine (contract 03 §1): NO test ever
 * invokes Python. Configurable to simulate the three outcomes of the
 * partition (decisions §3):
 *
 *  - success        → complete, realistic report (derived from the request
 *                     and parsed through EngineResult::fromStdout, so the
 *                     real parser is exercised too);
 *  - user error     → EngineValidationException (exit 2, stderr message);
 *  - internal error → EngineFailureException (any other exit code).
 *
 * Every EngineRequest received is recorded for test assertions.
 */
final class FakeMenuTagEngine implements MenuTagEngineContract
{
    private const string OUTCOME_SUCCESS = 'success';

    private const string OUTCOME_VALIDATION_ERROR = 'validation-error';

    private const string OUTCOME_INTERNAL_ERROR = 'internal-error';

    private string $outcome = self::OUTCOME_SUCCESS;

    private ?EngineResult $forcedResult = null;

    private string $validationMessage = 'Con questo indirizzo il codice QR richiede almeno 63.6 mm di lato, '
        .'oppure 86.0 mm di diametro: aumenta la dimensione oppure accorcia l’URL con un redirect breve.';

    private string $internalStderr = 'fake: mesh integrity check failed (not watertight)';

    private bool $writeStlStubs = false;

    /** @var list<EngineRequest> */
    private array $requests = [];

    /**
     * Simulate a successful run. Without an explicit result, a realistic
     * report is derived from each incoming request.
     */
    public function succeed(?EngineResult $result = null): self
    {
        $this->outcome = self::OUTCOME_SUCCESS;
        $this->forcedResult = $result;

        return $this;
    }

    /**
     * Simulate exit code 2: user error, Italian message shown as-is.
     */
    public function failValidation(?string $userMessage = null): self
    {
        $this->outcome = self::OUTCOME_VALIDATION_ERROR;

        if ($userMessage !== null) {
            $this->validationMessage = $userMessage;
        }

        return $this;
    }

    /**
     * Simulate any other non-zero exit code: internal error, logged stderr,
     * generic message for the user.
     */
    public function failInternally(?string $stderr = null): self
    {
        $this->outcome = self::OUTCOME_INTERNAL_ERROR;

        if ($stderr !== null) {
            $this->internalStderr = $stderr;
        }

        return $this;
    }

    /**
     * Also write placeholder STL files on success, for tests asserting on
     * the presence of the output artifacts (directories are created).
     */
    public function writingStlStubs(bool $write = true): self
    {
        $this->writeStlStubs = $write;

        return $this;
    }

    /** @return list<EngineRequest> */
    public function requests(): array
    {
        return $this->requests;
    }

    public function lastRequest(): ?EngineRequest
    {
        return $this->requests === [] ? null : $this->requests[array_key_last($this->requests)];
    }

    public function timesGenerated(): int
    {
        return count($this->requests);
    }

    public function generate(EngineRequest $request): EngineResult
    {
        $this->requests[] = $request;

        return match ($this->outcome) {
            self::OUTCOME_VALIDATION_ERROR => throw EngineValidationException::fromStderr($this->validationMessage),
            self::OUTCOME_INTERNAL_ERROR => throw EngineFailureException::fromProcess(1, $this->internalStderr),
            default => $this->succeedWith($request),
        };
    }

    private function succeedWith(EngineRequest $request): EngineResult
    {
        if ($this->writeStlStubs) {
            $this->writeStub($request->outPath);

            if ($request->outAccentPath !== null) {
                $this->writeStub($request->outAccentPath);
            }
        }

        return $this->forcedResult ?? self::realisticResultFor($request);
    }

    /**
     * Build a complete, plausible report for the request by emitting a
     * contract 03 §4 stdout and running it through the real parser. The
     * numbers are fake but coherent (volume from the solid, weight from the
     * configured material density, pause on the layer grid, conditional keys
     * only when their feature is active) — NOT real geometry: that stays in
     * Python (spec §11).
     */
    public static function realisticResultFor(EngineRequest $request): EngineResult
    {
        return EngineResult::fromStdout(self::buildStdout($request));
    }

    private static function buildStdout(EngineRequest $request): string
    {
        $p = $request->parameters;

        $layer = $p->resolvedLayerHeight();
        // The nozzle key contains a dot ('0.2'/'0.4'), so it CANNOT travel
        // inside a dotted config path (data_get would split it): fetch the
        // nozzles array and index it. Fixed by WS-6 — the mandated grid test
        // (first layer 0.15 with the 0.2 nozzle, spec §8.3) caught the
        // dotted lookup silently falling back to 0.20 for every nozzle.
        /** @var array<string, array{first_layer?: float}> $nozzles */
        $nozzles = (array) config(sprintf('printers.profiles.%s.nozzles', $p->printer), []);
        $firstLayer = (float) ($nozzles[$p->nozzle->value]['first_layer'] ?? 0.20);
        $spacing = (float) config(sprintf('printers.profiles.%s.plate_spacing_mm', $p->printer), 5.0);
        $bedWarn = (float) config(sprintf('printers.profiles.%s.bed_warn_mm', $p->printer), 175.0);

        // Solid volume (upper bound vs infill, as documented in decisions §2).
        $pieceArea = $p->shape === Shape::Square
            ? $p->size ** 2
            : M_PI * ($p->size / 2) ** 2;
        $volume = $pieceArea * $p->thickness * $p->plate;
        $density = (float) config(sprintf('product.materials.%s.density_g_cm3', $p->material->value), 1.24);
        $weight = $volume / 1000 * $density;

        // Plate grid: as square as possible, pitch = bbox + spacing.
        $cols = (int) ceil(sqrt($p->plate));
        $rows = (int) ceil($p->plate / $cols);
        $bboxX = $cols * $p->size + ($cols - 1) * $spacing;
        $bboxY = $rows * $p->size + ($rows - 1) * $spacing;
        $hasArtwork = $p->front !== FaceContent::None || $p->back !== FaceContent::None;
        $bboxZ = $p->thickness + ($p->mode === RenderMode::Relief && $hasArtwork ? $p->depth : 0.0);

        // QR metrics from the same ISO table used by the PHP validations.
        // Derived as one nullable tuple so version/modules/pitch stay correlated.
        $qrPayload = $p->front->hasQr() ? $p->qrDataFront : ($p->back->hasQr() ? $p->qrDataBack : null);

        /** @var array{version: int, modules: int, pitch: float}|null $qr */
        $qr = null;

        if ($qrPayload !== null) {
            $version = MenuTagParameters::minQrVersion($qrPayload, $p->qrEc);
            $modules = 17 + 4 * $version;
            $qr = [
                'version' => $version,
                'modules' => $modules,
                'pitch' => $p->shape === Shape::Square
                    ? $p->size / ($modules + 8)
                    : $p->size / ($modules * M_SQRT2 + 8),
            ];
        }

        // Triangle count: plausible magnitudes, not geometry.
        $perPiece = $p->shape === Shape::Square ? 420 : 2360;

        foreach ([$p->front, $p->back] as $face) {
            if ($face->hasQr() && $qr !== null) {
                $perPiece += (int) round($qr['modules'] ** 2 * 1.8);
            }

            if ($face->hasLogo()) {
                $perPiece += 3200;
            }
        }

        $triangles = $perPiece * $p->plate;
        $fileSizeKb = (int) ceil((84 + 50 * $triangles) / 1024);

        $sizeMinFunctional = (float) config('product.size_min_mm', 25.75);

        foreach ([$p->qrDataFront, $p->qrDataBack] as $payload) {
            if ($payload !== null) {
                $sizeMinFunctional = max(
                    $sizeMinFunctional,
                    MenuTagParameters::minSizeForQr($payload, $p->qrEc, $p->shape) ?? $sizeMinFunctional,
                );
            }
        }

        $lines = [
            'OK=1',
            'TRIANGLES='.$triangles,
            'VOLUME_MM3='.self::fmt($volume, 2),
            'WEIGHT_G='.self::fmt($weight, 2),
            'NOZZLE='.$p->nozzle->value,
            'LAYER_HEIGHT='.self::fmt($layer, 2),
            'FIRST_LAYER='.self::fmt($firstLayer, 2),
            'BBOX_X='.self::fmt($bboxX, 2),
            'BBOX_Y='.self::fmt($bboxY, 2),
            'BBOX_Z='.self::fmt($bboxZ, 2),
            'FILE_SIZE_KB='.$fileSizeKb,
            'VOLUME_DELTA_MM3=0.0002',
            'SIZE_MIN_FUNCTIONAL_MM='.self::fmt($sizeMinFunctional, 1),
            'RENDER_MODE='.$p->mode->value,
            'MATERIAL='.$p->material->value,
            'PLATE='.$p->plate,
            'XY_COMP_MM='.self::fmt($p->xyComp, 2),
        ];

        // Artwork metrics exist only with graphics on at least one face.
        if ($hasArtwork) {
            $featureMin = $qr['pitch'] ?? 0.8;
            $lines[] = 'FEATURE_MIN_MM='.self::fmt($featureMin, 3);
            $lines[] = 'FEATURE_LOSS_PCT=0.4';
            $lines[] = 'VOID_MIN_MM='.self::fmt($featureMin, 3);
            $lines[] = 'PERIMETER_RESIDUE_PCT=0.0';
            $lines[] = 'PERIMETER_RESIDUE_WIDTH_MM=0.0';
        }

        if ($qr !== null) {
            $lines[] = 'QR_VERSION='.$qr['version'];
            $lines[] = 'QR_EC='.$p->qrEc->value;
            $lines[] = 'QR_MODULES='.$qr['modules'];
            $lines[] = 'QR_PITCH_MM='.self::fmt($qr['pitch'], 3);
            $lines[] = 'QR_DECODED=yes';
        }

        if ($p->nfc) {
            // Pause on the layer grid, like the validated reference
            // (58.8 × 3.0, L=0.10, FL=0.20 → PAUSE_Z=2.0, PAUSE_LAYER=19).
            $pauseRaw = $p->thickness - 1.0;
            $pauseZ = $firstLayer + floor(($pauseRaw - $firstLayer) / $layer + 1e-9) * $layer;
            $pauseLayer = 1 + (int) round(($pauseZ - $firstLayer) / $layer);
            $lines[] = 'PAUSE_Z='.self::fmt($pauseZ, 2);
            $lines[] = 'PAUSE_LAYER='.$pauseLayer;
        }

        if ($p->mode === RenderMode::Inlay) {
            $lines[] = 'ACCENT_TRIANGLES='.(int) round($triangles * 0.3);
            $lines[] = 'ACCENT_VOLUME_MM3='.self::fmt($pieceArea * 0.18 * $p->depth * $p->plate, 2);
            $lines[] = 'BICOLOR_LAYERS='.(int) ceil($p->depth / $layer - 1e-9);
        }

        if ($p->baseProfile === BaseProfile::Rimmed) {
            $innerArea = $p->shape === Shape::Circle
                ? M_PI * ($p->size / 2 - $p->rimWidth) ** 2
                : ($p->size - 2 * $p->rimWidth) ** 2;
            $lines[] = 'RIM_WIDTH='.self::fmt($p->rimWidth, 1);
            $lines[] = 'RECESS_DEPTH='.self::fmt($p->recessDepth, 1);
            $lines[] = 'CAPACITY_ML='.self::fmt($innerArea * $p->recessDepth / 1000, 1);
        }

        $printability = 'ok';

        if (max($bboxX, $bboxY) > $bedWarn) {
            $lines[] = sprintf(
                'WARNING=La piastra occupa %s × %s mm e supera il margine consigliato di %s mm: brim e skirt potrebbero uscire dal piano.',
                self::fmt($bboxX, 1),
                self::fmt($bboxY, 1),
                self::fmt($bedWarn, 0),
            );
            $printability = 'warn';
        }

        $lines[] = 'PRINTABILITY='.$printability;

        return implode("\n", $lines)."\n";
    }

    private function writeStub(string $path): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // 84-byte binary STL header + zero triangle count: enough for tests
        // asserting existence or sniffing the binary format.
        file_put_contents($path, str_pad('FAKE-MENUTAG-STL', 80, "\0").pack('V', 0));
    }

    private static function fmt(float $value, int $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
