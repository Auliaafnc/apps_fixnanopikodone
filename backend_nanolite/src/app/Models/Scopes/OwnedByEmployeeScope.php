<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OwnedByEmployeeScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = auth()->user();
        if (!$user) return;

        $unrestricted = config('ownership.unrestricted_roles');
        $deptLead     = config('ownership.department_lead_roles');

        // Admin/manager/head_marketing bebas lihat semua
        if (in_array($user->role, $unrestricted, true)) return;

        // Kepala departemen: filter berdasarkan department_id
        if (in_array($user->role, $deptLead, true)
            && self::col($model, 'department_id')
            && $user->department_id) {
            $builder->where($model->getTable().'.department_id', $user->department_id);
            return;
        }

        // Default: filter berdasarkan employee_id
        if (self::col($model, 'employee_id') && $user->employee_id) {
            $builder->where($model->getTable().'.employee_id', $user->employee_id);
        }
    }

    protected static function col(Model $model, string $name): bool
    {
        return in_array($name, $model->getFillable(), true)
            || array_key_exists($name, $model->getCasts());
    }
}