<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Exports\CategoryExport;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'company_id',
        'name',
        'deskripsi',
        'status',
        'image',
    ];

    protected $casts = [
        'brand_id'   => 'integer',
        'company_id' => 'integer',
        'name'       => 'string',
        'deskripsi'  => 'string',
        'image'      => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function (Category $category) {
            $fileName = "Category-{$category->id}-" . Str::slug($category->name) . ".xlsx";
            Excel::store(new CategoryExport([
                'brand_id' => $category->brand_id
            ]), $fileName, 'public');

        });
    }

    /**
     * Relasi ke perusahaan
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
     * Relasi ke produk dalam kategori ini
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
