<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Handlers;

use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Api\Requests\UpdateBrandRequest;
use Rupadana\ApiService\Http\Handlers;

class UpdateHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = BrandResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Update Brand
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateBrandRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (! $model) {
            return static::sendNotFoundResponse();
        }

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Update Resource');
    }
}