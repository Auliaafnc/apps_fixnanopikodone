<?php

namespace App\Filament\Admin\Resources\CustomerResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerResource;
use App\Filament\Admin\Resources\CustomerResource\Api\Requests\CreateCustomerRequest;
use Rupadana\ApiService\Http\Handlers;
use Illuminate\Http\UploadedFile;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CustomerResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Customer
     */
    public function handler(CreateCustomerRequest $request)
    {
        $model = new (static::getModel());

        // isi semua field kecuali image
        $data = $request->except('image');
        $model->fill($data);

        // handle upload satu gambar
        $file = $request->file('image') 
            ?? $request->file('image.0') 
            ?? $request->file('image[]');

        if ($file instanceof UploadedFile) {
            $path = $file->store('customers', 'public');
            $model->image = $path; // ✅ simpan string path
        }

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Customer');
    }
}
