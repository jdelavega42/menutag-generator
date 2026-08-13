<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MenuTagStatus;
use App\Models\MenuTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API serialization of a MenuTag, field-for-field the `MenuTag` schema of
 * docs/openapi.yaml: id, label, preset, customized, status, parameters
 * (snake_case DTO snapshot), printability, report (raw engine keys, present
 * from `completed`), error_message, links and timestamps.
 *
 * The `links` object only exposes URLs that are actually usable NOW:
 * download/guide appear from status `completed`, download_accent only when
 * an accent STL exists (inlay mode). The API is Sanctum-only (no guests), so
 * every link points to the Policy-protected /api/v1 routes — signed URLs are
 * a web-guest concern (spec §7.2).
 *
 * @mixin MenuTag
 */
class MenuTagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $completed = $this->status === MenuTagStatus::Completed;

        return [
            'id' => $this->id,
            'label' => $this->label,
            'preset' => $this->preset->value,
            'customized' => $this->customized,
            'status' => $this->status->value,
            'parameters' => $this->parameters->toArray(),
            'printability' => $this->printability?->value,
            'report' => $this->report,
            'error_message' => $this->error_message,
            'links' => [
                'download' => $completed
                    ? route('api.v1.menu-tags.download', ['menuTag' => $this->id])
                    : null,
                'download_accent' => $completed && $this->stl_accent_path !== null
                    ? route('api.v1.menu-tags.download', ['menuTag' => $this->id, 'part' => 'accent'])
                    : null,
                'guide' => $completed
                    ? route('api.v1.menu-tags.guide', ['menuTag' => $this->id])
                    : null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
