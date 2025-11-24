<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers;

use App\Filament\Admin\Resources\ProductReturnResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Transformers\ProductReturnTransformer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = ProductReturnResource::class;

    public function handler()
    {
        switch (request('type')) {
            case 'status':
                return static::getModel()::select('status')
                    ->distinct()
                    ->orderBy('status')
                    ->get();

            case 'customers':
                return \App\Models\Customer::select('id','name')
                    ->orderBy('name')
                    ->get();

            case 'employees':
                return \App\Models\Employee::select('id','name')
                    ->when(request('department_id'), function ($q) {
                        $q->where('department_id', request('department_id'));
                    })
                    ->orderBy('name')
                    ->get();

            case 'departments':
                return \App\Models\Department::select('id','name')
                    ->orderBy('name')
                    ->get();

            case 'categories':
                return \App\Models\CustomerCategory::select('id','name')
                    ->orderBy('name')
                    ->get();
        }

        // default pagination
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters([
                'no_return',
                'status',
                'customer_id',
                'employee_id',
                'department_id',
                'customer_categories_id',
            ])
            ->with([
                'department:id,name',
                'employee:id,name',
                'customer:id,name',
                'category:id,name',
            ])
            ->latest('id')
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($row) => new ProductReturnTransformer($row));

        return static::sendSuccessResponse($paginator, 'Product return list retrieved successfully');
    }
}
