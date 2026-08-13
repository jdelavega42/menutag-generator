<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LogoAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An uploaded logo (PNG or SVG) on the 'assets' disk (contract 01).
 *
 * `disk_path` is RELATIVE to the disk root — never absolute: app and worker
 * are different containers and resolve it independently. Exactly one of
 * `user_id` / `guest_token` is set (application invariant, enforced by the
 * upload flow): null user = guest upload, removed by the retention command.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $guest_token
 * @property string $disk_path
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'guest_token',
    'disk_path',
    'original_name',
    'mime',
    'size_bytes',
])]
class LogoAsset extends Model
{
    /** @use HasFactory<LogoAssetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
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
     * @return HasMany<MenuTag, $this>
     */
    public function menuTags(): HasMany
    {
        return $this->hasMany(MenuTag::class);
    }

    /**
     * Filter by owner: an authenticated user OR a guest token — never both
     * (contract 01).
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
