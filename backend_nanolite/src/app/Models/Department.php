<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DepartmentExport;
use Illuminate\Support\Str;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = [
        'company_id',
        'name',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'name'       => 'string',
        'status'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke perusahaan induk
     */


    protected static function booted()
    {
        static::saved(function (Department $department) {
        $slug = Str::slug($department->name);
        // Buat nama file berdasarkan ID dan nama program
        $excelFileName = "Department-{$department->id}-{$slug}.xlsx";

        // Export satu program dalam bentuk collection
        Excel::store(new DepartmentExport(collect([$department])), $excelFileName, 'public');
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relasi ke semua karyawan dalam departemen ini
     */
    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
