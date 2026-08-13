{{--
    Dashboard history (spec §5.5): parameters, status, metadata and
    DUPLICATION — the central feature for resellers producing in series.
--}}
<section aria-label="Storico targhette">
    <div class="flex items-center justify-between gap-3">
        <flux:heading size="lg" level="2">Storico targhette</flux:heading>
        <flux:button :href="route('home')" variant="primary" size="sm" wire:navigate>
            Nuova targhetta
        </flux:button>
    </div>

    @if ($rows->isEmpty())
        <p class="mt-4 rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-500 dark:border-zinc-600 dark:text-zinc-400">
            Nessuna targhetta ancora. Parti dal configuratore: le generazioni fatte da ospite prima di registrarti
            sono state agganciate automaticamente a questo account.
        </p>
    @else
        <div class="mt-4 overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full min-w-[640px] divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-2">Targhetta</th>
                        <th class="px-4 py-2">Configurazione</th>
                        <th class="px-4 py-2">Stato</th>
                        <th class="px-4 py-2">Creata</th>
                        <th class="px-4 py-2 text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-900">
                    @foreach ($rows as $row)
                        <tr wire:key="tag-row-{{ $row['id'] }}">
                            <td class="px-4 py-3">
                                <a href="{{ $row['show_url'] }}" class="font-medium underline-offset-2 hover:underline" wire:navigate>
                                    {{ $row['label'] ?? 'Targhetta #'.$row['id'] }}
                                </a>
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ ['menutag' => 'MenuTag', 'coaster' => 'Coaster', 'coin_cart' => 'Coin Cart'][$row['preset']] ?? $row['preset'] }}
                                    @if ($row['customized']) · personalizzata @endif
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-600 dark:text-zinc-300">
                                {{ $row['summary'] }}
                                <span class="text-zinc-400">· {{ ['engrave' => 'incisa', 'relief' => 'rilievo', 'inlay' => 'intarsio'][$row['mode']] ?? $row['mode'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['status'] === 'completed')
                                    <flux:badge size="sm" color="lime">Completata</flux:badge>
                                    @if ($row['printability'] === 'blocked')
                                        <flux:badge size="sm" color="red">stampabilità critica</flux:badge>
                                    @elseif ($row['printability'] === 'warn')
                                        <flux:badge size="sm" color="amber">con avvisi</flux:badge>
                                    @endif
                                @elseif ($row['status'] === 'failed')
                                    <flux:badge size="sm" color="red">Non riuscita</flux:badge>
                                @elseif ($row['status'] === 'processing')
                                    <flux:badge size="sm" color="sky">In lavorazione</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">In coda</flux:badge>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $row['created_at'] }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <flux:button wire:click="duplicate({{ $row['id'] }})" variant="ghost" size="xs">
                                        Duplica
                                    </flux:button>
                                    @if ($row['download_base'] !== null)
                                        <flux:button :href="$row['download_base']" variant="ghost" size="xs">STL</flux:button>
                                    @endif
                                    @if ($row['download_accent'] !== null)
                                        <flux:button :href="$row['download_accent']" variant="ghost" size="xs">Accento</flux:button>
                                    @endif
                                    @if ($row['guide_url'] !== null)
                                        <flux:button :href="$row['guide_url']" variant="ghost" size="xs">Guida</flux:button>
                                    @endif
                                    <flux:button
                                        wire:click="delete({{ $row['id'] }})"
                                        wire:confirm="Eliminare questa targhetta e i suoi file STL?"
                                        variant="ghost" size="xs"
                                    >
                                        Elimina
                                    </flux:button>
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
