<?php

namespace App\Providers;

use App\Models\Ticket;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
        Gate::policy(Ticket::class, TicketPolicy::class);
        View::composer([
            'layouts.app',
            'auth.login',
            'auth.first-password',
            'auth.password-email',
            'auth.password-reset',
            'reports.assets-pdf',
            'reports.maintenance-pdf',
            'reports.export-pdf',
        ], function ($view): void {
            try {
                $name = DB::table('system_settings')->where('key', 'university_name')->value('value') ?: 'City of Ilagan College';
            } catch (Throwable) {
                $name = 'City of Ilagan College';
            }
            $view->with('universityName', $name);
        });
    }
}
