<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\UI\Theme;

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
        // A dedicated blue for "on leave" - the default palette has no blue, and reusing
        // 'secondary' isn't an option: it's the exact same hex as 'on-surface-variant' (the
        // untouched/future-day color), which made leave days unreadable against them.
        Theme::merge([
            'light' => ['leave' => '#2563EB', 'on-leave' => '#FFFFFF'],
            'dark' => ['leave' => '#60A5FA', 'on-leave' => '#0F172A'],
        ]);
    }
}
