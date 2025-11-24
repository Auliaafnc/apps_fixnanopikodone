<?php

namespace App\Models\Concerns;

use App\Models\Scopes\LatestFirstScope;
use Illuminate\Database\Eloquent\Builder;

trait LatestFirst
{
    protected static function bootLatestFirst(): void
    {
        static::addGlobalScope(new LatestFirstScope());
    }

    // kalau butuh matikan sorting global di query tertentu:
    public function scopeWithoutLatestFirst(Builder $query): Builder
    {
        return $query->withoutGlobalScope(LatestFirstScope::class);
    }
}