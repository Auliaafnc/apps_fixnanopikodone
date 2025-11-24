<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use Rupadana\ApiService\Facades\ApiService;
use App\Filament\Admin\Resources\CustomerResource;
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\DeliveryHandler as GaransiDeliveryHandler;


use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Village;

// Handlers Category
use App\Filament\Admin\Resources\CategoryResource\Api\Handlers\CreateHandler      as CategoryCreateHandler;
use App\Filament\Admin\Resources\CategoryResource\Api\Handlers\PaginationHandler as CategoryPaginationHandler;
use App\Filament\Admin\Resources\CategoryResource\Api\Handlers\DetailHandler     as CategoryDetailHandler;
use App\Filament\Admin\Resources\CategoryResource\Api\Handlers\UpdateHandler     as CategoryUpdateHandler;
use App\Filament\Admin\Resources\CategoryResource\Api\Handlers\DeleteHandler     as CategoryDeleteHandler;

// Handlers Company
use App\Filament\Admin\Resources\CompanyResource\Api\Handlers\CreateHandler      as CompanyCreateHandler;
use App\Filament\Admin\Resources\CompanyResource\Api\Handlers\PaginationHandler as CompanyPaginationHandler;
use App\Filament\Admin\Resources\CompanyResource\Api\Handlers\DetailHandler     as CompanyDetailHandler;
use App\Filament\Admin\Resources\CompanyResource\Api\Handlers\UpdateHandler     as CompanyUpdateHandler;
use App\Filament\Admin\Resources\CompanyResource\Api\Handlers\DeleteHandler     as CompanyDeleteHandler;

// Handlers Department
use App\Filament\Admin\Resources\DepartmentResource\Api\Handlers\CreateHandler      as DepartmentCreateHandler;
use App\Filament\Admin\Resources\DepartmentResource\Api\Handlers\PaginationHandler as DepartmentPaginationHandler;
use App\Filament\Admin\Resources\DepartmentResource\Api\Handlers\DetailHandler     as DepartmentDetailHandler;
use App\Filament\Admin\Resources\DepartmentResource\Api\Handlers\UpdateHandler     as DepartmentUpdateHandler;
use App\Filament\Admin\Resources\DepartmentResource\Api\Handlers\DeleteHandler     as DepartmentDeleteHandler;

// Handlers Product
use App\Filament\Admin\Resources\ProductResource\Api\Handlers\CreateHandler      as ProductCreateHandler;
use App\Filament\Admin\Resources\ProductResource\Api\Handlers\PaginationHandler as ProductPaginationHandler;
use App\Filament\Admin\Resources\ProductResource\Api\Handlers\DetailHandler     as ProductDetailHandler;
use App\Filament\Admin\Resources\ProductResource\Api\Handlers\UpdateHandler     as ProductUpdateHandler;
use App\Filament\Admin\Resources\ProductResource\Api\Handlers\DeleteHandler     as ProductDeleteHandler;

// Handlers Customer
use App\Filament\Admin\Resources\CustomerResource\Api\Handlers\CreateHandler as CustomerCreateHandler;
use App\Filament\Admin\Resources\CustomerResource\Api\Handlers\PaginationHandler as CustomerPaginationHandler;
use App\Filament\Admin\Resources\CustomerResource\Api\Handlers\DetailHandler     as CustomerDetailHandler;
use App\Filament\Admin\Resources\CustomerResource\Api\Handlers\UpdateHandler     as CustomerUpdateHandler;
use App\Filament\Admin\Resources\CustomerResource\Api\Handlers\DeleteHandler     as CustomerDeleteHandler;

// Handlers Order
use App\Filament\Admin\Resources\OrderResource\Api\Handlers\CreateHandler      as OrderCreateHandler;
use App\Filament\Admin\Resources\OrderResource\Api\Handlers\PaginationHandler as OrderPaginationHandler;
use App\Filament\Admin\Resources\OrderResource\Api\Handlers\DetailHandler     as OrderDetailHandler;
use App\Filament\Admin\Resources\OrderResource\Api\Handlers\UpdateHandler     as OrderUpdateHandler;
use App\Filament\Admin\Resources\OrderResource\Api\Handlers\DeleteHandler     as OrderDeleteHandler;

