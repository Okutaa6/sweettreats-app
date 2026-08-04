<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Paginator::useBootstrapFour();
        
        $publicHtml = base_path('../public_html');
        if (is_dir($publicHtml)) {
            $this->app->usePublicPath($publicHtml);
        }
        
    
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pastikan Carbon (tanggal/hari/bulan via translatedFormat) ikut Bahasa Indonesia.
        Carbon::setLocale('id');

        // Audit otomatis model sensitif tanpa pencatatan manual (Pusat Akun D).
        \App\Models\GroupPath::observe(\App\Observers\AuditTrailObserver::class);
        \App\Models\AttFlag::observe(\App\Observers\AuditTrailObserver::class);
    }
    
    
}

