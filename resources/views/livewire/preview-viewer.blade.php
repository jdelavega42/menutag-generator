{{--
    three.js canvas container (contract 04 / spec §7.3):
    - wire:ignore on the canvas div: Livewire morphing never touches it;
    - the scene is created ONCE at Alpine init and disposed in destroy();
    - NO wire:poll in this subtree — the viewer reacts to browser events
      only (menutag-preview, menutag-updated, menutag-completed), so it
      never resets while the JobStatus component polls.
--}}
<section aria-label="Anteprima 3D">
    <div
        wire:ignore
        x-data="menuTagViewer({
            params: @js($params),
            stlUrl: @js($stlUrl),
            accentStlUrl: @js($accentStlUrl),
        })"
        class="relative aspect-square w-full overflow-hidden rounded-xl border border-zinc-200 bg-gradient-to-b from-zinc-100 to-zinc-200 dark:border-zinc-700 dark:from-zinc-800 dark:to-zinc-900"
    >
        <div x-ref="canvasHost" class="absolute inset-0"></div>
        <p class="pointer-events-none absolute bottom-2 left-3 text-xs text-zinc-500 dark:text-zinc-400">
            Trascina per ruotare · rotellina per lo zoom — l'anteprima si aggiorna senza chiamate al server
        </p>
    </div>
</section>
