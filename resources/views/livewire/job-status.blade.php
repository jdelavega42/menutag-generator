{{--
    Generation status (contract 04): SEPARATE from the viewer, so wire:poll
    never touches the canvas subtree. The poll attribute is CONDITIONAL —
    rendered only for queued|processing; terminal states carry no poll at
    all (spec §7.4).
--}}
<section aria-label="Stato della generazione">
    @if ($menuTagId !== null)
        @if ($polling)
            <div
                wire:poll.2500ms="checkStatus"
                wire:key="job-status-polling-{{ $menuTagId }}"
                class="flex items-center gap-3 rounded-xl border border-sky-300 bg-sky-50 p-4 text-sky-900 dark:border-sky-700 dark:bg-sky-950 dark:text-sky-100"
            >
                <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <div class="text-sm">
                    <p class="font-semibold">
                        {{ $status === 'processing' ? 'In lavorazione…' : 'In coda…' }}
                    </p>
                    <p>Il motore geometrico sta costruendo la tua targhetta. Puoi continuare a guardare l'anteprima.</p>
                </div>
            </div>
        @elseif ($status === 'completed')
            <div
                wire:key="job-status-completed-{{ $menuTagId }}"
                class="rounded-xl border border-lime-300 bg-lime-50 p-4 text-sm text-lime-900 dark:border-lime-700 dark:bg-lime-950 dark:text-lime-100"
            >
                <p class="font-semibold">Generazione completata.</p>
                <p class="mt-1">
                    L'anteprima ora mostra l'STL reale prodotto dal motore.
                    Trovi esito e download nel report di stampabilità qui sotto.
                </p>
                <a href="{{ route('menu-tags.show', $menuTagId) }}" class="mt-2 inline-block underline" wire:navigate>
                    Apri la pagina della targhetta
                </a>
            </div>
        @elseif ($status === 'failed')
            <div
                wire:key="job-status-failed-{{ $menuTagId }}"
                class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100"
            >
                <p class="font-semibold">Generazione non riuscita.</p>
                <p class="mt-1">{{ $errorMessage ?? 'Errore interno: riprova. Se il problema persiste contattaci.' }}</p>
            </div>
        @endif
    @endif
</section>
