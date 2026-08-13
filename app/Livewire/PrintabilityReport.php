<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MenuTagStatus;
use App\Livewire\Concerns\BuildsDownloadUrls;
use App\Models\MenuTag;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Printability report at job completion (spec §8.8, contract 04): outcome,
 * full/void minimum detail, first-perimeter residue, QR decode result — and
 * the download, which stays AVAILABLE on `blocked` but behind an explicit
 * warning: the user decides, informed.
 *
 * The `report` array holds the engine's raw stdout keys (contract 01/03).
 */
class PrintabilityReport extends Component
{
    use BuildsDownloadUrls;

    public ?int $menuTagId = null;

    /** @var array<string, mixed> raw engine report (KEY=VALUE keys, §8.5) */
    public array $report = [];

    public ?string $stlUrl = null;

    public ?string $accentStlUrl = null;

    public ?string $printGuideUrl = null;

    public function mount(?MenuTag $menuTag = null): void
    {
        if ($menuTag !== null && $menuTag->status === MenuTagStatus::Completed) {
            $this->menuTagId = $menuTag->id;
            $this->report = $menuTag->report ?? [];
            $this->stlUrl = $this->downloadUrl($menuTag, 'base');
            $this->accentStlUrl = $this->downloadUrl($menuTag, 'accent');
            $this->printGuideUrl = $this->guideUrl($menuTag);
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    #[On('menutag-completed')]
    public function onCompleted(int $menuTagId, ?string $stlUrl = null, ?string $accentStlUrl = null, array $report = []): void
    {
        $this->menuTagId = $menuTagId;
        $this->report = $report;
        $this->stlUrl = $stlUrl;
        $this->accentStlUrl = $accentStlUrl;

        // The event payload is fixed by contract 04: the guide URL (guest
        // variant is a signed URL) is derived here from the record instead.
        $menuTag = MenuTag::find($menuTagId);
        $this->printGuideUrl = $menuTag !== null ? $this->guideUrl($menuTag) : null;
    }

    /**
     * Case-insensitive accessor over the raw report keys, so the component
     * tolerates both the engine's uppercase stdout keys and any normalized
     * variant WS-3 may persist.
     */
    public function value(string $key): ?string
    {
        foreach ([$key, strtolower($key), strtoupper($key)] as $candidate) {
            if (isset($this->report[$candidate]) && is_scalar($this->report[$candidate])) {
                return (string) $this->report[$candidate];
            }
        }

        return null;
    }

    public function printability(): ?string
    {
        return $this->value('PRINTABILITY');
    }

    /**
     * Semaforo copy of the «Verifica di stampa» (glossario.md): a human title
     * and one plain sentence per outcome. The numbers stay available as a
     * secondary detail in the closed «Dettagli tecnici» block.
     *
     * @return array{tone: string, title: string, phrase: string}
     */
    public function outcome(): array
    {
        $qrChecked = $this->value('QR_DECODED') !== null;

        return match ($this->printability()) {
            'ok' => [
                'tone' => 'ok',
                'title' => 'Verificato: pronto per la stampa',
                'phrase' => $qrChecked
                    ? 'Il QR è stato letto dalla geometria reale: si scansiona.'
                    : 'La grafica è stata verificata sulla geometria reale: si stampa.',
            ],
            'warn' => [
                'tone' => 'warn',
                'title' => 'Stampabile, con un\'attenzione',
                'phrase' => 'Il file si stampa, ma leggi le attenzioni qui sotto prima di avviare.',
            ],
            'blocked' => [
                'tone' => 'blocked',
                'title' => 'Sconsigliato così',
                'phrase' => 'Puoi comunque scaricare il file, ma ti spieghiamo cosa rischia.',
            ],
            default => [
                'tone' => 'muted',
                'title' => 'Esito non disponibile',
                'phrase' => 'La verifica di stampa non ha prodotto un esito per questo modello.',
            ],
        };
    }

    /**
     * Engine warnings, rewritten in the glossary voice (glossario.md): the
     * technical phrasing survives verbatim inside «Dettagli tecnici», the
     * primary line speaks human. Unknown warnings pass through unchanged.
     */
    public function humanizeWarning(string $warning): string
    {
        return match (true) {
            str_contains($warning, 'Dopo il primo perimetro') => 'Dettagli molto fini: stampa con almeno 2 contorni e non attivare la modalità a parete singola.',
            str_contains($warning, 'Apertura morfologica sul pieno') => 'Alcuni tratti della grafica sono più sottili di quanto l\'ugello possa disegnare: rischiano di sparire in stampa.',
            str_contains($warning, 'Apertura morfologica sul complemento') => 'Alcuni spazi vuoti della grafica sono molto stretti: rischiano di chiudersi in stampa.',
            str_contains($warning, 'decodifica del QR') => 'Il QR riletto dalla geometria non si scansiona: accorcia l\'indirizzo o scegli una dimensione maggiore.',
            default => $warning,
        };
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        foreach (['WARNINGS', 'warnings', 'WARNING', 'warning'] as $key) {
            $value = $this->report[$key] ?? null;

            if (is_array($value)) {
                return array_values(array_map(strval(...), $value));
            }

            if (is_string($value) && $value !== '') {
                return [$value];
            }
        }

        return [];
    }

    public function hasReport(): bool
    {
        return $this->menuTagId !== null && ($this->report !== [] || $this->stlUrl !== null);
    }

    public function render(): View
    {
        return view('livewire.printability-report');
    }
}
