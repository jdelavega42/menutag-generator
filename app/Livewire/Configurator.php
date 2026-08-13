<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DTOs\InvalidMenuTagParameters;
use App\DTOs\MenuTagParameters;
use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Enums\QrEcLevel;
use App\Enums\Shape;
use App\Http\Middleware\EnsureGuestToken;
use App\Http\Requests\StoreMenuTagRequest;
use App\Jobs\GenerateMenuTagJob;
use App\Livewire\Concerns\HandlesLogoUploads;
use App\Livewire\Concerns\ResolvesPresetDefaults;
use App\Models\MenuTag;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Configurator form state (contract 04). The preview parameters are ENTANGLED
 * with Alpine: parameter changes update the three.js preview entirely
 * client-side (zero server requests); Livewire receives the values only
 * through the debounced runLiveValidation() call and at submit.
 *
 * Live product bands (spec §3.2/§8.8), the dynamic MenuTag size floor
 * (spec §5.2) and the shape-change QR threshold (contract 04 flow rule 4)
 * are recomputed here at live validation with the SAME formulas and config
 * constants as the Form Request, the DTO and the engine.
 */
class Configurator extends Component
{
    use HandlesLogoUploads;
    use ResolvesPresetDefaults;
    use WithFileUploads;

    private const float EPS = 1e-9;

    // ---- Preset / customization state ---------------------------------

    public string $preset = 'menutag';

    /** True once the user unlocked "personalizza questo formato". */
    public bool $customized = false;

    public ?string $label = null;

    // ---- PreviewParams, entangled with Alpine (contract 04) -----------
    // Numeric fields are intentionally untyped: they round-trip through
    // Alpine/x-model as strings and are normalized server-side; a typed
    // float property would fail hydration on an emptied input.

    public string $shape = 'square';

    /** @var float|int|string|null */
    public $size = 58.8;

    /** @var float|int|string|null */
    public $fillet = 4.0;

    /** @var float|int|string|null */
    public $thickness = 3.0;

    public string $baseProfile = 'flat';

    /** @var float|int|string|null */
    public $rimWidth = 5.0;

    /** @var float|int|string|null */
    public $recessDepth = 1.2;

    public string $front = 'qr';

    public string $back = 'none';

    public string $mode = 'engrave';

    /** @var float|int|string|null */
    public $depth = 0.6;

    public ?string $qrDataFront = null;

    public ?string $qrDataBack = null;

    public string $qrEc = 'H';

    public bool $nfc = false;

    /** @var int|string */
    public $tagDiameter = 25;

    /** @var float|int|string|null */
    public $tagThickness = 0.80;

    public string $nozzle = '0.4';

    /** @var float|int|string|null */
    public $layerHeight = null;

    public string $printer = 'a1mini';

    public string $material = 'pla-matte';

    /** @var int|string */
    public $plate = 1;

    /** @var float|int|string|null */
    public $xyComp = 0.0;

    // ---- Logo ----------------------------------------------------------

    public ?int $logoAssetId = null;

    public ?string $logoPreviewUrl = null;

    /** @var UploadedFile|null */
    public $logoUpload = null;

    // ---- UX flags (entangled) ------------------------------------------

    /** The user typed the size by hand: never overwrite it (spec §5.2). */
    public bool $sizeTouched = false;

    /** The user touched depth: do not propose the inlay default over it. */
    public bool $depthTouched = false;

    // ---- Live validation output ----------------------------------------

    /** @var array<string, list<string>> non-blocking issues (spec §5.2) */
    public array $liveIssues = [];

    /** @var list<string> advisories that never block (contract 02) */
    public array $liveWarnings = [];

    /** Pending size adjustment proposal (never applied silently). */
    public ?float $proposedSize = null;

    public ?string $proposedSizeReason = null;

    /** Shape seen at the previous live validation (flow rule 4). */
    public ?string $lastValidatedShape = null;

    // ---------------------------------------------------------------------

    public function mount(): void
    {
        $duplicateId = request()->integer('duplica');

        if ($duplicateId > 0 && $this->fillFromExisting($duplicateId)) {
            return;
        }

        $this->applyPreset(Preset::tryFrom($this->preset) ?? Preset::MenuTag);

        $prefillQr = request()->string('qr')->toString();

        if ($prefillQr !== '' && str_contains($this->front, 'qr')) {
            $this->qrDataFront = $prefillQr;
        }
    }

    #[On('preset-selected')]
    public function onPresetSelected(string $preset): void
    {
        $presetEnum = Preset::tryFrom($preset);

        if ($presetEnum === null) {
            return;
        }

        $this->applyPreset($presetEnum);

        // Server-side mutation → the viewer must be resynced (contract 04).
        $this->dispatch('menutag-updated', params: $this->previewParams());
    }

