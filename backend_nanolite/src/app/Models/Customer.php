<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomerExport;

use App\Models\Concerns\OwnedByEmployee;
use App\Models\Concerns\LatestFirst;

class Customer extends Model
{
    use HasFactory, OwnedByEmployee, LatestFirst;

    protected $fillable = [
        'company_id',
        'customer_categories_id',
        'employee_id',
        'customer_program_id',
        'department_id',
        'name',
        'phone',
        'email',
        'address',
        'gmaps_link',
        'jumlah_program',
        'reward_point',
        'image',             // array of paths/urls
        'status_pengajuan',
        'status',
        'alasan_penolakan',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'customer_categories_id' => 'integer',
        'department_id'          => 'integer',
        'employee_id'            => 'integer',
        'customer_program_id'    => 'integer',
        'address'                => 'array',
        'image'                  => 'array',   // multi image
    ];

    public function getAddressAttribute($value)
    {
        // $value bisa string JSON, array, atau null
        if (is_string($value)) {
            $data = json_decode($value, true) ?: [];
        } else {
            $data = $value ?? [];
        }

        if (! is_array($data)) {
            return [];
        }

        foreach ($data as &$row) {
            if (! is_array($row)) {
                $row = [];
                continue;
            }

            foreach (['provinsi', 'kota_kab', 'kecamatan', 'kelurahan'] as $field) {
                if (isset($row[$field]) && is_array($row[$field])) {
                    // ambil kode kalau ada
                    $row[$field] =
                        $row[$field]['code']
                        ?? $row[$field]['id']
                        ?? (is_string(reset($row[$field])) ? reset($row[$field]) : null);
                }
            }
        }

        return $data;
    }

    /** Normalisasi email (tanpa bikin unik) */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = $value ? strtolower(trim($value)) : null;
    }

    /** Satu booted(): saving (olah image) lalu saved (export) */
    protected static function booted()
    {
        // Olah image menjadi array path/URL valid sebelum simpan
        static::saving(function (Customer $customer) {
            self::consumeImageArray($customer, 'image', 'customers');
        });

        // Export Excel setelah tersimpan
        static::saved(function (Customer $customer) {
            $excelFileName = "Customer-{$customer->id}.xlsx";
            Excel::store(new CustomerExport(collect([$customer])), $excelFileName, 'public');
        });
    }

    /**
     * Proses field array gambar:
     * - string JSON → decode
     * - data URI base64 → simpan ke storage/public/$folder
     * - URL http/https → pakai apa adanya
     * - path relatif → pakai apa adanya
     */
    protected static function consumeImageArray(Customer $model, string $field, string $folder): void
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
    public function customerProgram()  { return $this->belongsTo(CustomerProgram::class, 'customer_program_id'); }
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