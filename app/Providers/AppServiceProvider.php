<?php

namespace App\Providers;

use App\Listeners\UpdateLastLoginDate;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        // Propagate the per-request CSP nonce to all Vite-generated script/style tags
        if (! $this->app->runningInConsole() && config('csp.nonce_enabled', true)) {
            Vite::useCspNonce(app('csp-nonce'));
        }

        // Register login event listener
        Event::listen(Login::class, UpdateLastLoginDate::class);

        // Admin gate - check if user has is_admin flag
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });

        $this->app['mail.manager']->extend('brevo', function ($config) {
            $configuration = $this->app->make('config');

            return (new BrevoTransportFactory)->create(
                Dsn::fromString($configuration->get('services.brevo.dsn'))
            );
        });
    }
}
