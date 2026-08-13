<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MenuTagParametersCast;
use App\DTOs\MenuTagParameters;
use App\Enums\MenuTagStatus;
use App\Enums\Preset;
use App\Enums\Printability;
use Database\Factories\MenuTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A menu tag generation record: the parameter snapshot, the async job status
 * and the engine report (contract 01).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property string|null $label
 * @property Preset $preset
 * @property bool $customized
 * @property int|null $logo_asset_id
 * @property MenuTagParameters $parameters
 * @property MenuTagStatus $status
 * @property string|null $stl_path
 * @property string|null $stl_accent_path
 * @property array<string, mixed>|null $report
 * @property int|null $triangles
 * @property float|null $volume_mm3
 * @property float|null $weight_g
 * @property float|null $pause_z
 * @property int|null $pause_layer
 * @property Printability|null $printability
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'guest_token',
    'label',
    'preset',
    'customized',
    'logo_asset_id',
    'parameters',
    'status',
    'stl_path',
    'stl_accent_path',
    'report',
    'triangles',
    'volume_mm3',
    'weight_g',
    'pause_z',
    'pause_layer',
    'printability',
    'error_message',
])]
class MenuTag extends Model
{
    /** @use HasFactory<MenuTagFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preset' => Preset::class,
            'customized' => 'boolean',
            'parameters' => MenuTagParametersCast::class,
            'status' => MenuTagStatus::class,
            'report' => 'array',
            'triangles' => 'integer',
            'volume_mm3' => 'float',
            'weight_g' => 'float',
            'pause_z' => 'float',
            'pause_layer' => 'integer',
            'printability' => Printability::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LogoAsset, $this>
     */
    public function logoAsset(): BelongsTo
    {
        return $this->belongsTo(LogoAsset::class);
    }

    /**
     * Filter by owner: an authenticated user OR a guest token — never both
     * (contract 01). The guest branch also requires a null user_id, so a
     * record migrated to an account at registration (spec §7.2) stops being
     * reachable through its old guest token.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForOwner(Builder $query, User|string $owner): Builder
    {
        if ($owner instanceof User) {
            return $query->where('user_id', $owner->getKey());
        }

        return $query->whereNull('user_id')->where('guest_token', $owner);
    }
}
