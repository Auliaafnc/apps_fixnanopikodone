<?php

namespace App\Filament\Admin\Resources\CategoryResource\Api\Handlers;

use App\Filament\Admin\Resources\CategoryResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\CategoryResource\Api\Transformers\CategoryTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = CategoryResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','deskripsi'])
            ->with(['brand','brand.company','products'])
            ->withCount(['products'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($category) => new CategoryTransformer($category));

        return static::sendSuccessResponse($paginator, 'Category list retrieved successfully');
    }
}
