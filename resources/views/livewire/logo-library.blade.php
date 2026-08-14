{{-- Reusable logo library for resellers (spec §5.5), restyled on the tokens. --}}
<section aria-label="Libreria loghi" class="rounded-panel border border-border-subtle bg-surface-1 p-5">
    <h2 class="text-lg font-semibold text-text-primary">Libreria loghi</h2>
    <p class="mt-1 text-xs text-text-muted">
        PNG monocromatici ad alto contrasto o SVG vettoriali, massimo
        <span class="mono text-xs">{{ number_format((int) config('product.guests.upload_max_kb') / 1024, 0) }} MB</span>.
        Riutilizzabili su ogni targhetta.
    </p>

    <div class="mt-3">
        <label class="inline-block cursor-pointer rounded-control border border-dashed border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition-colors duration-[var(--t-micro)] hover:bg-surface-3 focus-within:outline-2 focus-within:outline-border-strong">
            <input type="file" wire:model="upload" accept=".png,.svg,image/png,image/svg+xml" class="sr-only" />
            Carica un logo…
        </label>
        <span wire:loading wire:target="upload" class="ms-2 text-xs text-text-muted">Caricamento…</span>
        @error('upload')
            <p class="mt-1.5 text-xs text-blocked">{{ $message }}</p>
        @enderror
    </div>

    @if ($logos->isEmpty())
        <p class="mt-4 rounded-card border border-dashed border-border-strong p-6 text-sm text-text-secondary">
            Nessun logo in libreria.
        </p>
    @else
        <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($logos as $logo)
                <li wire:key="logo-{{ $logo['id'] }}" class="rounded-card border border-border-subtle bg-surface-2 p-3 transition-colors duration-[var(--t-micro)] hover:border-border-strong">
                    <img src="{{ $logo['preview'] }}" alt="{{ $logo['name'] }}" class="mx-auto h-20 w-full rounded-control bg-text-primary object-contain p-1" />
                    <p class="mt-2 truncate text-xs font-medium text-text-primary" title="{{ $logo['name'] }}">{{ $logo['name'] }}</p>
                    <p class="text-xs text-text-muted">
                        {{ $logo['mime'] === 'image/svg+xml' ? 'SVG' : 'PNG' }} · <span class="mono text-xs">{{ $logo['size_kb'] }} kB</span>
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
