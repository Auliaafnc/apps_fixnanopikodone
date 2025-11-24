<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerProgramResource;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Requests\CreateCustomerProgramRequest;
use Rupadana\ApiService\Http\Handlers;

class CreateHandler extends Handlers
{
    public static ?string $uri = '/';

    public static ?string $resource = CustomerProgramResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel()
    {
        return static::$resource::getModel();
    }

    /**
     * Create CustomerProgram
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateCustomerProgramRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, 'Successfully Create Resource');
    }
}