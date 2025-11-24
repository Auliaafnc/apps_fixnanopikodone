<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers;

use App\Filament\Admin\Resources\ProductReturnResource;
use App\Models\Employee;
use App\Models\ProductReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    // pakai {id} biar konsisten dengan Garansi
    public static ?string $uri = '/{id}';
    public static ?string $resource = ProductReturnResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Update ProductReturn + upload bukti pengiriman (opsional).
     *
     * Body (opsional):
     * - image                : foto barang utama
     * - delivery_image       : 1 file bukti kirim
     * - delivery_images[]    : banyak file bukti kirim
     * - field lain sesuai fillable model
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        /** @var ProductReturn|null $retur */
        $retur = static::getModel()::find($id);
        if (! $retur) {
            return static::sendNotFoundResponse();
        }

        // validasi file (jangan paksa field lain required)
        $request->validate([
            'image'              => 'sometimes|image|max:4096',
            'delivery_image'     => 'sometimes|image|max:4096',
            'delivery_images.*'  => 'sometimes|image|max:4096',
        ]);

        /* ================== FOTO BARANG (image) ================== */

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // kalau multi (image[]) ambil pertama
            if (is_array($file)) {
                $file = $file[0] ?? null;
            }

            if ($file) {
                // hapus lama
                if ($retur->image && Storage::disk('public')->exists($retur->image)) {
                    Storage::disk('public')->delete($retur->image);
                }

                $path = $file->store('product-returns', 'public');
                $retur->image = $path;
            }
        }

        /* ================== BUKTI PENGIRIMAN ================== */

        $newPaths = [];

        // banyak file: delivery_images[]
        if ($request->hasFile('delivery_images')) {
            foreach ($request->file('delivery_images') as $file) {
                $newPaths[] = $file->store('return-delivery-photos', 'public');
            }
        }

        // single: delivery_image
        if ($request->hasFile('delivery_image')) {
            $newPaths[] = $request->file('delivery_image')
                                  ->store('return-delivery-photos', 'public');
        }

        if (!empty($newPaths)) {
            $existing = (array) ($retur->delivery_images ?? []);
            $retur->delivery_images = array_values(array_unique(array_merge($existing, $newPaths)));

            // kalau status_return masih kosong / pending, ubah ke delivered
            if (empty($retur->status_return) || $retur->status_return === 'pending') {
                $retur->status_return = 'delivered';
            }

            $retur->delivered_at = now();

            // cari delivered_by dari user login (by email) atau dari request
            $deliveredById = null;
            $user = $request->user();
            if ($user && !empty($user->email)) {
                $deliveredById = Employee::where('email', $user->email)->value('id');
            }
            if (!$deliveredById && $request->filled('delivered_by')) {
                $maybe = (int) $request->input('delivered_by');
                if (Employee::whereKey($maybe)->exists()) {
                    $deliveredById = $maybe;
                }
            }
            if ($deliveredById) {
                $retur->delivered_by = $deliveredById;
            }
        }

        /* ================== FIELD LAIN (optional) ================== */

        $input = $request->except(['image', 'delivery_image', 'delivery_images']);

        // kalau ada field JSON dikirim sebagai string, decode dulu
        foreach (['address', 'products', 'image', 'delivery_images'] as $k) {
            if ($request->has($k) && is_string($request->input($k))) {
                $decoded = json_decode($request->input($k), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $input[$k] = $decoded;
                }
            }
        }

        if (!empty($input)) {
            $retur->fill($input);
        }

        $retur->save();

        // response sederhana, FE cuma butuh status 2xx
        return response()->json([
            'message' => 'Successfully Update ProductReturn',
            'data'    => $retur->fresh(),
        ]);
    }
}
