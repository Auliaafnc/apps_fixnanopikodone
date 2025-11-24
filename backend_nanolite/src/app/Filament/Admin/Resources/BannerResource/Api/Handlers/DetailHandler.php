<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Handlers;

use App\Filament\Admin\Resources\BannerResource;
use App\Filament\Admin\Resources\BannerResource\Api\Transformers\BannerTransformer;
use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;

class DetailHandler extends Handlers
{
    public static ?string $uri = '/{id}';

    public static ?string $resource = BannerResource::class;

    /**
     * Show Banner
     *
     * @return BannerTransformer
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

        return new BannerTransformer($query);
    }
}