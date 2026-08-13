<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\InvalidMenuTagParameters;
use App\DTOs\MenuTagParameters;
use App\Enums\BaseProfile;
use App\Enums\FaceContent;
use App\Enums\Material;
use App\Enums\Nozzle;
use App\Enums\Preset;
use App\Enums\QrEcLevel;
use App\Enums\RenderMode;
use App\Enums\Shape;
use App\Enums\TagDiameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates POST /menu-tags input (openapi CreateMenuTagRequest): types, raw
 * ranges and PRESET rules, with Italian messages that explain how to get
 * back within limits (contract 02, level 1 of 3).
 *
 * The full V1..V12 invariants run in the after() hook by constructing the
 * MenuTagParameters DTO: same formulas, same config constants, zero
 * duplication — the DTO exception's per-field messages become 422 errors on
 * `parameters.<field>` (e.g. V5 reports the minimum size computed on the
 * actual URL and shape).
 *
 * Livewire components reuse the same logic through the static methods:
 * parameterRules(), validationMessages(), attributeNames(),
 * normalizeParameters() and presetErrors().
 */
class StoreMenuTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guests may generate too; ownership checks belong to the Policies (WS-5).
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parameters = $this->input('parameters');

        if (is_array($parameters)) {
            $this->merge([
                'parameters' => self::normalizeParameters($parameters, $this->presetEnum()),
            ]);
        }
    }

    /**
     * Preset-driven normalizations (contract 02 / decisions §4), applied
     * BEFORE validation so the stored snapshot is already coherent:
     * - a qr_logo face forces EC to H (the UI declares it to the user);
     * - switching to inlay without having touched depth proposes the
     *   contained default (product.inlay.default_depth_mm) to keep the
     *   bichromatic layer count low;
     * - a numeric nozzle is canonicalized to the exact CLI string ('0.4').
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public static function normalizeParameters(array $parameters, ?Preset $preset = null): array
    {
        if (isset($parameters['nozzle']) && is_numeric($parameters['nozzle']) && ! is_string($parameters['nozzle'])) {
            $parameters['nozzle'] = number_format((float) $parameters['nozzle'], 1, '.', '');
        }

        $faces = [
            $parameters['front'] ?? FaceContent::None->value,
            $parameters['back'] ?? FaceContent::None->value,
        ];

        if (in_array(FaceContent::QrLogo->value, $faces, true)) {
            $parameters['qr_ec'] = QrEcLevel::H->value;
        }

        if (
            ($parameters['mode'] ?? null) === RenderMode::Inlay->value
            && ! array_key_exists('depth', $parameters)
        ) {
            $parameters['depth'] = config('product.inlay.default_depth_mm');
        }

        return $parameters;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'preset' => ['required', Rule::in(self::enumValues(Preset::class))],
            'label' => ['nullable', 'string', 'max:255'],
            ...self::parameterRules(),
        ];
    }

    /**
     * Field-level rules for the `parameters` object, every bound read from
     * config/product.php / config/printers.php. Reusable as-is by the
     * Livewire Configurator (contract 02: same constants at every level).
     *
     * @return array<string, list<mixed>>
     */
    public static function parameterRules(): array
    {
        $product = config('product');
        [$xyMin, $xyMax] = $product['xy_comp_range_mm'];
        [$tagMin, $tagMax] = $product['nfc']['tag_thickness_range_mm'];

        return [
            'parameters' => ['required', 'array'],
            'parameters.shape' => ['required', Rule::in(self::enumValues(Shape::class))],
            'parameters.size' => [
                'required', 'numeric',
                'min:'.$product['size_min_mm'],
                'max:'.$product['size_max_mm'],
            ],
            'parameters.fillet' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters.thickness' => [
                'sometimes', 'nullable', 'numeric',
                'min:'.$product['thickness_min_mm'],
                'max:'.$product['thickness_max_mm'],
            ],
            'parameters.base_profile' => ['sometimes', 'nullable', Rule::in(self::enumValues(BaseProfile::class))],
            'parameters.rim_width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters.recess_depth' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters.front' => ['sometimes', 'nullable', Rule::in(self::enumValues(FaceContent::class))],
            'parameters.back' => ['sometimes', 'nullable', Rule::in(self::enumValues(FaceContent::class))],
            'parameters.mode' => ['sometimes', 'nullable', Rule::in(self::enumValues(RenderMode::class))],
            'parameters.depth' => [
                'sometimes', 'nullable', 'numeric',
                'min:'.$product['graphics']['depth_min_mm'],
                'max:'.$product['graphics']['depth_max_mm'],
            ],
            'parameters.margin' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'parameters.logo_asset_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'parameters.logo_rotate' => ['sometimes', 'nullable', 'numeric'],
            'parameters.qr_data_front' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:1000'],
            'parameters.qr_data_back' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:1000'],
            'parameters.qr_ec' => ['sometimes', 'nullable', Rule::in(self::enumValues(QrEcLevel::class))],
            'parameters.nfc' => ['sometimes', 'nullable', 'boolean'],
            'parameters.tag_diameter' => ['sometimes', 'nullable', 'integer', Rule::in(self::enumValues(TagDiameter::class))],
            'parameters.tag_thickness' => ['sometimes', 'nullable', 'numeric', 'min:'.$tagMin, 'max:'.$tagMax],
            'parameters.nozzle' => ['sometimes', 'nullable', Rule::in(self::enumValues(Nozzle::class))],
            'parameters.layer_height' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parameters.printer' => ['sometimes', 'nullable', Rule::in(array_keys((array) config('printers.profiles')))],
            'parameters.material' => ['sometimes', 'nullable', Rule::in(self::enumValues(Material::class))],
            'parameters.plate' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.$product['plate']['max_pieces']],
            'parameters.xy_comp' => ['sometimes', 'nullable', 'numeric', 'min:'.$xyMin, 'max:'.$xyMax],
        ];
    }

    /**
     * PRESET rules (contract 02 "fuori dal DTO"), config-driven from
     * product.presets.*: rejected render modes (engrave on the Coaster) and
     * allowed NFC tag diameters (Ø22 only on the Coin Cart). Returns
     * validation-error-shaped messages keyed by `parameters.<field>`.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, list<string>>
     */
    public static function presetErrors(?Preset $preset, array $parameters): array
    {
        if ($preset === null) {
            return [];
        }

        $errors = [];
        $presetConfig = (array) config('product.presets.'.$preset->value);
        $label = self::presetLabel($preset);

        $mode = $parameters['mode'] ?? null;
        $rejectedModes = (array) ($presetConfig['rejected_modes'] ?? []);

        if ($mode !== null && in_array($mode, $rejectedModes, true)) {
            $errors['parameters.mode'][] = $preset === Preset::Coaster && $mode === RenderMode::Engrave->value
                ? 'Il preset Coaster raccoglie liquido: la grafica incisa (engrave) trattiene la condensa nelle scanalature ed è un problema di igiene, non di estetica. Scegli il rilievo (relief) oppure l’intarsio a filo bicolore (inlay).'
                : sprintf('La resa "%s" non è disponibile sul preset %s: scegli una delle rese consentite.', (string) $mode, $label);
        }

        $nfc = filter_var($parameters['nfc'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        $allowedTags = (array) ($presetConfig['nfc_tag_diameters'] ?? []);
        $tag = isset($parameters['tag_diameter']) && is_numeric($parameters['tag_diameter'])
            ? (int) $parameters['tag_diameter']
            : TagDiameter::D25->value;

        if ($nfc && $allowedTags !== [] && ! in_array($tag, $allowedTags, true)) {
            $errors['parameters.tag_diameter'][] = sprintf(
                'Il preset %s supporta solo il tag NFC %s: alla dimensione di questo formato un tag più grande non lascerebbe la parete minima. Scegli uno di questi diametri.',
                $label,
                implode(' / ', array_map(static fn (int $d): string => 'Ø'.$d, $allowedTags)),
            );
        }

        return $errors;
    }

    /**
     * Level-2 validation (contract 02): construct the DTO so every V1..V12
     * invariant runs with the same formulas and constants, and surface its
     * per-field Italian messages as 422 errors.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var array<string, mixed> $parameters */
                $parameters = (array) $this->input('parameters', []);

                foreach (self::presetErrors($this->presetEnum(), $parameters) as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                try {
                    MenuTagParameters::fromArray($parameters);
                } catch (InvalidMenuTagParameters $exception) {
                    foreach ($exception->errors as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add('parameters.'.$field, $message);
                        }
                    }
                }
            },
        ];
    }

    /**
     * The validated parameters as the domain DTO — safe after validation.
     */
    public function toParameters(): MenuTagParameters
    {
        /** @var array<string, mixed> $parameters */
        $parameters = $this->validated('parameters');

        return MenuTagParameters::fromArray($parameters);
    }

    public function presetEnum(): ?Preset
    {
        return Preset::tryFrom((string) $this->input('preset'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::validationMessages();
    }

    /**
     * Italian messages for the field-level rules; the values referenced by
     * :min / :max come from config through parameterRules(). Reusable by the
     * Livewire components.
     *
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'preset.required' => 'Scegli uno dei tre formati: MenuTag, Coaster o Coin Cart.',
            'preset.in' => 'Formato sconosciuto: scegli fra menutag, coaster e coin_cart.',
            'label.max' => 'Il nome non può superare i :max caratteri: accorcialo.',
            'parameters.required' => 'I parametri della targhetta sono obbligatori.',
            'parameters.array' => 'I parametri devono essere un oggetto di campi.',
            'parameters.shape.required' => 'La forma è obbligatoria: scegli il quadrato ("square") o il cerchio ("circle").',
            'parameters.shape.in' => 'Forma non valida: usa "square" (quadrato) oppure "circle" (cerchio).',
            'parameters.size.required' => 'La dimensione è obbligatoria: indica il lato (quadrato) o il diametro (cerchio) in millimetri.',
            'parameters.size.numeric' => 'La dimensione deve essere un numero in millimetri.',
            'parameters.size.min' => 'La dimensione minima di prodotto è :min mm (la moneta da 2 €): aumenta la dimensione ad almeno :min mm.',
            'parameters.size.max' => 'La dimensione massima è :max mm: riduci la dimensione.',
            'parameters.fillet.numeric' => 'La smussatura degli angoli deve essere un numero in millimetri.',
            'parameters.fillet.min' => 'La smussatura degli angoli non può essere negativa.',
            'parameters.thickness.numeric' => 'Lo spessore deve essere un numero in millimetri.',
            'parameters.thickness.min' => 'Lo spessore minimo di prodotto è :min mm (la moneta da 2 €): aumenta lo spessore ad almeno :min mm.',
            'parameters.thickness.max' => 'Lo spessore massimo è :max mm: riduci lo spessore.',
            'parameters.base_profile.in' => 'Profilo base non valido: usa "flat" (piatto) oppure "rimmed" (bordo antigoccia).',
            'parameters.rim_width.min' => 'La larghezza del bordo non può essere negativa.',
            'parameters.recess_depth.min' => 'La profondità dell’incavo non può essere negativa.',
            'parameters.front.in' => 'Contenuto della faccia frontale non valido: usa none, logo, qr oppure qr_logo.',
            'parameters.back.in' => 'Contenuto della faccia posteriore non valido: usa none, logo, qr oppure qr_logo.',
            'parameters.mode.in' => 'Resa della grafica non valida: usa engrave (inciso), relief (rilievo) oppure inlay (a filo bicolore).',
            'parameters.depth.numeric' => 'La profondità (o altezza) della grafica deve essere un numero in millimetri.',
            'parameters.depth.min' => 'La profondità (o altezza) della grafica deve essere di almeno :min mm: aumentala.',
            'parameters.depth.max' => 'La profondità (o altezza) della grafica non può superare :max mm: riducila.',
            'parameters.margin.min' => 'Il margine non può essere negativo: usa un valore positivo oppure lascialo vuoto per il calcolo automatico.',
            'parameters.logo_asset_id.integer' => 'Il riferimento al logo non è valido: ricarica il logo o selezionane uno dalla libreria.',
            'parameters.logo_asset_id.min' => 'Il riferimento al logo non è valido: ricarica il logo o selezionane uno dalla libreria.',
            'parameters.qr_data_front.url' => 'L’indirizzo del QR frontale non è un URL valido: usa un indirizzo completo, ad esempio https://esempio.it/menu.',
            'parameters.qr_data_front.max' => 'L’indirizzo del QR frontale è troppo lungo: accorcialo o usa un redirect breve.',
            'parameters.qr_data_back.url' => 'L’indirizzo del QR posteriore non è un URL valido: usa un indirizzo completo, ad esempio https://esempio.it/menu.',
            'parameters.qr_data_back.max' => 'L’indirizzo del QR posteriore è troppo lungo: accorcialo o usa un redirect breve.',
            'parameters.qr_ec.in' => 'Livello di correzione d’errore non valido: usa L, M, Q oppure H.',
            'parameters.nfc.boolean' => 'Il campo NFC deve essere vero o falso.',
            'parameters.tag_diameter.in' => 'Diametro del tag NFC non valido: usa 22 oppure 25 (mm).',
            'parameters.tag_diameter.integer' => 'Diametro del tag NFC non valido: usa 22 oppure 25 (mm).',
            'parameters.tag_thickness.min' => 'Lo spessore del tag NFC deve essere di almeno :min mm: misura il tag reale e correggi il valore.',
            'parameters.tag_thickness.max' => 'Lo spessore del tag NFC non può superare :max mm: misura il tag reale e correggi il valore.',
            'parameters.nozzle.in' => 'Ugello non valido: usa "0.2" oppure "0.4".',
            'parameters.layer_height.gt' => 'L’altezza layer deve essere maggiore di zero, oppure lasciala vuota per usare il default del profilo stampante.',
            'parameters.printer.in' => 'Stampante non supportata: al momento il profilo disponibile è a1mini.',
            'parameters.material.in' => 'Materiale non valido: usa "pla-matte" oppure "petg".',
            'parameters.plate.integer' => 'Il numero di pezzi per piastra deve essere un numero intero.',
            'parameters.plate.min' => 'La piastra deve contenere almeno :min pezzo.',
            'parameters.plate.max' => 'La piastra può contenere al massimo :max pezzi: riduci il numero di pezzi.',
            'parameters.xy_comp.min' => 'La compensazione XY non può scendere sotto :min mm per lato: aumenta il valore.',
            'parameters.xy_comp.max' => 'La compensazione XY non può superare :max mm per lato: riduci il valore.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }

    /**
     * Italian display names, reusable by the Livewire components.
     *
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'preset' => 'formato',
            'label' => 'nome',
            'parameters' => 'parametri',
            'parameters.shape' => 'forma',
            'parameters.size' => 'dimensione',
            'parameters.fillet' => 'smussatura angoli',
            'parameters.thickness' => 'spessore',
            'parameters.base_profile' => 'profilo base',
            'parameters.rim_width' => 'larghezza bordo',
            'parameters.recess_depth' => 'profondità incavo',
            'parameters.front' => 'faccia frontale',
            'parameters.back' => 'faccia posteriore',
            'parameters.mode' => 'resa della grafica',
            'parameters.depth' => 'profondità grafica',
            'parameters.margin' => 'margine',
            'parameters.logo_asset_id' => 'logo',
            'parameters.logo_rotate' => 'rotazione logo',
            'parameters.qr_data_front' => 'indirizzo QR frontale',
            'parameters.qr_data_back' => 'indirizzo QR posteriore',
            'parameters.qr_ec' => 'correzione d’errore QR',
            'parameters.nfc' => 'tasca NFC',
            'parameters.tag_diameter' => 'diametro tag NFC',
            'parameters.tag_thickness' => 'spessore tag NFC',
            'parameters.nozzle' => 'ugello',
            'parameters.layer_height' => 'altezza layer',
            'parameters.printer' => 'stampante',
            'parameters.material' => 'materiale',
            'parameters.plate' => 'pezzi per piastra',
            'parameters.xy_comp' => 'compensazione XY',
        ];
    }

    private static function presetLabel(Preset $preset): string
    {
        return match ($preset) {
            Preset::MenuTag => 'MenuTag',
            Preset::Coaster => 'Coaster',
            Preset::CoinCart => 'Coin Cart',
        };
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @return list<int|string>
     */
    private static function enumValues(string $enumClass): array
    {
        return array_map(static fn (\BackedEnum $case): int|string => $case->value, $enumClass::cases());
    }
}
