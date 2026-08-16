<?php

namespace App\Providers;

use App\Patterns\Singleton\CredentialAuthority;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the Module 1 design pattern into Laravel's service container.
 *
 * $this->app->singleton() guarantees the container hands back the very same
 * CredentialAuthority for every resolution in a request, and the closure
 * returns CredentialAuthority::getInstance() so the container and the classic
 * GoF accessor can never disagree about which instance is "the" authority.
 */
class CredentialServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(CredentialAuthority::class, function () {
            return CredentialAuthority::getInstance();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
