<?php

namespace App\Filament\Admin\Resources\GaransiResource\Api\Handlers;

use App\Filament\Admin\Resources\GaransiResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\GaransiResource\Api\Transformers\GaransiTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = GaransiResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['no_garansi','status','phone','reason','purchase_date','claim_date'])
            ->with(['company','customerCategory','employee','customer','department'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(function ($g) {
                // penting: kirim $request ke toArray
                return (new GaransiTransformer($g))->toArray(request());
            }); // <-- SEMICOLON WAJIB

        return static::sendSuccessResponse($paginator, 'Garansi list retrieved successfully');
    }
}