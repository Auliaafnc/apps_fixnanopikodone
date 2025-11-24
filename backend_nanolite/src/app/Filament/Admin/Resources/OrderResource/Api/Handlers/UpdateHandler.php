<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Handlers;

use App\Filament\Admin\Resources\OrderResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';
    public static ?string $resource = OrderResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Update Order + upload bukti pengiriman (opsional).
     *
     * Body (opsional):
     * - delivery_image (file tunggal)
     * - delivery_images[] (multi file)
     * - field lain sesuai fillable model.
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        $order = static::getModel()::find($id);
        if (! $order) {
            return static::sendNotFoundResponse();
        }

        // validasi gambar
        $request->validate([
            'delivery_image'    => 'sometimes|image|max:4096',
            'delivery_images.*' => 'sometimes|image|max:4096',
            // kalau mau izinkan kirim delivered_by eksplisit dari client:
            // 'delivered_by' => 'sometimes|nullable|exists:employees,id',
        ]);

        // upload file bila ada
        $newPaths = [];

        if ($request->hasFile('delivery_images')) {
            foreach ($request->file('delivery_images') as $file) {
                $newPaths[] = $file->store('order-delivery-photos', 'public');
            }
        }

        if ($request->hasFile('delivery_image')) {
            $newPaths[] = $request->file('delivery_image')->store('order-delivery-photos', 'public');
        }

        if (!empty($newPaths)) {
            $existing = (array) ($order->delivery_images ?? []);
            $order->delivery_images = array_values(array_unique(array_merge($existing, $newPaths)));

            // status & meta delivered
            if (empty($order->status_order) || in_array($order->status_order, ['pending', 'confirmed', 'processing'])) {
                $order->status_order = 'delivered';
            }
            $order->delivered_at = now();

            // 🔑 cari employee id dari user login (berdasarkan email)
            $deliveredById = null;
            $user = $request->user();
            if ($user && !empty($user->email)) {
                $deliveredById = Employee::where('email', $user->email)->value('id');
            }

            // fallback: jika client kirim delivered_by eksplisit dan valid
            if (!$deliveredById && $request->filled('delivered_by')) {
                $maybe = (int) $request->input('delivered_by');
                if (Employee::whereKey($maybe)->exists()) {
                    $deliveredById = $maybe;
                }
            }

            // set hanya jika ada employee yang valid (hindari error FK)
            if ($deliveredById) {
                $order->delivered_by = $deliveredById;
            }
        }

        // update field lain (kecuali file)
        $input = $request->except(['delivery_image', 'delivery_images']);

        // normalisasi JSON string -> array
        foreach (['address', 'products', 'image', 'delivery_images'] as $k) {
            if ($request->has($k) && is_string($request->input($k))) {
                $decoded = json_decode($request->input($k), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $input[$k] = $decoded;
                }
            }
        }

        if (!empty($input)) {
            $order->fill($input);
        }

        $order->save();

        // response dengan URL absolut
        $data = $order->toArray();
        $imgs = (array) ($order->delivery_images ?? []);

        $data['delivery_images'] = array_map(
            fn ($p) => Storage::disk('public')->url($p),
            $imgs
        );

        // konsistensi dengan transformer style di Garansi
        $data['delivery_images_urls'] = $data['delivery_images'];

        $data['delivery_image_url'] = !empty($imgs)
            ? Storage::disk('public')->url($imgs[0])
            : null;

        return response()->json([
            'message' => 'Successfully Update Resource',
            'data'    => $data,
        ]);
    }
}
