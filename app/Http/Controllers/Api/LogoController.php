<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogoAssetResource;
use App\Models\LogoAsset;
use App\Models\User;
use App\Rules\CleanImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * POST /api/v1/logos (docs/openapi.yaml): upload a logo into the caller's
 * library. Same hardening as the web upload (WS-5): max size from config,
 * MIME verified from the CONTENT via CleanImageUpload (a PHP script renamed
 * .png is rejected), file name GENERATED SERVER-SIDE — the client name only
 * survives in the display-only `original_name` column. Stored on the private
 * 'assets' disk, never under public/.
 */
class LogoController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $maxKb = (int) config('product.guests.upload_max_kb');

        $request->validate(
            ['file' => ['required', 'file', 'max:'.$maxKb, new CleanImageUpload]],
            [
                'file.required' => 'Nessun file ricevuto: invia il logo nel campo multipart "file".',
                'file.file' => 'Il file caricato non è valido: riprova.',
                'file.max' => sprintf('Il logo non può superare %d KB: riduci il file oppure esporta un SVG più leggero.', $maxKb),
            ],
        );

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $mime = CleanImageUpload::detectMime((string) $file->getRealPath()) ?? CleanImageUpload::MIME_PNG;

        $diskPath = $file->storeAs(
            'logos',
            CleanImageUpload::generatedFileName($mime),
            'assets',
        );

        /** @var User $user */
        $user = $request->user();

        $asset = LogoAsset::create([
            'user_id' => $user->id,
            'guest_token' => null,
            'disk_path' => $diskPath,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size_bytes' => $file->getSize() ?: 0,
        ]);

        return LogoAssetResource::make($asset)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
