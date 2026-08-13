<x-layouts::public :title="$menuTag->label ?? 'Targhetta #'.$menuTag->id">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">
                {{ $menuTag->label ?? 'Targhetta #'.$menuTag->id }}
            </flux:heading>
            <flux:text class="mt-1 text-sm">
                Formato {{ ['menutag' => 'MenuTag', 'coaster' => 'Coaster', 'coin_cart' => 'Coin Cart'][$menuTag->preset->value] ?? $menuTag->preset->value }}
                @if ($menuTag->customized) · personalizzato @endif
                · creata il {{ $menuTag->created_at?->format('d/m/Y H:i') }}
            </flux:text>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @auth
                <flux:button :href="route('home', ['duplica' => $menuTag->id])" variant="ghost" size="sm">
                    Duplica e modifica
                </flux:button>
            @endauth
            <flux:button :href="route('home')" variant="ghost" size="sm">
                Nuova targhetta
            </flux:button>
        </div>
    </div>

    @guest
        {{--
            Duplication re-opens the parametric mode, which is registered
            only (restyle §5.4): contextual CTA, never a dead end. The
            download and the guide below keep working exactly as before.
        --}}
        <aside class="mb-6 rounded-card border border-dashed border-border-strong bg-surface-1 p-4" aria-label="Duplicazione riservata agli utenti registrati">
            <p class="text-sm font-semibold text-text-primary">Vuoi riaprire o duplicare questo modello?</p>
            <p class="mt-1 text-sm text-text-secondary">
                Serve l'archivio dello Studio: registrati — i modelli creati da ospite ti seguiranno.
            </p>
            <div class="mt-3 flex items-center gap-4">
                <flux:button :href="route('register')" variant="primary" size="sm">Registrati</flux:button>
                <a href="{{ route('studio-promo') }}" class="text-sm font-medium text-tech hover:underline" wire:navigate>Scopri lo Studio completo</a>
            </div>
        </aside>
    @endguest

    <div class="grid gap-8 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
        <div class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <livewire:preview-viewer :menu-tag="$menuTag" wire:key="preview-viewer-{{ $menuTag->id }}" />
            <livewire:job-status :menu-tag="$menuTag" wire:key="job-status-{{ $menuTag->id }}" />
        </div>

        <div class="space-y-4">
            <livewire:printability-report :menu-tag="$menuTag" wire:key="printability-report-{{ $menuTag->id }}" />

            @php($parameters = $menuTag->parameters)
            <section class="rounded-panel border border-border-subtle bg-surface-1 p-5">
                <flux:heading size="lg" level="2">Parametri della configurazione</flux:heading>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2.5 text-sm sm:grid-cols-3">
                    <div><dt class="text-text-muted">Forma</dt><dd class="text-text-primary">{{ $parameters->shape->value === 'square' ? 'Quadrato' : 'Cerchio' }}</dd></div>
                    <div><dt class="text-text-muted">Dimensione</dt><dd><span class="mono">{{ $parameters->size }} mm</span></dd></div>
                    <div><dt class="text-text-muted">Spessore</dt><dd><span class="mono">{{ $parameters->thickness }} mm</span></dd></div>
                    <div><dt class="text-text-muted">Profilo base</dt><dd class="text-text-primary">{{ $parameters->baseProfile->value === 'rimmed' ? 'Bordo antigoccia' : 'Piatto' }}</dd></div>
                    <div><dt class="text-text-muted">Faccia frontale</dt><dd class="text-text-primary">{{ ['none' => 'Nessuna', 'logo' => 'Logo', 'qr' => 'QR', 'qr_logo' => 'QR + logo'][$parameters->front->value] }}</dd></div>
                    <div><dt class="text-text-muted">Faccia posteriore</dt><dd class="text-text-primary">{{ ['none' => 'Nessuna', 'logo' => 'Logo', 'qr' => 'QR', 'qr_logo' => 'QR + logo'][$parameters->back->value] }}</dd></div>
                    <div><dt class="text-text-muted">Resa</dt><dd class="text-text-primary">{{ ['engrave' => 'Incisa', 'relief' => 'Rilievo', 'inlay' => 'A filo bicolore'][$parameters->mode->value] }} (<span class="mono">{{ $parameters->depth }} mm</span>)</dd></div>
                    <div><dt class="text-text-muted">Tag NFC</dt><dd class="text-text-primary">@if ($parameters->nfc) Sì, Ø <span class="mono">{{ $parameters->tagDiameter->value }} mm</span> @else No @endif</dd></div>
                    <div><dt class="text-text-muted">Materiale</dt><dd class="text-text-primary">{{ ['pla-matte' => 'PLA matte', 'petg' => 'PETG (lavastoviglie)'][$parameters->material->value] ?? $parameters->material->value }}</dd></div>
                    <div><dt class="text-text-muted">Qualità di stampa</dt><dd class="text-text-primary">{{ $parameters->nozzle->value === '0.2' ? 'Dettaglio fine' : 'Standard' }} (ugello <span class="mono">{{ $parameters->nozzle->value }} mm</span>)</dd></div>
                    <div><dt class="text-text-muted">Strati di stampa</dt><dd class="text-text-primary">@if ($parameters->layerHeight !== null)<span class="mono">{{ $parameters->layerHeight }} mm</span>@else default del profilo @endif</dd></div>
                    <div><dt class="text-text-muted">Pezzi per stampata</dt><dd><span class="mono">{{ $parameters->plate }}</span></dd></div>
                    <div><dt class="text-text-muted">Adattamento alla misura reale</dt><dd><span class="mono">{{ $parameters->xyComp }} mm</span> per lato</dd></div>
                    @if ($parameters->qrDataFront)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-text-muted">Indirizzo del QR frontale</dt><dd class="break-all text-text-primary">{{ $parameters->qrDataFront }}</dd></div>
                    @endif
                    @if ($parameters->qrDataBack)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-text-muted">Indirizzo del QR posteriore</dt><dd class="break-all text-text-primary">{{ $parameters->qrDataBack }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>
</x-layouts::public>
