<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointMinimum extends Model
{
    protected $fillable = [
        'type',        // 'reward' | 'program'
        'program_id',  // null jika type='reward'
        'min_amount',  // rupiah minimum
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(CustomerProgram::class, 'program_id');
    }

    /* === scopes bantu === */
    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeReward($q)      { return $q->where('type', 'reward'); }
    public function scopeProgramType($q) { return $q->where('type', 'program'); }

    /* === helpers === */

    /** Ambang minimum reward aktif. Fallback 1_000_000 jika tidak ada data. */
    public static function rewardMin(): int
    {
        return (int) (static::query()->active()->reward()->value('min_amount') ?? 1_000_000);
    }

    /** Ambang minimum program aktif. Null jika tidak ada program atau tidak aktif. */
    public static function programMin(?int $programId): ?int
    {
        if (empty($programId)) return null;

        return static::query()
            ->active()
            ->programType()
            ->where('program_id', $programId)
            ->value('min_amount');
    }
}
