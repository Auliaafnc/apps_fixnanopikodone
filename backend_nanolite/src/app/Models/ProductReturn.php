<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductReturnExport;
use App\Models\Concerns\OwnedByEmployee;
use App\Models\Concerns\LatestFirst;
use App\Models\Product;

class ProductReturn extends Model
{
    use OwnedByEmployee, LatestFirst;

    protected $fillable = [
        'no_return',
        'company_id',
        'customer_categories_id',
        'customer_id',
        'employee_id',
        'department_id',
        'reason',
        'amount',
        'image',
        'phone',
        'note',
        'address',
        'products',
        'status_pengajuan',
        'status_product',
        'status_return',
        // komentar & by siapa
        'rejection_comment',
        'rejected_by',
        'sold_out_comment',
        'sold_out_by',
        'on_hold_comment',
        'on_hold_until',
        'on_hold_by',
        'cancelled_comment',
        'cancelled_by',
        // bukti delivered
        'delivery_images',
        'delivered_at',
        'delivered_by',
        'return_file',
        'return_excel',
    ];

    protected $casts = [
        'company_id'             => 'integer',
        'customer_id'            => 'integer',
        'employee_id'            => 'integer',
        'department_id'          => 'integer',
        'customer_categories_id' => 'integer',

        'products'        => 'array',
        'address'         => 'array',
        'image'           => 'array',
        'delivery_images' => 'array',

        'amount'       => 'decimal:2',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'delivered_at' => 'datetime',
        'on_hold_until'=> 'datetime',
    ];

    protected $appends = [
        'address_text',
        'image_url',
        'delivery_image_url',
        'delivery_images_urls',
        'products_details',
    ];

    protected static function booted()
    {
        static::creating(function (ProductReturn $return) {
            if (blank($return->no_return)) {
                $return->no_return = 'RET-' . now()->format('Ymd') . strtoupper(Str::random(4));
            }

            // default status
            $return->status_pengajuan ??= 'pending';
            $return->status_product   ??= 'pending';
            $return->status_return    ??= 'pending';

            self::normalizeProductColors($return);
        });

        static::saving(function (ProductReturn $return) {
            // simpan base64 image ke storage
            self::consumeImageArray($return, 'image', 'return-photos');
            self::consumeImageArray($return, 'delivery_images', 'return-delivery-photos');

            // normalisasi warna produk
            self::normalizeProductColors($return);

            // kalau pengajuan ditolak -> semua ikut rejected
            if ($return->status_pengajuan === 'rejected') {
                $return->status_product = 'rejected';
                $return->status_return  = 'rejected';
            }
        });

        static::saved(function (ProductReturn $return) {
            // generate PDF
            $html = view('invoices.product-return', compact('return'))->render();
            $pdf  = Pdf::loadHtml($html)->setPaper('a4', 'portrait');

            $pdfFileName = "Return-{$return->no_return}.pdf";
            Storage::disk('public')->put($pdfFileName, $pdf->output());
            $return->updateQuietly(['return_file' => $pdfFileName]);

            // generate Excel
            $excelFileName = "Return-{$return->no_return}.xlsx";
            Excel::store(new ProductReturnExport($return), $excelFileName, 'public');
            $return->updateQuietly(['return_excel' => $excelFileName]);
        });
    }

    // ================= NORMALISASI PRODUK & WARNA =================

    /**
     * Ubah warna_id index -> teks warna berdasarkan Product::colors
     */
    protected static function normalizeProductColorsArray(?array $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        foreach ($items as &$it) {
            $pid = $it['produk_id'] ?? null;
            if (!$pid) {
                continue;
            }

            $product = Product::find($pid);
            if (!$product) {
                continue;
            }

            // kalau warna_id masih angka (0,1,2) -> ambil label di $product->colors
            if (array_key_exists('warna_id', $it) && is_numeric($it['warna_id'])) {
                $idx    = (int) $it['warna_id'];
                $colors = $product->colors ?? [];
                if (isset($colors[$idx])) {
                    $it['warna_id'] = $colors[$idx]; // simpan label, misal "3000K"
                }
            }
        }

        return $items;
    }

    protected static function normalizeProductColors(ProductReturn $return): void
    {
        $items = $return->products;

        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (!is_array($items)) {
            $items = [];
        }

        $return->products = self::normalizeProductColorsArray($items);
    }

    /**
     * Override accessor products → selalu balikin array
     * dengan warna sudah normal (index -> teks).
     */
    public function getProductsAttribute($value)
    {
        if (is_string($value)) {
            $items = json_decode($value, true) ?: [];
        } elseif (is_array($value)) {
            $items = $value;
        } else {
            $items = [];
        }

        return self::normalizeProductColorsArray($items);
    }

    // ================= IMAGE HELPERS =================

