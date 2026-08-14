{{--
    Generation status (contract 04): SEPARATE from the viewer, so wire:poll
    never touches the canvas subtree. The poll attribute is CONDITIONAL —
    rendered only for queued|processing; terminal states carry no poll at
    all (spec §7.4). Copy: narrative waiting states of glossario.md, hooked
    to the REAL job states — never a fake progress bar.
--}}
<section aria-label="Stato della generazione">
    @if ($menuTagId !== null)
        @if ($polling)
            <div
                wire:poll.2500ms="checkStatus"
                wire:key="job-status-polling-{{ $menuTagId }}"
                class="flex items-center gap-3 rounded-card border border-border-subtle bg-surface-1 p-4"
            >
                <svg class="size-5 flex-none animate-spin text-accent" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <div class="text-sm">
                    <p class="font-semibold text-text-primary">{{ $narrative['title'] }}</p>
                    <p class="mt-0.5 text-text-secondary">{{ $narrative['detail'] }}</p>
                </div>
            </div>
        @elseif ($status === 'completed')
            <div
                wire:key="job-status-completed-{{ $menuTagId }}"
                class="rounded-card border border-border-subtle bg-surface-1 p-4 text-sm"
            >
                <p class="inline-flex items-center gap-2 font-semibold text-ok">
                    <span class="size-2 rounded-full bg-ok" aria-hidden="true"></span>
                    Il tuo {{ $presetLabel }} è pronto.
                </p>
                <p class="mt-1 text-text-secondary">
                    L'anteprima ora mostra il pezzo reale prodotto dal motore.
                    Esito e download sono nella «Verifica di stampa» qui sotto.
                </p>
                <a href="{{ route('menu-tags.show', $menuTagId) }}" class="mt-2 inline-block font-medium text-tech hover:underline" wire:navigate>
                    Apri la pagina del modello
                </a>
            </div>
        @elseif ($status === 'failed')
            <div
                wire:key="job-status-failed-{{ $menuTagId }}"
                class="rounded-card border border-blocked bg-blocked-surface p-4 text-sm text-blocked"
            >
                <p class="font-semibold">La creazione non è riuscita.</p>
                <p class="mt-1">{{ $errorMessage ?? 'Errore interno: riprova. Se il problema persiste contattaci.' }}</p>
            </div>
        @endif
    @endif
</section>
