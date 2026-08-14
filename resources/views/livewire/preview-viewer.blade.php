{{--
    three.js canvas container (contract 04 / spec §7.3):
    - wire:ignore on the canvas div: Livewire morphing never touches it;
    - the scene is created ONCE at Alpine init and disposed in destroy();
    - NO wire:poll in this subtree — the viewer reacts to browser events
      only (menutag-preview, menutag-updated, menutag-completed), so it
      never resets while the JobStatus component polls.
    Restyle (R-2): VISUAL contour only — blueprint panel, live chip and the
    mono readout. The readout is presentation-only Alpine listening to the
    SAME browser events the viewer already consumes; the canvas mechanism
    is untouched.
--}}
<section aria-label="Anteprima 3D">
    <div class="overflow-hidden rounded-panel border border-border-subtle bg-surface-1">
        <div
            wire:ignore
            x-data="menuTagViewer({
                params: @js($params),
                stlUrl: @js($stlUrl),
                accentStlUrl: @js($accentStlUrl),
            })"
            class="blueprint relative aspect-square w-full bg-surface-1"
        >
            <div x-ref="canvasHost" class="absolute inset-0" role="img" aria-label="Anteprima 3D della targhetta: si aggiorna a ogni modifica, senza chiamate al server"></div>

            {{-- Opaque chip: the grid never runs under text (tokens §4) --}}
            <span class="pointer-events-none absolute left-3.5 top-3.5 inline-flex items-center gap-1.5 rounded-full border border-border-subtle bg-surface-2 px-3 py-1 text-xs font-medium text-text-secondary">
                <span class="size-1.5 rounded-full bg-tech" aria-hidden="true"></span>
                Anteprima live
            </span>
        </div>

        {{-- Mono readout: human labels, values in mono (mockup 01/02).
             Presentation only — it mirrors the same browser events. --}}
        <div
            x-data="{ p: @js($params) }"
            x-on:menutag-preview.window="p = $event.detail.params"
            x-on:menutag-updated.window="p = $event.detail.params"
            class="flex flex-wrap items-center gap-x-6 gap-y-1.5 border-t border-border-subtle bg-surface-1 px-4 py-2.5"
            aria-label="Specifiche del pezzo in anteprima"
        >
            <dl>
                <dt class="text-[11px] font-medium uppercase tracking-[0.08em] text-text-muted">Misura</dt>
                <dd class="text-sm text-text-primary">
                    <span x-text="p.shape === 'square' ? 'lato' : 'Ø'"></span>
                    <span class="mono" x-text="(Math.round(p.size * 100) / 100) + ' mm'"></span>
                </dd>
            </dl>
            <dl>
                <dt class="text-[11px] font-medium uppercase tracking-[0.08em] text-text-muted">Spessore</dt>
                <dd class="text-sm text-text-primary"><span class="mono" x-text="(Math.round(p.thickness * 100) / 100) + ' mm'"></span></dd>
            </dl>
            <dl>
                <dt class="text-[11px] font-medium uppercase tracking-[0.08em] text-text-muted">Resa</dt>
                <dd class="text-sm text-text-primary" x-text="{ engrave: 'Incisa', relief: 'Rilievo', inlay: 'A filo bicolore' }[p.mode] ?? p.mode"></dd>
            </dl>
            <dl>
                <dt class="text-[11px] font-medium uppercase tracking-[0.08em] text-text-muted">Tag NFC</dt>
                <dd class="text-sm text-text-primary">
                    <template x-if="p.nfc"><span>Ø <span class="mono" x-text="p.tagDiameter + ' mm'"></span></span></template>
                    <template x-if="!p.nfc"><span>No</span></template>
                </dd>
            </dl>
        </div>

        <p class="border-t border-border-subtle px-4 py-2 text-xs text-text-muted">
            Trascina per ruotare · rotellina per lo zoom — l'anteprima si aggiorna senza chiamate al server.
        </p>
    </div>
</section>
