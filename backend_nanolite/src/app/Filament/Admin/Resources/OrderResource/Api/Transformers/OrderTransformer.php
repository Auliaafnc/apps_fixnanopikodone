<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;
use App\Models\PostalCode;

class OrderTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing([
            'department:id,name',
            'employee:id,name',
            'customer:id,name,customer_category_id,phone,address',
            'customerCategory:id,name',
            'customerProgram:id,name',
        ]);

        /* ================= LABEL STATUS ================= */

        $statusPembayaranLabel = match ($this->status_pembayaran) {
            'belum bayar' => 'Belum Bayar',
            'sudah bayar' => 'Sudah Bayar',
            'belum lunas' => 'Belum Lunas',
            'sudah lunas' => 'Sudah Lunas',
            default       => $this->status_pembayaran
                ? ucfirst((string) $this->status_pembayaran)
                : null,
        };

        $statusPengajuanLabel = match ($this->status_pengajuan) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'pending'  => 'Pending',
            default    => $this->status_pengajuan
                ? ucfirst((string) $this->status_pengajuan)
                : null,
        };

        $statusProductLabel = match ($this->status_product) {
            'pending'     => 'Pending',
            'ready_stock' => 'Ready Stock',
            'sold_out'    => 'Sold Out',
            'rejected'    => 'Ditolak',
            default       => $this->status_product
                ? ucfirst((string) $this->status_product)
                : null,
        };

        $statusOrderLabel = match ($this->status_order) {
            'pending'    => 'Pending',
            'confirmed'  => 'Confirmed',
            'processing' => 'Processing',
            'on_hold'    => 'On Hold',
            'delivered'  => 'Delivered',
            'completed'  => 'Completed',
            'cancelled'  => 'Cancelled',
            'rejected'   => 'Ditolak',
            default      => $this->status_order
                ? ucfirst((string) $this->status_order)
                : null,
        };

        // ✅ alamat: dukung bentuk array (repeater) & string (teks bebas)
        [$alamatReadable, $alamatText] = $this->normalizeAddress($this->address);

        // ✅ BUKTI PENGIRIMAN – LOGIC MIRIP PRODUCT RETURN
        $deliveryPaths = $this->normalizeDeliveryPaths($this->delivery_images);
        $deliveryUrls  = collect($deliveryPaths)
            ->filter()
            ->map(fn ($p) => Storage::url($p))
            ->values()
            ->all();
        $singleDelivery = $deliveryUrls[0] ?? null;

        return [
            //  PENTING: kirim ID supaya FE nggak 0
            'id'                     => $this->id,

            'no_order'               => $this->no_order,
            'department'             => $this->department?->name ?? '-',
            'employee'               => $this->employee?->name ?? '-',

            // Relasi customer & kategori
            'customer_id'            => $this->customer?->id ?? null,
            'customer'               => $this->customer?->name ?? '-',
            'customer_category_id'   => $this->customer?->customer_category_id ?? null,
            'customer_category'      => $this->customerCategory?->name ?? '-',
            'customer_program_id'    => $this->customerProgram?->id ?? null,
            'customer_program'       => $this->customerProgram?->name ?? '-',

            // Kontak
            'phone'                  => $this->customer?->phone ?? $this->phone,
            'address_text'           => $alamatText,      // string gabungan
            'address_detail'         => $alamatReadable,  // array detail

            // Produk
            'products'               => $this->mapProductsReadable($this->products),

            // Diskon (1–4)
            'diskon' => [
                'enabled'             => (bool) ($this->diskons_enabled ?? false),

                'diskon_1'            => (float) ($this->diskon_1 ?? 0),
                'penjelasan_diskon_1' => $this->penjelasan_diskon_1,

                'diskon_2'            => (float) ($this->diskon_2 ?? 0),
                'penjelasan_diskon_2' => $this->penjelasan_diskon_2,

                'diskon_3'            => (float) ($this->diskon_3 ?? 0),
                'penjelasan_diskon_3' => $this->penjelasan_diskon_3,

                'diskon_4'            => (float) ($this->diskon_4 ?? 0),
                'penjelasan_diskon_4' => $this->penjelasan_diskon_4,
            ],

            // Reward & Program Point
            'reward' => [
                'enabled' => (bool) ($this->reward_enabled ?? false),
                'points'  => (int) ($this->reward_point ?? 0),
            ],
            'program_point' => [
                'enabled' => (bool) ($this->program_enabled ?? false),
                'points'  => (int) ($this->jumlah_program ?? 0),
            ],

            // Pembayaran
            'payment_method'         => $this->payment_method, // raw: cash / tempo
            'payment_method_label'   => $this->payment_method === 'tempo'
                ? 'Tempo'
                : ($this->payment_method === 'cash' ? 'Cash' : null),
            'payment_due_until'      => optional($this->payment_due_until)->format('Y-m-d'),

            // Status
            'status_pembayaran_raw'   => $this->status_pembayaran,
            'status_pembayaran'       => $statusPembayaranLabel,

            'status_pengajuan_raw'    => $this->status_pengajuan,
            'status_pengajuan'        => $statusPengajuanLabel,

            'status_product_raw'      => $this->status_product,
            'status_product'          => $statusProductLabel,

            'status_order_raw'        => $this->status_order,
            'status_order'            => $statusOrderLabel,

            // Untuk kompatibilitas lama: status utama = status_order
            'status'                  => $statusOrderLabel,

            'rejection_comment'       => $this->rejection_comment,
            'on_hold_comment'         => $this->on_hold_comment,
            'on_hold_until'           => optional($this->on_hold_until)->format('d/m/Y'),
            'cancelled_comment'       => $this->cancelled_comment,

            // Total harga
            'total_harga'            => (int) ($this->total_harga ?? 0),
            'total_harga_after_tax'  => (int) ($this->total_harga_after_tax ?? 0),

            // File unduhan
            'invoice_pdf_url'        => $this->order_file ? Storage::url($this->order_file) : null,

            //  BUKTI PENGIRIMAN KE FE (SAMA POLA DENGAN RETUR)
            'delivery_images_urls'   => $deliveryUrls,
            'delivery_image_url'     => $singleDelivery,

            'created_at'             => optional($this->created_at)->format('d/m/Y'),
            'updated_at'             => optional($this->updated_at)->format('d/m/Y'),
        ];
    }

    /* ---------------- Address helpers ---------------- */

    /**
     * Kembalikan [address_detail(array), address_text(string)]
     */
    private function normalizeAddress($address): array
    {
        // ... (biarkan sama persis dengan punyamu)
        // TIDAK DIUBAH
        // -------------
        if (is_array($address)) {
            $detail = $this->mapAddressesReadable($address);
            return [$detail, $this->addressText($detail)];
        }

        if (is_string($address)) {
            $decoded = json_decode($address, true);
            if (is_array($decoded)) {
                $detail = $this->mapAddressesReadable($decoded);
                return [$detail, $this->addressText($detail)];
            }

            $trim = trim($address);
            if ($trim !== '') {
                $detail = [[
                    'detail_alamat' => $trim,
                    'provinsi'      => ['code' => null, 'name' => null],
                    'kota_kab'      => ['code' => null, 'name' => null],
                    'kecamatan'     => ['code' => null, 'name' => null],
                    'kelurahan'     => ['code' => null, 'name' => null],
                    'kode_pos'      => null,
                ]];
                return [$detail, $trim];
            }
        }

        return [[], null];
    }

    private function addressText(array $items): ?string
    {
        // ... (biarkan sama)
        if (empty($items)) return null;
        return collect($items)->map(function ($a) {
            $parts = [
                $a['detail_alamat'] ?? null,
                $a['kelurahan']['name'] ?? null,
                $a['kecamatan']['name'] ?? null,
                $a['kota_kab']['name'] ?? null,
                $a['provinsi']['name'] ?? null,
                $a['kode_pos'] ?? null,
            ];
            $parts = array_filter($parts, fn ($v) => !is_null($v) && trim((string) $v) !== '');
            return implode(', ', $parts);
        })->filter()->join(' | ');
    }

    private function mapAddressesReadable($address): array
    {
        // ... (biarkan sama)
        $items = is_array($address) ? $address : json_decode($address ?? '[]', true);
        if (!is_array($items)) $items = [];

        return array_map(function ($a) {
            $provCode = $a['provinsi']       ?? $a['provinsi_code']   ?? null;
            $kabCode  = $a['kota_kab']       ?? $a['kota_kab_code']   ?? null;
            $kecCode  = $a['kecamatan']      ?? $a['kecamatan_code']  ?? null;
            $kelCode  = $a['kelurahan']      ?? $a['kelurahan_code']  ?? null;

            $provCode = is_array($provCode) ? ($provCode['code'] ?? null) : $provCode;
            $kabCode  = is_array($kabCode)  ? ($kabCode['code'] ?? null)  : $kabCode;
            $kecCode  = is_array($kecCode)  ? ($kecCode['code'] ?? null)  : $kecCode;
            $kelCode  = is_array($kelCode)  ? ($kelCode['code'] ?? null)  : $kelCode;

            return [
                'detail_alamat' => $a['detail_alamat'] ?? null,
                'provinsi'      => ['code' => $provCode, 'name' => $this->nameFromCode(Provinsi::class,  $provCode)],
                'kota_kab'      => ['code' => $kabCode,  'name' => $this->nameFromCode(Kabupaten::class, $kabCode)],
                'kecamatan'     => ['code' => $kecCode,  'name' => $this->nameFromCode(Kecamatan::class, $kecCode)],
                'kelurahan'     => ['code' => $kelCode,  'name' => $this->nameFromCode(Kelurahan::class, $kelCode)],
                'kode_pos'      => $a['kode_pos'] ?? $this->postalByVillage($kelCode),
            ];
        }, $items);
    }

    private function nameFromCode(string $model, ?string $code): ?string
    {
        if (!$code) return null;
        return optional($model::where('code', $code)->first())->name;
    }

    private function postalByVillage(?string $villageCode): ?string
    {
        if (!$villageCode) return null;
        return optional(PostalCode::where('village_code', $villageCode)->first())->postal_code;
    }

    private function mapProductsReadable($products): array
{
    // decode aman
    $items = is_array($products) ? $products : json_decode($products ?? '[]', true);
    if (!is_array($items)) $items = [];

    return array_map(function ($p) {
        // Ambil produk + relasi (brand, category) supaya dapat nama
        $product = !empty($p['produk_id'])
            ? Product::with(['brand:id,name', 'category:id,name'])->find($p['produk_id'])
            : null;

        // --- Warna: kompat nama / id, dan fallback 'warna'
        $warnaKey = $p['warna_id'] ?? $p['warna'] ?? null; // garansi mengikuti gaya order
        $warnaKey = is_string($warnaKey) ? trim($warnaKey) : $warnaKey;
        $colorName = null;

        if (!empty($warnaKey) && $warnaKey !== '-') {
            if ($product) {
                $colors = collect($product->colors ?? []);
                $color  = $colors->first(function ($c) use ($warnaKey) {
                    if (is_array($c)) {
                        return ($c['id'] ?? null) == $warnaKey
                            || strcasecmp((string)($c['name'] ?? ''), (string)$warnaKey) === 0;
                    }
                    if (is_object($c)) {
                        $id   = property_exists($c, 'id') ? $c->id : null;
                        $name = property_exists($c, 'name') ? $c->name : null;
                        return ($id != null && $id == $warnaKey)
                            || ($name != null && strcasecmp((string)$name, (string)$warnaKey) === 0);
                    }
                    return strcasecmp((string)$c, (string)$warnaKey) === 0; // string polos
                });

                if (is_array($color)) {
                    $colorName = $color['name'] ?? (string)($color['id'] ?? null);
                } elseif (is_object($color)) {
                    $colorName = $color->name ?? (string)($color->id ?? '');
                } elseif (!is_null($color)) {
                    $colorName = (string)$color;
                } else {
                    if (is_string($warnaKey) && strlen($warnaKey) > 0) {
                        $colorName = $warnaKey; // pakai apa adanya
                    }
                }
            } else {
                if (is_string($warnaKey) && strlen($warnaKey) > 0) {
                    $colorName = $warnaKey; // tidak ada product, pakai string warna langsung
                }
            }
        }

        // --- Brand & Category name
        $brandName = $product?->brand?->name;
        $categoryName = $product?->category?->name;

        if (!$brandName && !empty($p['brand_id']) && class_exists(\App\Models\Brand::class)) {
            try {
                $brand = \App\Models\Brand::query()->select('id','name')->find($p['brand_id']);
                $brandName = $brand?->name ?? $brandName;
            } catch (\Throwable $e) {}
        }

        if (!$categoryName && !empty($p['kategori_id']) && class_exists(\App\Models\Category::class)) {
            try {
                $cat = \App\Models\Category::query()->select('id','name')->find($p['kategori_id']);
                $categoryName = $cat?->name ?? $categoryName;
            } catch (\Throwable $e) {}
        }

        // --- Quantity saja (price & subtotal dihapus)
        $qty = (int)($p['quantity'] ?? 0);

        return [
            'brand'    => $brandName,
            'category' => $categoryName,
            'product'  => $product?->name,
            'color'    => $colorName,
            'quantity' => $qty,
        ];
    }, $items);
}


    /**  helper baru, sama kayak di ProductReturnTransformer */
    private function normalizeDeliveryPaths($raw): array
    {
        if (empty($raw)) return [];

        if (is_string($raw) && str_starts_with(trim($raw), '[')) {
            $arr = json_decode($raw, true);
            return is_array($arr) ? $arr : [];
        }

        if (is_string($raw)) {
            return [$raw];
        }

        if (is_array($raw)) {
            return $raw;
        }

        return [];
    }
}
