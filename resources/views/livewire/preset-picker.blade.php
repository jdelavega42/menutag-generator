{{--
    Entry: the choice between the THREE presets, MenuTag preselected.
    The parametric generator is NOT a fourth card (spec §5.2 / §6 WS-4).
    Cards per mockup 01: preview thumb + non-technical bullets + measure in
    mono. Stacked single-column inside the guest wizard, 3-across otherwise.
--}}
<section aria-label="Scelta del formato">
    @unless ($stacked)
        <flux:heading size="lg" level="2">Scegli il formato</flux:heading>
        <flux:text class="mt-1 text-sm">
            Tre prodotti validati. Ogni personalizzazione parte da uno di questi — mai da un modulo vuoto.
        </flux:text>
    @endunless

    <div
        @class([
            'gap-3',
            'flex flex-col' => $stacked,
            'mt-4 grid md:grid-cols-3' => ! $stacked,
        ])
        role="group"
        aria-label="Formati disponibili"
    >
        @foreach ($cards as $key => $card)
            <button
                type="button"
                wire:key="preset-card-{{ $key }}"
                wire:click="select('{{ $key }}')"
                @class([
                    'group rounded-card border p-3.5 text-left transition-colors duration-[var(--t-micro)]',
                    'flex items-start gap-3.5' => $stacked,
                    'border-accent bg-surface-2' => $selected === $key,
                    'border-border-subtle bg-surface-2 hover:bg-surface-3' => $selected !== $key,
                ])
                aria-pressed="{{ $selected === $key ? 'true' : 'false' }}"
            >
                <span
                    @class([
                        'flex size-16 flex-none items-center justify-center rounded-control border border-border-subtle bg-surface-1',
                        'mb-3' => ! $stacked,
                    ])
                    aria-hidden="true"
                >
                    @if ($key === 'menutag')
                        <svg width="44" height="44" viewBox="0 0 56 56">
                            <rect x="6" y="6" width="44" height="44" rx="6" class="fill-text-primary" />
                            <g class="fill-surface-0">
                                <rect x="11" y="11" width="10" height="10" /><rect x="35" y="11" width="10" height="10" />
                                <rect x="11" y="35" width="10" height="10" /><rect x="27" y="27" width="5" height="5" />
                                <rect x="36" y="30" width="4" height="4" /><rect x="30" y="38" width="5" height="4" />
                                <rect x="40" y="38" width="4" height="4" />
                            </g>
                            <g class="fill-text-primary">
                                <rect x="14" y="14" width="4" height="4" /><rect x="38" y="14" width="4" height="4" />
                                <rect x="14" y="38" width="4" height="4" />
                            </g>
                        </svg>
                    @elseif ($key === 'coaster')
                        <svg width="44" height="44" viewBox="0 0 56 56">
                            <circle cx="28" cy="28" r="22" class="fill-text-primary" />
                            <circle cx="28" cy="28" r="15" fill="none" class="stroke-surface-0" stroke-width="3" />
                            <circle cx="28" cy="28" r="4.5" class="fill-surface-0" />
                        </svg>
                    @else
                        <svg width="44" height="44" viewBox="0 0 56 56">
                            <circle cx="28" cy="28" r="20" class="fill-text-primary" />
                            <circle cx="28" cy="28" r="15" fill="none" class="stroke-surface-0" stroke-width="2" stroke-dasharray="2 4" />
                            <rect x="24" y="24" width="8" height="8" rx="2" class="fill-surface-0" transform="rotate(45 28 28)" />
                        </svg>
                    @endif
                </span>

                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-lg font-semibold text-text-primary">{{ $card['title'] }}</span>
                        @if ($selected === $key)
                            <span class="text-xs font-semibold tracking-wide text-accent">● Selezionato</span>
                        @else
                            <span class="rounded-full border border-border-strong bg-surface-3 px-2 py-px text-xs font-medium text-text-secondary">
                                {{ $card['badge'] }}
                            </span>
                        @endif
                    </span>

                    <ul class="mt-1.5 space-y-0.5">
                        @foreach ($card['bullets'] as $bullet)
                            <li class="relative pl-3.5 text-sm text-text-secondary before:absolute before:left-0 before:top-[0.55em] before:size-[5px] before:rounded-[1px] before:bg-text-muted before:content-['']">
                                {{ $bullet }}
                            </li>
                        @endforeach
                    </ul>

                    <span class="mt-1.5 block text-xs text-text-muted">
                        {{ $card['meta'] }} <span class="mono text-xs">{{ $card['measure'] }}</span>
                    </span>
                </span>
            </button>
        @endforeach
    </div>
</section>