    /** "Personalizza questo formato" — the ONLY way into parametric mode. */
    public function unlockCustomization(): void
    {
        $this->customized = true;
    }

    #[On('logo-uploaded')]
    public function onLogoUploaded(int $logoAssetId, string $previewUrl): void
    {
        $this->logoAssetId = $logoAssetId;
        $this->logoPreviewUrl = $previewUrl;
        $this->dispatch('menutag-updated', params: $this->previewParams());
    }

    public function updatedLogoUpload(): void
    {
        $this->validate(
            $this->logoUploadRules('logoUpload'),
            $this->logoUploadMessages('logoUpload'),
            ['logoUpload' => 'logo'],
        );

        if ($this->logoUpload !== null) {
            $this->storeLogoUpload($this->logoUpload);
            $this->reset('logoUpload');
        }
    }

    public function removeLogo(): void
    {
        $this->logoAssetId = null;
        $this->logoPreviewUrl = null;
        $this->dispatch('menutag-updated', params: $this->previewParams());
    }

    /**
     * Debounced, NON-blocking live validation (spec §5.2): recomputes the
     * dynamic QR size floor, then runs the same three levels as the Form
     * Request (field rules, preset rules, DTO invariants V1..V12) collecting
     * messages instead of failing. Called by Alpine ~800 ms after the last
     * parameter change — the preview itself never waits for the server.
     */
    public function runLiveValidation(): void
    {
        $this->syncDynamicSizeFloor();

        $parameters = $this->parametersArray();
        $this->liveIssues = $this->collectIssues($parameters);
        $this->liveWarnings = $this->collectWarnings($parameters);
    }

    /**
     * The user accepted the proposed size adjustment: applied HERE and only
     * here for manual sizes — never silently (spec §5.2), always through the
     * `size-adjusted` event.
     */
    public function acceptProposedSize(): void
    {
        if ($this->proposedSize === null) {
            return;
        }

        $this->adjustSize($this->proposedSize, $this->proposedSizeReason ?? 'Dimensione adeguata al codice QR.');
        $this->proposedSize = null;
        $this->proposedSizeReason = null;
        $this->liveIssues = $this->collectIssues($this->parametersArray());
        $this->liveWarnings = $this->collectWarnings($this->parametersArray());
    }

    /**
     * Submit: blocking validation (same rules as StoreMenuTagRequest), guest
     * quota, record creation with status `queued`, job dispatch, then the
     * `menutag-queued` event (L+B) that starts the JobStatus polling.
     */
    public function submit(): void
    {
        $this->resetErrorBag();
        $this->syncDynamicSizeFloor();

        if ($this->proposedSize !== null) {
            $this->addError('submit', sprintf(
                'La dimensione attuale non basta per il codice QR: adeguala a %s mm (proposta qui sopra) oppure accorcia l\'URL.',
                $this->formatMm($this->proposedSize),
            ));

            return;
        }

        $parameters = $this->parametersArray();

        $validator = Validator::make(
            ['preset' => $this->preset, 'label' => $this->label, 'parameters' => $parameters],
            [
                'preset' => ['required', 'string'],
                'label' => ['nullable', 'string', 'max:255'],
                ...StoreMenuTagRequest::parameterRules(),
            ],
            StoreMenuTagRequest::validationMessages(),
            StoreMenuTagRequest::attributeNames(),
        );

        try {
            $validator->validate();
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());

            return;
        }

        foreach (StoreMenuTagRequest::presetErrors($this->presetEnum(), $parameters) as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        try {
            $dto = MenuTagParameters::fromArray($parameters);
        } catch (InvalidMenuTagParameters $exception) {
            foreach ($exception->errors as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError('parameters.'.$field, $message);
                }
            }

            return;
        }

        if (! $this->passesGenerationQuota()) {
            return;
        }

        $menuTag = MenuTag::create([
            'user_id' => Auth::id(),
            'guest_token' => Auth::check() ? null : EnsureGuestToken::token(),
            'label' => $this->label !== '' ? $this->label : null,
            'preset' => $this->presetEnum() ?? Preset::MenuTag,
            'customized' => $this->customized,
            'logo_asset_id' => $this->logoAssetId,
            'parameters' => $dto,
            'status' => MenuTagStatus::Queued,
        ]);

        $this->dispatchGenerationJob($menuTag);