// Handlers ProductReturn
use App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers\CreateHandler      as ProductReturnCreateHandler;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers\PaginationHandler as ProductReturnPaginationHandler;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers\DetailHandler     as ProductReturnDetailHandler;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers\UpdateHandler     as ProductReturnUpdateHandler;
use App\Filament\Admin\Resources\ProductReturnResource\Api\Handlers\DeleteHandler     as ProductReturnDeleteHandler;

// Handlers Brand
use App\Filament\Admin\Resources\BrandResource\Api\Handlers\CreateHandler      as BrandCreateHandler;
use App\Filament\Admin\Resources\BrandResource\Api\Handlers\PaginationHandler as BrandPaginationHandler;
use App\Filament\Admin\Resources\BrandResource\Api\Handlers\DetailHandler     as BrandDetailHandler;
use App\Filament\Admin\Resources\BrandResource\Api\Handlers\UpdateHandler     as BrandUpdateHandler;
use App\Filament\Admin\Resources\BrandResource\Api\Handlers\DeleteHandler     as BrandDeleteHandler;

// Handlers Garansi
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\CreateHandler      as GaransiCreateHandler;
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\PaginationHandler as GaransiPaginationHandler;
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\DetailHandler     as GaransiDetailHandler;
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\UpdateHandler     as GaransiUpdateHandler;
use App\Filament\Admin\Resources\GaransiResource\Api\Handlers\DeleteHandler     as GaransiDeleteHandler;

// Handlers Banner
use App\Filament\Admin\Resources\BannerResource\Api\Handlers\CreateHandler      as BannerCreateHandler;
use App\Filament\Admin\Resources\BannerResource\Api\Handlers\PaginationHandler as BannerPaginationHandler;
use App\Filament\Admin\Resources\BannerResource\Api\Handlers\DetailHandler     as BannerDetailHandler;
use App\Filament\Admin\Resources\BannerResource\Api\Handlers\UpdateHandler     as BannerUpdateHandler;
use App\Filament\Admin\Resources\BannerResource\Api\Handlers\DeleteHandler     as BannerDeleteHandler;


// Handlers CustomerCategories
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers\CreateHandler      as CustomerCategoriesCreateHandler;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers\PaginationHandler as CustomerCategoriesPaginationHandler;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers\DetailHandler     as CustomerCategoriesDetailHandler;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers\UpdateHandler     as CustomerCategoriesUpdateHandler;
use App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Handlers\DeleteHandler     as CustomerCategoriesDeleteHandler;

// Handlers CustomerProgram
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers\CreateHandler      as CustomerProgramCreateHandler;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers\PaginationHandler as CustomerProgramPaginationHandler;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers\DetailHandler     as CustomerProgramDetailHandler;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers\UpdateHandler     as CustomerProgramUpdateHandler;
use App\Filament\Admin\Resources\CustomerProgramResource\Api\Handlers\DeleteHandler     as CustomerProgramDeleteHandler;

