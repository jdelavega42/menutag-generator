<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LogoAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API serialization of an uploaded logo, matching the `LogoAsset` schema of
 * docs/openapi.yaml. `disk_path` is deliberately NOT exposed: the file lives
 * on a private disk and storage layout is an implementation detail.
 *
 * @mixin LogoAsset
 */
class LogoAssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size_bytes' => $this->size_bytes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
