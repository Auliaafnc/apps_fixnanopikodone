<?php

namespace App\Models\Concerns;

use App\Models\Scopes\OwnedByEmployeeScope;

trait OwnedByEmployee
{
    protected static function bootOwnedByEmployee(): void
    {
        static::addGlobalScope(new OwnedByEmployeeScope);

        // Saat create, isi otomatis employee_id & department_id
        static::creating(function ($model) {
            $user = auth()->user();
            if (!$user) return;

            if (self::col($model, 'employee_id') && empty($model->employee_id)) {
                $model->employee_id = $user->employee_id;
            }

            if (self::col($model, 'department_id') && empty($model->department_id)) {
                $model->department_id = $user->department_id;
            }
        });
    }

    protected static function col($model, string $name): bool
    {
        return in_array($name, $model->getFillable(), true)
            || array_key_exists($name, $model->getCasts());
    }

    public static function queryWithoutOwnership()
    {
        return static::withoutGlobalScope(OwnedByEmployeeScope::class)->query();
    }
}