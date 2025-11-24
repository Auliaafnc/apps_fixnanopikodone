<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\LatestFirst; 

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use HasFactory, HasRoles, Notifiable, HasApiTokens, LatestFirst;

    protected $fillable = [
        'avatar_url',
        'name',
        'email',
        'password',
        'role',            // opsional: jika kamu simpan role string di users
        'employee_id',
        'department_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $user) {
            // Sinkron ke Spatie HANYA saat kolom 'role' berubah & terisi
            if ($user->isDirty('role') && ! empty($user->role)) {
                $user->syncRoles([$user->role]);
            }
        });
    }

    /* ============ Filament Avatar ============ */
    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return asset('storage/' . $this->avatar_url);
        }
        $hash = md5(strtolower(trim($this->email)));
        return 'https://www.gravatar.com/avatar/' . $hash . '?d=mp&r=g&s=250';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /* ============ Relasi (pakai FK eksplisit) ============ */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /* ============ Accessors AMAN (tanpa panggil relasi) ============ */
    public function getEmployeeIdAttribute($value)
    {
        // nilai mentah dari DB; JANGAN panggil $this->employee di sini
        return $value ?? ($this->attributes['employee_id'] ?? null);
    }

    public function getDepartmentIdAttribute($value)
    {
        return $value ?? ($this->attributes['department_id'] ?? null);
    }

    public function getRoleAttribute()
    {
        if (array_key_exists('role', $this->attributes) && $this->attributes['role']) {
            return $this->attributes['role'];
        }
        // fallback Spatie role pertama
        return $this->getRoleNames()->first();
    }

    /* ============ Helper opsional ============ */
    public function isAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles, true) || $this->hasAnyRole($roles);
    }
}