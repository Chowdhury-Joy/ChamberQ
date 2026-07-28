<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Services\Sms\HttpSmsGateway;
use App\Services\Sms\LogSmsGateway;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, function () {
            return match (config('sms.driver', 'log')) {
                'log' => new LogSmsGateway,
                'http' => new HttpSmsGateway,
                default => throw new InvalidArgumentException(
                    'Unsupported SMS_DRIVER ['.config('sms.driver').']. Use log or http.'
                ),
            };
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
