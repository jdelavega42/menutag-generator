{{--
    Entry: the choice between the THREE presets, MenuTag preselected.
    The parametric generator is NOT a fourth card (spec §5.2 / §6 WS-4).
--}}
<section aria-label="Scelta del formato">
    <flux:heading size="lg" level="2">Scegli il formato</flux:heading>
    <flux:text class="mt-1 text-sm">
        Tre prodotti validati. Ogni personalizzazione parte da uno di questi — mai da un modulo vuoto.
    </flux:text>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        @foreach ($cards as $key => $card)
            <button
                type="button"
                wire:key="preset-card-{{ $key }}"
                wire:click="select('{{ $key }}')"
                @class([
                    'group rounded-xl border p-5 text-left transition',
                    'border-accent ring-2 ring-accent bg-white dark:bg-zinc-800' => $selected === $key,
                    'border-zinc-200 bg-white hover:border-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-500' => $selected !== $key,
                ])
                aria-pressed="{{ $selected === $key ? 'true' : 'false' }}"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-lg font-semibold">{{ $card['title'] }}</span>
                    <flux:badge size="sm" :color="$selected === $key ? 'lime' : 'zinc'">
                        {{ $selected === $key ? 'Selezionato' : $card['badge'] }}
                    </flux:badge>
                </div>

                <p class="mt-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $card['tagline'] }}</p>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $card['description'] }}</p>

                <ul class="mt-3 space-y-1 text-xs text-zinc-600 dark:text-zinc-400">
                    @foreach ($card['specs'] as $spec)
                        <li class="flex items-center gap-1.5">
                            <span class="inline-block size-1 rounded-full bg-zinc-400"></span>{{ $spec }}
                        </li>
                    @endforeach
                    <li class="flex items-center gap-1.5">
                        <span class="inline-block size-1 rounded-full bg-zinc-400"></span>
                        {{ $card['plate'] }} pezzi per piastra sull'A1 mini
                    </li>
                </ul>
            </button>
        @endforeach
    </div>
</section>
