<?php

namespace App\Providers;

use App\Services\PassRequests\ApiAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Auth::viaRequest(ApiAccess::GUARD, fn () => null);
        config(['auth.guards.'.ApiAccess::GUARD => ['driver' => ApiAccess::GUARD]]);
        RateLimiter::for('pass-request-api', fn (Request $request) => Limit::perMinute(60)->by('pass-request-api:'.$request->ip())
        );

        // Propagate the per-request CSP nonce to all Vite-generated script/style tags
        if (! $this->app->runningInConsole() && config('csp.nonce_enabled', true)) {
            Vite::useCspNonce(app('csp-nonce'));
        }

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
