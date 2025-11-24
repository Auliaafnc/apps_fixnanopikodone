<?php
namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Admin\Resources\PerbaikandataResource;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Requests\CreatePerbaikandataRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = PerbaikandataResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Perbaikandata
     *
     * @param CreatePerbaikandataRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreatePerbaikandataRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->only([
            'company_id','department_id','employee_id','customer_categories_id',
            'customer_id','pilihan_data','data_baru','address','status_pengajuan',
        ]));

        if (empty($model->status_pengajuan)) {
            $model->status_pengajuan = 'pending';
        }
        if (empty($model->company_id) && auth()->check()) {
            $model->company_id = auth()->user()->company_id ?? null;
        }
    
        // proses images[]
        $paths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file && $file->isValid()) {
                    $paths[] = $file->store('perbaikandatas', 'public');
                }
            }
        }
        if (!empty($paths)) {
            $model->image = $paths; // model cast ke array → ok
        }

        $model->save();

        $model->load(['department:id,name','employee:id,name','customer:id,name','customerCategory:id,name']);

        

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}