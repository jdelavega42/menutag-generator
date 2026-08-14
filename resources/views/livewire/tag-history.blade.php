{{--
    Dashboard history (spec §5.5): parameters, status, metadata and
    DUPLICATION — the central feature for resellers producing in series.
    Restyled on the tokens per mockup 04: rows with a preset thumb, the
    measure in mono and a semaforo badge for the state. Functions unchanged.
--}}
<section aria-label="Archivio dei modelli">
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-text-primary">Modelli generati</h2>
        <flux:button :href="route('home')" variant="primary" size="sm" wire:navigate>
            Nuova targhetta
        </flux:button>
    </div>

    @if ($rows->isEmpty())
        <p class="mt-4 rounded-card border border-dashed border-border-strong bg-surface-1 p-6 text-sm text-text-secondary">
            Nessuna targhetta ancora. Parti dal configuratore: le generazioni fatte da ospite prima di registrarti
            sono state agganciate automaticamente a questo account.
        </p>
    @else
        <div class="mt-4 overflow-x-auto rounded-card border border-border-subtle bg-surface-1">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-[0.08em] text-text-muted">
                    <tr class="border-b border-border-subtle">
                        <th class="px-3 py-2.5 font-medium">Modello</th>
                        <th class="px-3 py-2.5 font-medium">Formato</th>
                        <th class="px-3 py-2.5 text-right font-medium">Misura</th>
                        <th class="px-3 py-2.5 font-medium">Stato</th>
                        <th class="px-3 py-2.5 font-medium">Creata</th>
                        <th class="px-3 py-2.5 text-right font-medium"><span class="sr-only">Azioni</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="tag-row-{{ $row['id'] }}" class="border-b border-border-subtle transition-colors duration-[var(--t-micro)] last:border-b-0 hover:bg-surface-3">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    {{-- Bicolor product thumb (mockup 04): light base, dark engraving --}}
                                    <span class="flex size-8 flex-none items-center justify-center" aria-hidden="true">
                                        @if ($row['preset'] === 'coaster')
                                            <svg width="28" height="28" viewBox="0 0 28 28">
                                                <circle cx="14" cy="14" r="12" class="fill-text-primary" />
                                                <circle cx="14" cy="14" r="8" fill="none" class="stroke-surface-0" stroke-width="2" />
                                                <circle cx="14" cy="14" r="2.5" class="fill-surface-0" />
                                            </svg>
                                        @elseif ($row['preset'] === 'coin_cart')
                                            <svg width="28" height="28" viewBox="0 0 28 28">
                                                <circle cx="14" cy="14" r="11" class="fill-text-primary" />
                                                <circle cx="14" cy="14" r="8" fill="none" class="stroke-surface-0" stroke-width="1.5" stroke-dasharray="2 3" />
                                                <rect x="11" y="11" width="6" height="6" rx="1.5" class="fill-surface-0" transform="rotate(45 14 14)" />
                                            </svg>
                                        @else
                                            <svg width="28" height="28" viewBox="0 0 28 28">
                                                <rect x="2" y="2" width="24" height="24" rx="4" class="fill-text-primary" />
                                                <g class="fill-surface-0">
                                                    <rect x="6" y="6" width="6" height="6" /><rect x="16" y="6" width="6" height="6" />
                                                    <rect x="6" y="16" width="6" height="6" /><rect x="16" y="16" width="2.5" height="2.5" />
                                                    <rect x="19.5" y="19.5" width="2.5" height="2.5" />
                                                </g>
                                                <g class="fill-text-primary">
                                                    <rect x="8" y="8" width="2" height="2" /><rect x="18" y="8" width="2" height="2" />
                                                    <rect x="8" y="18" width="2" height="2" />
                                                </g>
                                            </svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <a
                                            href="{{ $row['show_url'] }}"
                                            class="block max-w-[30ch] truncate font-medium text-text-primary underline-offset-2 hover:underline"
                                            title="{{ $row['label'] ?? 'Targhetta #'.$row['id'] }}"
                                            wire:navigate
                                        >
                                            {{ $row['label'] ?? 'Targhetta #'.$row['id'] }}
                                        </a>
                                        <p class="mt-0.5 max-w-[32ch] truncate text-xs text-text-muted" title="{{ $row['detail'] }}">{{ $row['detail'] }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-text-secondary">
                                {{ ['menutag' => 'MenuTag', 'coaster' => 'Coaster', 'coin_cart' => 'Coin Cart'][$row['preset']] ?? $row['preset'] }}
                                @if ($row['customized'])
                                    <span class="block text-xs text-text-muted">personalizzata</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right"><span class="mono">{{ $row['measure'] }}</span></td>
                            <td class="px-3 py-3">
                                {{-- Semaforo badge (mockup 04 / tokens §3): full color on text+dot, -surface behind --}}
                                @php($badge = match (true) {
                                    $row['status'] === 'completed' && $row['printability'] === 'blocked' => ['Sconsigliato così', 'blocked'],
                                    $row['status'] === 'completed' && $row['printability'] === 'warn' => ['Pronto, con un\'attenzione', 'warn'],
                                    $row['status'] === 'completed' => ['Pronto', 'ok'],
                                    $row['status'] === 'failed' => ['Non riuscita', 'blocked'],
                                    $row['status'] === 'processing' => ['In lavorazione', 'neutral'],
                                    default => ['In coda', 'neutral'],
                                })
                                <span
                                    @class([
                                        'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        'border border-ok/30 bg-ok-surface text-ok' => $badge[1] === 'ok',
                                        'border border-warn/30 bg-warn-surface text-warn' => $badge[1] === 'warn',
                                        'border border-blocked/30 bg-blocked-surface text-blocked' => $badge[1] === 'blocked',
                                        'border border-border-subtle bg-surface-2 text-text-secondary' => $badge[1] === 'neutral',
                                    ])
                                >
                                    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                    {{ $badge[0] }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-xs text-text-muted"><span class="mono text-xs">{{ $row['created_at'] }}</span></td>
                            <td class="px-3 py-3">
                                {{-- Compact icon actions (mockup 04): accessible names via aria-label + title --}}
                                @php($iconButton = 'flex size-7 items-center justify-center rounded-control text-text-muted transition-colors duration-[var(--t-micro)] hover:bg-surface-2 hover:text-text-primary')
                                <div class="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        wire:click="duplicate({{ $row['id'] }})"
                                        class="{{ $iconButton }}"
                                        aria-label="Duplica il modello" title="Duplica il modello"
                                    >
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                                            <path d="M9 9h11v11H9z" /><path d="M4 15V4h11" />
                                        </svg>
                                    </button>
                                    @if ($row['download_base'] !== null)
                                        <a
                                            href="{{ $row['download_base'] }}"
                                            class="{{ $iconButton }}"
                                            aria-label="Scarica il file di stampa (STL)" title="Scarica il file di stampa (STL)"
                                        >
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                                                <path d="M12 4v10m0 0 4-4m-4 4-4-4" /><path d="M4 18h16" />
                                            </svg>
                                        </a>
                                    @endif
                                    @if ($row['download_accent'] !== null)
                                        <a
                                            href="{{ $row['download_accent'] }}"
                                            class="{{ $iconButton }}"
                                            aria-label="Scarica il secondo colore (accento)" title="Scarica il secondo colore (accento)"
                                        >
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                                                <path d="M12 3v8m0 0 3-3m-3 3-3-3" /><rect x="5" y="14" width="14" height="6" rx="1" fill="currentColor" stroke="none" />
                                            </svg>
                                        </a>
                                    @endif
                                    @if ($row['guide_url'] !== null)
                                        <a
                                            href="{{ $row['guide_url'] }}"
                                            class="{{ $iconButton }}"
                                            aria-label="Guida per chi stampa" title="Guida per chi stampa"
                                        >
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                                                <path d="M6 3h9l4 4v14H6z" /><path d="M9 11h6M9 15h6" />
                                            </svg>
                                        </a>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="delete({{ $row['id'] }})"
                                        wire:confirm="Eliminare questa targhetta e i suoi file di stampa?"
                                        class="{{ $iconButton }} hover:text-blocked"
                                        aria-label="Elimina la targhetta" title="Elimina la targhetta"
                                    >
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                                            <path d="M4 7h16" /><path d="M9 7V4h6v3" /><path d="M6 7l1 13h10l1-13" /><path d="M10 11v5m4-5v5" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $tags->links() }}
        </div>
    @endif
</section>
