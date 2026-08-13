<x-layouts::app title="Dashboard">
    <div class="flex h-full w-full flex-1 flex-col gap-8">
        <div>
            <flux:heading size="xl" level="1">La tua produzione</flux:heading>
            <flux:text class="mt-1 text-sm">
                Storico targhette, loghi riutilizzabili e QR salvati: tutto quello che serve per
                generare in serie per clienti diversi. «Duplica» riapre una configurazione nel
                configuratore, pronta da modificare e rigenerare.
            </flux:text>
        </div>

        <livewire:tag-history wire:key="tag-history" />

        <div class="grid gap-8 lg:grid-cols-2">
            <livewire:logo-library wire:key="logo-library" />
            <livewire:qr-preset-manager wire:key="qr-preset-manager" />
        </div>
    </div>
</x-layouts::app>
