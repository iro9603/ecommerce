<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use App\Observers\ProductReferenceModerationObserver;
use App\Observers\SellerTypeModerationObserver;
use App\Policies\ProductPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Gate::policy(Product::class, ProductPolicy::class);
        Brand::observe(ProductReferenceModerationObserver::class);
        Category::observe(ProductReferenceModerationObserver::class);
        Tag::observe(ProductReferenceModerationObserver::class);
        User::observe(SellerTypeModerationObserver::class);

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            return $user instanceof Admin && $user->hasRole('Super Admin')
                ? true
                : null;
        });
    }
}
