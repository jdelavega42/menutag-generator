<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Livewire\Concerns\BuildsDownloadUrls;
use App\Livewire\Concerns\ResolvesPresetDefaults;
use App\Models\MenuTag;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Container of the three.js canvas (contract 04): the canvas div carries
 * `wire:ignore`, the scene is initialized ONCE at Alpine init and disposed
 * in destroy(). NO `wire:poll` lives in this subtree — the component only
 * renders once and the viewer reacts to browser events (`menutag-preview`,
 * `menutag-updated`, `menutag-completed`), so Livewire morphing never
 * touches the WebGL context and the viewer never resets during polling.
 */
class PreviewViewer extends Component
{
    use BuildsDownloadUrls;
    use ResolvesPresetDefaults;

    /** @var array<string, mixed> initial PreviewParams for the scene */
    public array $params = [];

    public ?string $stlUrl = null;

    public ?string $accentStlUrl = null;

    /**
     * @param  array<string, mixed>|null  $params
     */
    public function mount(?array $params = null, ?MenuTag $menuTag = null): void
    {
        if ($menuTag !== null) {
            $this->params = self::previewParamsFromDto($menuTag->parameters);

            if ($menuTag->status === MenuTagStatus::Completed && $menuTag->stl_path !== null) {
                $this->stlUrl = $this->downloadUrl($menuTag, 'base');
                $this->accentStlUrl = $this->downloadUrl($menuTag, 'accent');
            }

            return;
        }

        $this->params = $params ?? self::presetDefaults(Preset::MenuTag);
    }

    public function render(): View
    {
        return view('livewire.preview-viewer');
    }
}
