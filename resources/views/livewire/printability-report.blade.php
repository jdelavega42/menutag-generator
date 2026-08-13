{{--
    Printability report (spec §8.8): outcome, full/void detail, first-perimeter
    residue, QR decode. On `blocked` the download STAYS available, behind an
    explicit warning: the user decides, informed.
--}}
<section aria-label="Report di stampabilità">
    @if ($this->hasReport())
        @php($printability = $this->printability())
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg" level="2">Report di stampabilità</flux:heading>
                @if ($printability === 'ok')
                    <flux:badge color="lime">Esito: OK</flux:badge>
                @elseif ($printability === 'warn')
                    <flux:badge color="amber">Esito: con avvisi</flux:badge>
                @elseif ($printability === 'blocked')
                    <flux:badge color="red">Esito: critico</flux:badge>
                @else
                    <flux:badge color="zinc">Esito non disponibile</flux:badge>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                @if ($this->value('FEATURE_MIN_MM') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Dettaglio minimo (pieno)</dt>
                        <dd>{{ $this->value('FEATURE_MIN_MM') }} mm</dd>
                    </div>
                @endif
                @if ($this->value('VOID_MIN_MM') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Dettaglio minimo (vuoto)</dt>
                        <dd>{{ $this->value('VOID_MIN_MM') }} mm</dd>
                    </div>
                @endif
                @if ($this->value('FEATURE_LOSS_PCT') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Area a rischio</dt>
                        <dd>{{ $this->value('FEATURE_LOSS_PCT') }} %</dd>
                    </div>
                @endif
                @if ($this->value('PERIMETER_RESIDUE_PCT') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Residuo dopo il primo perimetro</dt>
                        <dd>
                            {{ $this->value('PERIMETER_RESIDUE_PCT') }} %
                            @if ($this->value('PERIMETER_RESIDUE_WIDTH_MM') !== null)
                                ({{ $this->value('PERIMETER_RESIDUE_WIDTH_MM') }} mm)
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($this->value('QR_DECODED') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Decodifica del QR</dt>
                        <dd class="{{ $this->value('QR_DECODED') === 'yes' ? 'text-lime-700 dark:text-lime-300' : 'text-red-700 dark:text-red-300' }}">
                            {{ $this->value('QR_DECODED') === 'yes' ? 'Riuscita' : 'NON riuscita' }}
                            @if ($this->value('QR_VERSION') !== null)
                                · v{{ $this->value('QR_VERSION') }}
                            @endif
                            @if ($this->value('QR_PITCH_MM') !== null)
                                · modulo {{ $this->value('QR_PITCH_MM') }} mm
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($this->value('TRIANGLES') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Triangoli</dt>
                        <dd>{{ $this->value('TRIANGLES') }}</dd>
                    </div>
                @endif
                @if ($this->value('WEIGHT_G') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Peso (solido pieno)</dt>
                        <dd>{{ $this->value('WEIGHT_G') }} g</dd>
                    </div>
                @endif
                @if ($this->value('BICOLOR_LAYERS') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Layer bicromatici</dt>
                        <dd>{{ $this->value('BICOLOR_LAYERS') }}</dd>
                    </div>
                @endif
                @if ($this->value('CAPACITY_ML') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Capacità dell'incavo</dt>
                        <dd>{{ $this->value('CAPACITY_ML') }} ml</dd>
                    </div>
                @endif
                @if ($this->value('PAUSE_LAYER') !== null)
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Pausa NFC</dt>
                        <dd>dopo il layer {{ $this->value('PAUSE_LAYER') }} (Z = {{ $this->value('PAUSE_Z') }} mm)</dd>
                    </div>
                @endif
            </dl>

            @if ($this->warnings() !== [])
                <ul class="mt-4 space-y-1 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
                    @foreach ($this->warnings() as $warning)
                        <li wire:key="report-warning-{{ $loop->index }}">⚠ {{ $warning }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($printability === 'blocked')
                <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100">
                    <p class="font-semibold">Attenzione: il report segnala problemi critici.</p>
                    <p class="mt-1">
                        Oltre il 10&nbsp;% dell'area grafica rischia di non essere riprodotta, oppure il QR
                        prodotto non è stato decodificato. Il download resta possibile — decidi tu, informato —
                        ma il pezzo stampato potrebbe non essere leggibile o vendibile così com'è.
                    </p>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap gap-2">
                @if ($stlUrl !== null)
                    <flux:button :href="$stlUrl" variant="primary" size="sm">
                        Scarica STL{{ $accentStlUrl !== null ? ' (corpo base)' : '' }}
                    </flux:button>
                @endif
                @if ($accentStlUrl !== null)
                    <flux:button :href="$accentStlUrl" variant="filled" size="sm">
                        Scarica STL accento (intarsio)
                    </flux:button>
                @endif
                @if ($printGuideUrl !== null)
                    <flux:button :href="$printGuideUrl" variant="ghost" size="sm">
                        Guida di stampa (Markdown)
                    </flux:button>
                @endif
            </div>

            @auth
            @else
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    I link di download degli ospiti sono firmati e scadono dopo
                    {{ (int) config('product.guests.retention_hours') }} ore, insieme al file.
                </p>
            @endauth
        </div>
    @endif
</section>
