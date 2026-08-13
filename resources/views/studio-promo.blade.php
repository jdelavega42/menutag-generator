{{--
    Studio promo page (restyle §5.3, flussi.md §3) — MINIMAL PLACEHOLDER.
    R-4 builds the full page (mockup 04). Only TRUE benefits, no invented
    numbers or testimonials (restyle §9).
--}}
<x-layouts::public title="Lo Studio completo">
    <section class="mx-auto max-w-2xl py-8">
        <flux:heading size="xl" level="1">Lo Studio completo</flux:heading>
        <flux:text class="mt-3 text-base">
            Registrandoti sblocchi tutto quello che il generatore sa fare:
        </flux:text>

        <ul class="mt-4 space-y-2">
            @foreach ([
                'L\'archivio dei tuoi modelli: ogni targhetta salvata, pronta da riaprire',
                'La duplicazione in serie, per chi produce per più clienti o più tavoli',
                'Loghi e QR salvati, pronti al riuso',
                'La personalizzazione completa: dimensioni, fronte e retro, rese, tag NFC',
            ] as $benefit)
                <li class="relative pl-3.5 text-sm text-text-secondary before:absolute before:left-0 before:top-[0.55em] before:size-[5px] before:rounded-[1px] before:bg-text-muted before:content-['']">
                    {{ $benefit }}
                </li>
            @endforeach
        </ul>

        <flux:text class="mt-4 text-sm text-text-muted">
            I modelli creati da ospite ti seguiranno al momento della registrazione.
        </flux:text>

        <div class="mt-6 flex items-center gap-4">
            @if (Route::has('register'))
                <flux:button :href="route('register')" variant="primary">Registrati</flux:button>
            @endif
            <a href="{{ route('login') }}" class="text-sm font-medium text-tech hover:underline">Hai già un account? Accedi</a>
        </div>

        <a href="{{ route('home') }}" class="mt-8 inline-block text-sm text-text-muted hover:text-text-secondary" wire:navigate>
            ← Torna al configuratore
        </a>
    </section>
</x-layouts::public>
