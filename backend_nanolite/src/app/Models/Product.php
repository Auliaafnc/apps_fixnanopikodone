<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductExport;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'brand_id',
        'category_id',
        'name',
        'price',
        'description',
        'colors',
        'status',
        'image',
    ];

    protected $casts = [
        'company_id'  => 'integer',
        'brand_id'    => 'integer',
        'category_id' => 'integer',
        'colors'      => 'array',
        'price'       => 'float',
    ];



    protected static function booted()
    {
        static::saved(function (Product $product) {
             // Generate Excel profil
            $excelFileName = "Product-{$product->id}.xlsx";
            Excel::store(new ProductExport(['product_id' => $product->id]), $excelFileName, 'public');

        });
    }

    /**
     * Relasi ke perusahaan pemilik produk
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi ke brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relasi ke kategori produk
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi ke semua pesanan yang mengandung produk ini
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Relasi ke semua garansi dari produk ini
     */
    public function garansis()
    {
        return $this->hasMany(Garansi::class);
    }

    /**
     * Relasi ke semua retur produk ini
     */
    public function returns()
    {
        return $this->hasMany(ProductReturn::class);
    }
}
