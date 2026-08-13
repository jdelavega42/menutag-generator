<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BaseProfile;
use App\Enums\FaceContent;
use App\Enums\Material;
use App\Enums\Nozzle;
use App\Enums\QrEcLevel;
use App\Enums\RenderMode;
use App\Enums\Shape;
use App\Enums\TagDiameter;
use LogicException;

/**
 * Complete snapshot of a menu tag configuration (contract 02).
 *
 * Serialized into `menu_tags.parameters`, validated on construction
 * (invariants V1..V12) and translated to CLI arguments exclusively by
 * toCliArguments(). Every constant comes from config/product.php and
 * config/printers.php — never inline — so the Python engine can re-verify
 * the same rules with the same values (defense in depth).
 *
 * All float comparisons use an explicit 1e-9 tolerance (spec §8.3): naive
 * floating-point comparisons on steps, layers and thresholds are the class
 * of bug that silently degrades geometry.
 */
final readonly class MenuTagParameters
{
    private const float EPS = 1e-9;

    public function __construct(
        public Shape $shape,
        public float $size,
        public float $fillet = 0.0,
        public float $thickness = 4.0,
        public BaseProfile $baseProfile = BaseProfile::Flat,
        public float $rimWidth = 5.0,
        public float $recessDepth = 1.2,
        public FaceContent $front = FaceContent::None,
        public FaceContent $back = FaceContent::None,
        public RenderMode $mode = RenderMode::Engrave,
        public float $depth = 0.8,
        public ?float $margin = null,
        public ?int $logoAssetId = null,
        public float $logoRotate = 0.0,
        public ?string $qrDataFront = null,
        public ?string $qrDataBack = null,
        public QrEcLevel $qrEc = QrEcLevel::H,
        public bool $nfc = false,
        public TagDiameter $tagDiameter = TagDiameter::D25,
        public float $tagThickness = 0.80,
        public Nozzle $nozzle = Nozzle::N04,
        public ?float $layerHeight = null,
        public string $printer = 'a1mini',
        public Material $material = Material::PlaMatte,
        public int $plate = 1,
        public float $xyComp = 0.0,
    ) {
        $this->validate();
    }

    /**
     * Build from the snake_case array shape used by the API payload and the
     * JSON column (contract 02 / openapi MenuTagParameters schema).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidMenuTagParameters
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $shape = self::enumField($data, 'shape', Shape::class, null, $errors, required: true);
        $size = self::floatField($data, 'size', null, $errors, required: true);

        $baseProfile = self::enumField($data, 'base_profile', BaseProfile::class, BaseProfile::Flat, $errors);
        $front = self::enumField($data, 'front', FaceContent::class, FaceContent::None, $errors);
        $back = self::enumField($data, 'back', FaceContent::class, FaceContent::None, $errors);
        $mode = self::enumField($data, 'mode', RenderMode::class, RenderMode::Engrave, $errors);
        $qrEc = self::enumField($data, 'qr_ec', QrEcLevel::class, QrEcLevel::H, $errors);
        $tagDiameter = self::enumField($data, 'tag_diameter', TagDiameter::class, TagDiameter::D25, $errors, intBacked: true);
        $nozzle = self::enumField($data, 'nozzle', Nozzle::class, Nozzle::N04, $errors);
        $material = self::enumField($data, 'material', Material::class, Material::PlaMatte, $errors);

        $fillet = self::floatField($data, 'fillet', 0.0, $errors);
        $thickness = self::floatField($data, 'thickness', 4.0, $errors);
        $rimWidth = self::floatField($data, 'rim_width', 5.0, $errors);
        $recessDepth = self::floatField($data, 'recess_depth', 1.2, $errors);
        $depth = self::floatField($data, 'depth', 0.8, $errors);
        $margin = self::floatField($data, 'margin', null, $errors, nullable: true);
        $logoRotate = self::floatField($data, 'logo_rotate', 0.0, $errors);
        $tagThickness = self::floatField($data, 'tag_thickness', 0.80, $errors);
        $layerHeight = self::floatField($data, 'layer_height', null, $errors, nullable: true);
        $xyComp = self::floatField($data, 'xy_comp', 0.0, $errors);

        $logoAssetId = self::intField($data, 'logo_asset_id', null, $errors, nullable: true);
        $plate = self::intField($data, 'plate', 1, $errors);

        $nfc = self::boolField($data, 'nfc', false, $errors);

        $qrDataFront = self::stringField($data, 'qr_data_front');
        $qrDataBack = self::stringField($data, 'qr_data_back');
        $printer = self::stringField($data, 'printer') ?? 'a1mini';

        if ($errors !== []) {
            throw InvalidMenuTagParameters::withErrors($errors);
        }

        /** @var Shape $shape */
        /** @var float $size */
        return new self(
            shape: $shape,
            size: $size,
            fillet: $fillet ?? 0.0,
            thickness: $thickness ?? 4.0,
            baseProfile: $baseProfile ?? BaseProfile::Flat,
            rimWidth: $rimWidth ?? 5.0,
            recessDepth: $recessDepth ?? 1.2,
            front: $front ?? FaceContent::None,
            back: $back ?? FaceContent::None,
            mode: $mode ?? RenderMode::Engrave,
            depth: $depth ?? 0.8,
            margin: $margin,
            logoAssetId: $logoAssetId,
            logoRotate: $logoRotate ?? 0.0,
            qrDataFront: $qrDataFront,
            qrDataBack: $qrDataBack,
            qrEc: $qrEc ?? QrEcLevel::H,
            nfc: $nfc,
            tagDiameter: $tagDiameter ?? TagDiameter::D25,
            tagThickness: $tagThickness ?? 0.80,
            nozzle: $nozzle ?? Nozzle::N04,
            layerHeight: $layerHeight,
            printer: $printer,
            material: $material ?? Material::PlaMatte,
            plate: $plate ?? 1,
            xyComp: $xyComp ?? 0.0,
        );
    }

    /**
     * Snake_case snapshot, key order = contract 02 field table. This is the
     * exact shape stored in the `parameters` JSON column.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shape' => $this->shape->value,
            'size' => $this->size,
            'fillet' => $this->fillet,
            'thickness' => $this->thickness,
            'base_profile' => $this->baseProfile->value,
            'rim_width' => $this->rimWidth,
            'recess_depth' => $this->recessDepth,
            'front' => $this->front->value,
            'back' => $this->back->value,
            'mode' => $this->mode->value,
            'depth' => $this->depth,
            'margin' => $this->margin,
            'logo_asset_id' => $this->logoAssetId,
            'logo_rotate' => $this->logoRotate,
            'qr_data_front' => $this->qrDataFront,
            'qr_data_back' => $this->qrDataBack,
            'qr_ec' => $this->qrEc->value,
            'nfc' => $this->nfc,
            'tag_diameter' => $this->tagDiameter->value,
            'tag_thickness' => $this->tagThickness,
            'nozzle' => $this->nozzle->value,
            'layer_height' => $this->layerHeight,
            'printer' => $this->printer,
            'material' => $this->material->value,
            'plate' => $this->plate,
            'xy_comp' => $this->xyComp,
        ];
    }

    /**
     * Effective (as-printed) size: nominal size + 2 × per-side XY
     * compensation. Used ONLY by the NFC pocket plan check (V8), which
     * measures the piece that actually leaves the printer. Product limits
     * (V1) and QR floors (V5) apply to the NOMINAL size: they are design
     * quotas, the compensation corrects print drift (decisions §2).
     */
    public function effectiveSize(): float
    {
        return $this->size + 2 * $this->xyComp;
    }

    /**
     * CLI argument list for the Process facade — NEVER a concatenated string.
     *
     * Emission rules (contract 02, deterministic so the DTO → CLI mapping
     * tests have a single expected value):
     * - numbers with a decimal point, no scientific notation, trailing zeros
     *   trimmed down to one decimal (3.0 → "3.0", 0.80 → "0.8");
     * - conditional flags omitted when their condition is inactive;
     * - `--margin auto` emitted literally when margin is null;
     * - `--layer-height` omitted when null (engine default applies);
     * - never `--qr-data`: always the per-face variants;
     * - everything else always emitted, defaults included (explicit beats
     *   implicit in the job log);
     * - order = contract 02 field table, then --out / --out-accent.
     *
     * @param  string  $outPath  absolute path inside the 'stl' disk
     * @param  ?string  $outAccentPath  required ⇔ mode=inlay
     * @param  ?string  $logoPath  absolute path resolved by the Job inside the worker
     * @return list<string>
     */
    public function toCliArguments(
        string $outPath,
        ?string $outAccentPath = null,
        ?string $logoPath = null,
    ): array {
        if ($this->mode === RenderMode::Inlay && $outAccentPath === null) {
            throw new LogicException('outAccentPath is required when mode is inlay.');
        }

        if ($logoPath === null && ($this->front->hasLogo() || $this->back->hasLogo())) {
            throw new LogicException('logoPath is required when a face contains a logo.');
        }

        $args = [
            '--shape', $this->shape->value,
            '--size', self::formatFloat($this->size),
        ];

        if ($this->shape === Shape::Square) {
            array_push($args, '--fillet', self::formatFloat($this->fillet));
        }

        array_push($args, '--thickness', self::formatFloat($this->thickness));
        array_push($args, '--base-profile', $this->baseProfile->value);

        if ($this->baseProfile === BaseProfile::Rimmed) {
            array_push($args, '--rim-width', self::formatFloat($this->rimWidth));
            array_push($args, '--recess-depth', self::formatFloat($this->recessDepth));
        }

        array_push($args, '--front', $this->front->value);
        array_push($args, '--back', $this->back->value);
        array_push($args, '--mode', $this->mode->value);
        array_push($args, '--depth', self::formatFloat($this->depth));
        array_push($args, '--margin', $this->margin === null ? 'auto' : self::formatFloat($this->margin));

        if ($logoPath !== null) {
            array_push($args, '--logo', $logoPath);
            array_push($args, '--logo-rotate', self::formatFloat($this->logoRotate));
        }

        if ($this->front->hasQr()) {
            array_push($args, '--qr-data-front', (string) $this->qrDataFront);
        }

        if ($this->back->hasQr()) {
            array_push($args, '--qr-data-back', (string) $this->qrDataBack);
        }

        array_push($args, '--qr-ec', $this->qrEc->value);

        if ($this->nfc) {
            $args[] = '--nfc';
            array_push($args, '--tag', (string) $this->tagDiameter->value);
            array_push($args, '--tag-thickness', self::formatFloat($this->tagThickness));
        }

        array_push($args, '--nozzle', $this->nozzle->value);

        if ($this->layerHeight !== null) {
            array_push($args, '--layer-height', self::formatFloat($this->layerHeight));
        }

        array_push($args, '--printer', $this->printer);
        array_push($args, '--material', $this->material->value);
        array_push($args, '--plate', (string) $this->plate);
        array_push($args, '--xy-comp', self::formatFloat($this->xyComp));
        array_push($args, '--out', $outPath);

        if ($this->mode === RenderMode::Inlay) {
            array_push($args, '--out-accent', (string) $outAccentPath);
        }

        return $args;
    }

    /**
     * Smallest byte-mode QR version (1..20) able to hold $data at the given
     * EC level, from the ISO table in config — the same rule the engine and
     * the JS preview apply (byte mode forced, no segmentation). Null when
     * the payload exceeds version 20.
     */
    public static function minQrVersion(string $data, QrEcLevel $ec): ?int
    {
        /** @var array<int, int> $capacities */
        $capacities = config('product.qr.byte_capacity.'.$ec->value);
        $bytes = strlen($data);

        foreach ($capacities as $version => $capacity) {
            if ($bytes <= $capacity) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Minimum size (mm) at which $data fits as a scannable QR on the given
     * shape (V5): pitch_min × (n + 8) for squares, pitch_min × (n·√2 + 8)
     * for circles, with n = 17 + 4 × version. Rounded UP to 0.1 mm and
     * clamped to the shape's product floor (58.8 square / 79.2 circle).
     * Null when the payload exceeds version 20. Reusable by the Form
     * Request and the Livewire Configurator (same table, same result).
     */
    public static function minSizeForQr(string $data, QrEcLevel $ec, Shape $shape): ?float
    {
        $version = self::minQrVersion($data, $ec);

        if ($version === null) {
            return null;
        }

        $modules = 17 + 4 * $version;
        $pitch = (float) config('product.qr.min_pitch_mm');

        $raw = $shape === Shape::Square
            ? $pitch * ($modules + 8)
            : $pitch * ($modules * M_SQRT2 + 8);

        $floor = $shape === Shape::Square
            ? (float) config('product.qr.floor_square_mm')
            : (float) config('product.qr.floor_circle_mm');

        return max($floor, self::roundUpToTenth($raw));
    }

    /**
     * Layer height actually used by the validations: explicit value, or the
     * printer profile default for the selected nozzle.
     */
    public function resolvedLayerHeight(): float
    {
        if ($this->layerHeight !== null) {
            return $this->layerHeight;
        }

        // The nozzle key contains a dot ('0.2'/'0.4'): it cannot travel
        // inside a dotted config path (data_get would split it into two
        // segments), so fetch the nozzles array and index it. Fixed by WS-6
        // together with the same latent lookup in FakeMenuTagEngine.
        /** @var array<string, array{layer_default?: float}> $nozzles */
        $nozzles = (array) config(sprintf('printers.profiles.%s.nozzles', $this->printer), []);

        return (float) ($nozzles[$this->nozzle->value]['layer_default'] ?? 0.10);
    }

    /**
     * Total depth carved out of the body: engrave and inlay consume the
     * thickness budget for every face with content, relief consumes nothing.
     */
    public function engravedDepthTotal(): float
    {
        if (! $this->mode->consumesThickness()) {
            return 0.0;
        }

        $faces = ($this->front === FaceContent::None ? 0 : 1)
            + ($this->back === FaceContent::None ? 0 : 1);

        return $this->depth * $faces;
    }

    /**
     * Invariants V1..V12 (contract 02). Collects every violation and throws
     * once, with per-field Italian messages that explain how to get back
     * within limits.
     */
    private function validate(): void
    {
        $errors = [];
        $product = config('product');

        /** @var array<string, mixed>|null $profile */
        $profile = config('printers.profiles.'.$this->printer);

        if ($profile === null) {
            $errors['printer'][] = sprintf(
                "Stampante '%s' non supportata: scegli una fra %s.",
                $this->printer,
                implode(', ', array_keys((array) config('printers.profiles'))),
            );
        }

        /** @var array{layer_min: float, layer_max: float, layer_default: float, first_layer: float}|null $nozzleCfg */
        $nozzleCfg = $profile['nozzles'][$this->nozzle->value] ?? null;
        $layer = $this->layerHeight ?? ($nozzleCfg['layer_default'] ?? 0.10);

        // V1 — Product minimums and maximums (NOMINAL size, never effectiveSize()).
        if ($this->size < $product['size_min_mm'] - self::EPS || $this->size > $product['size_max_mm'] + self::EPS) {
            $errors['size'][] = sprintf(
                'La dimensione deve essere compresa fra %s e %s mm (il minimo è la moneta da 2 €): imposta un valore in questo intervallo.',
                self::formatFloat($product['size_min_mm']),
                self::formatFloat($product['size_max_mm']),
            );
        }

        if ($this->thickness < $product['thickness_min_mm'] - self::EPS || $this->thickness > $product['thickness_max_mm'] + self::EPS) {
            $errors['thickness'][] = sprintf(
                'Lo spessore deve essere compreso fra %s e %s mm (il minimo è la moneta da 2 €): imposta un valore in questo intervallo.',
                self::formatFloat($product['thickness_min_mm']),
                self::formatFloat($product['thickness_max_mm']),
            );
        }

        // V2 — Fillet: only on squares, within [0, size/2].
        if ($this->fillet < -self::EPS) {
            $errors['fillet'][] = 'La smussatura degli angoli non può essere negativa: impostala a 0 o a un valore positivo.';
        } elseif ($this->shape !== Shape::Square && $this->fillet > self::EPS) {
            $errors['fillet'][] = 'La smussatura degli angoli esiste solo per la forma quadrata: impostala a 0 oppure scegli il quadrato.';
        } elseif ($this->shape === Shape::Square && $this->fillet > $this->size / 2 + self::EPS) {
            $errors['fillet'][] = sprintf(
                'La smussatura degli angoli non può superare metà del lato (%s mm): riducila.',
                self::formatFloat($this->size / 2),
            );
        }

        // V3 — Rimmed profile: rim at least 3 nozzle passes; recess sane
        // (its budget impact is enforced by V6).
        if ($this->baseProfile === BaseProfile::Rimmed) {
            $minRim = 3 * $this->nozzle->mm();

            if ($this->rimWidth < $minRim - self::EPS) {
                $errors['rim_width'][] = sprintf(
                    'Il bordo antigoccia richiede almeno 3 passate dell’ugello (%s mm con ugello %s): allarga il bordo ad almeno %s mm.',
                    self::formatFloat($minRim),
                    $this->nozzle->value,
                    self::formatFloat($minRim),
                );
            }

            if ($this->recessDepth < self::EPS) {
                $errors['recess_depth'][] = 'La profondità dell’incavo deve essere maggiore di zero: imposta un valore positivo.';
            }
        }

        // V4 — QR faces require their payload (and only them); logo faces
        // require a logo asset.
        $this->validateFaceContents($errors);

        // V5 — QR floor depending on shape AND url (NOMINAL size).
        $this->validateQrFloor($errors);

        // V6 — Thickness budget: residual core after engraved faces and recess.
        $engraved = $this->engravedDepthTotal();
        $recess = $this->baseProfile === BaseProfile::Rimmed ? $this->recessDepth : 0.0;
        $core = $this->thickness - $engraved - $recess;
        $coreMin = max(
            (float) $product['graphics']['core_min_mm'],
            $product['graphics']['core_min_layers'] * $layer,
        );

        if ($core < $coreMin - self::EPS) {
            $errors['thickness'][] = sprintf(
                'Il nucleo residuo è %s mm (spessore %s − incisioni %s − incavo %s) ma deve essere di almeno %s mm (≥ %s mm e ≥ %d layer da %s mm): aumenta lo spessore oppure riduci profondità della grafica e incavo.',
                self::formatFloat($core),
                self::formatFloat($this->thickness),
                self::formatFloat($engraved),
                self::formatFloat($recess),
                self::formatFloat($coreMin),
                self::formatFloat((float) $product['graphics']['core_min_mm']),
                $product['graphics']['core_min_layers'],
                self::formatFloat($layer),
            );
        }

        // V7 — Minimum thickness with NFC: computed, never a constant. The
        // V6 core must contain pocket + 2 axial walls, i.e.:
        // thickness ≥ tag + axial clearance + 2 × wall + engraved + recess,
        // with wall = max(0.40 mm, 2 layers).
        if ($this->nfc) {
            $nfcCfg = $product['nfc'];
            $axialWall = max(
                (float) $nfcCfg['axial_wall_min_mm'],
                $nfcCfg['axial_wall_min_layers'] * $layer,
            );
            $minThickness = $this->tagThickness
                + (float) $nfcCfg['axial_clearance_mm']
                + 2 * $axialWall
                + $engraved
                + $recess;

            if ($this->thickness < $minThickness - self::EPS) {
                $errors['thickness'][] = sprintf(
                    'Con la tasca NFC lo spessore minimo calcolato è %s mm (tag %s + gioco assiale %s + 2 pareti da %s + incisioni %s + incavo %s): porta lo spessore ad almeno %s mm, oppure riduci profondità della grafica o spessore del tag.',
                    self::formatFloat($minThickness),
                    self::formatFloat($this->tagThickness),
                    self::formatFloat((float) $nfcCfg['axial_clearance_mm']),
                    self::formatFloat($axialWall),
                    self::formatFloat($engraved),
                    self::formatFloat($recess),
                    self::formatFloat($minThickness),
                );
            }

            // V8 — Minimum plan with NFC, on the EFFECTIVE size (the piece
            // that actually leaves the printer): tag + 2 × radial clearance
            // + 2 × radial wall → 25.4 mm (Ø22) / 28.4 mm (Ø25). For squares
            // the reference is the side (circular pocket, nearest edge).
            $minPlan = $this->tagDiameter->mm()
                + 2 * (float) $nfcCfg['radial_clearance_mm']
                + 2 * (float) $nfcCfg['radial_wall_min_mm'];

            if ($this->effectiveSize() < $minPlan - self::EPS) {
                $suggestion = $this->tagDiameter === TagDiameter::D25
                    ? ', oppure scegli il tag Ø22 (pianta minima 25.4 mm)'
                    : '';
                $errors['size'][] = sprintf(
                    'Con il tag NFC Ø%d la pianta minima è %s mm, ma la dimensione effettiva è %s mm (nominale %s + 2 × compensazione XY %s): porta la dimensione nominale ad almeno %s mm%s.',
                    $this->tagDiameter->value,
                    self::formatFloat($minPlan),
                    self::formatFloat($this->effectiveSize()),
                    self::formatFloat($this->size),
                    self::formatFloat($this->xyComp),
                    self::formatFloat(self::roundUpToTenth($minPlan - 2 * $this->xyComp)),
                    $suggestion,
                );
            }
        }

        // V9 — Graphic depth range; in relief on a rimmed profile the height
        // must stay strictly below the rim.
        $graphics = $product['graphics'];

        if ($this->depth < $graphics['depth_min_mm'] - self::EPS || $this->depth > $graphics['depth_max_mm'] + self::EPS) {
            $errors['depth'][] = sprintf(
                'La profondità (o altezza) della grafica deve essere compresa fra %s e %s mm: imposta un valore in questo intervallo.',
                self::formatFloat((float) $graphics['depth_min_mm']),
                self::formatFloat((float) $graphics['depth_max_mm']),
            );
        }

        if (
            $this->mode === RenderMode::Relief
            && $this->baseProfile === BaseProfile::Rimmed
            && $this->depth > $this->recessDepth - self::EPS
        ) {
            $errors['depth'][] = sprintf(
                'In rilievo con bordo antigoccia l’altezza della grafica deve restare sotto il bordo, altrimenti il bicchiere appoggia sul rilievo: riduci l’altezza sotto %s mm (profondità dell’incavo).',
                self::formatFloat($this->recessDepth),
            );
        }

        // V10 — QR with center logo forces EC level H.
        if (($this->front === FaceContent::QrLogo || $this->back === FaceContent::QrLogo) && $this->qrEc !== QrEcLevel::H) {
            $errors['qr_ec'][] = 'Con il logo al centro del QR la correzione d’errore deve essere H (il logo copre parte del simbolo): imposta la correzione a H.';
        }

        // V11 — Explicit layer height within the nozzle range of the profile.
        if ($this->layerHeight !== null && $nozzleCfg !== null) {
            if ($this->layerHeight < $nozzleCfg['layer_min'] - self::EPS || $this->layerHeight > $nozzleCfg['layer_max'] + self::EPS) {
                $errors['layer_height'][] = sprintf(
                    'Con ugello %s l’altezza layer deve essere compresa fra %s e %s mm: imposta un valore nel range oppure lascia il default (%s mm).',
                    $this->nozzle->value,
                    self::formatFloat($nozzleCfg['layer_min']),
                    self::formatFloat($nozzleCfg['layer_max']),
                    self::formatFloat($nozzleCfg['layer_default']),
                );
            }
        }

        // V12 — Plate, XY compensation and tag thickness ranges.
        $maxPieces = (int) $product['plate']['max_pieces'];

        if ($this->plate < 1 || $this->plate > $maxPieces) {
            $errors['plate'][] = sprintf(
                'La piastra può contenere da 1 a %d pezzi: imposta un numero in questo intervallo.',
                $maxPieces,
            );
        }

        [$xyMin, $xyMax] = $product['xy_comp_range_mm'];

        if ($this->xyComp < $xyMin - self::EPS || $this->xyComp > $xyMax + self::EPS) {
            $errors['xy_comp'][] = sprintf(
                'La compensazione XY deve essere compresa fra %s e %s mm per lato: imposta un valore in questo intervallo.',
                self::formatFloat((float) $xyMin),
                self::formatFloat((float) $xyMax),
            );
        }

        [$tagMin, $tagMax] = $product['nfc']['tag_thickness_range_mm'];

        if ($this->tagThickness < $tagMin - self::EPS || $this->tagThickness > $tagMax + self::EPS) {
            $errors['tag_thickness'][] = sprintf(
                'Lo spessore del tag NFC deve essere compreso fra %s e %s mm: misura il tag reale e imposta un valore in questo intervallo.',
                self::formatFloat((float) $tagMin),
                self::formatFloat((float) $tagMax),
            );
        }

        if ($errors !== []) {
            throw InvalidMenuTagParameters::withErrors($errors);
        }
    }

    /**
     * V4 — face content coherence.
     *
     * @param  array<string, list<string>>  $errors
     */
    private function validateFaceContents(array &$errors): void
    {
        if ($this->front->hasQr() && ($this->qrDataFront === null || $this->qrDataFront === '')) {
            $errors['qr_data_front'][] = 'La faccia frontale contiene un codice QR: inserisci l’indirizzo (URL) da codificare.';
        }

        if (! $this->front->hasQr() && $this->qrDataFront !== null) {
            $errors['qr_data_front'][] = 'La faccia frontale non prevede un codice QR: rimuovi l’indirizzo oppure imposta il contenuto della faccia su QR.';
        }

        if ($this->back->hasQr() && ($this->qrDataBack === null || $this->qrDataBack === '')) {
            $errors['qr_data_back'][] = 'La faccia posteriore contiene un codice QR: inserisci l’indirizzo (URL) da codificare.';
        }

        if (! $this->back->hasQr() && $this->qrDataBack !== null) {
            $errors['qr_data_back'][] = 'La faccia posteriore non prevede un codice QR: rimuovi l’indirizzo oppure imposta il contenuto della faccia su QR.';
        }

        if (($this->front->hasLogo() || $this->back->hasLogo()) && $this->logoAssetId === null) {
            $errors['logo_asset_id'][] = 'Il contenuto scelto include un logo: carica un logo oppure selezionane uno dalla libreria.';
        }
    }

    /**
     * V5 — QR floor depending on shape and URL, on the NOMINAL size. The
     * error message reports the minimum size computed for the actual URL on
     * BOTH shapes, so the user knows every way back within limits.
     *
     * @param  array<string, list<string>>  $errors
     */
    private function validateQrFloor(array &$errors): void
    {
        $payloads = [];

        if ($this->front->hasQr() && $this->qrDataFront !== null && $this->qrDataFront !== '') {
            $payloads['qr_data_front'] = $this->qrDataFront;
        }

        if ($this->back->hasQr() && $this->qrDataBack !== null && $this->qrDataBack !== '') {
            $payloads['qr_data_back'] = $this->qrDataBack;
        }

        if ($payloads === []) {
            return;
        }

        $minSquare = null;
        $minCircle = null;

        foreach ($payloads as $field => $payload) {
            $squareMin = self::minSizeForQr($payload, $this->qrEc, Shape::Square);

            if ($squareMin === null) {
                /** @var array<int, int> $capacities */
                $capacities = config('product.qr.byte_capacity.'.$this->qrEc->value);
                $errors[$field][] = sprintf(
                    'L’indirizzo è di %d byte e supera la capacità massima di un QR alla correzione %s (%d byte): accorcialo oppure usa un redirect breve.',
                    strlen($payload),
                    $this->qrEc->value,
                    max([0, ...$capacities]),
                );

                continue;
            }

            $circleMin = (float) self::minSizeForQr($payload, $this->qrEc, Shape::Circle);
            $minSquare = max($minSquare ?? 0.0, $squareMin);
            $minCircle = max($minCircle ?? 0.0, $circleMin);
        }

        if ($minSquare === null || $minCircle === null) {
            return;
        }

        $required = $this->shape === Shape::Square ? $minSquare : $minCircle;

        if ($this->size < $required - self::EPS) {
            $errors['size'][] = sprintf(
                'Con questo indirizzo il codice QR richiede almeno %s mm di lato, oppure %s mm di diametro: aumenta la dimensione ad almeno %s mm, oppure accorcia l’URL — un indirizzo breve o un redirect mantiene il formato base.',
                self::formatFloat($minSquare),
                self::formatFloat($minCircle),
                self::formatFloat($required),
            );
        }
    }

    /**
     * Deterministic float formatting for CLI arguments and messages: decimal
     * point, no scientific notation, at most 6 decimals, trailing zeros
     * trimmed but always at least one decimal (3.0 → "3.0", 58.8 → "58.8").
     */
    private static function formatFloat(float $value): string
    {
        $formatted = number_format($value, 6, '.', '');
        $formatted = rtrim($formatted, '0');

        if (str_ends_with($formatted, '.')) {
            $formatted .= '0';
        }

        return $formatted;
    }

    /**
     * Round up to the next 0.1 mm with the explicit 1e-9 tolerance, matching
     * the preset rule "rounded up to 0.1" and the config floor values.
     */
    private static function roundUpToTenth(float $value): float
    {
        return ceil($value * 10 - self::EPS) / 10;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<TEnum>  $enumClass
     * @param  TEnum|null  $default
     * @param  array<string, list<string>>  $errors
     * @return TEnum|null
     */
    private static function enumField(
        array $data,
        string $key,
        string $enumClass,
        ?\BackedEnum $default,
        array &$errors,
        bool $required = false,
        bool $intBacked = false,
    ): ?\BackedEnum {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            if ($required) {
                $errors[$key][] = sprintf('Il campo %s è obbligatorio.', $key);
            }

            return $default;
        }

        $raw = $data[$key];

        if ($raw instanceof $enumClass) {
            return $raw;
        }

        $value = $intBacked
            ? (is_numeric($raw) ? $enumClass::tryFrom((int) $raw) : null)
            : (is_scalar($raw) ? $enumClass::tryFrom((string) $raw) : null);

        if ($value === null) {
            $errors[$key][] = sprintf(
                'Valore non valido per %s: usa uno fra %s.',
                $key,
                implode(', ', array_map(static fn (\BackedEnum $case): string => (string) $case->value, $enumClass::cases())),
            );

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $errors
     */
    private static function floatField(
        array $data,
        string $key,
        ?float $default,
        array &$errors,
        bool $required = false,
        bool $nullable = false,
    ): ?float {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            if ($required && ! $nullable) {
                $errors[$key][] = sprintf('Il campo %s è obbligatorio.', $key);
            }

            return $default;
        }

        if (! is_numeric($data[$key])) {
            $errors[$key][] = sprintf('Il campo %s deve essere un numero.', $key);

            return $default;
        }

        return (float) $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $errors
     */
    private static function intField(
        array $data,
        string $key,
        ?int $default,
        array &$errors,
        bool $nullable = false,
    ): ?int {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        if (! is_numeric($data[$key])) {
            $errors[$key][] = sprintf('Il campo %s deve essere un numero intero.', $key);

            return $default;
        }

        return (int) $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, list<string>>  $errors
     */
    private static function boolField(array $data, string $key, bool $default, array &$errors): bool
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return $default;
        }

        $value = filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value === null) {
            $errors[$key][] = sprintf('Il campo %s deve essere vero o falso.', $key);

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stringField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
