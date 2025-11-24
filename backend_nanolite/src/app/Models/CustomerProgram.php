<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomerProgramExport;
use Carbon\Carbon;

class CustomerProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'status',
        'deskripsi',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'starts_at'  => 'date',
        'ends_at'    => 'date',
    ];

    /**
     * Nama-nama program yang tidak pernah kedaluwarsa
     */
    public const NON_EXPIRING_NAMES = [
        'tidak ada program',
        'tidak ikut program',
        'non program',
        'tanpa program',
    ];

    /**
     * Boot: setelah disimpan, export otomatis ke Excel
     */
    protected static function booted()
    {
        // export otomatis setiap kali disimpan
        static::saved(function (CustomerProgram $program) {
            $slug = Str::slug($program->name);
            $excelFileName = "CustomerProgram-{$program->id}-{$slug}.xlsx";
            Excel::store(new CustomerProgramExport(collect([$program])), $excelFileName, 'public');
        });

        static::saving(function (CustomerProgram $program) {
            if ($program->name !== 'Tidak Ada Program'
                && $program->ends_at
                && $program->ends_at->lt(today())
            ) {
                $program->status = 'non-active';
            }
        });

        // cek otomatis & ubah status jika sudah berakhir
        static::retrieved(function (CustomerProgram $program) {
            $program->checkAndUpdateStatus();
        });
    }

    /**
     * Relasi ke perusahaan pemilik program
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi many-to-many ke banyak kategori pelanggan
     */
    public function customerCategories()
    {
        return $this->belongsToMany(CustomerCategories::class, 'customer_category_customer_program', 'program_id', 'category_id');
    }

    /**
     * Relasi ke semua pelanggan yang tergabung dalam program ini
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_program_id');
    }

    /**
     * Relasi ke semua karyawan yang menangani program ini
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'customer_program_id');
    }

    /**
     * Relasi ke semua pesanan dari pelanggan yang mengikuti program ini
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_program_id');
    }

    /**
     * Mengecek apakah program ini termasuk non-expiring (nama khusus)
     */
    public function isNonExpiringByName(): bool
    {
        return in_array(strtolower(trim($this->name ?? '')), self::NON_EXPIRING_NAMES, true);
    }

    /**
     * Mengecek apakah program saat ini aktif (dalam rentang tanggal)
     */
    public function getIsCurrentlyActiveAttribute(): bool
    {
        // “Tidak Ada Program” selalu dianggap aktif (meski tidak memberi poin)
        if ($this->isNonExpiringByName()) {
            return true;
        }

        if ($this->status !== 'active') {
            return false;
        }

        $today = Carbon::today();

        if ($this->starts_at && $this->starts_at->gt($today)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($today)) {
            return false;
        }

        return true;
    }

    /**
     * Scope untuk hanya ambil program yang masih aktif
     */
    public function scopeCurrentlyActive($query)
    {
        $today = Carbon::today()->toDateString();

        return $query->where(function ($q) use ($today) {
            $q->whereIn(\DB::raw('LOWER(TRIM(name))'), self::NON_EXPIRING_NAMES)
              ->orWhere(function ($qq) use ($today) {
                  $qq->where('status', 'active')
                     ->where(function ($q2) use ($today) {
                         $q2->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', $today);
                     })
                     ->where(function ($q3) use ($today) {
                         $q3->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $today);
                     });
              });
        });
    }

    /**
     * Ubah status ke non-active jika program sudah lewat tanggal berakhir
     */
    public function checkAndUpdateStatus(): void
    {
        if ($this->isNonExpiringByName()) {
            // program “Tidak Ada Program” tidak pernah dinonaktifkan
            return;
        }

        $today = Carbon::today();

        if ($this->ends_at && $this->ends_at->lt($today) && $this->status === 'active') {
            $this->updateQuietly(['status' => 'non-active']);
        }
    }
}
