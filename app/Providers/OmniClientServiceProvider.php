<?php

namespace App\Providers;

use DeveloperAwam\OmniCentralAuth\OmniCentralAuthServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Passport\Passport;
use Laravel\Socialite\SocialiteServiceProvider;

class OmniClientServiceProvider extends OmniCentralAuthServiceProvider
{
    public function register(): void
    {
        $this->app->register(SocialiteServiceProvider::class);
        parent::register();
        Fortify::ignoreRoutes();
        Passport::ignoreRoutes();
    }

    protected function registerRoutes(): void
    {
        // Client callbacks and account linking are handled by the application.
    }

    protected function registerMigrations(): void
    {
        // Existing roles and passwords are retained by the client migration.
    }

    protected function registerLivewireComponents(): void
    {
        // The identity provider dashboard belongs to the central server.
    }
}
