<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Http\Middleware\EnsureGuestToken;
use App\Models\LogoAsset;
use App\Rules\CleanImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Shared logo-upload flow (Configurator guest uploader + dashboard
 * LogoLibrary, contract 04): max size from config, content-based PNG/SVG
 * validation via {@see CleanImageUpload} (WS-5, referenced by name), file
 * name GENERATED SERVER-SIDE (never the client name on disk), stored on the
 * private 'assets' disk, then the `logo-uploaded` event is dispatched with
 * the asset id and an inline preview URL.
 */
trait HandlesLogoUploads
{
    /**
     * Persist a validated upload as a LogoAsset owned by the current user or
     * by the session guest token (exactly one of the two, contract 01).
     */
    protected function storeLogoUpload(UploadedFile $file): LogoAsset
    {
        $mime = CleanImageUpload::detectMime((string) $file->getRealPath()) ?? CleanImageUpload::MIME_PNG;

        $diskPath = $file->storeAs(
            'logos',
            CleanImageUpload::generatedFileName($mime),
            'assets',
        );

        $asset = LogoAsset::create([
            'user_id' => Auth::id(),
            'guest_token' => Auth::check() ? null : EnsureGuestToken::token(),
            'disk_path' => $diskPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        $this->dispatch(
            'logo-uploaded',
            logoAssetId: $asset->id,
            previewUrl: $this->logoPreviewUrl($asset),
        );

        return $asset;
    }

    /**
     * Inline data-URI preview: the 'assets' disk is private ('serve' =>
     * false, never from public/), so the browser preview travels embedded.
     * Uploads are capped at 2 MB by config, which keeps this bounded.
     */
    protected function logoPreviewUrl(LogoAsset $asset): string
    {
        $contents = Storage::disk('assets')->get($asset->disk_path);

        return 'data:'.$asset->mime.';base64,'.base64_encode((string) $contents);
    }

    /**
     * Validation rules for the upload property, config-driven.
     *
     * @return array<string, list<mixed>>
     */
    protected function logoUploadRules(string $attribute): array
    {
        return [
            $attribute => [
                'required',
                'file',
                'max:'.(int) config('product.guests.upload_max_kb'),
                new CleanImageUpload,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function logoUploadMessages(string $attribute): array
    {
        $maxKb = (int) config('product.guests.upload_max_kb');

        return [
            $attribute.'.required' => 'Seleziona un file da caricare.',
            $attribute.'.file' => 'Il file caricato non è valido: riprova.',
            $attribute.'.max' => sprintf(
                'Il logo non può superare %s MB: riduci il file oppure esporta un SVG più leggero.',
                number_format($maxKb / 1024, 0),
            ),
        ];
    }
}
