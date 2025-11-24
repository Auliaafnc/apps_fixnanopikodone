<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Handlers;

use App\Filament\Admin\Resources\OrderResource;
use App\Support\ApiPaging;
use Rupadana\ApiService\Http\Handlers;
use Spatie\QueryBuilder\QueryBuilder;
use App\Filament\Admin\Resources\OrderResource\Api\Transformers\OrderTransformer;
use App\Models\CustomerProgram;
use App\Models\Customer;

class PaginationHandler extends Handlers
{
    use ApiPaging;

    public static ?string $uri = '/';
    public static ?string $resource = OrderResource::class;

    public function handler()
    {
        switch (request('type')) {
            case 'departments':
                return \App\Models\Department::select('id','name')
                    ->orderBy('name')
                    ->get();

            case 'employees':
                return \App\Models\Employee::select('id','name')
                    ->when(request('department_id'), function ($q) {
                        $q->where('department_id', request('department_id'));
                    })
                    ->orderBy('name')
                    ->get();

            case 'customer-categories':
                $employeeId = request('employee_id');
                return \App\Models\CustomerCategory::query()
                    ->when($employeeId, function ($q) use ($employeeId) {
                        $q->whereHas('customers', fn($sub) => $sub->where('employee_id', $employeeId));
                    })
                    ->select('id','name')
                    ->orderBy('name')
                    ->get();

            case 'customers':
                $employeeId   = request('employee_id');
                $categoryId   = request('customer_categories_id');
                $departmentId = request('department_id');

                return \App\Models\Customer::query()
                    ->where('status', 'active')
                    ->when($employeeId, fn($q) => $q->where('employee_id', $employeeId))
                    ->when($categoryId, fn($q) => $q->where('customer_categories_id', $categoryId))
                    ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
                    ->select(
                            'id',
                            'name',
                            'phone',
                            'address',
                            'customer_program_id',
                            'employee_id',
                            'department_id',
                            'customer_categories_id',
                            'reward_point',   
                            'jumlah_program'
                    )
                    ->orderBy('name')
                    ->distinct()
                    ->get();


            case 'customer-programs':
                $customerId = request('customer_id');
                if ($customerId) {
                    $customer = \App\Models\Customer::with('customerProgram')->find($customerId);
                    return $customer && $customer->customerProgram
                        ? collect([$customer->customerProgram->only('id','name')])
                        : collect([]);
                }
                return \App\Models\CustomerProgram::select('id','name')
                    ->orderBy('name')
                    ->get();



            case 'categories-by-brand':
                $brandId = request('brand_id');
                return \App\Models\Category::query()
                    ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
                    ->select('id','name')
                    ->orderBy('name')
                    ->get();

            case 'products-by-brand-category':
                $brandId    = request('brand_id');
                $categoryId = request('category_id');
                return \App\Models\Product::query()
                    ->when($brandId, fn($q) => $q->where('brand_id', $brandId))
                    ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                    ->select('id','name','price')
                    ->orderBy('name')
                    ->get();

            case 'colors-by-product':
                $productId = request('product_id');
                $product   = \App\Models\Product::find($productId);

                if (!$product) {
                    return collect([]);
                }

                // Kolom colors sudah otomatis dicast ke array (lihat $casts di model)
                $colors = $product->colors ?? [];

                if (!is_array($colors)) {
                    $colors = [$colors];
                }

                // Normalisasi jadi format id-name supaya Flutter dropdown bisa baca
                return collect($colors)->map(function ($c, $i) {
                    if (is_array($c)) {
                        // kalau misalnya JSON berisi [{"id":1,"name":"Putih"}]
                        return [
                            'id'   => $c['id'] ?? ($i + 1),
                            'name' => $c['name'] ?? (string) ($c['value'] ?? 'Warna ' . ($i + 1)),
                        ];
                    }
                    // kalau cuma string ["Putih","Hitam"]
                    return [
                        'id'   => $i + 1,
                        'name' => (string) $c,
                    ];
                });



        }

        // default pagination
        $paginator = QueryBuilder::for(static::getModel())
            ->allowedFilters([
                'status',
                'status_pembayaran',
                'payment_method',
                'customer_id',
                'employee_id',
                'department_id',
                'customer_categories_id',
            ])
            ->with([
                'department:id,name',
                'employee:id,name',
                'customer:id,name',
                'customerCategory:id,name',
                'customerProgram:id,name',
            ])
            ->latest('id')
            ->paginate($this->perPage(request()))
            ->appends(request()->query())
            ->through(fn ($row) => new OrderTransformer($row));

        return static::sendSuccessResponse($paginator, 'Order list retrieved successfully');
    }
}