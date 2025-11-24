<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerCategoriesResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Transformers\CustomerCategoriesTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = CustomerCategoriesResource::class;

    public function handler()
    {
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name', 'deskripsi'])
            ->when(request('employee_id'), function ($q, $employeeId) {
                $q->whereHas('customers', function ($sub) use ($employeeId) {
                    $sub->where('employee_id', $employeeId);
                });
            })

            ->select('customer_categories.*')
            ->paginate($this->perPage(request()))
            ->appends(request()->query());

        return static::sendSuccessResponse($paginator, 'Customer categories list retrieved successfully');
    }

}
