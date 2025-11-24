<?php
namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Admin\Resources\PerbaikandataResource;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Requests\UpdatePerbaikandataRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = PerbaikandataResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Perbaikandata
     *
     * @param UpdatePerbaikandataRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdatePerbaikandataRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}