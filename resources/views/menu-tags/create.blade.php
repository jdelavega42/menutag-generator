<x-layouts::public title="Configuratore">
    {{-- The double function IS the selling point (spec §1.1). --}}
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
        <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            Configura, guarda l'anteprima 3D e scarica l'STL pronto per la stampa — anche senza registrarti.
            Menù, carta dei vini, allergeni, Wi-Fi per gli ospiti, recensioni: qualunque indirizzo diventa una targhetta.
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
</x-layouts::public>
