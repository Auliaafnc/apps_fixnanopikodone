<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomerCategoriesExport;
use Illuminate\Support\Str;

class CustomerCategories extends Model
{
    protected $fillable = [
        'company_id',  // tambahkan agar mass assignment bisa digunakan
        'name',
        'status',
        'deskripsi',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    /**
     * Relasi ke perusahaan pemilik kategori
     */



    protected static function booted()
    {
        static::saved(function (CustomerCategories $category) {
            // Ambil semua data kategori customer untuk diekspor
            $allCategories = CustomerCategories::with('customers')->get();

            // Simpan file Excel ke storage/app/public/export_customer_categories.xlsx
            Excel::store(new CustomerCategoriesExport($allCategories), 'export_customer_categories.xlsx', 'public');
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi ke customer (satu kategori punya banyak customer)
     */
    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_categories_id');
    }

    /**
     * Relasi ke banyak program (many-to-many)
     */
    public function customerPrograms()
    {
        return $this->belongsToMany(
            CustomerProgram::class,
            'customer_category_customer_program', // nama pivot table
            'category_id',                        // foreignKey untuk model ini
            'program_id'                          // foreignKey untuk model lain
        );
    }

    /**
     * Relasi ke semua employee yang menangani kategori ini
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'customer_categories_id');
    }

    /**
     * Relasi ke semua order dari kategori ini
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_categories_id');
    }

    /**
     * Relasi ke semua pengembalian produk
     */
    public function productReturns()
    {
        return $this->hasMany(ProductReturn::class, 'customer_categories_id');
    }

    /**
     * Relasi ke semua garansi
     */
    public function garansis()
    {
        return $this->hasMany(Garansi::class, 'customer_categories_id');
    }
}
