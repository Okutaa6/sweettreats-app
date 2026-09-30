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

        // Tracking Ortho: jaga snapshot kolom RESERVASI tetap jujur.
        //
        // ort_control menyimpan salinan rsv_date/rsv_status (bukan join —
        // collation prod campur). Tanpa observer ini, mengubah tanggal atau
        // membatalkan reservasi dari modul Reservasi membuat kolom itu BOHONG
        // tanpa error apa pun.
        //
        // Dipasang di sini, BUKAN sebagai kait di controller: reservasi ditulis
        // dari enam tempat berbeda. Observer-nya sendiri dibungkus try/catch —
        // pembuatan reservasi tidak boleh gagal gara-gara modul ortho.
        \App\Models\RawCusReservation::observe(\App\Observers\OrtReservationObserver::class);
    }
    
    
}

