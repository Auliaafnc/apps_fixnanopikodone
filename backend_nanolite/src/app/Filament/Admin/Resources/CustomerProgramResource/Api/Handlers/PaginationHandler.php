<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerProgramResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Transformers\CustomerProgramTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = CustomerProgramResource::class;

    public function handler()
{
    $paginator = QueryBuilder::for(static::getModel())
        ->allowedFilters(['name','deskripsi'])
        ->select(['id','name','status']) // minimal kolom penting
        ->paginate($this->perPage(request()))
        ->appends(request()->query());

    return static::sendSuccessResponse($paginator, 'Customer programs list retrieved successfully');
}

}
