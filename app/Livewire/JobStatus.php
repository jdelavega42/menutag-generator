<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Livewire\Concerns\BuildsDownloadUrls;
use App\Models\MenuTag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Generation status poller (contract 04) — a SEPARATE component from the
 * viewer so `wire:poll` never touches the canvas subtree. The poll attribute
 * is CONDITIONAL: rendered only while the status is queued|processing, so
 * terminal states stop the polling entirely (spec §7.4).
 *
 * On completion it dispatches `menutag-completed` (L+B: the viewer JS loads
 * the STL, the PrintabilityReport fills in); on failure `menutag-failed`.
 */
class JobStatus extends Component
{
    use BuildsDownloadUrls;

    public ?int $menuTagId = null;

    public ?string $status = null;

    public ?string $errorMessage = null;

    /** Preset of the tracked record: picks the narrative voice (QR vs logo). */
    #[Locked]
    public ?string $preset = null;

    public function mount(?MenuTag $menuTag = null): void
    {
        if ($menuTag !== null) {
            $this->menuTagId = $menuTag->id;
            $this->status = $menuTag->status->value;
            $this->errorMessage = $menuTag->error_message;
            $this->preset = $menuTag->preset->value;
        }
    }

    #[On('menutag-queued')]
    public function onQueued(int $menuTagId): void
    {
        $this->menuTagId = $menuTagId;
        $this->status = MenuTagStatus::Queued->value;
        $this->errorMessage = null;

        $menuTag = MenuTag::find($menuTagId);

        if ($menuTag !== null) {
            // Same ownership gate the poll enforces, just one tick earlier.
            Gate::authorize('view', $menuTag);
            $this->preset = $menuTag->preset->value;
        }
    }

    /** Poll tick (wire:poll.2500ms while the status is not terminal). */
    public function checkStatus(): void
    {
        if ($this->menuTagId === null) {
            return;
        }

        $menuTag = MenuTag::find($this->menuTagId);

        if ($menuTag === null) {
            $this->status = MenuTagStatus::Failed->value;
            $this->errorMessage = 'La targhetta non esiste più.';

            return;
        }

        Gate::authorize('view', $menuTag);

        $previous = $this->status;
        $this->status = $menuTag->status->value;
        $this->errorMessage = $menuTag->error_message;

        if ($previous === $this->status) {
            return;
        }

        if ($menuTag->status === MenuTagStatus::Completed) {
            $this->dispatch(
                'menutag-completed',
                menuTagId: $menuTag->id,
                stlUrl: $this->downloadUrl($menuTag, 'base'),
                accentStlUrl: $this->downloadUrl($menuTag, 'accent'),
                report: $menuTag->report ?? [],
            );
        }

        if ($menuTag->status === MenuTagStatus::Failed) {
            $this->dispatch(
                'menutag-failed',
                menuTagId: $menuTag->id,
                message: $menuTag->error_message
                    ?? 'La generazione non è riuscita per un errore interno. Riprova; se il problema persiste contattaci.',
            );
        }
    }

    /** The poll attribute exists only in non-terminal states (spec §7.4). */
    public function isPolling(): bool
    {
        return $this->menuTagId !== null
            && in_array($this->status, [MenuTagStatus::Queued->value, MenuTagStatus::Processing->value], true);
    }

    /** Human label of the tracked format, for the narrative copy. */
    public function presetLabel(): string
    {
        return match (Preset::tryFrom($this->preset ?? '')) {
            Preset::Coaster => 'Coaster',
            Preset::CoinCart => 'Coin Cart',
            default => 'MenuTag',
        };
    }

    /**
     * Narrative waiting states (glossario.md / flussi.md §1), HOOKED to the
     * real job states — never a fake progress bar: `queued` and `processing`
     * are the only two non-terminal states the record can be in.
     *
     * @return array{title: string, detail: string}
     */
    public function narrative(): array
    {
        $engraving = Preset::tryFrom($this->preset ?? '') === Preset::MenuTag
            ? 'Stiamo incidendo il tuo QR…'
            : 'Stiamo incidendo il tuo logo…';

        return $this->status === MenuTagStatus::Processing->value
            ? [
                'title' => $engraving,
                'detail' => 'Subito dopo arriva la verifica di stampa: leggiamo la geometria reale prima di dartela.',
            ]
            : [
                'title' => 'In coda…',
                'detail' => 'Il banco di lavoro sta per iniziare. Puoi continuare a guardare l\'anteprima.',
            ];
    }

    public function render(): View
    {
        return view('livewire.job-status', [
            'polling' => $this->isPolling(),
            'narrative' => $this->narrative(),
            'presetLabel' => $this->presetLabel(),
        ]);
    }
}
