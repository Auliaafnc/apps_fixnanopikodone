<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BrandExport;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'deskripsi',
        'status',
        'image',
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    protected static function booted()
    {
        static::saved(function (Brand $brand) {
            $fileName = "Brand-{$brand->id}-" . Str::slug($brand->name) . ".xlsx";
            Excel::store(new BrandExport(collect([$brand])), $fileName, 'public');
        });
    }

    /**
     * Relasi ke company (satu brand dimiliki satu company)
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi ke categories (satu brand punya banyak kategori)
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Relasi ke products (satu brand punya banyak produk)
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
