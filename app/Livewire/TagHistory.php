<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\MenuTagStatus;
use App\Livewire\Concerns\BuildsDownloadUrls;
use App\Models\MenuTag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Dashboard history (contract 04 / spec §5.5): every generated tag with
 * parameters, status and metadata — and DUPLICATION, the central feature for
 * resellers producing in series: it reopens the configurator prefilled with
 * the stored snapshot. Every query passes through forOwner() and the
 * MenuTagPolicy (WS-5).
 */
class TagHistory extends Component
{
    use BuildsDownloadUrls;
    use WithPagination;

    public function duplicate(int $menuTagId): void
    {
        $menuTag = MenuTag::findOrFail($menuTagId);

        Gate::authorize('view', $menuTag);

        $this->redirect(route('home', ['duplica' => $menuTag->id]));
    }

    public function delete(int $menuTagId): void
    {
        $menuTag = MenuTag::findOrFail($menuTagId);

        Gate::authorize('delete', $menuTag);

        foreach ([$menuTag->stl_path, $menuTag->stl_accent_path] as $path) {
            if ($path !== null) {
                Storage::disk('stl')->delete($path);
            }
        }

        $menuTag->delete();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $tags = MenuTag::forOwner($user)
            ->with('logoAsset')
            ->latest()
            ->paginate(10);

        $rows = collect($tags->items())->map(function (MenuTag $menuTag): array {
            $parameters = $menuTag->parameters;

            return [
                'id' => $menuTag->id,
                'label' => $menuTag->label,
                'preset' => $menuTag->preset->value,
                'customized' => $menuTag->customized,
                'status' => $menuTag->status->value,
                'printability' => $menuTag->printability?->value,
                'summary' => sprintf(
                    '%s · %s mm × %s mm%s%s',
                    $parameters->shape->value === 'square' ? 'quadrato' : 'cerchio',
                    rtrim(rtrim(number_format($parameters->size, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($parameters->thickness, 2, '.', ''), '0'), '.'),
                    $parameters->nfc ? ' · NFC Ø'.$parameters->tagDiameter->value : '',
                    $parameters->plate > 1 ? ' · piastra da '.$parameters->plate : '',
                ),
                'mode' => $parameters->mode->value,
                'created_at' => $menuTag->created_at?->format('d/m/Y H:i'),
                'download_base' => $menuTag->status === MenuTagStatus::Completed
                    ? $this->downloadUrl($menuTag, 'base')
                    : null,
                'download_accent' => $menuTag->status === MenuTagStatus::Completed
                    ? $this->downloadUrl($menuTag, 'accent')
                    : null,
                'guide_url' => $menuTag->status === MenuTagStatus::Completed
                    ? $this->guideUrl($menuTag)
                    : null,
                'show_url' => route('menu-tags.show', $menuTag),
            ];
        });

        return view('livewire.tag-history', [
            'tags' => $tags,
            'rows' => $rows,
        ]);
    }
}
