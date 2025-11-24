<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Handlers;

use App\Filament\Admin\Resources\OrderResource;
use App\Filament\Admin\Resources\OrderResource\Api\Requests\CreateOrderRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = OrderResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Order
     */
    public function handler(CreateOrderRequest $request)
    {
        $model = new (static::getModel());

        // isi semua field (Order tidak ada image)
        $data = $request->all();
        $model->fill($data);

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Order');
    }
}
