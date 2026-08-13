<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Concerns\HandlesLogoUploads;
use App\Models\LogoAsset;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dashboard logo library (contract 04): reusable logos for resellers who
 * generate tags for many clients. Every query goes through the forOwner()
 * scope and deletions through the LogoAssetPolicy (WS-5).
 */
class LogoLibrary extends Component
{
    use HandlesLogoUploads;
    use WithFileUploads;

    /** @var UploadedFile|null */
    public $upload = null;

    public function updatedUpload(): void
    {
        $this->validate(
            $this->logoUploadRules('upload'),
            $this->logoUploadMessages('upload'),
            ['upload' => 'logo'],
        );

        if ($this->upload !== null) {
            $this->storeLogoUpload($this->upload);
            $this->reset('upload');
        }
    }

    public function delete(int $logoAssetId): void
    {
        $asset = LogoAsset::findOrFail($logoAssetId);

        Gate::authorize('delete', $asset);

        Storage::disk('assets')->delete($asset->disk_path);
        $asset->delete();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();

        $logos = LogoAsset::forOwner($user)
            ->latest()
            ->get()
            ->map(fn (LogoAsset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->original_name,
                'mime' => $asset->mime,
                'size_kb' => (int) round($asset->size_bytes / 1024),
                'preview' => $this->logoPreviewUrl($asset),
            ]);

        return view('livewire.logo-library', [
            'logos' => $logos,
        ]);
    }
}
