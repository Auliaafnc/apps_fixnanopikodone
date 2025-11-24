<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PerbaikandataExport;
use App\Models\Concerns\OwnedByEmployee;
use App\Models\Concerns\LatestFirst;

class Perbaikandata extends Model
{
    use HasFactory, OwnedByEmployee, LatestFirst;

    protected $fillable = [
        'company_id',
        'department_id',
        'employee_id',
        'customer_categories_id',
        'customer_id',
        'pilihan_data',
        'data_baru',
        'address',
        'image',
        'status_pengajuan',
        'alasan_penolakan',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'department_id'          => 'integer',
        'employee_id'            => 'integer',
        'customer_categories_id' => 'integer',
        'customer_id'            => 'integer',
        'address'                => 'array',
        'image'                  => 'array',
    ];

   /** Normalisasi email (tanpa bikin unik) */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    /** Satu booted(): saving (olah image) lalu saved (export) */
    protected static function booted()
    {
        // Olah image menjadi array path/URL valid sebelum simpan
        static::saving(function (Perbaikandata $perbaikandata) {
            self::consumeImageArray($perbaikandata, 'image', 'perbaikandatas');
        });

        // Export Excel setelah tersimpan
        static::saved(function (Perbaikandata $perbaikandata) {
            $excelFileName = "Perbaikandata-{$perbaikandata->id}.xlsx";
            Excel::store(new PerbaikandataExport(collect([$perbaikandata])), $excelFileName, 'public');
            $perbaikandata->updateQuietly(['perbaikandata_excel' => $excelFileName]);
        });
    }

    protected static function consumeImageArray(Perbaikandata $model, string $field, string $folder): void
    {
        $imgs = $model->$field ?? [];

        if (is_string($imgs)) {
            $decoded = json_decode($imgs, true);
            $imgs = is_array($decoded) ? $decoded : ($imgs !== '' ? [$imgs] : []);
        }

        if (!is_array($imgs)) {
            $model->$field = [];
            return;
        }

        $saved = [];
        foreach ($imgs as $img) {
            if (!is_string($img) || $img === '') continue;

            // URL http/https
            if (preg_match('#^https?://#i', $img)) {
                $saved[] = $img;
                continue;
            }

            // Data URI base64
            if (preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $img, $m)) {
                $ext  = strtolower($m[1] ?? 'png');
                $data = substr($img, strpos($img, ',') + 1);
                $bin  = base64_decode($data, true);
                if ($bin === false) continue;

                $name = $folder . '/' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
                Storage::disk('public')->put($name, $bin);
                $saved[] = $name;
                continue;
            }

            // Path relatif
            $saved[] = ltrim($img, '/');
        }

        $model->$field = array_values(array_filter($saved, fn ($v) => $v !== null && $v !== ''));
    }

    // ================= RELASI =================
    public function company()          { return $this->belongsTo(Company::class); }
    public function department()       { return $this->belongsTo(Department::class, 'department_id'); }
    public function customerCategory() { return $this->belongsTo(CustomerCategories::class, 'customer_categories_id'); }
    public function customer()         { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function employee()         { return $this->belongsTo(Employee::class); }
    public function orders()           { return $this->hasMany(Order::class); }
    public function productReturns()   { return $this->hasMany(ProductReturn::class); }
    public function garansis()         { return $this->hasMany(Garansi::class); }

    // ================= ADDRESS HELPERS =================
    public function addressesWithDetails(): array
    {
        $raw = $this->address;
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        elseif (!is_array($raw)) $raw = [];

        return array_map(fn($r) => [
            'detail_alamat' => $r['detail_alamat'] ?? null,
            'kelurahan'     => $r['kelurahan']     ?? null, // kode
            'kecamatan'     => $r['kecamatan']     ?? null, // kode
            'kota_kab'      => $r['kota_kab']      ?? null, // kode
            'provinsi'      => $r['provinsi']      ?? null, // kode
            'kode_pos'      => $r['kode_pos']      ?? null,
        ], $raw);
    }

    public function getFullAddressAttribute(): string
    {
        $items = $this->addressesWithDetails();
        if (empty($items)) return '-';

        return collect($items)->map(function ($i) {
            $kel = $i['kelurahan'] ? \Laravolt\Indonesia\Models\Kelurahan::where('code',$i['kelurahan'])->value('name') : null;
            $kec = $i['kecamatan'] ? \Laravolt\Indonesia\Models\Kecamatan::where('code',$i['kecamatan'])->value('name') : null;
            $kab = $i['kota_kab']  ? \Laravolt\Indonesia\Models\Kabupaten::where('code',$i['kota_kab'])->value('name') : null;
            $prov= $i['provinsi']  ? \Laravolt\Indonesia\Models\Provinsi::where('code',$i['provinsi'])->value('name') : null;

            $parts = array_filter([$kel, $kec, $kab, $prov, $i['kode_pos'] ?? null, $i['detail_alamat'] ?? null], fn($v)=>filled($v));
            return $parts ? implode(', ', $parts) : '-';
        })->implode('<br>');
    }



}
