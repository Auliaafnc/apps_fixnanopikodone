<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;

class RoleLimiter
{
    /** role yang dibatasi */
    public const LIMITED_ROLES = ['sales', 'head_sales', 'head_digital'];

    protected static function limited(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(self::LIMITED_ROLES);
    }

    public static function defaultDepartmentId(): ?int
    {
        return auth()->user()?->employee?->department_id;
    }

    public static function defaultEmployeeId(): ?int
    {
        return auth()->user()?->employee?->id;
    }

    /** Opsi department untuk dropdown */
    public static function departmentOptions(): array
    {
        if (self::limited()) {
            $id = self::defaultDepartmentId();
            return $id ? Department::whereKey($id)->pluck('name','id')->toArray() : [];
        }
        return Department::where('status','active')->pluck('name','id')->toArray();
    }

    /** Opsi employee untuk dropdown (opsional filter by dept) */
    public static function employeeOptions(?int $deptId): array
    {
        if (self::limited()) {
            $id = self::defaultEmployeeId();
            return $id ? Employee::whereKey($id)->pluck('name','id')->toArray() : [];
        }

        return Employee::where('status','active')
            ->when($deptId, fn($q) => $q->where('department_id', $deptId))
            ->pluck('name','id')
            ->toArray();
    }

    /** apakah field harus disabled */
    public static function lock(): bool
    {
        return self::limited();
    }

    /** hard-enforce di server (anti-tamper) */
    public static function forceDepartmentId(int $incoming = null): ?int
    {
        return self::limited() ? self::defaultDepartmentId() : $incoming;
    }
    public static function forceEmployeeId(int $incoming = null): ?int
    {
        return self::limited() ? self::defaultEmployeeId() : $incoming;
    }
}
