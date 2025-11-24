<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Models\Product;
use Laravolt\Indonesia\Models\Provinsi;
use Laravolt\Indonesia\Models\Kabupaten;
use Laravolt\Indonesia\Models\Kecamatan;
use Laravolt\Indonesia\Models\Kelurahan;
use App\Models\PostalCode;

class ProductReturnTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing([
            'department:id,name',
            'employee:id,name',
            'customer:id,name',
            'category:id,name',
        ]);

        $statusLabel = match ($this->status) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'pending'  => 'Pending',
            default    => ucfirst((string) $this->status),
        };

        $alamatReadable   = $this->mapAddressesReadable($this->address);
        $productsReadable = $this->mapProductsReadable($this->products);

        // ===== FOTO BARANG (MULTI) =====
        $imagePaths = $this->normalizeImagePaths($this->image);
        $imageUrls  = collect($imagePaths)
            ->filter()
            ->map(fn ($p) => Storage::url($p))
            ->values()
            ->all();
        $singleImage = $imageUrls[0] ?? null;

        // ===== BUKTI PENGIRIMAN (BIARIN, TIDAK DIUBAH) =====
        $deliveryPaths = $this->normalizeDeliveryPaths($this->delivery_images);
        $deliveryUrls  = collect($deliveryPaths)
            ->filter()
            ->map(fn ($p) => Storage::url($p))
            ->values()
            ->all();
        $singleDelivery = $deliveryUrls[0] ?? null;

        return [
            'id'                 => $this->id,

            'no_return'          => $this->no_return,
            'department'         => $this->department?->name ?? '-',
            'employee'           => $this->employee?->name ?? '-',
            'customer'           => $this->customer?->name ?? '-',
            'customer_category'  => $this->category?->name ?? '-',
            'phone'              => $this->phone,

            'address_text'       => $this->addressText($alamatReadable),
            'address_detail'     => $alamatReadable,
            'amount'             => (int)($this->amount ?? 0),
            'reason'             => $this->reason,
            'note'               => $this->note ?: null,

            // ===== FOTO BARANG KE FE =====
            'image'       => $singleImage,   // untuk kode lama (single)
            'image_url'   => $singleImage,   // kalau butuh
            'image_urls'  => $imageUrls,     // <== ini list semua foto barang

            // PRODUK
            'products'           => $productsReadable,

            'status'             => $statusLabel,

            'status_pengajuan_raw' => $this->status_pengajuan ?? null,
            'status_product_raw'   => $this->status_product ?? null,
            'status_retur_raw'     => $this->status_return ?? null,

            'rejection_comment'    => $this->rejection_comment ?? null,
            'on_hold_comment'      => $this->on_hold_comment ?? null,
            'on_hold_until'        => optional($this->on_hold_until)->format('d/m/Y'),
            'cancelled_comment'    => $this->cancelled_comment ?? null,

            // ====== BUKTI PENGIRIMAN (TETAP) ======
            'delivery_images_urls' => $deliveryUrls,
            'delivery_image_url'   => $singleDelivery,

            'file_pdf_url'         => $this->return_file ? Storage::url($this->return_file) : null,

            'created_at'           => optional($this->created_at)->format('d/m/Y'),
            'updated_at'           => optional($this->updated_at)->format('d/m/Y'),
        ];
    }

    // ---------- helpers image ----------

    /** image di DB bisa: string, array, atau string JSON array */
    private function normalizeImagePaths($raw): array
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

    /** delivery_images tetap seperti sebelumnya */
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

    private function addressText(array $items): ?string
    {
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
            return implode(', ', array_filter($parts));
        })->join(' | ');
    }

    private function mapAddressesReadable($address): array
    {
        $items = is_array($address) ? $address : json_decode($address ?? '[]', true);
        if (!is_array($items)) $items = [];

        return array_map(function ($a) {
            $provCode = $a['provinsi']  ?? null;
            $kabCode  = $a['kota_kab']  ?? null;
            $kecCode  = $a['kecamatan'] ?? null;
            $kelCode  = $a['kelurahan'] ?? null;

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
        $items = is_array($products) ? $products : json_decode($products ?? '[]', true);
        if (! is_array($items)) {
            $items = [];
        }

        return array_map(function ($p) {
            $product = isset($p['produk_id'])
                ? Product::with(['brand:id,name', 'category:id,name'])->find($p['produk_id'])
                : null;

            $warnaId   = $p['warna_id'] ?? $p['color'] ?? null;
            $colorName = null;

            if ($product && $warnaId !== null) {
                $colors = is_array($product->colors) ? $product->colors : [];

                foreach ($colors as $key => $c) {
                    // Bentuk: [{"id":2,"name":"Putih",...}, ...]
                    if (is_array($c)) {
                        $candidateIds = [
                            $c['id']    ?? null,
                            $c['value'] ?? null,
                            $c['code']  ?? null,
                        ];
                        $candidateNames = [
                            $c['name'] ?? $c['label'] ?? $c['color'] ?? null,
                        ];

                        // match dengan id / value / key array
                        $matchId = in_array((string) $warnaId, array_map('strval', $candidateIds), true)
                            || (string) $key === (string) $warnaId;

                        // match dengan nama langsung
                        $matchName = in_array((string) $warnaId, array_map('strval', $candidateNames), true);

                        if ($matchId || $matchName) {
                            $colorName = $c['name']
                                ?? $c['label']
                                ?? $c['value']
                                ?? $c['color']
                                ?? (string) $warnaId;
                            break;
                        }
                    } else {
                        // Bentuk: ["Putih","Kuning"] atau {"2":"Putih", "3":"Kuning"}
                        if ((string) $c === (string) $warnaId || (string) $key === (string) $warnaId) {
                            $colorName = (string) $c;
                            break;
                        }
                    }
                }
            }

            // fallback: kalau tetap nggak ketemu, pakai saja isi warna_id
            if (! $colorName && $warnaId !== null) {
                $colorName = (string) $warnaId;
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
