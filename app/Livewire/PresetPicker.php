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

    /**
     * Layout hint: stacked single-column cards inside the guest wizard
     * (mockup 01), three-across grid on the registered home.
     */
    public bool $stacked = false;

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
     * Card copy (Italian, user-facing): the non-technical bullets of mockup
     * 01 (Fase 0), with the binding measures read from config/product.php —
     * never re-declared. Two mockup claims were corrected against the real
     * product record: the Coin Cart logo is front-only and the geometry has
     * no keyring hole (restyle §9: no invented claims).
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
                'bullets' => [
                    'Il QR del tuo menù inciso nell\'oggetto: niente adesivi che si staccano',
                    'Sta sul tavolo, si scansiona al primo colpo',
                    'Lo stampi tu o lo porti a un service: la guida è inclusa',
                ],
                'meta' => 'Targhetta quadrata · lato',
                'measure' => sprintf('%s mm', self::formatNumber((float) data_get($presets, 'menutag.defaults.size', 58.8))),
            ],
            'coaster' => [
                'title' => 'Coaster',
                'badge' => 'Sottobicchiere brandizzato',
                'bullets' => [
                    'Il sottobicchiere con il tuo logo al centro, in due colori',
                    'Protegge il tavolo e si fa notare a ogni ordinazione',
                    'Una stampata, più pezzi: la serie per tutti i tavoli',
                ],
                'meta' => 'Disco bicolore, logo al centro · Ø',
                'measure' => sprintf('%s mm', self::formatNumber((float) data_get($presets, 'coaster.defaults.size', 85.0))),
            ],
            'coin_cart' => [
                'title' => 'Coin Cart',
                'badge' => 'Linea promozionale',
                'bullets' => [
                    'Sblocca il carrello della spesa come una moneta',
                    'Il tuo logo in rilievo, sempre in tasca al cliente',
                    'Il gadget che non finisce in un cassetto',
                ],
                'meta' => 'Gettone da moneta da 2 € · Ø',
                'measure' => sprintf('%s mm', self::formatNumber((float) data_get($presets, 'coin_cart.defaults.size', 25.75))),
            ],
        ];
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
