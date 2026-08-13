<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\QrPreset;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Saved QR contents for authenticated users (contract 04 / spec §5.5):
 * labelled URLs («Menù EN», «Recensioni») reusable across generations.
 * "Usa" prefills the configurator through the home route's ?qr= parameter.
 */
class QrPresetManager extends Component
{
    public string $name = '';

    public string $data = '';

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $this->validate(
            [
                'name' => [
                    'required', 'string', 'max:255',
                    Rule::unique('qr_presets', 'name')->where('user_id', $user->id),
                ],
                'data' => ['required', 'string', 'url:http,https', 'max:1000'],
            ],
            [
                'name.required' => 'Dai un nome al QR salvato, ad esempio «Menù EN».',
                'name.unique' => 'Hai già un QR salvato con questo nome: scegline un altro.',
                'name.max' => 'Il nome non può superare i :max caratteri.',
                'data.required' => 'Inserisci l\'indirizzo da salvare.',
                'data.url' => 'L\'indirizzo non è un URL valido: usa un indirizzo completo, ad esempio https://esempio.it/menu.',
                'data.max' => 'L\'indirizzo è troppo lungo: accorcialo o usa un redirect breve.',
            ],
            ['name' => 'nome', 'data' => 'indirizzo'],
        );

        QrPreset::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'data' => $validated['data'],
        ]);

        $this->reset('name', 'data');
    }

    public function delete(int $qrPresetId): void
    {
        $preset = QrPreset::findOrFail($qrPresetId);

        Gate::authorize('delete', $preset);

        $preset->delete();
    }

    public function apply(int $qrPresetId): void
    {
        $preset = QrPreset::findOrFail($qrPresetId);

        Gate::authorize('view', $preset);

        $this->redirect(route('home', ['qr' => $preset->data]));
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.qr-preset-manager', [
            'presets' => QrPreset::query()
                ->where('user_id', $user->id)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
