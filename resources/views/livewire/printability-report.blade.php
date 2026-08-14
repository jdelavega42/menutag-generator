{{--
    «Verifica di stampa» (spec §8.8, glossario.md, mockup 03): a semaforo
    with one human sentence per outcome, the NFC pause humanized, and every
    number demoted to a CLOSED «Dettagli tecnici» block in mono. On `blocked`
    the download STAYS available behind an explicit warning: the user
    decides, informed.
--}}
<section aria-label="Verifica di stampa">
    @if ($this->hasReport())
        @php($outcome = $this->outcome())
        <div class="space-y-4">
            <div class="rounded-panel border border-border-subtle bg-surface-1 p-5">
                <flux:heading size="lg" level="2">Verifica di stampa</flux:heading>

                {{-- Semaforo badge: full color on text/icon only, -surface behind (tokens §3) --}}
                <p
                    @class([
                        'mt-3.5 inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold',
                        'border-ok/30 bg-ok-surface text-ok' => $outcome['tone'] === 'ok',
                        'border-warn/30 bg-warn-surface text-warn' => $outcome['tone'] === 'warn',
                        'border-blocked/30 bg-blocked-surface text-blocked' => $outcome['tone'] === 'blocked',
                        'border-border-strong bg-surface-2 text-text-secondary' => $outcome['tone'] === 'muted',
                    ])
                >
                    <span
                        @class([
                            'size-2 rounded-full',
                            'bg-ok' => $outcome['tone'] === 'ok',
                            'bg-warn' => $outcome['tone'] === 'warn',
                            'bg-blocked' => $outcome['tone'] === 'blocked',
                            'bg-text-muted' => $outcome['tone'] === 'muted',
                        ])
                        aria-hidden="true"
                    ></span>
                    {{ $outcome['title'] }}
                </p>

                <p class="mt-3 text-sm text-text-secondary">{{ $outcome['phrase'] }}</p>

                {{-- One human sentence per engine warning (glossario.md) --}}
                @if ($this->warnings() !== [])
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($this->warnings() as $warning)
                            <li wire:key="report-warning-{{ $loop->index }}" class="flex gap-2 text-sm text-text-secondary">
                                <span class="mt-px flex-none font-semibold {{ $outcome['tone'] === 'blocked' ? 'text-blocked' : 'text-warn' }}" aria-hidden="true">⚠</span>
                                {{ $this->humanizeWarning($warning) }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- NFC pause, humanized: numbers in mono (glossario.md) --}}
                @if ($this->value('PAUSE_LAYER') !== null)
                    <div class="mt-3.5 flex items-start gap-2.5 rounded-card border border-border-subtle bg-surface-2 px-3.5 py-3 text-sm text-text-secondary">
                        <svg class="mt-0.5 size-3.5 flex-none" viewBox="0 0 14 14" fill="currentColor" aria-hidden="true">
                            <rect x="2" y="1" width="4" height="12" rx="1" />
                            <rect x="8" y="1" width="4" height="12" rx="1" />
                        </svg>
                        <span>
                            Pausa per inserire il tag NFC: dopo lo strato
                            <span class="mono">{{ $this->value('PAUSE_LAYER') }}</span>
                            (quota <span class="mono">{{ $this->value('PAUSE_Z') }} mm</span>)
                        </span>
                    </div>
                @endif

                {{-- Every number survives as secondary detail, closed by default --}}
                <details class="mt-3.5 border-t border-border-subtle pt-3">
                    <summary class="cursor-pointer text-sm font-medium text-text-secondary transition-colors duration-[var(--t-micro)] hover:text-text-primary">
                        Dettagli tecnici
                    </summary>
                    <dl class="mt-2.5 grid grid-cols-[1fr_auto] gap-x-4 gap-y-1.5 text-sm">
                        @if ($this->value('FEATURE_MIN_MM') !== null)
                            <dt class="text-text-muted">Dettaglio minimo (pieno)</dt>
                            <dd class="mono text-right">{{ $this->value('FEATURE_MIN_MM') }} mm</dd>
                        @endif
                        @if ($this->value('VOID_MIN_MM') !== null)
                            <dt class="text-text-muted">Dettaglio minimo (vuoto)</dt>
                            <dd class="mono text-right">{{ $this->value('VOID_MIN_MM') }} mm</dd>
                        @endif
                        @if ($this->value('FEATURE_LOSS_PCT') !== null)
                            <dt class="text-text-muted">Area a rischio</dt>
                            <dd class="mono text-right">{{ $this->value('FEATURE_LOSS_PCT') }} %</dd>
                        @endif
                        @if ($this->value('PERIMETER_RESIDUE_PCT') !== null)
                            <dt class="text-text-muted">Residuo dopo il primo contorno</dt>
                            <dd class="mono text-right">
                                {{ $this->value('PERIMETER_RESIDUE_PCT') }} %@if ($this->value('PERIMETER_RESIDUE_WIDTH_MM') !== null) ({{ $this->value('PERIMETER_RESIDUE_WIDTH_MM') }} mm)@endif
                            </dd>
                        @endif
                        @if ($this->value('QR_DECODED') !== null)
                            <dt class="text-text-muted">Decodifica del QR</dt>
                            <dd class="text-right {{ $this->value('QR_DECODED') === 'yes' ? 'text-ok' : 'text-blocked' }}">
                                {{ $this->value('QR_DECODED') === 'yes' ? 'Riuscita' : 'NON riuscita' }}@if ($this->value('QR_VERSION') !== null) · <span class="mono">v{{ $this->value('QR_VERSION') }}</span>@endif @if ($this->value('QR_PITCH_MM') !== null) · modulo <span class="mono">{{ $this->value('QR_PITCH_MM') }} mm</span>@endif
                            </dd>
                        @endif
                        @if ($this->value('WEIGHT_G') !== null)
                            <dt class="text-text-muted">Peso (solido pieno)</dt>
                            <dd class="mono text-right">{{ $this->value('WEIGHT_G') }} g</dd>
                        @endif
                        @if ($this->value('BICOLOR_LAYERS') !== null)
                            <dt class="text-text-muted">Strati a due colori</dt>
                            <dd class="mono text-right">{{ $this->value('BICOLOR_LAYERS') }}</dd>
                        @endif
                        @if ($this->value('CAPACITY_ML') !== null)
                            <dt class="text-text-muted">Capacità dell'incavo</dt>
                            <dd class="mono text-right">{{ $this->value('CAPACITY_ML') }} ml</dd>
                        @endif
                        @if ($this->value('TRIANGLES') !== null)
                            <dt class="text-text-muted">Facce del modello</dt>
                            <dd class="mono text-right">{{ $this->value('TRIANGLES') }}</dd>
                        @endif
                    </dl>
                </details>
            </div>

            {{-- CTA column (mockup 03) --}}
            <div class="flex flex-col gap-2.5">
                @if ($stlUrl !== null)
                    <flux:button :href="$stlUrl" variant="primary" class="w-full">
                        Scarica il file di stampa (STL){{ $accentStlUrl !== null ? ' — corpo base' : '' }}
                    </flux:button>
                @endif
                @if ($accentStlUrl !== null)
                    <flux:button :href="$accentStlUrl" variant="filled" class="w-full">
                        Scarica il secondo colore (accento)
                    </flux:button>
                @endif
                @if ($printGuideUrl !== null)
                    <flux:button :href="$printGuideUrl" variant="filled" class="w-full">
                        Guida per chi stampa
                    </flux:button>
                @endif
            </div>

            @guest
                {{-- Contextual registration CTA at the point of maximum interest (§5.1) --}}
                <aside class="rounded-card border border-border-subtle bg-surface-1 p-4" aria-label="Salva il modello">
                    <h3 class="text-sm font-semibold text-text-primary">Salva questo modello nel tuo archivio</h3>
                    <p class="mt-1 text-sm text-text-secondary">
                        Registrandoti lo ritrovi quando vuoi, lo duplichi per altri tavoli e
                        tieni loghi e QR pronti al riuso. I modelli creati da ospite ti seguiranno.
                    </p>
                    <div class="mt-3 flex items-center gap-4">
                        <flux:button :href="route('register')" variant="primary" size="sm">Registrati</flux:button>
                        <a href="{{ route('studio-promo') }}" class="text-sm font-medium text-tech hover:underline" wire:navigate>Scopri lo Studio completo</a>
                    </div>
                </aside>

                <p class="text-center text-xs text-text-muted">
                    I file dei visitatori restano disponibili per
                    <span class="mono text-xs">{{ (int) config('product.guests.retention_hours') }} ore</span>.
                </p>
            @endguest
        </div>
    @endif
</section>
