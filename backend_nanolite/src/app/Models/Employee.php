<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeeExport;
use Illuminate\Support\Str;
use App\Models\Concerns\LatestFirst; 

class Employee extends Model
{
    use HasFactory, LatestFirst;

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'email',
        'phone',
        'address',
        'photo',
        'status',
    ];

    protected $casts = [
        'company_id'        => 'integer',
        'department_id'     => 'integer',
        'name'              => 'string',
        'email'             => 'string',
        'phone'             => 'string',
        'address'           => 'array',
        'photo'             => 'string',
        'status'            => 'string',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /**
     * Relasi ke perusahaan
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi ke departemen
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function productReturns()
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function garansis()
    {
        return $this->hasMany(Garansi::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }



    protected static function booted()
    {
        static::saved(function (Employee $employee) {
             // Generate Excel profil
            $excelFileName = "Employee-{$employee->id}.xlsx";
            Excel::store(new EmployeeExport(collect([$employee])), $excelFileName, 'public');

            
        });
    }

    /**
     * Getter array alamat lengkap
     */
    public function addressesWithDetails(): array
    {
        $raw = $this->address;

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        return array_map(function ($item) {
            return [
                'detail_alamat' => $item['detail_alamat'] ?? '-',
                'kelurahan'     => $item['kelurahan'] ?? '-',
                'kecamatan'     => $item['kecamatan'] ?? '-',
                'kota_kab'      => $item['kota_kab'] ?? '-',
                'provinsi'      => $item['provinsi'] ?? '-',
                'kode_pos'      => $item['kode_pos'] ?? '-',
            ];
        }, $raw);
    }

    /**
     * Getter string alamat lengkap
     */
    public function getFullAddressAttribute(): string
    {
        $items = $this->addressesWithDetails();

        if (empty($items)) {
            return '-';
        }

        return collect($items)->map(function ($i) {
            $kelurahan = \Laravolt\Indonesia\Models\Kelurahan::where('code', $i['kelurahan'])->first();
            $kecamatan = \Laravolt\Indonesia\Models\Kecamatan::where('code', $i['kecamatan'])->first();
            $kota      = \Laravolt\Indonesia\Models\Kabupaten::where('code', $i['kota_kab'])->first();
            $provinsi  = \Laravolt\Indonesia\Models\Provinsi::where('code', $i['provinsi'])->first();

            return sprintf(
                "%s, %s, %s, %s, %s, %s",
                $kelurahan?->name ?? '-',
                $kecamatan?->name ?? '-',
                $kota?->name ?? '-',
                $provinsi?->name ?? '-',
                $i['kode_pos'] ?? '-',
                $i['detail_alamat'] ?? '-'
            );
        })->implode('<br>');
    }

    /**
     * (Opsional) Relasi ke cabang
     */
    
}