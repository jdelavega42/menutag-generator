<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\Preset;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Entry point of the configurator (contract 04): the choice between the
 * THREE presets, with MenuTag preselected. The parametric generator is NOT a
 * fourth card — it is only reachable as "personalizza questo formato" inside
 * the Configurator. Selecting a card dispatches `preset-selected` (Livewire
 * event, scoped to the Configurator).
 */
class PresetPicker extends Component
{
    /** MenuTag preselected on entry (spec §6 WS-4). */
    public string $selected = 'menutag';

    public function select(string $preset): void
    {
        $presetEnum = Preset::tryFrom($preset);

        if ($presetEnum === null) {
            return;
        }

        $this->selected = $presetEnum->value;

        $this->dispatch('preset-selected', preset: $presetEnum->value)->to(Configurator::class);
    }

    public function render(): View
    {
        return view('livewire.preset-picker', [
            'cards' => self::cards(),
        ]);
    }

    /**
     * Card copy (Italian, user-facing) with the binding numbers read from
     * config/product.php — never re-declared.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function cards(): array
    {
        /** @var array<string, array<string, mixed>> $presets */
        $presets = (array) config('product.presets');

        return [
            'menutag' => [
                'title' => 'MenuTag',
                'badge' => 'Il prodotto principale',
                'tagline' => 'Sottobicchiere e accesso al menù digitale, in un solo oggetto.',
                'description' => 'Quadrato con QR inciso e tag NFC opzionale: appoggiato sotto il bicchiere resta sempre sul tavolo, senza espositori. Il cliente inquadra o avvicina il telefono e apre il menù.',
                'specs' => [
                    sprintf('Lato %s mm (calcolato sull\'URL)', self::formatNumber((float) data_get($presets, 'menutag.defaults.size', 58.8))),
                    sprintf('Spessore %s mm', self::formatNumber((float) data_get($presets, 'menutag.defaults.thickness', 3.0))),
                    'QR + logo opzionale · NFC Ø22/Ø25',
                    'PLA matte',
                ],
                'plate' => (int) data_get($presets, 'menutag.plate_suggested', 4),
            ],
            'coaster' => [
                'title' => 'Coaster',
                'badge' => 'Sottobicchiere brandizzato',
                'tagline' => 'Bordo antigoccia, logo in vista, lavabile in lavastoviglie.',
                'description' => 'Tondo con incavo che trattiene la condensa del bicchiere. In PETG perché va lavato: si vende in coppia con il MenuTag allo stesso locale, senza cannibalizzarlo.',
                'specs' => [
                    sprintf('Ø %s mm', self::formatNumber((float) data_get($presets, 'coaster.defaults.size', 85.0))),
                    sprintf('Spessore %s mm · bordo antigoccia', self::formatNumber((float) data_get($presets, 'coaster.defaults.thickness', 4.0))),
                    'Solo logo · NFC Ø22/Ø25',
                    'PETG (lavastoviglie)',
                ],
                'plate' => (int) data_get($presets, 'coaster.plate_suggested', 4),
            ],
            'coin_cart' => [
                'title' => 'Coin Cart',
                'badge' => 'Linea promozionale',
                'tagline' => 'Gettone da carrello e portachiavi, formato moneta da 2 €.',
                'description' => 'Tondo da 25.75 mm con logo in rilievo e NFC Ø22 opzionale: il gadget che resta nel portafoglio del cliente. Linea distinta dal sottobicchiere, per GDO e promozionale.',
                'specs' => [
                    sprintf('Ø %s mm (moneta da 2 €)', self::formatNumber((float) data_get($presets, 'coin_cart.defaults.size', 25.75))),
                    sprintf('Spessore %s mm', self::formatNumber((float) data_get($presets, 'coin_cart.defaults.thickness', 2.20))),
                    'Solo logo · NFC Ø22',
                    'PLA matte · compensazione XY di serie',
                ],
                'plate' => (int) data_get($presets, 'coin_cart.plate_suggested', 25),
            ],
        ];
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
