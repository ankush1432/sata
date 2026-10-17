<?php

namespace App\Providers;

use App\Contracts\OtpProviderInterface;
use App\Services\Otp\MockOtpProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OtpProviderInterface::class, function () {
            return new MockOtpProvider();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
