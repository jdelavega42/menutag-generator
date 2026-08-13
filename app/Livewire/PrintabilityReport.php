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
