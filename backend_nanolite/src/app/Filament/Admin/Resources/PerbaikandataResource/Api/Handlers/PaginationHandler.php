<?php
namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\PerbaikandataResource;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Transformers\PerbaikandataTransformer;

class PaginationHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = PerbaikandataResource::class;


    /**
     * List of Perbaikandata
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function handler()
    {
        $query = static::getEloquentQuery()
            ->with(['department:id,name','employee:id,name',
                    'customer:id,name','customerCategory:id,name']);

        $query = \Spatie\QueryBuilder\QueryBuilder::for($query)
            ->allowedFields($this->getAllowedFields() ?? [])
            ->allowedSorts($this->getAllowedSorts() ?? [])
            ->allowedFilters($this->getAllowedFilters() ?? [])
            ->allowedIncludes($this->getAllowedIncludes() ?? [])
            ->paginate(request()->query('per_page'))
            ->appends(request()->query());

        return PerbaikandataTransformer::collection($query);
    }
}