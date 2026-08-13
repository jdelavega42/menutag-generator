<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QrPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved QR content ("Menù EN", "Recensioni", ...) in the B2B dashboard.
 * Authenticated users only — guests have no library (contract 01).
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'name', 'data'])]
class QrPreset extends Model
{
    /** @use HasFactory<QrPresetFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * QR presets exist only for authenticated users, so the owner filter
     * accepts a User only (contract 01: dashboard-only resource).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForOwner(Builder $query, User $owner): Builder
    {
        return $query->where('user_id', $owner->getKey());
    }
}
