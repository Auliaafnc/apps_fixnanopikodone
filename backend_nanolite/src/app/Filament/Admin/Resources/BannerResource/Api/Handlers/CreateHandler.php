<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Handlers;

use App\Filament\Admin\Resources\BannerResource;
use App\Filament\Admin\Resources\BannerResource\Api\Requests\CreateCompanyRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = BannerResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Branch Company
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(BannerRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}