<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LatestFirstScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        // terbaru dulu + tie-breaker by id
        $builder->orderByDesc($table.'.created_at')
                ->orderByDesc($table.'.id');
    }
}