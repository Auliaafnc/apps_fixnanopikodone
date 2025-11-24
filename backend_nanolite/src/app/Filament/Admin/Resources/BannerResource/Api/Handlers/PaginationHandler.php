<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Handlers;

use App\Filament\Admin\Resources\BannerResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\BannerResource\Api\Transformers\BannerTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = BannerResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($banner) => new BannerTransformer($banner));

        return static::sendSuccessResponse($paginator, 'Banner list retrieved successfully');
    }
}
