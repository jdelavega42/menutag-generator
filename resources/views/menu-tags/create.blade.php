<x-layouts::public title="Configuratore">
    @guest
        {{--
            Guest home = the 3-step wizard (flussi.md §1, mockup 01/02): the
            wizard column on the left, the 3D viewer protagonist on the right
            (≥ 50 % of the useful width, above the fold at 1280×800). JobStatus
            and the «Verifica di stampa» live under the wizard: they are empty
            until step 3. The parametric mode is not rendered here — and the
            server refuses it anyway (Configurator gate).
        --}}
        <section class="mb-5 max-w-3xl">
            <flux:heading size="xl" level="1">
                Sottobicchiere e menù digitale. Lo stesso oggetto.
            </flux:heading>
            <flux:text class="mt-2 text-base">
                Tre passi e nessun termine tecnico: scegli il formato, dicci cosa deve mostrare,
                scarica il file di stampa (STL). Zero complicazioni — al resto pensiamo noi.
            </flux:text>
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,47fr)_minmax(0,53fr)]">
            <div class="space-y-4">
                <livewire:configurator wire:key="configurator" />
                <livewire:job-status wire:key="job-status" />
                <livewire:printability-report wire:key="printability-report" />
            </div>

            <div class="lg:sticky lg:top-6 lg:self-start">
                <livewire:preview-viewer wire:key="preview-viewer" />
            </div>
        </div>
    @else
        {{-- Registered home: full studio (parametric sections restyled by R-3). --}}
        <section class="mb-8 max-w-3xl">
            <flux:heading size="xl" level="1">
                Sottobicchiere e menù digitale. Lo stesso oggetto.
            </flux:heading>
            <flux:text class="mt-3 text-base">
                Un MenuTag è una targhetta rigida stampata in 3D che apre il menù del tuo locale
                in due modi: <strong>inquadrando il QR</strong> inciso sulla superficie o
                <strong>avvicinando lo smartphone</strong> al tag NFC annegato all'interno.
                Il formato è quello di un sottobicchiere, e non per estetica: appoggiato sotto il
                bicchiere resta sempre sul tavolo, a portata di mano, senza espositori.
                La doppia funzione è ciò che lo rende un prodotto da vendere, non un gadget.
            </flux:text>
        </section>

        <livewire:preset-picker wire:key="preset-picker" />

        <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
            <div>
                <livewire:configurator wire:key="configurator" />
            </div>

            <div class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <livewire:preview-viewer wire:key="preview-viewer" />
                <livewire:job-status wire:key="job-status" />
                <livewire:printability-report wire:key="printability-report" />
            </div>
        </div>
    @endguest
</x-layouts::public>
