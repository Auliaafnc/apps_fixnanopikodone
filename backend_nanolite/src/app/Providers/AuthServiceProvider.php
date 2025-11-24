<?php

namespace App\Providers;

use App\Models\Garansi;
use App\Policies\GaransiPolicy;
use App\Models\ProductReturn;
use App\Policies\ProductReturnPolicy;
use App\Models\Order;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Garansi::class => GaransiPolicy::class,
        ProductReturn::class => ProductReturnPolicy::class,
        Order::class => OrderPolicy::class,
        
    ];

   

    public function boot(): void
    {
        // Daftarkan mapping policies di atas
        $this->registerPolicies();

        // super_admin bypass semua Gate/Policy
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
    }
}
