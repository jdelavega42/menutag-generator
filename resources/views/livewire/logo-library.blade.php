{{-- Reusable logo library for resellers (spec §5.5). --}}
<section aria-label="Libreria loghi">
    <flux:heading size="lg" level="2">Libreria loghi</flux:heading>
    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
        PNG monocromatici ad alto contrasto o SVG vettoriali, massimo
        {{ number_format((int) config('product.guests.upload_max_kb') / 1024, 0) }} MB.
        Riutilizzabili su ogni targhetta.
    </p>

    <div class="mt-3">
        <label class="inline-block cursor-pointer rounded-lg border border-dashed border-zinc-400 px-4 py-2 text-sm hover:border-zinc-600 dark:border-zinc-500">
            <input type="file" wire:model="upload" accept=".png,.svg,image/png,image/svg+xml" class="hidden" />
            Carica un logo…
        </label>
        <span wire:loading wire:target="upload" class="ms-2 text-xs text-zinc-500">Caricamento…</span>
        @error('upload')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    @if ($logos->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
            Nessun logo in libreria.
        </p>
    @else
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($logos as $logo)
                <li wire:key="logo-{{ $logo['id'] }}" class="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-900">
                    <img src="{{ $logo['preview'] }}" alt="{{ $logo['name'] }}" class="mx-auto h-20 w-full rounded bg-white object-contain p-1" />
                    <p class="mt-2 truncate text-xs font-medium" title="{{ $logo['name'] }}">{{ $logo['name'] }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $logo['mime'] === 'image/svg+xml' ? 'SVG' : 'PNG' }} · {{ $logo['size_kb'] }} kB
                    </p>
                    <flux:button
                        wire:click="delete({{ $logo['id'] }})"
                        wire:confirm="Eliminare questo logo dalla libreria?"
                        variant="ghost" size="xs" class="mt-1"
                    >
                        Elimina
                    </flux:button>
                </li>
            @endforeach
        </ul>
    @endif
</section>
