<?php

use App\Http\Controllers\Api\ApiCekatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Open API Vendor — Cekat (AI WhatsApp)
|--------------------------------------------------------------------------
| LIVE    : /apicekat/*      (token environment=live,    tulis tabel asli)
| SANDBOX : /apicekatdev/*   (token environment=sandbox, tulis tabel _dev)
|
| Auth   : header  Authorization: Bearer <token>   (middleware api.client)
| Limit  : 60 request / menit
| CSRF   : dikecualikan di VerifyCsrfToken (server-to-server)
*/

Route::prefix('apicekat')
    ->middleware(['api.client:live', 'throttle:60,1'])
    ->group(function () {
        Route::post('customer_profile', [ApiCekatController::class, 'customerProfile']);
        Route::get('service_range',     [ApiCekatController::class, 'serviceRange']);
        Route::get('jadwal_dokter',     [ApiCekatController::class, 'jadwalDokter']);
        Route::post('reservation',      [ApiCekatController::class, 'reservation']);
    });

Route::prefix('apicekatdev')
    ->middleware(['api.client:sandbox', 'throttle:60,1'])
    ->group(function () {
        Route::post('customer_profile', [ApiCekatController::class, 'customerProfile']);
        Route::get('service_range',     [ApiCekatController::class, 'serviceRange']);
        Route::get('jadwal_dokter',     [ApiCekatController::class, 'jadwalDokter']);
        Route::post('reservation',      [ApiCekatController::class, 'reservation']);
    });
