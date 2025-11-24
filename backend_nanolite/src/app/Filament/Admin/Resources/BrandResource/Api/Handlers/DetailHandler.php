<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Handlers;

use App\Filament\Admin\Resources\BrandResource;
use App\Filament\Admin\Resources\BrandResource\Api\Transformers\BrandTransformer;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = BrandResource::class;

    /**
     * Show Brand
     *
     * @return BrandTransformer
     */
    public function handler(Request $request)
    {
        $id = $request->route('id');

        $query = static::getEloquentQuery();

        $query = QueryBuilder::for(
            $query->where(static::getKeyName(), $id)
        )
            ->first();

        if (! $query) {
            return static::sendNotFoundResponse();
        }

        return new BrandTransformer($query);
    }
}