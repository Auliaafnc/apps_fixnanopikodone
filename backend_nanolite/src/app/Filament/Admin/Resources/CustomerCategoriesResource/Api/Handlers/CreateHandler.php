<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerCategoriesResource;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Requests\CreateCustomerCategoriesRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CustomerCategoriesResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create CustomerCategories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateCustomerCategoriesRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}