<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'status',
        'image',
    ];

    protected $casts = [
        'address' => 'array',
    ];

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
                $i['detail_alamat'] ?? '-',
            );
        })->implode('<br>');
    }

    // ===========================
    // RELASI YANG BERDASARKAN company_id
    // ===========================

    public function departemen()
    {
        return $this->hasMany(Departemen::class, 'company_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    public function brands()
    {
        return $this->hasMany(Brand::class, 'company_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'company_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'company_id');
    }

    public function customerCategories()
    {
        return $this->hasMany(CustomerCategories::class, 'company_id');
    }

    public function customerPrograms()
    {
        return $this->hasMany(CustomerProgram::class, 'company_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class, 'company_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'company_id');
    }

    public function productReturns()
    {
        return $this->hasMany(ProductReturn::class, 'company_id');
    }

    public function garansis()
    {
        return $this->hasMany(Garansi::class, 'company_id');
    }
}