        // L+B (contract 04): JobStatus (#[On]) starts polling, the browser
        // listeners can react too.
        $this->dispatch('menutag-queued', menuTagId: $menuTag->id);
    }

    public function render(): View
    {
        return view('livewire.configurator', [
            'presetConfig' => (array) config('product.presets.'.$this->preset),
            'presetLabel' => $this->presetLabel(),
            'rejectedModes' => (array) config('product.presets.'.$this->preset.'.rejected_modes', []),
            'allowedNfcTags' => (array) config('product.presets.'.$this->preset.'.nfc_tag_diameters', [22, 25]),
            'plateSuggested' => (int) config('product.presets.'.$this->preset.'.plate_suggested', 1),
            'recommendedMode' => (string) config('product.presets.'.$this->preset.'.recommended_mode', 'relief'),
        ]);
    }

    // ---------------------------------------------------------------------

    /**
     * PreviewParams (contract 04) for browser events toward the viewer —
     * emitted only on server-side mutations (preset change, size adjustment,
     * logo changes); every other update flows client-side through Alpine.
     *
     * @return array<string, mixed>
     */
    public function previewParams(): array
    {
        return [
            'shape' => $this->shape,
            'size' => $this->toFloat($this->size) ?? 0.0,
            'fillet' => $this->toFloat($this->fillet) ?? 0.0,
            'thickness' => $this->toFloat($this->thickness) ?? 0.0,
            'baseProfile' => $this->baseProfile,
            'rimWidth' => $this->toFloat($this->rimWidth) ?? 0.0,
            'recessDepth' => $this->toFloat($this->recessDepth) ?? 0.0,
            'front' => $this->front,
            'back' => $this->back,
            'mode' => $this->mode,
            'depth' => $this->toFloat($this->depth) ?? 0.0,
            'qrDataFront' => $this->qrDataFront ?: null,
            'qrDataBack' => $this->qrDataBack ?: null,
            'qrEc' => $this->qrEc,
            'nfc' => $this->nfc,
            'tagDiameter' => (int) $this->tagDiameter,
            'logoPreviewUrl' => $this->logoPreviewUrl,
        ];
    }

    private function applyPreset(Preset $preset): void
    {
        $this->preset = $preset->value;
        $this->customized = false;
        $this->sizeTouched = false;
        $this->depthTouched = false;
        $this->proposedSize = null;
        $this->proposedSizeReason = null;
        $this->liveIssues = [];
        $this->liveWarnings = [];
        $this->lastValidatedShape = null;
        $this->resetErrorBag();

        foreach (self::presetDefaults($preset) as $property => $value) {
            $this->{$property} = $value;
        }
    }

    private function fillFromExisting(int $menuTagId): bool
    {
        $menuTag = MenuTag::find($menuTagId);

        if ($menuTag === null) {
            return false;
        }

        Gate::authorize('view', $menuTag);

        $this->applyPreset($menuTag->preset);
        $this->customized = true; // duplication exists to tweak parameters
        $this->label = $menuTag->label !== null ? $menuTag->label.' (copia)' : null;

        $parameters = $menuTag->parameters;

        $this->shape = $parameters->shape->value;
        $this->size = $parameters->size;
        $this->fillet = $parameters->fillet;
        $this->thickness = $parameters->thickness;
        $this->baseProfile = $parameters->baseProfile->value;
        $this->rimWidth = $parameters->rimWidth;
        $this->recessDepth = $parameters->recessDepth;
        $this->front = $parameters->front->value;
        $this->back = $parameters->back->value;
        $this->mode = $parameters->mode->value;
        $this->depth = $parameters->depth;
        $this->qrDataFront = $parameters->qrDataFront;
        $this->qrDataBack = $parameters->qrDataBack;
        $this->qrEc = $parameters->qrEc->value;
        $this->nfc = $parameters->nfc;
        $this->tagDiameter = $parameters->tagDiameter->value;
        $this->tagThickness = $parameters->tagThickness;
        $this->nozzle = $parameters->nozzle->value;
        $this->layerHeight = $parameters->layerHeight;
        $this->printer = $parameters->printer;
        $this->material = $parameters->material->value;
        $this->plate = $parameters->plate;
        $this->xyComp = $parameters->xyComp;
        $this->depthTouched = true;
        $this->sizeTouched = true;

        if ($parameters->logoAssetId !== null && $menuTag->logoAsset !== null) {
            $this->logoAssetId = $parameters->logoAssetId;
            $this->logoPreviewUrl = $this->logoPreviewUrl($menuTag->logoAsset);
        }

        return true;
    }

    /**
     * Flow rules 3 and 4 of contract 04: recompute the QR floor for the
     * current URL(s) and shape. Below the floor the size is adjusted upward
     * with an explicit `size-adjusted` event — or, when the user set the
     * size by hand, a PROPOSAL is stored instead: never silent, never
     * overwriting a manual size.
     */
    private function syncDynamicSizeFloor(): void
    {
        $this->proposedSize = null;
        $this->proposedSizeReason = null;

        $shapeChanged = $this->lastValidatedShape !== null && $this->lastValidatedShape !== $this->shape;
        $this->lastValidatedShape = $this->shape;

        $required = $this->requiredQrSize();

        if ($required === null) {
            return;
        }

        $size = $this->toFloat($this->size) ?? 0.0;

        if ($size + self::EPS >= $required) {
            return;
        }

        $dimensionWord = $this->shape === 'square' ? 'lato' : 'diametro';

        if ($shapeChanged && $this->shape === 'circle') {
            $reason = sprintf(
                'Passando al cerchio il simbolo QR va inscritto sulla diagonale: la soglia sale a %s mm (dal pavimento quadrato di %s mm) e, a parità di ingombro, il modulo si riduce di circa il 35%%. Con l\'indirizzo inserito servono almeno %s mm di diametro.',
                $this->formatMm((float) config('product.qr.floor_circle_mm')),
                $this->formatMm((float) config('product.qr.floor_square_mm')),
                $this->formatMm($required),
            );
        } else {
            $version = $this->currentQrVersion();
            $reason = sprintf(
                'L\'indirizzo inserito richiede un QR versione %s: la dimensione minima è %s mm di %s. Un URL più corto (o un redirect breve) mantiene il formato base.',
                $version !== null ? (string) $version : '>6',
                $this->formatMm($required),
                $dimensionWord,
            );
        }

        if ($this->sizeTouched) {
            // Manual size: signal the incompatibility and PROPOSE (§5.2).
            $this->proposedSize = $required;
            $this->proposedSizeReason = $reason;

            return;
        }

        $this->adjustSize($required, $reason);
    }

    private function adjustSize(float $newSize, string $reason): void
    {
        $oldSize = $this->toFloat($this->size) ?? 0.0;
        $this->size = $newSize;
        $this->sizeTouched = false;

        // Explicit, never silent (contract 04): toast + viewer resync.
        $this->dispatch('size-adjusted', oldSize: $oldSize, newSize: $newSize, reason: $reason);
        $this->dispatch('menutag-updated', params: $this->previewParams());
    }

    /**
     * Largest QR minimum size across the active QR faces, on the current
     * shape — same table and formulas as PHP DTO / JS / engine (parity).
     */
    private function requiredQrSize(): ?float
    {
        $shape = Shape::tryFrom($this->shape) ?? Shape::Square;
        $ec = QrEcLevel::tryFrom($this->qrEc) ?? QrEcLevel::H;
        $required = null;

        foreach ($this->activeQrPayloads() as $payload) {
            $min = MenuTagParameters::minSizeForQr($payload, $ec, $shape);

            if ($min !== null) {
                $required = max($required ?? 0.0, $min);
            }
        }

        return $required;
    }

    private function currentQrVersion(): ?int
    {
        $ec = QrEcLevel::tryFrom($this->qrEc) ?? QrEcLevel::H;
        $version = null;

        foreach ($this->activeQrPayloads() as $payload) {
            $candidate = MenuTagParameters::minQrVersion($payload, $ec);

            if ($candidate !== null) {
                $version = max($version ?? 0, $candidate);
            }
        }

        return $version;
    }

    /**
     * @return list<string>
     */
    private function activeQrPayloads(): array
    {
        $payloads = [];

        if (str_contains($this->front, 'qr') && ($this->qrDataFront ?? '') !== '') {
            $payloads[] = (string) $this->qrDataFront;
        }

        if (str_contains($this->back, 'qr') && ($this->qrDataBack ?? '') !== '') {
            $payloads[] = (string) $this->qrDataBack;
        }

        return $payloads;
    }

    /**
     * Snake_case parameters for validation / DTO / persistence, normalized
     * with the SAME preset rules as the Form Request (contract 02).
     *
     * @return array<string, mixed>
     */
    private function parametersArray(): array
    {
        $parameters = [
            'shape' => $this->shape,
            'size' => $this->toFloat($this->size),
            'fillet' => $this->shape === 'square' ? $this->toFloat($this->fillet) : 0.0,
            'thickness' => $this->toFloat($this->thickness),
            'base_profile' => $this->baseProfile,
            'rim_width' => $this->toFloat($this->rimWidth),
            'recess_depth' => $this->toFloat($this->recessDepth),
            'front' => $this->front,
            'back' => $this->back,
            'mode' => $this->mode,
            'depth' => $this->toFloat($this->depth),
            'logo_asset_id' => $this->logoAssetId,
            'qr_data_front' => str_contains($this->front, 'qr') ? ($this->qrDataFront ?: null) : null,
            'qr_data_back' => str_contains($this->back, 'qr') ? ($this->qrDataBack ?: null) : null,
            'qr_ec' => $this->qrEc,
            'nfc' => $this->nfc,
            'tag_diameter' => (int) $this->tagDiameter,
            'tag_thickness' => $this->toFloat($this->tagThickness),
            'nozzle' => $this->nozzle,
            'layer_height' => $this->toFloat($this->layerHeight),
            'printer' => $this->printer,
            'material' => $this->material,
            'plate' => max(1, (int) $this->plate),
            'xy_comp' => $this->toFloat($this->xyComp) ?? 0.0,
        ];

        return StoreMenuTagRequest::normalizeParameters($parameters, $this->presetEnum());
    }

    /**
     * The three validation levels of contract 02, collected as messages
     * instead of thrown — powers the non-blocking live panel.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, list<string>>
     */
    private function collectIssues(array $parameters): array
    {
        $issues = [];

        $validator = Validator::make(
            ['preset' => $this->preset, 'parameters' => $parameters],
            StoreMenuTagRequest::parameterRules(),
            StoreMenuTagRequest::validationMessages(),
            StoreMenuTagRequest::attributeNames(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                $issues[$field] = array_values($messages);
            }
        }

        foreach (StoreMenuTagRequest::presetErrors($this->presetEnum(), $parameters) as $field => $messages) {
            $issues[$field] = [...($issues[$field] ?? []), ...$messages];
        }

        if ($issues !== []) {
            return $issues;
        }

        try {
            MenuTagParameters::fromArray($parameters);
        } catch (InvalidMenuTagParameters $exception) {
            foreach ($exception->errors as $field => $messages) {
                $issues['parameters.'.$field] = $messages;
            }
        }

        return $issues;
    }

    /**
     * Advisories that never block (contract 02): custom + rimmed + engrave is
     * warned against — grooves inside the drip recess hold liquid — while the
     * Coaster preset outright rejects it via presetErrors().
     *
     * @param  array<string, mixed>  $parameters
     * @return list<string>
     */
    private function collectWarnings(array $parameters): array
    {
        $warnings = [];

        if (($parameters['base_profile'] ?? null) === 'rimmed'
            && ($parameters['mode'] ?? null) === 'engrave'
            && $this->presetEnum() !== Preset::Coaster) {
            $warnings[] = 'Con il bordo antigoccia la grafica incisa trattiene il liquido '
                .'nelle scanalature e si asciuga male: su una superficie che raccoglie '
                .'condensa conviene il rilievo o l\'intarsio a filo (più igienici).';
        }

        return $warnings;
    }

    /**
     * Guest quota (spec §5.1): same key convention and config caps as the
     * named limiter in AppServiceProvider, so web UI and throttled routes
     * share the counter. The quota is COMMUNICATED when it triggers.
     */
    private function passesGenerationQuota(): bool
    {
        if (Auth::check()) {
            $max = (int) config('product.api.generations_per_hour');
            $key = 'generate|user|'.Auth::id();
            $message = 'Hai raggiunto il limite di %d generazioni all\'ora per questo account. Riprova fra %s.';
        } else {
            $max = (int) config('product.guests.generations_per_hour');
            $key = 'generate|ip|'.request()->ip();
            $message = 'Come ospite puoi avviare al massimo %d generazioni all\'ora. Riprova fra %s, oppure registrati per continuare a lavorare.';
        }

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
            $this->addError('submit', sprintf(
                $message,
                $max,
                $minutes === 1 ? '1 minuto' : $minutes.' minuti',
            ));

            return false;
        }

        RateLimiter::hit($key, 3600);

        return true;
    }

    private function dispatchGenerationJob(MenuTag $menuTag): void
    {
        GenerateMenuTagJob::dispatch($menuTag->id);
    }

    private function presetEnum(): ?Preset
    {
        return Preset::tryFrom($this->preset);
    }

    public function presetLabel(): string
    {
        return match ($this->presetEnum()) {
            Preset::Coaster => 'Coaster',
            Preset::CoinCart => 'Coin Cart',
            default => 'MenuTag',
        };
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function formatMm(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return str_contains($formatted, '.') ? $formatted : $formatted.'.0';
    }
}
