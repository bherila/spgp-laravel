<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginDate;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        // Register login event listener
        Event::listen(Login::class, UpdateLastLoginDate::class);

        // Admin gate - check if user has is_admin flag
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });
    }
}
