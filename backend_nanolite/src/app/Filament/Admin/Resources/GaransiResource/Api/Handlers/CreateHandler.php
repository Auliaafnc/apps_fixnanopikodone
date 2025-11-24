<?php

namespace App\Filament\Admin\Resources\GaransiResource\Api\Handlers;

use App\Filament\Admin\Resources\GaransiResource;
use App\Filament\Admin\Resources\GaransiResource\Api\Requests\CreateGaransiRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = GaransiResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Garansi
     *
     * @return \Illuminate\Http\JsonResponse
     */
   public function handler(CreateGaransiRequest $request)
    {
        $model = new (static::getModel());

        // pakai data validasi
        $data = $request->validated();

        // 🔁 normalisasi field JSON yang mungkin dikirim sebagai string
        foreach (['address', 'products', 'image', 'delivery_images'] as $k) {
            if (! $request->has($k)) {
                continue;
            }

            $value = $request->input($k);

            // kalau sudah array dari client, langsung pakai
            if (is_array($value)) {
                $data[$k] = $value;
                continue;
            }

            // kalau string → coba decode JSON
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data[$k] = $decoded;
                } else {
                    // fallback: simpan apa adanya (misal address lama berupa string polos)
                    $data[$k] = $value;
                }
            }
        }

        $model->fill($data);
        $model->save(); // di sini akan kepanggil normalizeProductColors() dari model

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}