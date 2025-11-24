<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Handlers;

use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Api\Requests\CreateBrandRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = BrandResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create Brand
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateBrandRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}