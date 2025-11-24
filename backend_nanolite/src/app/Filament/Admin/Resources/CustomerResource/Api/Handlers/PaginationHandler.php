<?php

namespace App\Filament\Admin\Resources\CustomerResource\Api\Handlers;

use App\Filament\Admin\Resources\CustomerResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\CustomerResource\Api\Transformers\CustomerTransformer;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;
use App\Models\Employee; // 🔥 tambahin ini biar bisa query employees

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = CustomerResource::class;

    public function handler()
    {
        switch (request('type')) {
            case 'provinces':
                return Province::select('code as id', 'name')
                    ->orderBy('name')->get();

            case 'cities':
                return City::select('code as id', 'name')
                    ->when(request('province_code'), fn($q) => $q->where('province_code', request('province_code')))
                    ->orderBy('name')->get();

            case 'districts':
                return District::select('code as id', 'name')
                    ->when(request('city_code'), fn($q) => $q->where('city_code', request('city_code')))
                    ->orderBy('name')->get();

            case 'villages':
                return Village::select('code as id', 'name')
                    ->when(request('district_code'), fn($q) => $q->where('district_code', request('district_code')))
                    ->orderBy('name')->get();

            case 'postal_code':
                $villageCode = request('village_code');
                $postal = \DB::table('postal_codes')
                    ->where('village_code', $villageCode)
                    ->value('postal_code');

                return response()->json([
                    'postal_code' => $postal
                ]);

            case 'employees':
                return \App\Models\Employee::select('id','name')
                    ->when(request('department_id'), function ($q) {
                        $q->where('department_id', request('department_id'));
                    })
                    ->orderBy('name')
                    ->get();

        }

        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters(['name','email','phone','status', 'department'])
            ->with([
                'company',
                'customerCategory',
                'customerProgram',
                'employee',
                'department',
                'orders',
                'garansis',
                'productReturns'
            ])
            ->withCount(['orders','garansis','productReturns'])
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($cust) => new CustomerTransformer($cust));

        return static::sendSuccessResponse($paginator, 'Customer list retrieved successfully');
    }
}
