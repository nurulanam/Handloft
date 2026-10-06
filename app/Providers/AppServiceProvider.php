<?php

namespace App\Providers;

use App\Support\MailSettings;
use Illuminate\Foundation\DevCommands;
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
        MailSettings::applyFromDatabase();

        // `composer dev` also starts the Reverb WebSocket server, which pushes
        // live notifications to the browser.
        if ($this->app->runningInConsole()) {
            DevCommands::artisan('reverb:start', 'reverb');
        }
    }
}
