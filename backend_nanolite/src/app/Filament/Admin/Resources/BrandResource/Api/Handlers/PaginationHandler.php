<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Handlers;

use App\Filament\Admin\Resources\BrandResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\BrandResource\Api\Transformers\BrandTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = BrandResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name', 'deskripsi'])
            ->with(['company','categories','products'])
            ->withCount(['categories','products'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($brand) => new BrandTransformer($brand));

        return static::sendSuccessResponse($paginator, 'Brand list retrieved successfully');
    }
}
