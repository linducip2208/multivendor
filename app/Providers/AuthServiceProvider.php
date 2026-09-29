<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Product;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Backoffice bypass: admins are authorized for every product/order
        // ability, mirroring the per-policy isAdmin() checks. Vendors and
        // customers still fall through to ProductPolicy/OrderPolicy scoping.
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof \App\Models\User && $user->isAdmin()) {
                return true;
            }

            return null;
        });
    }
}
