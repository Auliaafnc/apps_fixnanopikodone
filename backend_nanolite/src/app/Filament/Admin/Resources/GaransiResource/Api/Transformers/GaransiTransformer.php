<?php

namespace App\Filament\Admin\Resources\GaransiResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;
use App\Models\PostalCode;

class GaransiTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing([
            'department:id,name',
            'employee:id,name',
            'customer:id,name',
            'customerCategory:id,name',
        ]);

        $statusLabel = match ($this->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'pending'  => 'Pending',
            default    => ucfirst((string) $this->status),
        };

        // ---------- ALAMAT ----------
        $alamatReadable = $this->mapAddressesReadable($this->address);
        $addressText    = $this->buildAddressText($alamatReadable);

        if (!$addressText && is_string($this->address) && trim($this->address) !== '') {
            $addressText = trim($this->address);
        }

        // ---------- PRODUK ----------
        $productsReadable = $this->mapProductsReadable($this->products);

        // ---------- FOTO BARANG (MULTI) ----------
        $imageUrl  = null;
        $imageUrls = [];

        if (is_array($this->image)) {
            $imageUrls = collect($this->image)
                ->filter(fn ($p) => is_string($p) && trim($p) !== '')
                ->map(fn ($p) => Storage::url($p))
                ->values()
                ->toArray();
        } elseif (is_string($this->image) && $this->image !== '') {
            $imageUrls = [Storage::url($this->image)];
        }

        if (!empty($imageUrls)) {
            $imageUrl = $imageUrls[0];
        }

        // ---------- FOTO BUKTI PENGIRIMAN (MULTI) ----------
        // Asumsikan di model ada accessor delivery_images_urls / delivery_images
        $deliveryImages = [];

        if (is_array($this->delivery_images_urls)) {
            $deliveryImages = $this->delivery_images_urls;
        } elseif (is_array($this->delivery_images)) {
            $deliveryImages = $this->delivery_images;
        }

        $deliveryImages = collect($deliveryImages)
            ->filter(fn ($p) => is_string($p) && trim($p) !== '')
            ->map(function ($p) {
                if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                    return $p;
                }
                return Storage::url($p);
            })
            ->values()
            ->toArray();

        $deliveryImageUrl = $this->delivery_image_url;
        if (!$deliveryImageUrl && !empty($deliveryImages)) {
            $deliveryImageUrl = $deliveryImages[0];
        }

        return [
            'id'                => $this->id,
            'no_garansi'        => $this->no_garansi,
            'department'        => $this->department?->name ?? '-',
            'employee'          => $this->employee?->name ?? '-',
            'customer'          => $this->customer?->name ?? '-',
            'category'          => $this->customerCategory?->name ?? '-',
            'customer_category' => $this->customerCategory?->name ?? '-',

            'phone'             => $this->phone,

            // inilah yang dipakai Flutter
            'address'           => $this->address,
            'address_text'      => $addressText,
            'address_detail'    => $alamatReadable,

            'purchase_date'     => optional($this->purchase_date)->format('d/m/Y'),
            'claim_date'        => optional($this->claim_date)->format('d/m/Y'),

            'reason'            => $this->reason,
            'note'              => $this->note ?: null,

            // FOTO BARANG
            'image'       => $imageUrl,    // single (fallback)
            'image_urls'  => $imageUrls,   // array (baru)

            // FOTO BUKTI PENGIRIMAN
            'delivery_image_url'   => $deliveryImageUrl, // single (fallback)
            'delivery_images_urls' => $deliveryImages,   // array (baru)

            'products'          => $productsReadable,
            'products_details'  => collect($productsReadable)->map(function ($p) {
                $brand = $p['brand'] ?? '-';
                $cat   = $p['category'] ?? '-';
                $prod  = $p['product'] ?? '-';
                $color = $p['color'] ?? '-';
                $qty   = $p['quantity'] ?? 0;
                return "{$brand} – {$cat} – {$prod} – {$color} – Qty: {$qty}";
            })->implode("\n"),

            'status_pengajuan_raw' => $this->status_pengajuan,
            'status_product_raw'   => $this->status_product,
            'status_garansi_raw'   => $this->status_garansi,
            'status'               => $statusLabel,

            'rejection_comment' => $this->rejection_comment,
            'on_hold_comment'   => $this->on_hold_comment,
            'on_hold_until'     => optional($this->on_hold_until)->format('d/m/Y'),
            'cancelled_comment' => $this->cancelled_comment,

            'pdf_url'           => $this->garansi_file ? Storage::url($this->garansi_file) : null,

            'created_at'        => optional($this->created_at)->format('d/m/Y'),
            'updated_at'        => optional($this->updated_at)->format('d/m/Y'),
        ];
    }

    /* ================== HELPERS (semua DI DALAM CLASS) ================== */

    /** Build string alamat untuk ditampilkan di Flutter */
    protected function buildAddressText(array $items): ?string
    {
        if (empty($items)) return null;

        return collect($items)->map(function ($a) {
            $name = function ($objOrStr) {
                if (is_array($objOrStr)) {
                    return $objOrStr['name'] ?? null;
                }
                return is_string($objOrStr) ? $objOrStr : null;
            };

            $parts = [
                $a['detail_alamat'] ?? null,
                $name($a['kelurahan'] ?? null),
                $name($a['kecamatan'] ?? null),
                $name($a['kota_kab'] ?? null),
                $name($a['provinsi'] ?? null),
                $a['kode_pos'] ?? null,
            ];

            $parts = array_values(array_filter($parts, function ($v) {
                $t = trim((string) $v);
                return $t !== '' && $t !== '-' && strtolower($t) !== 'null';
            }));

            return implode(', ', $parts);
        })->filter()->join(' | ');
    }

    /** Normalisasi address jadi array rapi (detail_alamat, provinsi, kota, dll) */
    protected function mapAddressesReadable($address): array
    {
        $items = is_array($address) ? $address : json_decode($address ?? '[]', true);
        if (!is_array($items)) {
            $items = [];
        }

        // kalau associative array (satu object), bungkus jadi [ {...} ]
        if ($items && array_keys($items) !== range(0, count($items) - 1)) {
            $items = [$items];
        }

        $getCode = function (array $a, string $key) {
            if (!empty($a["{$key}_code"])) return (string) $a["{$key}_code"];
            if (!empty($a[$key]['code']))  return (string) $a[$key]['code'];
            if (!empty($a[$key]) && is_string($a[$key])) {
                $v = (string) $a[$key];
                return preg_match('/^[A-Za-z0-9._-]{2,10}$/', $v) ? $v : null;
            }
            return null;
        };

        $getName = function (array $a, string $key, ?string $code, string $model) {
            if (!empty($a["{$key}_name"])) return (string) $a["{$key}_name"];
            if (!empty($a[$key]['name']))  return (string) $a[$key]['name'];
            if ($code) return $this->nameFromCode($model, $code);
            if (!empty($a[$key]) && is_string($a[$key])) {
                $v = (string) $a[$key];
                if (!preg_match('/^[A-Za-z0-9._-]{2,10}$/', $v)) return $v;
            }
            return null;
        };

        return array_map(function ($a) use ($getCode, $getName) {
            $provCode = $getCode($a, 'provinsi');
            $kabCode  = $getCode($a, 'kota_kab');
            $kecCode  = $getCode($a, 'kecamatan');
            $kelCode  = $getCode($a, 'kelurahan');

            return [
                'detail_alamat' => $a['detail_alamat'] ?? null,
                'provinsi'      => [
                    'code' => $provCode,
                    'name' => $getName($a, 'provinsi',  $provCode,  Provinsi::class),
                ],
                'kota_kab'      => [
                    'code' => $kabCode,
                    'name' => $getName($a, 'kota_kab',  $kabCode,   Kabupaten::class),
                ],
                'kecamatan'     => [
                    'code' => $kecCode,
                    'name' => $getName($a, 'kecamatan', $kecCode,   Kecamatan::class),
                ],
                'kelurahan'     => [
                    'code' => $kelCode,
                    'name' => $getName($a, 'kelurahan', $kelCode,   Kelurahan::class),
                ],
                'kode_pos'      => $a['kode_pos'] ?? $this->postalByVillage($kelCode),
            ];
        }, $items);
    }

    protected function nameFromCode(string $model, ?string $code): ?string
    {
        if (!$code) return null;
        return optional($model::where('code', $code)->first())->name;
    }

    protected function postalByVillage(?string $villageCode): ?string
    {
        if (!$villageCode) return null;
        return optional(PostalCode::where('village_code', $villageCode)->first())->postal_code;
    }

    /** Mapping produk untuk dikirim ke Flutter */
    /** Mapping produk untuk dikirim ke Flutter */
