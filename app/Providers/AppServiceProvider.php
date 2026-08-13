<?php

namespace App\Providers;

use App\Actions\GeneratePreferredVerificationOptions;
use App\Actions\GeneratePlatformRegistrationOptions;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Actions\GenerateRegistrationOptions;
use Laravel\Passkeys\Actions\GenerateVerificationOptions;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passkeys::ignoreRoutes();

        $this->app->bind(GenerateRegistrationOptions::class, GeneratePlatformRegistrationOptions::class);
        $this->app->bind(GenerateVerificationOptions::class, GeneratePreferredVerificationOptions::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
