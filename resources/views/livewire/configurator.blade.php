{{--
    Configurator (contract 04). Every preview parameter is ENTANGLED with
    Alpine: changes update the three.js viewer via a browser event with ZERO
    server requests (x-effect → syncPreview). Livewire receives the values
    only through the debounced runLiveValidation() and at submit.
--}}
<section
    aria-label="Configuratore"
    x-data="menuTagConfigurator({
        shape: $wire.entangle('shape'),
        size: $wire.entangle('size'),
        fillet: $wire.entangle('fillet'),
        thickness: $wire.entangle('thickness'),
        baseProfile: $wire.entangle('baseProfile'),
        rimWidth: $wire.entangle('rimWidth'),
        recessDepth: $wire.entangle('recessDepth'),
        front: $wire.entangle('front'),
        back: $wire.entangle('back'),
        mode: $wire.entangle('mode'),
        depth: $wire.entangle('depth'),
        qrDataFront: $wire.entangle('qrDataFront'),
        qrDataBack: $wire.entangle('qrDataBack'),
        qrEc: $wire.entangle('qrEc'),
        nfc: $wire.entangle('nfc'),
        tagDiameter: $wire.entangle('tagDiameter'),
        tagThickness: $wire.entangle('tagThickness'),
        nozzle: $wire.entangle('nozzle'),
        layerHeight: $wire.entangle('layerHeight'),
        printer: $wire.entangle('printer'),
        material: $wire.entangle('material'),
        plate: $wire.entangle('plate'),
        xyComp: $wire.entangle('xyComp'),
        logoPreviewUrl: $wire.entangle('logoPreviewUrl'),
        sizeTouched: $wire.entangle('sizeTouched'),
        depthTouched: $wire.entangle('depthTouched'),
    })"
    x-effect="syncPreview(JSON.stringify(previewParams))"
    class="space-y-5"