// Handlers Perbaikandata
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers\CreateHandler      as PerbaikandataCreateHandler;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers\PaginationHandler as PerbaikandataPaginationHandler;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers\DetailHandler     as PerbaikandataDetailHandler;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers\UpdateHandler     as PerbaikandataUpdateHandler;
use App\Filament\Admin\Resources\PerbaikandataResource\Api\Handlers\DeleteHandler     as PerbaikandataDeleteHandler;

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();

    });

    Route::prefix('categories')->group(function () {
        Route::post('/',      [CategoryCreateHandler::class,      'handler'])->name('api.categories.create');
        Route::get('/',       [CategoryPaginationHandler::class, 'handler'])->name('api.categories.pagination');
        Route::get('/{id}',   [CategoryDetailHandler::class,     'handler'])->name('api.categories.detail');
        Route::put('/{id}',   [CategoryUpdateHandler::class,     'handler'])->name('api.categories.update');
        Route::delete('/{id}',[CategoryDeleteHandler::class,     'handler'])->name('api.categories.delete');
    });

    Route::prefix('companies')->group(function () {
        Route::post('/',      [CompanyCreateHandler::class,      'handler'])->name('api.companies.create');
        Route::get('/',       [CompanyPaginationHandler::class, 'handler'])->name('api.companies.pagination');
        Route::get('/{id}',   [CompanyDetailHandler::class,     'handler'])->name('api.companies.detail');
        Route::put('/{id}',   [CompanyUpdateHandler::class,     'handler'])->name('api.companies.update');
        Route::delete('/{id}',[CompanyDeleteHandler::class,     'handler'])->name('api.companies.delete');
    });


    Route::prefix('departments')->group(function () {
        Route::post('/',       [DepartmentCreateHandler::class,      'handler'])->name('api.departments.create');
        Route::get('/',        [DepartmentPaginationHandler::class, 'handler'])->name('api.departments.pagination');
        Route::get('/{id}',    [DepartmentDetailHandler::class,     'handler'])->name('api.departments.detail');
        Route::put('/{id}',    [DepartmentUpdateHandler::class,     'handler'])->name('api.departments.update');
        Route::delete('/{id}', [DepartmentDeleteHandler::class,     'handler'])->name('api.departments.delete');
    });

    Route::prefix('products')->group(function () {
        Route::post('/',       [ProductCreateHandler::class,      'handler'])->name('api.Products.create');
        Route::get('/',        [ProductPaginationHandler::class, 'handler'])->name('api.products.pagination');
        Route::get('/{id}',    [ProductDetailHandler::class,     'handler'])->name('api.products.detail');
        Route::put('/{id}',    [ProductUpdateHandler::class,     'handler'])->name('api.products.update');
        Route::delete('/{id}', [ProductDeleteHandler::class,     'handler'])->name('api.products.delete');
    });

    Route::prefix('customers')->group(function () {
        Route::post('/create', [CustomerCreateHandler::class,      'handler'])->name('api.customers.create');
        Route::get('/',        [CustomerPaginationHandler::class, 'handler'])->name('api.customers.pagination');
        Route::get('/{id}',    [CustomerDetailHandler::class,     'handler'])->name('api.customers.detail');
        Route::put('/{id}',    [CustomerUpdateHandler::class,     'handler'])->name('api.customers.update');
        Route::delete('/{id}', [CustomerDeleteHandler::class,     'handler'])->name('api.customers.delete');
    });

    Route::prefix('orders')->group(function () {
        Route::post('/',       [OrderCreateHandler::class,      'handler'])->name('api.orders.create');
        Route::get('/',        [OrderPaginationHandler::class, 'handler'])->name('api.orders.pagination');
        Route::get('/{id}',    [OrderDetailHandler::class,     'handler'])->name('api.orders.detail');
        Route::put('/{id}',    [OrderUpdateHandler::class,     'handler'])->name('api.orders.update');
        Route::delete('/{id}', [OrderDeleteHandler::class,     'handler'])->name('api.orders.delete');
    });

    Route::prefix('product_returns')->group(function () {
        Route::post('/',       [ProductReturnCreateHandler::class,      'handler'])->name('api.product_returns.create');
        Route::get('/',        [ProductReturnPaginationHandler::class, 'handler'])->name('api.product_returns.pagination');
        Route::get('/{id}',    [ProductReturnDetailHandler::class,     'handler'])->name('api.product_returns.detail');
        Route::put('/{id}',    [ProductReturnUpdateHandler::class,     'handler'])->name('api.product_returns.update');
        Route::delete('/{id}', [ProductReturnDeleteHandler::class,     'handler'])->name('api.product_returns.delete');
    });

    Route::prefix('brands')->group(function () {
        Route::post('/',       [BrandCreateHandler::class,      'handler'])->name('api.brands.create');
        Route::get('/',        [BrandPaginationHandler::class, 'handler'])->name('api.brands.pagination');
        Route::get('/{id}',    [BrandDetailHandler::class,     'handler'])->name('api.brands.detail');
        Route::put('/{id}',    [BrandUpdateHandler::class,     'handler'])->name('api.brands.update');
        Route::delete('/{id}', [BrandDeleteHandler::class,     'handler'])->name('api.brands.delete');
    });



    Route::prefix('garansis')->group(function () {
        Route::post('/',       [GaransiCreateHandler::class,      'handler'])->name('api.garansis.create');
        Route::get('/',        [GaransiPaginationHandler::class, 'handler'])->name('api.garansis.pagination');
        Route::get('/{id}',    [GaransiDetailHandler::class,     'handler'])->name('api.garansis.detail');
        Route::put('/{id}',    [GaransiUpdateHandler::class,     'handler'])->name('api.garansis.update');
        Route::delete('/{id}', [GaransiDeleteHandler::class,     'handler'])->name('api.garansis.delete');
    });

    Route::prefix('banners')->group(function () {
        Route::post('/',       [BannerCreateHandler::class,      'handler'])->name('api.banners.create');
        Route::get('/',        [BannerPaginationHandler::class, 'handler'])->name('api.banners.pagination');
        Route::get('/{id}',    [BannerDetailHandler::class,     'handler'])->name('api.banners.detail');
        Route::put('/{id}',    [BannerUpdateHandler::class,     'handler'])->name('api.banners.update');
        Route::delete('/{id}', [BannerDeleteHandler::class,     'handler'])->name('api.banners.delete');
    });

    Route::prefix('customer_categories')->group(function () {
        Route::post('/',       [CustomerCategoriesCreateHandler::class,      'handler'])->name('api.customer_categories.create');
        Route::get('/',        [CustomerCategoriesPaginationHandler::class, 'handler'])->name('api.customer_categories.pagination');
        Route::get('/{id}',    [CustomerCategoriesDetailHandler::class,     'handler'])->name('api.customer_categories.detail');
        Route::put('/{id}',    [CustomerCategoriesUpdateHandler::class,     'handler'])->name('api.customer_categories.update');
        Route::delete('/{id}', [CustomerCategoriesDeleteHandler::class,     'handler'])->name('api.customer_categories.delete');
    });

    Route::prefix('customer_programs')->group(function () {
        Route::post('/',       [CustomerProgramCreateHandler::class,      'handler'])->name('api.customer_programs.create');
        Route::get('/',        [CustomerProgramPaginationHandler::class, 'handler'])->name('api.customer_programs.pagination');
        Route::get('/{id}',    [CustomerProgramDetailHandler::class,     'handler'])->name('api.customer_programs.detail');
        Route::put('/{id}',    [CustomerProgramUpdateHandler::class,     'handler'])->name('api.customer_programs.update');
        Route::delete('/{id}', [CustomerProgramDeleteHandler::class,     'handler'])->name('api.customer_categories.delete');
    });

    Route::prefix('perbaikandatas')->group(function () {
        Route::post('/',       [PerbaikandataCreateHandler::class,      'handler'])->name('api.perbaikandatas.create');
        Route::get('/',        [PerbaikandataPaginationHandler::class, 'handler'])->name('api.perbaikandatas.pagination');
        Route::get('/{id}',    [PerbaikandataDetailHandler::class,     'handler'])->name('api.perbaikandatas.detail');
        Route::put('/{id}',    [PerbaikandataUpdateHandler::class,     'handler'])->name('api.perbaikandatas.update');
        Route::delete('/{id}', [PerbaikandataDeleteHandler::class,     'handler'])->name('api.perbaikandatas.delete');
    });

    
});



ApiService::routes(function () {
    CustomerResource::routes();
});
