<?php

namespace App\Filament\Admin\Resources\GaransiResource\Api\Handlers;

use App\Filament\Admin\Resources\GaransiResource;
use App\Models\Employee;
use App\Models\Garansi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';
    public static ?string $resource = GaransiResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Update Garansi + upload bukti pengiriman (opsional).
     *
     * Body (opsional):
     * - delivery_image (file tunggal)
     * - delivery_images[] (multi file)
     * - field lain sesuai fillable model.
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        /** @var Garansi|null $garansi */
        $garansi = static::getModel()::find($id);
        if (! $garansi) {
            return static::sendNotFoundResponse();
        }

        // validasi gambar
        $request->validate([
            'delivery_image'    => 'sometimes|image|max:4096',
            'delivery_images.*' => 'sometimes|image|max:4096',
            // 'delivered_by' => 'sometimes|nullable|exists:employees,id',
        ]);

        // =====================================
        // 1) Update field biasa (kecuali file)
        // =====================================

        // jangan ikutkan field file
        $input = $request->except(['delivery_image', 'delivery_images']);

        // normalisasi JSON untuk beberapa field
        foreach (['address', 'products', 'image', 'delivery_images'] as $k) {
            if (! $request->has($k)) {
                continue;
            }

            $value = $request->input($k);

            // kalau frontend kirim array → langsung pakai
            if (is_array($value)) {
                // khusus delivery_images: kalau user upload file juga, jangan timpa hasil upload
                if ($k === 'delivery_images') {
                    continue;
                }

                $input[$k] = $value;
                continue;
            }

            // kalau string → coba decode JSON
            if (is_string($value)) {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    // lagi-lagi, jangan override delivery_images jika akan diisi dari upload file
                    if ($k === 'delivery_images') {
                        continue;
                    }

                    $input[$k] = $decoded;
                } else {
                    // fallback: simpan apa adanya (misal address lama berupa string)
                    if ($k !== 'delivery_images') {
                        $input[$k] = $value;
                    }
                }
            }
        }

        if (! empty($input)) {
            $garansi->fill($input);
        }

        // =====================================
        // 2) Upload file bukti pengiriman
        // =====================================

        $newPaths = [];

        if ($request->hasFile('delivery_images')) {
            foreach ($request->file('delivery_images') as $file) {
                $newPaths[] = $file->store('garansi-delivery-photos', 'public');
            }
        }

        if ($request->hasFile('delivery_image')) {
            $newPaths[] = $request->file('delivery_image')->store('garansi-delivery-photos', 'public');
        }

        if (! empty($newPaths)) {
            $existing = (array) ($garansi->delivery_images ?? []);
            $garansi->delivery_images = array_values(array_unique(array_merge($existing, $newPaths)));

            // status & meta delivered
            if (empty($garansi->status_garansi) || $garansi->status_garansi === 'pending') {
                $garansi->status_garansi = 'delivered';
            }
            $garansi->delivered_at = now();

            // 🔑 cari employee id dari user login (berdasarkan email)
            $deliveredById = null;
            $user = $request->user();
            if ($user && ! empty($user->email)) {
                $deliveredById = Employee::where('email', $user->email)->value('id');
            }

            // fallback: jika client kirim delivered_by eksplisit dan valid
            if (! $deliveredById && $request->filled('delivered_by')) {
                $maybe = (int) $request->input('delivered_by');
                if (Employee::whereKey($maybe)->exists()) {
                    $deliveredById = $maybe;
                }
            }

            // set hanya jika ada employee yang valid (hindari error FK)
            if ($deliveredById) {
                $garansi->delivered_by = $deliveredById;
            }
        }

        // =====================================
        // 3) Simpan model
        // =====================================
        $garansi->save();

        // =====================================
        // 4) Response dengan URL absolut
        // =====================================
        $data = $garansi->toArray();

        $imgs = (array) ($garansi->delivery_images ?? []);
        $data['delivery_images']      = array_map(fn ($p) => Storage::disk('public')->url($p), $imgs);
        $data['delivery_images_urls'] = $data['delivery_images']; // konsisten dengan transformer
        $data['delivery_image_url']   = ! empty($imgs)
            ? Storage::disk('public')->url($imgs[0])
            : null;

        return response()->json([
            'message' => 'Successfully Update Resource',
            'data'    => $data,
        ]);
    }
}