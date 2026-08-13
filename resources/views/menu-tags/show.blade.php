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
        <div class="flex gap-2">
            <flux:button :href="route('home', ['duplica' => $menuTag->id])" variant="ghost" size="sm">
                Duplica e modifica
            </flux:button>
            <flux:button :href="route('home')" variant="ghost" size="sm">
                Nuova targhetta
            </flux:button>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,6fr)_minmax(0,6fr)]">
        <div class="space-y-4 lg:sticky lg:top-6 lg:self-start">
            <livewire:preview-viewer :menu-tag="$menuTag" wire:key="preview-viewer-{{ $menuTag->id }}" />
            <livewire:job-status :menu-tag="$menuTag" wire:key="job-status-{{ $menuTag->id }}" />
        </div>

        <div class="space-y-4">
            <livewire:printability-report :menu-tag="$menuTag" wire:key="printability-report-{{ $menuTag->id }}" />

            @php($parameters = $menuTag->parameters)
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:heading size="lg" level="2">Parametri della configurazione</flux:heading>
                <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-3">
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Forma</dt><dd>{{ $parameters->shape->value === 'square' ? 'Quadrato' : 'Cerchio' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Dimensione</dt><dd>{{ $parameters->size }} mm</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Spessore</dt><dd>{{ $parameters->thickness }} mm</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Profilo base</dt><dd>{{ $parameters->baseProfile->value === 'rimmed' ? 'Bordo antigoccia' : 'Piatto' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Faccia frontale</dt><dd>{{ ['none' => 'Nessuna', 'logo' => 'Logo', 'qr' => 'QR', 'qr_logo' => 'QR + logo'][$parameters->front->value] }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Faccia posteriore</dt><dd>{{ ['none' => 'Nessuna', 'logo' => 'Logo', 'qr' => 'QR', 'qr_logo' => 'QR + logo'][$parameters->back->value] }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Resa</dt><dd>{{ ['engrave' => 'Incisa', 'relief' => 'Rilievo', 'inlay' => 'Intarsio bicolore'][$parameters->mode->value] }} ({{ $parameters->depth }} mm)</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">NFC</dt><dd>{{ $parameters->nfc ? 'Sì, Ø'.$parameters->tagDiameter->value : 'No' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Materiale</dt><dd>{{ $parameters->material->value }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Ugello / layer</dt><dd>{{ $parameters->nozzle->value }} mm / {{ $parameters->layerHeight ?? 'default' }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Pezzi per piastra</dt><dd>{{ $parameters->plate }}</dd></div>
                    <div><dt class="text-zinc-500 dark:text-zinc-400">Compensazione XY</dt><dd>{{ $parameters->xyComp }} mm/lato</dd></div>
                    @if ($parameters->qrDataFront)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-zinc-500 dark:text-zinc-400">QR frontale</dt><dd class="break-all">{{ $parameters->qrDataFront }}</dd></div>
                    @endif
                    @if ($parameters->qrDataBack)
                        <div class="col-span-2 sm:col-span-3"><dt class="text-zinc-500 dark:text-zinc-400">QR posteriore</dt><dd class="break-all">{{ $parameters->qrDataBack }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>
</x-layouts::public>
