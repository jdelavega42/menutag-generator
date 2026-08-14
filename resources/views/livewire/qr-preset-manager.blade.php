{{-- Saved QR contents («Menù EN», «Recensioni») — spec §5.5, restyled on the tokens. --}}
<section aria-label="QR salvati" class="rounded-panel border border-border-subtle bg-surface-1 p-5">
    <h2 class="text-lg font-semibold text-text-primary">QR salvati</h2>
    <p class="mt-1 text-xs text-text-muted">
        Indirizzi con etichetta, riutilizzabili su ogni targhetta: «Usa» apre il configuratore già compilato.
    </p>

    <form wire:submit="save" class="mt-3 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium text-text-secondary" for="qr-preset-name">Nome</label>
            <input
                id="qr-preset-name"
                type="text"
                wire:model="name"
                placeholder="Menù EN"
                class="mt-1.5 rounded-control border border-border-strong bg-surface-2 px-3 py-2 text-base text-text-primary placeholder:text-text-muted"
            />
        </div>
        <div class="min-w-64 flex-1">
            <label class="block text-sm font-medium text-text-secondary" for="qr-preset-data">Indirizzo</label>
            <input
                id="qr-preset-data"
                type="url"
                inputmode="url"
                spellcheck="false"
                wire:model="data"
                placeholder="https://esempio.it/menu-en"
                class="mt-1.5 w-full rounded-control border border-border-strong bg-surface-2 px-3 py-2 font-mono text-base text-text-primary placeholder:text-text-muted"
            />
        </div>
        <flux:button type="submit" variant="primary" size="sm">Salva</flux:button>
    </form>
    @error('name')
        <p class="mt-1.5 text-xs text-blocked">{{ $message }}</p>
    @enderror
    @error('data')
        <p class="mt-1.5 text-xs text-blocked">{{ $message }}</p>
    @enderror

    @if ($presets->isEmpty())
        <p class="mt-4 rounded-card border border-dashed border-border-strong p-6 text-sm text-text-secondary">
            Nessun QR salvato.
        </p>
    @else
        <ul class="mt-4 divide-y divide-border-subtle rounded-card border border-border-subtle bg-surface-2">
            @foreach ($presets as $preset)
                <li wire:key="qr-preset-{{ $preset->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 transition-colors duration-[var(--t-micro)] hover:bg-surface-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-text-primary">{{ $preset->name }}</p>
                        <p class="truncate font-mono text-xs text-text-muted" title="{{ $preset->data }}">{{ $preset->data }}</p>
                    </div>
                    <div class="flex gap-1">
                        <flux:button wire:click="apply({{ $preset->id }})" variant="ghost" size="xs">Usa</flux:button>
                        <flux:button
                            wire:click="delete({{ $preset->id }})"
                            wire:confirm="Eliminare questo QR salvato?"
                            variant="ghost" size="xs"
                        >
                            Elimina
                        </flux:button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