/** Mapping produk untuk dikirim ke Flutter */
protected function mapProductsReadable($products): array
{
    $items = is_array($products) ? $products : json_decode($products ?? '[]', true);
    if (!is_array($items)) {
        $items = [];
    }

    return array_map(function ($p) {
        $product = isset($p['produk_id'])
            ? Product::with(['brand:id,name', 'category:id,name'])->find($p['produk_id'])
            : null;

        // --- ambil warna dari payload ---
        $warnaId   = $p['warna_id'] ?? $p['color'] ?? null;
        $colorName = $p['color_name'] ?? $p['warna_name'] ?? null; // kalau sudah disimpan nama, pakai ini dulu

        // --- normalisasi field colors dari product (bisa array / json / collection) ---
        $colorsRaw = $product?->colors;
        if ($colorsRaw instanceof \Illuminate\Support\Collection) {
            $colorsRaw = $colorsRaw->toArray();
        } elseif (is_string($colorsRaw)) {
            $decoded = json_decode($colorsRaw, true);
            $colorsRaw = is_array($decoded) ? $decoded : [];
        }
        $colors = is_array($colorsRaw) ? $colorsRaw : [];

        // --- kalau belum punya nama, coba cari berdasarkan warna_id di $product->colors ---
        if (!$colorName && $product && $warnaId !== null) {
            foreach ($colors as $key => $c) {
                if (is_array($c)) {
                    // contoh: [{"id":2,"name":"Putih","code":"PUTIH"}, ...]
                    $candidateIds = [
                        $c['id']    ?? null,
                        $c['value'] ?? null,
                        $c['code']  ?? null,
                    ];
                    $candidateNames = [
                        $c['name']  ?? $c['label'] ?? $c['color'] ?? null,
                    ];

                    $matchId = in_array((string) $warnaId, array_map('strval', $candidateIds), true)
                        || (string) $key === (string) $warnaId;

                    $matchName = in_array((string) $warnaId, array_map('strval', $candidateNames), true);

                    if ($matchId || $matchName) {
                        $colorName = $c['name']
                            ?? $c['label']
                            ?? $c['value']
                            ?? $c['color'];
                        break;
                    }
                } else {
                    // contoh: ["Putih","Kuning"] atau {"2":"Putih","3":"Kuning"}
                    if ((string) $c === (string) $warnaId || (string) $key === (string) $warnaId) {
                        $colorName = (string) $c;
                        break;
                    }
                }
            }
        }

        // --- fallback terakhir: kalau masih kosong, pakai ID (opsional, bisa kamu ganti '-' kalau nggak mau ID sama sekali) ---
        if (!$colorName && $warnaId !== null) {
            $colorName = (string) $warnaId;   // atau ganti jadi '-' kalau mau hard-only nama
        }

        return [
            'brand'    => $product?->brand?->name ?? null,
            'category' => $product?->category?->name ?? null,
            'product'  => $product?->name ?? null,
            'color'    => $colorName,
            'quantity' => (int) ($p['quantity'] ?? 0),
        ];
    }, $items);
}


}
