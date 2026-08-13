{{-- Saved QR contents («Menù EN», «Recensioni») — spec §5.5. --}}
<section aria-label="QR salvati">
    <flux:heading size="lg" level="2">QR salvati</flux:heading>
    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
        Indirizzi con etichetta, riutilizzabili su ogni targhetta: «Usa» apre il configuratore già compilato.
    </p>

    <form wire:submit="save" class="mt-3 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium" for="qr-preset-name">Nome</label>
            <input
                id="qr-preset-name"
                type="text"
                wire:model="name"
                placeholder="Menù EN"
                class="mt-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
            />
        </div>
        <div class="min-w-64 flex-1">
            <label class="block text-sm font-medium" for="qr-preset-data">Indirizzo</label>
            <input
                id="qr-preset-data"
                type="url"
                wire:model="data"
                placeholder="https://esempio.it/menu-en"
                class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
            />
        </div>
        <flux:button type="submit" variant="primary" size="sm">Salva</flux:button>
    </form>
    @error('name')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
    @error('data')
        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($presets->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
            Nessun QR salvato.
        </p>
    @else
        <ul class="mt-4 divide-y divide-zinc-200 rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ($presets as $preset)
                <li wire:key="qr-preset-{{ $preset->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium">{{ $preset->name }}</p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400" title="{{ $preset->data }}">{{ $preset->data }}</p>
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
