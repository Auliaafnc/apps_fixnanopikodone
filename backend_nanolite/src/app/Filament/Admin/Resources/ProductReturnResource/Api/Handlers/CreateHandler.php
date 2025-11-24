<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers;

use App\Filament\Admin\Resources\ProductReturnResource;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Requests\CreateProductReturnRequest;
use Rupadana\ApiService\Http\Handlers;
use Illuminate\Http\UploadedFile;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = ProductReturnResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create ProductReturn
     */
    public function handler(CreateProductReturnRequest $request)
    {
        $model = new (static::getModel());

        // isi semua field kecuali image
        $data = $request->except('image');
        $model->fill($data);

        /**
         * HANDLE UPLOAD BANYAK GAMBAR
         *
         * FE: kirim image[] (multipart)
         * PHP: $request->file('image') => UploadedFile|UploadedFile[]
         */
        $files = $request->file('image'); // nama field = "image[]"

        $paths = [];

        if ($files instanceof UploadedFile) {
            // kasus 1 file saja
            $paths[] = $files->store('product-returns', 'public');
        } elseif (is_array($files)) {
            // kasus multiple file
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $paths[] = $file->store('product-returns', 'public');
                }
            }
        }

        // kalau ada file yang berhasil diupload, simpan sebagai array path
        if (! empty($paths)) {
            $model->image = $paths; // dikast ke JSON array oleh model
        }

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create ProductReturn');
    }
}
