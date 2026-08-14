<x-layouts::app title="Il tuo archivio">
    <div class="flex h-full w-full flex-1 flex-col gap-8">
        <div>
            <h1 class="text-2xl font-semibold text-text-primary">Il tuo archivio</h1>
            <p class="mt-1 max-w-2xl text-sm text-text-secondary">
                Modelli generati, loghi riutilizzabili e QR salvati: tutto quello che serve per
                produrre in serie per clienti diversi. «Duplica» riapre una configurazione nel
                configuratore, pronta da modificare e rigenerare.
            </p>
        </div>

        <livewire:tag-history wire:key="tag-history" />

        <div class="grid gap-8 lg:grid-cols-2">
            <livewire:logo-library wire:key="logo-library" />
            <livewire:qr-preset-manager wire:key="qr-preset-manager" />
        </div>
    </div>
</x-layouts::app>