    protected static function consumeImageArray(ProductReturn $return, string $field, string $folder): void
    {
        $imgs = $return->$field ?? [];

        // Kalau string:
        // - jika JSON array => decode
        // - kalau bukan JSON => jadikan array 1 elemen
        if (is_string($imgs)) {
            $decoded = json_decode($imgs, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $imgs = $decoded;
            } else {
                $imgs = [$imgs];
            }
        }

        if (!is_array($imgs)) {
            return;
        }

        $saved = [];

        foreach ($imgs as $img) {
            if (!is_string($img) || $img === '') {
                continue;
            }

            // base64 image
            if (preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/', $img, $m)) {
                $ext  = strtolower($m[1] ?? 'png');
                $data = substr($img, strpos($img, ',') + 1);
                $bin  = base64_decode($data, true);
                if ($bin === false) {
                    continue;
                }

                $name = $folder . '/' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
                Storage::disk('public')->put($name, $bin);
                $saved[] = $name;
            } else {
                // sudah path / URL
                $saved[] = $img;
            }
        }

        $return->$field = $saved;
    }

    protected function makeUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return Storage::disk('public')->url($path);
    }

    public function getImageUrlAttribute(): ?string
    {
        $img = $this->image;
        if (is_array($img) && !empty($img)) return $this->makeUrl($img[0]);
        if (is_string($img) && $img !== '')  return $this->makeUrl($img);
        return null;
    }

    public function getDeliveryImageUrlAttribute(): ?string
    {
        $imgs = $this->delivery_images;
        if (is_array($imgs) && !empty($imgs)) {
            return $this->makeUrl($imgs[0]);
        }
        return null;
    }

    public function getDeliveryImagesUrlsAttribute(): array
    {
        $imgs = $this->delivery_images;
        if (!is_array($imgs) || empty($imgs)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(fn ($p) => $this->makeUrl($p), $imgs)
            )
        );
    }

    // ================= RELASI =================

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategories::class, 'customer_categories_id');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delivered_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'rejected_by');
    }

    public function onHoldBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'on_hold_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cancelled_by');
    }

    // ================= PRODUK (TANPA HARGA) =================

    public function productsWithDetails(): array
    {
        $raw = $this->products;

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        } elseif (!is_array($raw)) {
            $raw = [];
        }

        return array_map(function ($item) {
            $product = Product::find($item['produk_id'] ?? null);

            $warna = $item['warna_id'] ?? '-';

            if ($product) {
                $colors = $product->colors ?? [];

                // kalau masih angka (index), convert ke teks
                if (is_numeric($warna)) {
                    $idx = (int) $warna;
                    if (isset($colors[$idx])) {
                        $warna = $colors[$idx];
                    }
                }
            }

            return [
                'brand_name'    => $product?->brand?->name ?? '(Brand hilang)',
                'category_name' => $product?->category?->name ?? '(Kategori hilang)',
                'product_name'  => $product?->name ?? '(Produk hilang)',
                'color'         => $warna,
                'quantity'      => (int) ($item['quantity'] ?? 0),

                'barcode'       => $product?->description,
            ];
        }, $raw);
    }

    /**
     * Untuk TextColumn::make('products_details') di Filament.
     * Format: Brand – Kategori – Produk – Warna – Qty: X
     */
    public function getProductsDetailsAttribute(): string
    {
        $items = $this->productsWithDetails();
        if (empty($items)) return '';

        return collect($items)->map(fn ($i) =>
            "{$i['brand_name']} – {$i['category_name']} – {$i['product_name']} – {$i['color']} – Qty: {$i['quantity']}"
        )->implode('<br>');
    }

    // ================= ALAMAT =================

    public function getAddressTextAttribute(): ?string
    {
        // 1. Ambil mentah dari DB
        $raw   = $this->getAttributes()['address'] ?? null;
        $value = $this->address; // hasil cast (array/string)

        // 2. Kalau raw string, coba decode JSON (alamat dari Flutter)
        if (is_string($raw)) {
            $trim    = trim($raw);
            $decoded = json_decode($trim, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    $value = $decoded;
                } elseif (is_string($decoded)) {
                    $trimDecoded = trim($decoded);
                    return $trimDecoded !== '' ? $trimDecoded : null;
                }
            } else {
                // bukan JSON: alamat polos
                return $trim !== '' ? $trim : null;
            }
        }

        // 3. Kalau masih string juga, anggap alamat polos
        if (is_string($value)) {
            $trim = trim($value);
            return $trim !== '' ? $trim : null;
        }

        // 4. Di sini kita harap value = array [{...}] atau {...}
        if (!is_array($value) || empty($value)) {
            return null;
        }

        $first = $value[0] ?? $value;
        if (!is_array($first)) {
            return null;
        }

        // kalau Flutter kirim detail_alamat sudah lengkap
        if (!empty($first['detail_alamat'])) {
            return $first['detail_alamat'];
        }

        $kel  = $first['kelurahan']['name']  ?? $first['kelurahan_name']  ?? $first['kelurahan']  ?? null;
        $kec  = $first['kecamatan']['name']  ?? $first['kecamatan_name']  ?? $first['kecamatan']  ?? null;
        $kab  = $first['kota_kab']['name']   ?? $first['kota_kab_name']   ?? $first['kota_kab']   ?? null;
        $prov = $first['provinsi']['name']   ?? $first['provinsi_name']   ?? $first['provinsi']   ?? null;
        $kode = $first['kode_pos']           ?? null;

        $parts = array_filter(
            [$kel, $kec, $kab, $prov, $kode],
            fn ($v) => filled($v) && $v !== '-'
        );

        return empty($parts) ? null : implode(', ', $parts);
    }
}
