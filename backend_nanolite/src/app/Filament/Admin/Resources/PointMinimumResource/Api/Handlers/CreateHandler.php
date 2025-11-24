<?php
namespace App\Filament\Admin\Resources\PointMinimumResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Admin\Resources\PointMinimumResource;
use App\Filament\Admin\Resources\PointMinimumResource\Api\Requests\CreatePointMinimumRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = PointMinimumResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create PointMinimum
     *
     * @param CreatePointMinimumRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreatePointMinimumRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}