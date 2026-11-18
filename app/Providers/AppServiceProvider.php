<?php

namespace App\Providers;

use App\Domain\Shared\Tenancy\TenantContext;
use App\Http\Middleware\EnsureCompanyContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([EnsureCompanyContext::class]);
        Paginator::defaultView('pagination.laralekh');
    }
}
