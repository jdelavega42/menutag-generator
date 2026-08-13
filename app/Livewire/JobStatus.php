<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MenuTagStatus;
use App\Livewire\Concerns\BuildsDownloadUrls;
use App\Models\MenuTag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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

    public function mount(?MenuTag $menuTag = null): void
    {
        if ($menuTag !== null) {
            $this->menuTagId = $menuTag->id;
            $this->status = $menuTag->status->value;
            $this->errorMessage = $menuTag->error_message;
        }
    }

    #[On('menutag-queued')]
    public function onQueued(int $menuTagId): void
    {
        $this->menuTagId = $menuTagId;
        $this->status = MenuTagStatus::Queued->value;
        $this->errorMessage = null;
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

    public function render(): View
    {
        return view('livewire.job-status', [
            'polling' => $this->isPolling(),
        ]);
    }
}
