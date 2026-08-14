{{--
    Studio promo page — /studio (restyle §5.3, flussi.md §3, mockup 04).
    Sells the four TRUE benefits of an account: model archive, series
    duplication, saved logos/QRs, full customization. No invented numbers,
    no testimonials (restyle §9). The "dashboard" in the hero is an ABSTRACT
    schema (skeleton rows), declared as such — never a fake screenshot.
    Authenticated visitors get a single "open your archive" CTA instead of
    register/login: the page is reachable from the nav, never a dead end.
--}}
<x-layouts::public title="Lo Studio completo">

    {{-- ============ HERO: pitch + blueprint frame (≥ 50 % width, above the fold) ============ --}}
    <section class="py-2 lg:py-6">
        <div class="grid items-center gap-10 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-12">
            <div>
                <p class="mono text-xs tracking-[0.08em]">/studio</p>
                <h1 class="mt-3 text-4xl font-semibold text-text-primary">Lo Studio completo</h1>
                <p class="mt-3 max-w-[44ch] text-lg text-text-secondary">
                    Lo stesso generatore, la stessa verifica di stampa. In più, tutto
                    quello che serve a chi produce davvero: un archivio che ricorda i
                    tuoi modelli, loghi e QR pronti al riuso, ogni parametro sotto
                    controllo.
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" wire:navigate>Apri il tuo archivio</flux:button>
                        <flux:button :href="route('home')" variant="filled" wire:navigate>Vai al configuratore</flux:button>
                    @else
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary">Crea un account</flux:button>
                        @endif
                        <flux:button :href="route('login')" variant="filled">Accedi</flux:button>
                    @endauth
                </div>
            </div>

            <div>
                {{-- Blueprint frame with measuring ticks: the workbench look (tokens §4) --}}
                <div class="blueprint relative rounded-panel border border-border-subtle p-5 sm:p-7">
                    <span class="absolute left-2.5 top-2.5 size-3.5 border-l border-t border-tech opacity-40" aria-hidden="true"></span>
                    <span class="absolute right-2.5 top-2.5 size-3.5 border-r border-t border-tech opacity-40" aria-hidden="true"></span>
                    <span class="absolute bottom-2.5 left-2.5 size-3.5 border-b border-l border-tech opacity-40" aria-hidden="true"></span>
                    <span class="absolute bottom-2.5 right-2.5 size-3.5 border-b border-r border-tech opacity-40" aria-hidden="true"></span>

                    {{-- Abstract dashboard schema (mockup 04): skeleton rows, not a screenshot --}}
                    <div
                        class="overflow-hidden rounded-card border border-border-subtle bg-surface-1"
                        role="img"
                        aria-label="Schema illustrativo della dashboard dello Studio: archivio dei modelli con formato, misura e stato"
                    >
                        <div class="flex items-center gap-2.5 border-b border-border-subtle px-4 py-3" aria-hidden="true">
                            <x-app-logo-icon class="size-[18px] text-text-primary" />
                            <span class="text-sm font-semibold text-text-primary">Il tuo archivio</span>
                            <span class="flex-1"></span>
                            <span class="rounded-control bg-accent px-3.5 py-1.5 text-sm font-semibold text-accent-ink">Nuovo modello</span>
                            <span class="size-6.5 rounded-full border border-border-subtle bg-surface-3"></span>
                        </div>
                        <div class="flex items-center gap-2.5 border-b border-border-subtle px-4 py-3" aria-hidden="true">
                            <span class="flex h-7.5 w-56 items-center gap-2 rounded-control border border-border-strong bg-surface-2 px-2.5">
                                <span class="size-2 rounded-full bg-text-muted"></span>
                                <span class="h-2 w-24 rounded-full bg-surface-3"></span>
                            </span>
                            <span class="h-7.5 w-20 rounded-control border border-border-subtle bg-surface-2"></span>
                            <span class="h-7.5 w-16 rounded-control border border-border-subtle bg-surface-2 max-sm:hidden"></span>
                        </div>
                        <div class="grid grid-cols-[34px_minmax(0,1fr)_78px_86px_118px_58px] items-center gap-3 border-b border-border-subtle px-4 py-2 text-xs font-medium uppercase tracking-[0.08em] text-text-muted max-sm:grid-cols-[34px_minmax(0,1fr)_86px_118px]" aria-hidden="true">
                            <span></span><span>Modello</span><span class="max-sm:hidden">Formato</span>
                            <span class="text-right">Misura</span><span>Stato</span><span class="max-sm:hidden"></span>
                        </div>

                        @foreach ([
                            ['preset' => 'menutag',   'skeleton' => 'w-32', 'format' => 'MenuTag',   'measure' => '58.8 mm',   'state' => 'Pronto',         'tone' => 'ok',      'active' => true],
                            ['preset' => 'coaster',   'skeleton' => 'w-24', 'format' => 'Coaster',   'measure' => 'Ø 85 mm',   'state' => 'Pronto',         'tone' => 'ok',      'active' => false],
                            ['preset' => 'coin_cart', 'skeleton' => 'w-36', 'format' => 'Coin Cart', 'measure' => 'Ø 25.75 mm', 'state' => 'In lavorazione', 'tone' => 'neutral', 'active' => false],
                            ['preset' => 'menutag',   'skeleton' => 'w-28', 'format' => 'MenuTag',   'measure' => '58.8 mm',   'state' => 'Pronto',         'tone' => 'ok',      'active' => false],
                        ] as $row)
                            <div
                                class="grid grid-cols-[34px_minmax(0,1fr)_78px_86px_118px_58px] items-center gap-3 border-b border-border-subtle px-4 py-2.5 last:border-b-0 max-sm:grid-cols-[34px_minmax(0,1fr)_86px_118px] {{ $row['active'] ? 'bg-surface-3' : '' }}"
                                aria-hidden="true"
                            >
                                {{-- Bicolor product thumb: light base, dark engraving (product echo) --}}
                                <span class="flex size-7 items-center justify-center">
                                    @if ($row['preset'] === 'coaster')
                                        <svg width="26" height="26" viewBox="0 0 28 28">
                                            <circle cx="14" cy="14" r="12" class="fill-text-primary" />
                                            <circle cx="14" cy="14" r="8" fill="none" class="stroke-surface-0" stroke-width="2" />
                                            <circle cx="14" cy="14" r="2.5" class="fill-surface-0" />
                                        </svg>
                                    @elseif ($row['preset'] === 'coin_cart')
                                        <svg width="26" height="26" viewBox="0 0 28 28">
                                            <circle cx="14" cy="14" r="11" class="fill-text-primary" />
                                            <circle cx="14" cy="14" r="8" fill="none" class="stroke-surface-0" stroke-width="1.5" stroke-dasharray="2 3" />
                                            <rect x="11" y="11" width="6" height="6" rx="1.5" class="fill-surface-0" transform="rotate(45 14 14)" />
                                        </svg>
                                    @else
                                        <svg width="26" height="26" viewBox="0 0 28 28">
                                            <rect x="2" y="2" width="24" height="24" rx="4" class="fill-text-primary" />
                                            <g class="fill-surface-0">
                                                <rect x="6" y="6" width="6" height="6" /><rect x="16" y="6" width="6" height="6" />
                                                <rect x="6" y="16" width="6" height="6" /><rect x="16" y="16" width="2.5" height="2.5" />
                                                <rect x="19.5" y="19.5" width="2.5" height="2.5" />
                                            </g>
                                            <g class="fill-text-primary">
                                                <rect x="8" y="8" width="2" height="2" /><rect x="18" y="8" width="2" height="2" />
                                                <rect x="8" y="18" width="2" height="2" />
                                            </g>
                                        </svg>
                                    @endif
                                </span>
                                <span class="h-2 {{ $row['skeleton'] }} rounded-full {{ $row['active'] ? 'bg-border-strong' : 'bg-surface-3' }}"></span>
                                <span class="text-sm text-text-secondary max-sm:hidden">{{ $row['format'] }}</span>
                                <span class="text-right"><span class="mono text-sm">{{ $row['measure'] }}</span></span>
                                <span>
                                    <span @class([
                                        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'border border-ok/30 bg-ok-surface text-ok' => $row['tone'] === 'ok',
                                        'border border-border-subtle bg-surface-2 text-text-secondary' => $row['tone'] === 'neutral',
                                    ])>
                                        <span class="size-1.5 rounded-full bg-current"></span>
                                        {{ $row['state'] }}
                                    </span>
                                </span>
                                <span class="flex justify-end gap-1 text-text-muted max-sm:hidden">
                                    <span class="flex size-6.5 items-center justify-center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">
                                            <path d="M9 9h11v11H9z" /><path d="M4 15V4h11" />
                                        </svg>
                                    </span>
                                    <span class="flex size-6.5 items-center justify-center">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round">
                                            <path d="M12 4v10m0 0 4-4m-4 4-4-4" /><path d="M4 18h16" />
                                        </svg>
                                    </span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="mt-2.5 text-center text-xs text-text-muted">
                    La dashboard dello Studio — schema illustrativo, non uno screenshot.
                </p>
            </div>
        </div>
    </section>

    {{-- ============ THE 4 TRUE BENEFITS (flussi.md §3 — nothing invented) ============ --}}
    <section class="py-8" aria-label="Cosa sblocca l'account">
        <h2 class="text-2xl font-semibold text-text-primary">Cosa sblocca l'account</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

            <article class="rounded-card border border-border-subtle bg-surface-2 p-5 transition-colors duration-[var(--t-micro)] hover:border-border-strong">
                <div class="mb-3.5 flex size-10 items-center justify-center rounded-control bg-surface-3 text-text-secondary" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round">
                        <path d="M3 5h18v4H3z" /><path d="M5 9v10h14V9" /><path d="M10 13h4" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text-primary">Archivio dei modelli</h3>
                <p class="mt-1.5 text-sm text-text-secondary">
                    Ogni modello che generi resta salvato: riaprilo, controlla la
                    verifica di stampa, riscarica il file di stampa (STL) quando ti serve.
                </p>
            </article>

            <article class="rounded-card border border-border-subtle bg-surface-2 p-5 transition-colors duration-[var(--t-micro)] hover:border-border-strong">
                <div class="mb-3.5 flex size-10 items-center justify-center rounded-control bg-surface-3 text-text-secondary" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round">
                        <path d="M9 9h12v12H9z" /><path d="M6 15V6h9" /><path d="M3 12V3h9" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text-primary">Duplica in serie</h3>
                <p class="mt-1.5 text-sm text-text-secondary">
                    Produci per più clienti? Parti da un modello esistente, cambia
                    l'indirizzo o il logo e genera la variante in pochi passaggi.
                </p>
            </article>

            <article class="rounded-card border border-border-subtle bg-surface-2 p-5 transition-colors duration-[var(--t-micro)] hover:border-border-strong">
                <div class="mb-3.5 flex size-10 items-center justify-center rounded-control bg-surface-3 text-text-secondary" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round">
                        <rect x="3.5" y="3.5" width="6.5" height="6.5" />
                        <rect x="14" y="3.5" width="6.5" height="6.5" />
                        <rect x="3.5" y="14" width="6.5" height="6.5" />
                        <g fill="currentColor" stroke="none">
                            <rect x="14" y="14" width="2.6" height="2.6" />
                            <rect x="17.9" y="14" width="2.6" height="2.6" />
                            <rect x="14" y="17.9" width="2.6" height="2.6" />
                            <rect x="17.9" y="17.9" width="2.6" height="2.6" />
                        </g>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text-primary">Loghi e QR salvati</h3>
                <p class="mt-1.5 text-sm text-text-secondary">
                    La tua libreria di loghi e i QR che usi più spesso, pronti da
                    riusare su ogni nuovo modello senza ricaricarli.
                </p>
            </article>

            <article class="rounded-card border border-border-subtle bg-surface-2 p-5 transition-colors duration-[var(--t-micro)] hover:border-border-strong">
                <div class="mb-3.5 flex size-10 items-center justify-center rounded-control bg-surface-3 text-text-secondary" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round">
                        <path d="M3 6h18" /><path d="M3 12h18" /><path d="M3 18h18" />
                        <g fill="currentColor" stroke="none">
                            <circle cx="15" cy="6" r="2.4" />
                            <circle cx="8" cy="12" r="2.4" />
                            <circle cx="17" cy="18" r="2.4" />
                        </g>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-text-primary">Personalizzazione completa</h3>
                <p class="mt-1.5 text-sm text-text-secondary">
                    Dimensioni, fronte e retro, resa e tag NFC — più le impostazioni
                    di stampa avanzate, per chi stampa in proprio o per il service.
                </p>
            </article>

        </div>
    </section>

    {{-- ============ FINAL CTA BAND + guest→user migration promise (§5.4) ============ --}}
    <section class="py-6">
        <div class="rounded-panel border border-border-subtle bg-surface-1 px-6 py-10 text-center sm:px-10">
            <h2 class="text-2xl font-semibold text-text-primary">Porta i tuoi modelli nello Studio</h2>
            <p class="mt-1.5 text-text-secondary">Crea l'account e ritrova qui quello che hai già generato.</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary" wire:navigate>Apri il tuo archivio</flux:button>
                @else
                    @if (Route::has('register'))
                        <flux:button :href="route('register')" variant="primary">Crea un account</flux:button>
                    @endif
                    <flux:button :href="route('login')" variant="filled">Accedi</flux:button>
                @endauth
            </div>
            @guest
                {{-- The existing guest→user migration makes this promise TRUE (flussi.md §4). --}}
                <p class="mt-4 text-sm text-text-secondary">
                    I modelli creati da visitatore ti seguiranno dopo la registrazione.
                </p>
            @endguest
        </div>
    </section>

</x-layouts::public>