>
    @if ($isGuest)
        {{--
            ============ GUEST WIZARD (flussi.md §1, mockup 01/02/03) ============
            Three steps, zero knobs: format → essential input → create and
            download. NO parametric control ships in this branch — and the
            server refuses any parametric mutation anyway (see Configurator
            updating()/unlockCustomization(): the gate is server-side).
        --}}
        @php($stepTwoLabel = $preset === 'menutag' ? 'Il tuo menù' : 'Il tuo logo')
        @php($wizardSteps = [
            1 => ['label' => 'Formato', 'small' => $presetLabel],
            2 => ['label' => $stepTwoLabel, 'small' => 'l\'input essenziale'],
            3 => ['label' => 'Crea e scarica', 'small' => 'file di stampa + guida'],
        ])

        {{-- Step indicator (mockup 02) --}}
        <ol class="flex items-center gap-3" aria-label="Avanzamento: passo {{ $step }} di 3">
            @foreach ($wizardSteps as $number => $labels)
                @if ($number > 1)
                    <li class="h-px w-6 flex-none {{ $step > $number - 1 ? 'bg-accent' : 'bg-border-subtle' }} sm:w-9" role="presentation" aria-hidden="true"></li>
                @endif
                <li
                    wire:key="wizard-step-{{ $number }}"
                    class="flex items-center gap-2.5"
                    @if ($step === $number) aria-current="step" @endif
                >
                    <span
                        @class([
                            'flex size-7 flex-none items-center justify-center rounded-full border text-sm font-semibold',
                            'border-accent bg-accent text-accent-ink' => $step === $number,
                            'border-accent bg-transparent text-accent' => $step > $number,
                            'border-border-subtle bg-surface-2 text-text-muted' => $step < $number,
                        ])
                        aria-hidden="true"
                    >{{ $step > $number ? '✓' : $number }}</span>
                    <span class="leading-tight">
                        <span @class([
                            'block text-sm',
                            'font-semibold text-text-primary' => $step === $number,
                            'font-medium text-text-secondary' => $step > $number,
                            'font-medium text-text-muted' => $step < $number,
                        ])>{{ $labels['label'] }}</span>
                        <span class="block text-xs text-text-muted max-sm:hidden">{{ $labels['small'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>

        {{-- A guest followed «Duplica e modifica»: CTA, never an error (flussi §4) --}}
        @if ($duplicateRequiresAccount)
            <aside class="rounded-card border border-dashed border-border-strong bg-surface-1 p-4" wire:key="guest-duplicate-cta" aria-label="Duplicazione riservata agli utenti registrati">
                <p class="text-sm font-semibold text-text-primary">Per riaprire e modificare un modello serve l'archivio.</p>
                <p class="mt-1 text-sm text-text-secondary">
                    Registrati per duplicare i tuoi modelli e riaprirli quando vuoi:
                    quelli creati da ospite ti seguiranno.
                </p>
                <div class="mt-3 flex items-center gap-4">
                    <flux:button :href="route('register')" variant="primary" size="sm">Registrati</flux:button>
                    <a href="{{ route('studio-promo') }}" class="text-sm font-medium text-tech hover:underline" wire:navigate>Scopri lo Studio completo</a>
                </div>
            </aside>
        @endif

        @if ($step === 1)
            {{-- ============ Passo 1 · Scegli il formato (mockup 01) ============ --}}
            <div wire:key="wizard-step-format" class="space-y-4">
                <livewire:preset-picker :stacked="true" :selected="$preset" wire:key="wizard-preset-picker" />

                <flux:button wire:click="continueToInput" variant="primary" class="w-full">
                    Continua — {{ $preset === 'menutag' ? 'collega il tuo menù' : 'carica il tuo logo' }}
                </flux:button>

                {{-- Where «Personalizza questo formato» used to be (restyle §5.4) --}}
                <aside class="rounded-card border border-dashed border-border-strong bg-surface-1 p-4" aria-label="Sblocca lo Studio completo">
                    <h3 class="text-lg font-semibold text-text-primary">Sblocca lo Studio completo</h3>
                    <ul class="mt-2 space-y-1">
                        <li class="relative pl-3.5 text-sm text-text-secondary before:absolute before:left-0 before:top-[0.55em] before:size-[5px] before:rounded-[1px] before:bg-text-muted before:content-['']">
                            Il tuo archivio: ogni modello salvato, pronto da riaprire e duplicare
                        </li>
                        <li class="relative pl-3.5 text-sm text-text-secondary before:absolute before:left-0 before:top-[0.55em] before:size-[5px] before:rounded-[1px] before:bg-text-muted before:content-['']">
                            Personalizzazione completa: dimensioni, fronte e retro, logo nel QR, tag NFC
                        </li>
                    </ul>
                    <a href="{{ route('studio-promo') }}" class="mt-2.5 inline-block text-sm font-medium text-tech hover:underline" wire:navigate>
                        Scopri lo Studio completo →
                    </a>
                </aside>
            </div>
        @elseif ($step === 2)
            {{-- ============ Passo 2 · L'input essenziale (mockup 02) ============ --}}
            <div wire:key="wizard-step-input" class="rounded-panel border border-border-subtle bg-surface-1 p-6">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-xs font-medium uppercase tracking-[0.08em] text-text-muted">Passo 2 di 3</p>
                    <button type="button" wire:click="backToFormat" class="text-xs font-medium text-tech hover:underline">
                        ← Cambia formato
                    </button>
                </div>

                @if ($preset === 'menutag')
                    <h2 class="mt-1.5 text-2xl font-semibold text-text-primary">Collega il tuo menù</h2>
                    <p class="mt-1 text-text-secondary">Scrivi l'indirizzo: il QR si incide da solo.</p>

                    <label class="mt-5 block text-sm font-medium text-text-secondary" for="guest-menu-url">L'indirizzo del tuo menù</label>
                    <input
                        id="guest-menu-url"
                        type="url"
                        inputmode="url"
                        spellcheck="false"
                        x-model="qrDataFront"
                        placeholder="https://il-tuo-menu.it"
                        class="mt-1.5 w-full rounded-control border border-border-strong bg-surface-2 px-3 py-2.5 font-mono text-base text-text-primary placeholder:text-text-muted"
                    />

                    @php($qrFrontIssues = $liveIssues['parameters.qr_data_front'] ?? [])
                    @if ($qrFrontIssues !== [])
                        <p class="mt-2 text-xs text-blocked">{{ $qrFrontIssues[0] }}</p>
                    @elseif (($qrDataFront ?? '') !== '')
                        <p class="mt-2 flex items-center gap-1.5 text-xs text-text-secondary">
                            <span class="font-semibold text-ok" aria-hidden="true">✓</span>
                            Indirizzo valido — il QR qui accanto è già aggiornato.
                        </p>
                    @endif

                    {{-- Short-URL advice, humanized (flussi §1 / mockup 02) --}}
                    <div class="mt-4 flex gap-2.5 rounded-card border border-border-subtle border-l-[3px] border-l-tech bg-surface-2 p-3.5">
                        <svg class="mt-0.5 size-4 flex-none text-tech" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path d="M1.5 11.5 11.5 1.5l3 3L4.5 14.5l-3 0z" />
                            <path d="M8 5l1.5 1.5M6 7l1.5 1.5M4 9l1.5 1.5" />
                        </svg>
                        <div>
                            <p class="text-sm text-text-secondary">
                                Un indirizzo corto mantiene la targhetta compatta:
                                ora <span class="mono" x-text="formatMm(size) + ' mm'"></span>.
                            </p>
                            <p class="mt-1 text-xs text-text-muted">
                                Con un indirizzo più lungo il QR ha bisogno di più blocchi
                                e la targhetta cresce di conseguenza.
                            </p>
                        </div>
                    </div>

                    {{-- «Affidabilità di scansione» as an informative row (glossario.md) --}}
                    <dl class="mt-4 flex items-baseline justify-between gap-3 rounded-card border border-border-subtle bg-surface-2 px-3.5 py-3">
                        <dt class="text-sm font-medium text-text-secondary">Affidabilità di scansione</dt>
                        <dd class="text-right">
                            <span class="block text-sm font-semibold text-text-primary">Massima</span>
                            <span class="block text-xs text-text-muted">consigliata — impostata automaticamente dal formato</span>
                        </dd>
                    </dl>
                @else
                    <h2 class="mt-1.5 text-2xl font-semibold text-text-primary">Il tuo logo</h2>
                    <p class="mt-1 text-text-secondary">Caricalo: l'anteprima qui accanto si aggiorna subito.</p>

                    @if ($logoPreviewUrl !== null)
                        <div class="mt-5 flex flex-wrap items-center gap-4">
                            <img src="{{ $logoPreviewUrl }}" alt="Anteprima del logo caricato" class="size-16 rounded-control border border-border-subtle bg-text-primary object-contain p-1" />
                            <label class="cursor-pointer rounded-control border border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition-colors duration-[var(--t-micro)] hover:bg-surface-3 focus-within:outline-2 focus-within:outline-border-strong">
                                <input type="file" wire:model="logoUpload" accept=".png,.svg,image/png,image/svg+xml" class="sr-only" />
                                Sostituisci logo…
                            </label>
                            <flux:button wire:click="removeLogo" variant="ghost" size="sm">Rimuovi</flux:button>
                        </div>
                    @else
                        <label class="mt-5 block cursor-pointer rounded-card border-2 border-dashed border-border-strong bg-surface-2 px-5 py-8 text-center transition-colors duration-[var(--t-micro)] hover:bg-surface-3 focus-within:outline-2 focus-within:outline-border-strong">
                            <input type="file" wire:model="logoUpload" accept=".png,.svg,image/png,image/svg+xml" class="sr-only" />
                            <svg class="mx-auto mb-2.5 size-10 text-text-muted" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 26v6a2 2 0 0 0 2 2h24a2 2 0 0 0 2-2v-6" />
                                <path d="M20 27V8" />
                                <path d="M13 15l7-7 7 7" />
                            </svg>
                            <span class="block text-lg font-semibold text-text-primary">Trascina qui il tuo logo</span>
                            <span class="mt-0.5 block text-xs text-text-muted">PNG o SVG — sfondo trasparente consigliato</span>
                            <span class="mt-3.5 inline-block rounded-control border border-border-strong px-4 py-2 text-sm font-medium text-text-primary">Scegli un file</span>
                        </label>
                    @endif

                    <p wire:loading wire:target="logoUpload" class="mt-2 text-xs text-text-muted">Caricamento…</p>
                    @error('logoUpload')
                        <p class="mt-2 text-xs text-blocked">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex gap-2.5 rounded-card border border-border-subtle border-l-[3px] border-l-tech bg-surface-2 p-3.5">
                        <svg class="mt-0.5 size-4 flex-none text-tech" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <circle cx="8" cy="8" r="6.5" />
                            <path d="M8 5v.5M8 8v3" />
                        </svg>
                        <div>
                            <p class="text-sm text-text-secondary">
                                Il logo finisce al centro della base:
                                {{ $presetLabel }} Ø <span class="mono">{{ rtrim(rtrim(number_format((float) ($presetConfig['defaults']['size'] ?? 0), 2, '.', ''), '0'), '.') }} mm</span>.
                            </p>
                            <p class="mt-1 text-xs text-text-muted">Tracciati semplici e pieni rendono meglio dei dettagli sottili.</p>
                        </div>
                    </div>

                    @if ($preset === 'coin_cart')
                        <p class="mt-4 text-xs text-text-muted">
                            Nota (Reg. CE 2182/2004): un gettone nel formato della moneta da 2&nbsp;€ ricade nella
                            normativa UE su medaglie e gettoni simili alle monete. L'uso commerciale va verificato
                            da un legale — questa nota non è consulenza legale.
                        </p>
                    @endif
                @endif

                @if ($errors->isNotEmpty())
                    <div class="mt-4 rounded-card border border-blocked bg-blocked-surface p-3 text-sm text-blocked" wire:key="guest-submit-errors">
                        <p class="font-semibold">La generazione non è partita:</p>
                        <ul class="mt-1 list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $message)
                                <li wire:key="guest-error-{{ $loop->index }}">{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <flux:button wire:click="submit" variant="primary" class="mt-6 w-full">
                    <span wire:loading.remove wire:target="submit">Crea il file di stampa</span>
                    <span wire:loading wire:target="submit">Creazione in corso…</span>
                </flux:button>
                <p class="mt-2.5 text-center text-xs text-text-muted">
                    {{ (int) config('product.guests.generations_per_hour') }} generazioni all'ora per i visitatori ·
                    i file restano disponibili per {{ (int) config('product.guests.retention_hours') }} ore.
                </p>
            </div>
        @else
            {{-- ============ Passo 3 · Crea e scarica (mockup 03) ============ --}}
            <div wire:key="wizard-step-result" class="rounded-panel border border-border-subtle bg-surface-1 p-6">
                <p class="text-xs font-medium uppercase tracking-[0.08em] text-text-muted">Passo 3 di 3</p>
                <h2 class="mt-1.5 text-2xl font-semibold text-text-primary">Crea e scarica</h2>
                <p class="mt-1 text-text-secondary">
                    Qui sotto trovi lo stato del lavoro e la «Verifica di stampa»;
                    l'anteprima accanto mostrerà il pezzo reale appena è pronto.
                </p>
                <a href="{{ route('home') }}" class="mt-3 inline-block text-sm font-medium text-tech hover:underline">
                    Crea un'altra targhetta →
                </a>
            </div>
        @endif
    @else
    {{-- Header: locked preset or unlocked parametric mode --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div>
            <flux:heading size="lg" level="2">Formato {{ $presetLabel }}</flux:heading>
            <flux:text class="mt-0.5 text-sm">
                @if ($customized)
                    Parametri sbloccati: stai personalizzando il formato {{ $presetLabel }}.
                @else
                    Formato preimpostato e validato. I parametri geometrici sono bloccati.
                @endif
            </flux:text>
        </div>
        @if (! $customized)
            <flux:button wire:click="unlockCustomization" variant="filled" size="sm">
                Personalizza questo formato
            </flux:button>
        @else
            <flux:badge color="amber">Personalizzato</flux:badge>
        @endif
    </div>

    {{-- Preset-specific mandatory notices (contract 05, avvertenze UI) --}}
    @if ($preset === 'coin_cart')
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" wire:key="notice-coin-cart">
            <p class="font-semibold">Avvertenza normativa (Reg. CE 2182/2004)</p>
            <p class="mt-1">
                Un gettone con le dimensioni di una moneta da 2&nbsp;€ ricade nella normativa UE su medaglie e
                gettoni simili alle monete in euro, che prevede bande dimensionali e vincoli di design.
                I gettoni da carrello legittimi esistono e sono diffusi, ma <strong>l'uso commerciale va
                verificato da un legale</strong>. Questa nota non è consulenza legale, ed è corretto dirlo.
            </p>
            <p class="mt-2">
                <strong>Compensazione XY di serie:</strong> il preset applica {{ config('product.presets.coin_cart.defaults.xy_comp') }}&nbsp;mm
                per lato, perché una stampa FDM di 25.75&nbsp;mm nominali esce a 25.85–25.95 e si incepperebbe
                nella fessura del carrello. Misura il primo pezzo col calibro e affina il valore.
            </p>
        </div>
    @elseif ($preset === 'coaster')
        <div class="rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100" wire:key="notice-coaster">
            <p>
                <strong>Capacità dell'incavo: <span x-text="formatMm(capacityMl)"></span> ml.</strong>
                Il bordo antigoccia trattiene la condensa del bicchiere, ma il sottobicchiere non è impermeabile.
                Materiale <strong>PETG</strong>: resiste alla lavastoviglie, dove il PLA (Tg ~60&nbsp;°C) si imbarcherebbe.
            </p>
        </div>
    @elseif ($preset === 'menutag')
        <div class="rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100" wire:key="notice-menutag">
            <p>
                Configurazione validata in stampa reale (58.8 × 3.0&nbsp;mm, incisione 0.6, modulo QR 1.200&nbsp;mm).
                Per il QR la resa <strong>a filo bicolore (inlay)</strong> è consigliata: contrasto reale di
                riflettanza, scansione affidabile in ogni condizione di luce — richiede però stampa multicolore (AMS).
            </p>
        </div>
    @endif

    {{-- ============ QR content ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800" x-show="front.includes('qr') || back.includes('qr') || {{ $customized ? 'true' : 'false' }}">
        <flux:heading size="lg" level="3">Contenuto del QR</flux:heading>

        <div class="mt-3 space-y-4">
            <div x-show="front.includes('qr')" x-cloak>
                <label class="block text-sm font-medium" for="qr-data-front">Indirizzo del QR frontale</label>
                <input
                    id="qr-data-front"
                    type="url"
                    x-model="qrDataFront"
                    placeholder="{{ config('product.qr.demo_url') }}"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                {{-- Short-URL advice, exactly where the URL is typed (spec §5.2) --}}
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <strong>Consiglio: un URL breve mantiene il formato base.</strong>
                    Un indirizzo compatto o un redirect tiene la targhetta a
                    <span x-text="formatMm(window.menuTagProduct.qr.floor_square_mm)"></span> mm invece di farla crescere —
                    e incide sul costo del pezzo.
                    Adesso: <span x-text="qrUrlBytes"></span> byte → QR versione <span x-text="qrVersion ?? '—'"></span>,
                    minimo <span x-text="formatMm(qrMinSquare)"></span> mm di lato
                    oppure <span x-text="formatMm(qrMinCircle)"></span> mm di diametro.
                </p>
                @error('parameters.qr_data_front')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="back.includes('qr')" x-cloak>
                <label class="block text-sm font-medium" for="qr-data-back">Indirizzo del QR posteriore</label>
                <input
                    id="qr-data-back"
                    type="url"
                    x-model="qrDataBack"
                    placeholder="https://esempio.it/menu-en"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Puoi usare due QR diversi sulle due facce, ad esempio menù italiano e inglese.
                    Anche qui un URL breve mantiene il formato base.
                </p>
                @error('parameters.qr_data_back')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div x-show="front.includes('qr') || back.includes('qr')">
                <label class="block text-sm font-medium" for="qr-ec">Correzione d'errore</label>
                <select
                    id="qr-ec"
                    x-model="qrEc"
                    :disabled="front === 'qr_logo' || back === 'qr_logo'"
                    class="mt-1 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm disabled:opacity-60 dark:border-zinc-600 dark:bg-zinc-900"
                >
                    <option value="L">L — minima (più capacità)</option>
                    <option value="M">M — media</option>
                    <option value="Q">Q — alta</option>
                    <option value="H">H — massima (consigliata)</option>
                </select>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-show="front === 'qr_logo' || back === 'qr_logo'" x-cloak>
                    Con il logo al centro del QR la correzione è forzata a <strong>H</strong>: il logo copre parte del simbolo.
                </p>
            </div>

            {{-- Circle + QR: the threshold rises and the module shrinks (§3.2) --}}
            <div
                x-show="shape === 'circle' && (front.includes('qr') || back.includes('qr'))"
                x-cloak
                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100"
            >
                Sul cerchio il QR va inscritto sulla diagonale: la soglia sale a
                <span x-text="formatMm(window.menuTagProduct.qr.floor_circle_mm)"></span> mm di diametro
                (contro <span x-text="formatMm(window.menuTagProduct.qr.floor_square_mm)"></span> di lato del quadrato)
                e, a parità di ingombro, il modulo del quadrato è circa il
                <span x-text="squareAdvantagePct"></span>% più grande. Il cerchio resta supportato: è il costo della scelta.
            </div>
        </div>
    </div>

    {{-- ============ Faces (custom mode) ============ --}}
    @if ($customized)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800" wire:key="faces-section">
            <flux:heading size="lg" level="3">Contenuto per faccia</flux:heading>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach (['front' => 'Faccia frontale', 'back' => 'Faccia posteriore'] as $face => $faceLabel)
                    <div wire:key="face-{{ $face }}">
                        <label class="block text-sm font-medium" for="face-{{ $face }}">{{ $faceLabel }}</label>
                        <select
                            id="face-{{ $face }}"
                            x-model="{{ $face }}"
                            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                        >
                            <option value="none">Nessun contenuto</option>
                            <option value="logo">Logo</option>
                            <option value="qr" :disabled="!qrAvailable">QR</option>
                            <option value="qr_logo" :disabled="!qrAvailable">QR con logo al centro</option>
                        </select>
                    </div>
                @endforeach
            </div>
            {{-- QR options disabled below the shape-dependent threshold: explain WHY + the minimum size (spec §3.2) --}}
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300" x-show="!qrAvailable" x-cloak>
                Le opzioni QR sono disabilitate: alla dimensione attuale
                (<span x-text="formatMm(size)"></span> mm) il passo del modulo scenderebbe sotto
                {{ config('product.qr.min_pitch_mm') }} mm e il codice diventerebbe inaffidabile alla scansione.
                Con l'indirizzo inserito il QR richiede almeno
                <span x-text="formatMm(qrMinSquare)"></span> mm di lato,
                oppure <span x-text="formatMm(qrMinCircle)"></span> mm di diametro.
            </p>
        </div>
    @endif

    {{-- ============ Product bands + functional minimum (spec §3.2 / §8.8) ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="3">Fasce di prodotto</flux:heading>
        <ul class="mt-3 space-y-1 text-sm">
            <li
                class="flex items-center gap-2 rounded-lg px-2 py-1"
                :class="effectiveSize < nfcPlanMin(25) && 'bg-zinc-100 font-medium dark:bg-zinc-700'"
            >
                <span class="text-zinc-500 dark:text-zinc-400 tabular-nums" x-text="`${window.menuTagProduct.size_min_mm} – ${(nfcPlanMin(25) - 0.01).toFixed(2)} mm`"></span>
                <span>logo + NFC Ø22 — il tag Ø25 non entra (parete radiale insufficiente)</span>
            </li>
            <li
                class="flex items-center gap-2 rounded-lg px-2 py-1"
                :class="effectiveSize >= nfcPlanMin(25) && size < (shape === 'square' ? window.menuTagProduct.qr.floor_square_mm : window.menuTagProduct.qr.floor_circle_mm) && 'bg-zinc-100 font-medium dark:bg-zinc-700'"
            >
                <span class="text-zinc-500 dark:text-zinc-400 tabular-nums" x-text="`${nfcPlanMin(25)} – ${((shape === 'square' ? window.menuTagProduct.qr.floor_square_mm : window.menuTagProduct.qr.floor_circle_mm) - 0.01).toFixed(2)} mm`"></span>
                <span>logo + NFC Ø22/Ø25 — formato «gettone», accesso solo NFC</span>
            </li>
            <li
                class="flex items-center gap-2 rounded-lg px-2 py-1"
                :class="size >= (shape === 'square' ? window.menuTagProduct.qr.floor_square_mm : window.menuTagProduct.qr.floor_circle_mm) && 'bg-zinc-100 font-medium dark:bg-zinc-700'"
            >
                <span class="text-zinc-500 dark:text-zinc-400 tabular-nums" x-text="`≥ ${shape === 'square' ? window.menuTagProduct.qr.floor_square_mm : window.menuTagProduct.qr.floor_circle_mm} mm`"></span>
                <span>logo + QR + NFC — formato sottobicchiere completo, il prodotto principale</span>
            </li>
        </ul>
        <p class="mt-3 border-t border-zinc-100 pt-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            Minimo di prodotto: <strong x-text="window.menuTagProduct.size_min_mm + ' mm'"></strong> (la moneta da 2&nbsp;€).
            Minimo funzionale per questa configurazione:
            <strong x-text="(Math.round(minFunctional.size * 100) / 100) + ' mm'"></strong>
            — <span x-text="minFunctional.reason"></span>.
        </p>
    </div>

    {{-- Pending size adjustment proposal: NEVER applied silently over a manual size (spec §5.2) --}}
    @if ($proposedSize !== null)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" wire:key="size-proposal">
            <p class="font-semibold">La dimensione impostata a mano non basta più.</p>
            <p class="mt-1">{{ $proposedSizeReason }}</p>
            <flux:button wire:click="acceptProposedSize" variant="primary" size="sm" class="mt-2">
                Adegua a {{ rtrim(rtrim(number_format($proposedSize, 2, '.', ''), '0'), '.') }} mm
            </flux:button>
        </div>
    @endif

    {{-- ============ Graphic rendering mode (§3.6) ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="3">Resa della grafica</flux:heading>
        <div class="mt-3 grid gap-2 sm:grid-cols-3">
            @foreach ([
                'engrave' => ['Incisa', 'Scanalature: legge per ombra, trattiene liquido.'],
                'relief' => ['Rilievo', 'Sporgenze pulibili, nessun requisito hardware.'],
                'inlay' => ['A filo bicolore', 'Superficie liscia, contrasto reale: la scelta giusta per il QR.'],
            ] as $modeValue => [$modeLabel, $modeHint])
                @php($rejected = in_array($modeValue, $rejectedModes, true))
                <label
                    wire:key="mode-{{ $modeValue }}"
                    class="flex cursor-pointer flex-col rounded-lg border p-3 text-sm transition {{ $rejected ? 'cursor-not-allowed opacity-50' : '' }}"
                    :class="mode === '{{ $modeValue }}' ? 'border-accent ring-1 ring-accent' : 'border-zinc-300 dark:border-zinc-600'"
                >
                    <span class="flex items-center gap-2 font-medium">
                        <input type="radio" name="mode" value="{{ $modeValue }}" x-model="mode" @disabled($rejected) class="accent-current" />
                        {{ $modeLabel }}
                        @if ($modeValue === $recommendedMode)
                            <flux:badge size="sm" color="lime">consigliata</flux:badge>
                        @endif
                    </span>
                    <span class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $modeHint }}</span>
                </label>
            @endforeach
        </div>

        @if (in_array('engrave', $rejectedModes, true))
            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                Sul Coaster la grafica <strong>incisa non è disponibile</strong>: le scanalature restano piene di
                liquido, si asciugano male e in poco tempo sembrano sporche — un problema di igiene in ambito
                HORECA, non di estetica. Usa il rilievo o l'intarsio a filo.
            </p>
        @endif

        {{-- Inlay: multicolor requirement + bichromatic layer count (§3.6) --}}
        <div x-show="mode === 'inlay'" x-cloak class="mt-3 rounded-lg border border-sky-300 bg-sky-50 p-3 text-xs text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100">
            <p class="font-semibold">L'intarsio richiede una stampante multicolore (AMS o cambio filamento manuale).</p>
            <p class="mt-1">
                Produce due STL complanari (corpo + accento). Con la profondità attuale di
                <span x-text="formatMm(depth)"></span> mm e layer da
                <span x-text="resolvedLayerHeight"></span> mm servono
                <strong><span x-text="inlayBicolorLayers"></span> layer bicromatici</strong>,
                ognuno con il suo spurgo: la profondità proposta è
                {{ config('product.inlay.default_depth_mm') }} mm proprio per contenerli.
            </p>
            <p class="mt-1">
                Senza hardware multicolore l'alternativa universale è il <strong>rilievo</strong>, oppure
                un'incisione riempita a mano con vernice acrilica dopo la stampa.
            </p>
        </div>
    </div>

    {{-- ============ NFC ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="3">Tag NFC</flux:heading>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" x-model="nfc" class="size-4 accent-current" />
                Annega un tag NFC nella targhetta
            </label>
        </div>

        <div x-show="nfc" x-cloak class="mt-3 space-y-3">
            <div class="flex flex-wrap gap-3">
                @foreach ([22, 25] as $diameter)
                    <label
                        wire:key="tag-diameter-{{ $diameter }}"
                        class="flex items-center gap-2 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-600"
                        :class="(!nfcTagAvailable({{ $diameter }}) || {{ json_encode(! in_array($diameter, $allowedNfcTags, true)) }}) && 'cursor-not-allowed opacity-50'"
                    >
                        <input
                            type="radio"
                            name="tagDiameter"
                            value="{{ $diameter }}"
                            x-model.number="tagDiameter"
                            :disabled="!nfcTagAvailable({{ $diameter }}) || {{ json_encode(! in_array($diameter, $allowedNfcTags, true)) }}"
                            class="accent-current"
                        />
                        Ø{{ $diameter }} mm
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400" x-show="!nfcTagAvailable(25)" x-cloak>
                Il tag Ø25 richiede una pianta effettiva di almeno <span x-text="formatMm(nfcPlanMin(25))"></span> mm
                (tag + gioco radiale + pareti minime): alla dimensione attuale non lascerebbe la parete richiesta.
            </p>
            @if (! in_array(25, $allowedNfcTags, true))
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Il preset {{ $presetLabel }} supporta solo il tag Ø22: un Ø25 lascerebbe 0.175 mm di parete radiale.
                </p>
            @endif

            <div>
                <label class="block text-sm font-medium" for="tag-thickness">Spessore reale del tag (mm)</label>
                <input
                    id="tag-thickness"
                    type="number" step="0.05" min="{{ config('product.nfc.tag_thickness_range_mm.0') }}" max="{{ config('product.nfc.tag_thickness_range_mm.1') }}"
                    x-model.number="tagThickness"
                    class="mt-1 w-32 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Misuralo col calibro: la tasca viene calcolata sul valore dichiarato, con
                    {{ config('product.nfc.axial_clearance_mm') }} mm di gioco assiale.
                </p>
                @error('parameters.tag_thickness')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- ============ Geometry (locked until "personalizza questo formato") ============ --}}
    <fieldset
        @disabled(! $customized)
        class="rounded-xl border border-zinc-200 bg-white p-4 disabled:opacity-70 dark:border-zinc-700 dark:bg-zinc-800"
    >
        <div class="flex items-center justify-between">
            <flux:heading size="lg" level="3">Geometria</flux:heading>
            @if (! $customized)
                <span class="text-xs text-zinc-500 dark:text-zinc-400">
                    Bloccata dal formato — usa «Personalizza questo formato»
                </span>
            @endif
        </div>

        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div>
                <span class="block text-sm font-medium">Forma</span>
                <div class="mt-1 flex gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="shape" value="square" x-model="shape" class="accent-current" /> Quadrato (lato)
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="shape" value="circle" x-model="shape" class="accent-current" /> Cerchio (diametro)
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium" for="size">
                    Dimensione (mm) — <span x-text="shape === 'square' ? 'lato' : 'diametro'"></span>
                </label>
                <input
                    id="size"
                    type="number" step="0.1" min="{{ config('product.size_min_mm') }}" max="{{ config('product.size_max_mm') }}"
                    x-model.number="size"
                    x-on:input="sizeTouched = true"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                @error('parameters.size')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- Fillet: ONLY for squares (spec §6 WS-4) --}}
            <div x-show="shape === 'square'" x-cloak>
                <label class="block text-sm font-medium" for="fillet">Raggio smussatura angoli (mm)</label>
                <input
                    id="fillet"
                    type="number" step="0.5" min="0"
                    x-model.number="fillet"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                @error('parameters.fillet')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium" for="thickness">Spessore (mm)</label>
                <input
                    id="thickness"
                    type="number" step="0.1" min="{{ config('product.thickness_min_mm') }}" max="{{ config('product.thickness_max_mm') }}"
                    x-model.number="thickness"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                @error('parameters.thickness')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <span class="block text-sm font-medium">Profilo base</span>
                <div class="mt-1 flex gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="baseProfile" value="flat" x-model="baseProfile" class="accent-current" /> Piatto
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="baseProfile" value="rimmed" x-model="baseProfile" class="accent-current" /> Bordo antigoccia
                    </label>
                </div>
            </div>

            <div x-show="baseProfile === 'rimmed'" x-cloak class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium" for="rim-width">Bordo (mm)</label>
                    <input id="rim-width" type="number" step="0.5" min="0" x-model.number="rimWidth"
                        class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                </div>
                <div>
                    <label class="block text-sm font-medium" for="recess-depth">Incavo (mm)</label>
                    <input id="recess-depth" type="number" step="0.1" min="0" x-model.number="recessDepth"
                        class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium" for="depth">
                    <span x-text="mode === 'relief' ? 'Altezza della grafica (mm)' : 'Profondità della grafica (mm)'"></span>
                </label>
                <input
                    id="depth"
                    type="number" step="0.1"
                    min="{{ config('product.graphics.depth_min_mm') }}" max="{{ config('product.graphics.depth_max_mm') }}"
                    x-model.number="depth"
                    x-on:input="depthTouched = true"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-show="mode === 'relief' && baseProfile === 'rimmed'" x-cloak>
                    In rilievo con bordo antigoccia l'altezza deve restare <strong>sotto il bordo</strong>
                    (&lt; <span x-text="formatMm(recessDepth)"></span> mm), altrimenti il bicchiere appoggia sul rilievo.
                </p>
                @error('parameters.depth')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium" for="nozzle">Ugello (mm)</label>
                <select id="nozzle" x-model="nozzle"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                    <option value="0.2">0.2 — massimo dettaglio</option>
                    <option value="0.4">0.4 — standard</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium" for="layer-height">Altezza layer (mm)</label>
                <input
                    id="layer-height"
                    type="number" step="0.01"
                    x-model.number="layerHeight"
                    :placeholder="nozzleRange ? `default ${nozzleRange.layer_default}` : ''"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400" x-show="nozzleRange">
                    Con ugello <span x-text="nozzle"></span>: da <span x-text="nozzleRange?.layer_min"></span>
                    a <span x-text="nozzleRange?.layer_max"></span> mm. Vuoto = default del profilo stampante.
                </p>
                @error('parameters.layer_height')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium" for="material">Materiale</label>
                <select id="material" x-model="material"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                    <option value="pla-matte">PLA matte</option>
                    <option value="petg">PETG (lavastoviglie)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium" for="xy-comp">Compensazione XY (mm per lato)</label>
                <input
                    id="xy-comp"
                    type="number" step="0.05"
                    min="{{ config('product.xy_comp_range_mm.0') }}" max="{{ config('product.xy_comp_range_mm.1') }}"
                    x-model.number="xyComp"
                    class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Negativa = pezzo più piccolo. Dimensione effettiva stampata:
                    <span x-text="formatMm(effectiveSize)"></span> mm.
                </p>
            </div>
        </div>
    </fieldset>

    {{-- ============ Logo ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="3">Logo</flux:heading>
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
            Consigliati <strong>PNG monocromatici ad alto contrasto o SVG vettoriali</strong>:
            sfumature e fotografie non si prestano all'estrusione.
            Massimo {{ number_format((int) config('product.guests.upload_max_kb') / 1024, 0) }} MB, solo PNG o SVG.
        </p>

        <div class="mt-3 flex flex-wrap items-center gap-4">
            @if ($logoPreviewUrl !== null)
                <img src="{{ $logoPreviewUrl }}" alt="Anteprima del logo caricato" class="size-16 rounded-lg border border-zinc-200 bg-white object-contain p-1 dark:border-zinc-600" />
                <flux:button wire:click="removeLogo" variant="ghost" size="sm">Rimuovi logo</flux:button>
            @endif
            <label class="cursor-pointer rounded-lg border border-dashed border-zinc-400 px-4 py-2 text-sm hover:border-zinc-600 dark:border-zinc-500">
                <input type="file" wire:model="logoUpload" accept=".png,.svg,image/png,image/svg+xml" class="hidden" />
                {{ $logoPreviewUrl !== null ? 'Sostituisci logo…' : 'Carica un logo…' }}
            </label>
            <span wire:loading wire:target="logoUpload" class="text-xs text-zinc-500">Caricamento…</span>
        </div>
        @error('logoUpload')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
        @error('parameters.logo_asset_id')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ============ Series production (spec §5.2) ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <flux:heading size="lg" level="3">Produzione in serie</flux:heading>
        <div class="mt-3 flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium" for="plate">Piastra da N pezzi</label>
                <input
                    id="plate"
                    type="number" step="1" min="1" max="{{ config('product.plate.max_pieces') }}"
                    x-model.number="plate"
                    class="mt-1 w-28 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
                />
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                L'STL esce già con l'array spaziato di {{ config('printers.profiles.a1mini.plate_spacing_mm') }} mm.
                Sul formato {{ $presetLabel }} l'A1 mini ospita <strong>{{ $plateSuggested }} pezzi</strong> per piastra.
            </p>
        </div>
        @error('parameters.plate')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    </div>

    {{-- ============ Label + live issues + submit ============ --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
        <label class="block text-sm font-medium" for="label">Nome della targhetta (facoltativo)</label>
        <input
            id="label"
            type="text"
            wire:model="label"
            maxlength="255"
            placeholder="Es. «Trattoria Da Mario — menù IT»"
            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-900"
        />
        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Ti aiuta a ritrovarla nello storico della dashboard.</p>

        {{-- Non-blocking live validation issues (spec §5.2: BEFORE wasting a job) --}}
        @if ($liveIssues !== [])
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100" wire:key="live-issues">
                <p class="font-semibold">Da sistemare prima di generare:</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach ($liveIssues as $field => $messages)
                        @foreach ($messages as $message)
                            <li wire:key="issue-{{ $field }}-{{ $loop->index }}">{{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Advisories that never block (contract 02: custom + rimmed + engrave) --}}
        @if ($liveWarnings !== [])
            <div class="mt-4 rounded-lg border border-sky-300 bg-sky-50 p-3 text-sm text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100" wire:key="live-warnings">
                <p class="font-semibold">Avvertenze (non bloccanti):</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach ($liveWarnings as $warning)
                        <li wire:key="warning-{{ $loop->index }}">{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($errors->isNotEmpty())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100" wire:key="submit-errors">
                <p class="font-semibold">La generazione non è partita:</p>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li wire:key="error-{{ $loop->index }}">{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-4 flex items-center gap-3">
            <flux:button wire:click="submit" variant="primary">
                <span wire:loading.remove wire:target="submit">Genera l'STL</span>
                <span wire:loading wire:target="submit">Invio in corso…</span>
            </flux:button>
        </div>
    </div>
    @endif
</section>
