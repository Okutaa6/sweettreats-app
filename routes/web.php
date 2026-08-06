<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Set\Access;
use App\Http\Controllers\Set\Group;
use App\Http\Controllers\Set\SystemProfile;
use App\Http\Controllers\Set\UserManagement;
use App\Http\Controllers\Crud\DoctorController;
use App\Http\Controllers\Crud\DocServiceController;
use App\Http\Controllers\Crud\BranchController;
use App\Http\Controllers\Crud\CustomerController;
use App\Http\Controllers\Crud\ReservationController;
use App\Http\Controllers\Crud\ReservationImportController;
use App\Http\Controllers\Absensi\AbsensiUploadController;
use App\Http\Controllers\Absensi\AbsensiRecapController;
use App\Http\Controllers\Absensi\AbsensiPeriodController;
use App\Http\Controllers\Absensi\AbsensiMappingController;
use App\Http\Controllers\Absensi\AbsensiBranchIpController;
use App\Http\Controllers\Absensi\AturanKerjaController;
use App\Http\Controllers\Absensi\AkunAbsenController;
use App\Http\Controllers\Absensi\AkunAbsensiController;
use App\Http\Controllers\Absensi\AkunPengajuanController;
use App\Http\Controllers\Absensi\PengawasanController;
use App\Http\Controllers\Absensi\PersetujuanController;
use App\Http\Controllers\Crud\ReservasiCabangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicDoctorScheduleController;
use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\IsLoggedIn;
use App\Http\Controllers\Crud\AffiliateController;
use App\Http\Controllers\Crud\PromoController;
use App\Http\Controllers\Crud\MarketingPromoController;
use App\Http\Controllers\Crud\CommissionClaimController;
use App\Http\Controllers\Crud\KontenPillarController;
use App\Http\Controllers\Crud\EpKontenController;
use App\Http\Controllers\Crud\EpKontenImageController;
use App\Http\Controllers\Crud\EpBreakdownController;
use App\Http\Controllers\Crud\EpCampaignController;
use App\Http\Controllers\Crud\KeywordController;
use App\Http\Controllers\Crud\ReportReferralController;
use App\Http\Controllers\Crud\KolController;
use App\Http\Controllers\Crud\KocController;
use App\Http\Controllers\Crud\SweetMatesController;
use App\Http\Controllers\Crud\PettyCashController;
use App\Http\Controllers\Crud\CashDepositController;
use App\Http\Controllers\Crud\FundSourceController;
use App\Http\Controllers\Crud\DigitalChecklistController;
use App\Http\Controllers\Crud\ChecklistSetupController;
use App\Http\Controllers\Crud\MyChecklistController;
use App\Http\Controllers\Crud\AcMaintenanceController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\TopupRequestController;
use App\Http\Controllers\Finance\RopQueueController;
use App\Http\Controllers\Finance\RopRekapController;
use App\Http\Controllers\Rop\PengajuanController;


Route::get('/', function () {
    // Landing baru hanya butuh jumlah cabang.
    $branchCount = \App\Models\RawBranch::count();
    return view('landing', compact('branchCount'));
})->name('home');

Route::get('/reservasi', function () {
    $branches = \App\Models\RawBranch::orderBy('name')->get();
    return view('reservation-form', compact('branches'));
})->name('landing.reservation');

Route::post('/reservasi', [\App\Http\Controllers\LandingReservationController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('landing.reservation.store');

// Halaman publik jadwal dokter (read-only, tanpa login)
Route::get('/jadwal-dokter', [PublicDoctorScheduleController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('public.doctor-schedule');

// Affiliate Portal
Route::prefix('affiliate')->group(function () {
    $portal = \App\Http\Controllers\AffiliatePortalController::class;

    // Public
    Route::get('login',  [$portal, 'login'])->name('affiliate.login');
    Route::post('login', [$portal, 'loginProcess'])->name('affiliate.login.process');
    Route::get('register',  [$portal, 'register'])->name('affiliate.register');
    Route::post('register', [$portal, 'registerProcess'])->name('affiliate.register.process');
    Route::get('thank-you', [$portal, 'thankYou'])->name('affiliate.thankyou');
    Route::get('logout', [$portal, 'logout'])->name('affiliate.logout');

    // Portal (session guard via controller)
    Route::get('dashboard', [$portal, 'dashboard'])->name('affiliate.dashboard');

    // Profile — MUST be declared before content/{id}/* to avoid wildcard capture
    Route::get('profile',       [$portal, 'profile'])->name('affiliate.profile');
    Route::put('profile',       [$portal, 'profileUpdate'])->name('affiliate.profile.update');
    Route::post('profile/photo',[$portal, 'profilePhoto'])->name('affiliate.profile.photo');
    Route::put('profile/pin',   [$portal, 'profilePin'])->name('affiliate.profile.pin');

    // Content
    Route::get('content',            [$portal, 'contentIndex'])->name('affiliate.content');
    Route::post('content',           [$portal, 'contentStore'])->name('affiliate.content.store');
    Route::get('content/{id}/edit',  [$portal, 'contentEdit'])->name('affiliate.content.edit');
    Route::put('content/{id}',       [$portal, 'contentUpdate'])->name('affiliate.content.update');
    Route::delete('content/{id}',    [$portal, 'contentDelete'])->name('affiliate.content.delete');

    // Rewards
    Route::get('rewards', [$portal, 'rewards'])->name('affiliate.rewards');
    Route::post('rewards/{tierId}/claim', [$portal, 'claimReward'])->name('affiliate.reward.claim');
    Route::post('rewards/switch', [$portal, 'switchRewardProgram'])->name('affiliate.reward.switch');

    // Commissions
    Route::get('commissions', [$portal, 'commissions'])->name('affiliate.commissions');
    Route::post('commissions/claim',                   [$portal, 'requestCommissionClaim'])->name('affiliate.commissions.claim');
    Route::post('commissions/claim/{batchId}/confirm', [$portal, 'confirmCommissionClaim'])->name('affiliate.commissions.confirm');
});

Route::get('/reservasi/riwayat', function () {
    $phone        = request('phone');
    $reservations = collect();

    if ($phone) {
        // Only allow if user just submitted a reservation (phone stored in session via redirect)
        // or if the session has a matching success flash (comes from POST /reservasi redirect)
        if (!session()->has('success') && !session()->has('_riwayat_phone')) {
            return redirect()->route('landing.reservation')->with('error', 'Silakan reservasi terlebih dahulu.');
        }

        // Store phone in session for page refreshes within the same session
        session(['_riwayat_phone' => $phone]);

        $customer = \App\Models\RawCustomer::where('phone', $phone)->first();
        if ($customer) {
            $reservations = \App\Models\RawCusReservation::where('raw_customer_id', $customer->id)
                ->with(['branch', 'doctor', 'services.docService'])
                ->orderByDesc('date')
                ->get();
        }
    }

    return view('reservation-history', compact('reservations', 'phone'));
})->name('landing.reservation.history');

// API endpoints for reservation chain filter
Route::prefix('api/reservation')->middleware('throttle:60,1')->group(function () {
    $ctrl = \App\Http\Controllers\Api\ReservationApiController::class;
    Route::get('services', [$ctrl, 'getServices']);
    Route::get('doctors-by-service/{branchId}/{serviceId}', [$ctrl, 'getDoctorsByService']);
    Route::get('doctors/{branchId}', [$ctrl, 'getDoctorsByBranch']);
    Route::get('branches/{doctorId}', [$ctrl, 'getBranchesByDoctor']);
    Route::get('services-by-doctor/{doctorId}', [$ctrl, 'getServicesByDoctor']);
    Route::get('dates/{branchId}/{doctorId}', [$ctrl, 'getAvailableDates']);
    Route::get('slots/{branchId}/{doctorId}/{date}', [$ctrl, 'getTimeSlots']);
    Route::post('plan-schedule', [$ctrl, 'planSchedule']);
    Route::get('check-customer', [$ctrl, 'checkCustomerByPhone']);
    Route::get('check-new', [$ctrl, 'checkNew']);
});

Route::get('api/promo/validate', [PromoController::class, 'validate_promo'])->middleware('throttle:30,1')->name('api.promo.validate');

Route::middleware([AuthGuard::class])->group(function () {

    // Employee Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reservasi', [DashboardController::class, 'reservasi'])->name('dashboard.reservasi');
        Route::get('/waiting-list', [DashboardController::class, 'waitingList'])->name('dashboard.waiting-list');
        Route::get('/berlangsung', [DashboardController::class, 'berlangsung'])->name('dashboard.berlangsung');
        Route::get('/menunggu', [DashboardController::class, 'menungguKonfirmasi'])->name('dashboard.menunggu');
        Route::patch('/reservasi/{id}/status', [DashboardController::class, 'updateStatus'])->name('dashboard.update-status');
        Route::get('/executive', [\App\Http\Controllers\ExecutiveDashboardController::class, 'index'])->name('dashboard.executive');
    });

    // Sales Dashboard — segmen URL sendiri (/sales-dashboard) supaya RBAC-nya
    // independen dari segmen 'dashboard' (bisa di-grant khusus Sales, tanpa
    // ikut ngebuka /dashboard/executive dll).
    Route::get('/sales-dashboard', [\App\Http\Controllers\SalesDashboardController::class, 'index'])->name('sales-dashboard');

    // Marketing Dashboard — segmen URL sendiri (/marketing-dashboard) supaya
    // RBAC-nya independen (bisa di-grant khusus Marketing). Global + filter
    // cabang opsional, fokus akuisisi pasien + aktivitas marketing.
    Route::get('/marketing-dashboard', [\App\Http\Controllers\MarketingDashboardController::class, 'index'])->name('marketing-dashboard');

    // Sales Report — rekap penjualan periodik (data Omnicare)
    Route::prefix('report')->group(function () {
        // /report → redirect ke report sales (dipakai sidebar menu, link='report')
        Route::get('/', fn() => redirect()->route('report.sales'))->name('report.index');
        Route::get('/sales', [\App\Http\Controllers\SalesReportController::class, 'index'])->name('report.sales');
        Route::get('/sales/export', [\App\Http\Controllers\SalesReportController::class, 'export'])->name('report.sales.export');
        // Executive Report — admin only (gate di controller); analitik bulanan revenue & target
        Route::get('/executive', [\App\Http\Controllers\ExecutiveReportController::class, 'index'])->name('report.executive');
    });

    // Rekonsiliasi — reservasi SweetTreats ↔ encounter Omnicare (link='reconcile')
    Route::prefix('reconcile')->group(function () {
        Route::get('/', [\App\Http\Controllers\ReconciliationController::class, 'index'])->name('reconcile.index');
        Route::post('/{id}/link', [\App\Http\Controllers\ReconciliationController::class, 'link'])->name('reconcile.link');
        Route::post('/{id}/noshow', [\App\Http\Controllers\ReconciliationController::class, 'noshow'])->name('reconcile.noshow');
    });

    // Export
    Route::prefix('export')->group(function () {
        Route::get('reservasi', [\App\Http\Controllers\ExportController::class, 'reservations'])->name('export.reservasi');
        Route::get('komisi', [\App\Http\Controllers\ExportController::class, 'commissions'])->name('export.komisi');
    });

    // Pusat Akun → Kesehatan (detektor kegagalan-senyap) — segment 'setkesehatan'
    Route::get('setkesehatan', [\App\Http\Controllers\Set\KesehatanController::class, 'index'])->name('setkesehatan');

    // Access
    Route::get('setaccess', [Access::class, 'index'])->name('setaccess');
    // Pusat Akun Akses — grant/cabut role + rebuild (3-4 segmen, tak bentrok edit {id})
    Route::post('setaccess/rebuild',            [Access::class, 'rebuild'])->name('setaccess.rebuild');
    Route::post('setaccess/node',               [Access::class, 'storeNode'])->name('setaccess.store-node');
    Route::post('setaccess/{id}/grant',         [Access::class, 'grantRole'])->name('setaccess.grant');
    Route::post('setaccess/{id}/revoke/{gid}',  [Access::class, 'revokeRole'])->name('setaccess.revoke');
    Route::post('setaccess/{id}/update-node',   [Access::class, 'updateNode'])->name('setaccess.update-node');
    Route::post('setaccess/{id}/delete-node',   [Access::class, 'destroyNode'])->name('setaccess.delete-node');
    Route::get('setaccess/new', [Access::class, 'create'])->name('setaccess.new');
    Route::post('setaccess/new', [Access::class, 'store'])->name('setaccess.store');
    Route::get('setaccess/{id}', [Access::class, 'edit'])->name('setaccess.edit');
    Route::put('setaccess/{id}', [Access::class, 'update'])->name('setaccess.update');
    Route::delete('setaccess/{id}', [Access::class, 'destroy'])->name('setaccess.destroy');

    // User Management
    Route::get('setusermanagement', [UserManagement::class, 'index'])->name('setusermanagement');
    // Pusat Akun — detail user + aksi IT (3 segmen, tak bentrok dgn edit {id} 2-segmen)
    Route::get('setusermanagement/detail/{id}',            [UserManagement::class, 'detail'])->name('setusermanagement.detail');
    Route::post('setusermanagement/{id}/force-logout',     [UserManagement::class, 'forceLogout'])->name('setusermanagement.force-logout');
    Route::post('setusermanagement/{id}/toggle-status',    [UserManagement::class, 'toggleStatus'])->name('setusermanagement.toggle-status');
    Route::post('setusermanagement/{id}/revoke-all',       [UserManagement::class, 'revokeAllDevices'])->name('setusermanagement.revoke-all');
    Route::post('setusermanagement/{id}/revoke-device/{deviceId}', [UserManagement::class, 'revokeDevice'])->name('setusermanagement.revoke-device');
    Route::get('setusermanagement/new', [UserManagement::class, 'create'])->name('setusermanagement.new');
    Route::post('setusermanagement/new', [UserManagement::class, 'store'])->name('setusermanagement.store');
    Route::get('setusermanagement/{id}', [UserManagement::class, 'edit'])->name('setusermanagement.edit');
    Route::put('setusermanagement/{id}', [UserManagement::class, 'update'])->name('setusermanagement.update');
    Route::delete('setusermanagement/{id}', [UserManagement::class, 'destroy'])->name('setusermanagement.destroy');
    Route::get('setusermanagement/reset/{id}', [UserManagement::class, 'reset'])->name('setusermanagement.reset');
    Route::put('setusermanagement/reset/{id}', [UserManagement::class, 'resetProcess'])->name('setusermanagement.reset.process');
    Route::get('setusermanagement/apv/{id}', [UserManagement::class, 'approval'])->name('setusermanagement.apv');
    Route::put('setusermanagement/apv/{id}', [UserManagement::class, 'approvalProcess'])->name('setusermanagement.apv.process');

    // Group
    Route::get('setgroup', [Group::class, 'index'])->name('setgroup');
    Route::get('setgroup/new', [Group::class, 'create'])->name('setgroup.new');
    Route::post('setgroup/new', [Group::class, 'store'])->name('setgroup.store');
    Route::post('setgroup/reparent', [Group::class, 'reparent'])->name('setgroup.reparent');
    Route::post('setgroup/{id}/detach', [Group::class, 'detach'])->name('setgroup.detach');
    Route::get('setgroup/{id}', [Group::class, 'edit'])->name('setgroup.edit');
    Route::put('setgroup/{id}', [Group::class, 'update'])->name('setgroup.update');
    Route::delete('setgroup/{id}', [Group::class, 'destroy'])->name('setgroup.destroy');
    Route::get('setgroup/access/{id}', [Group::class, 'access'])->name('setgroup.access');
    Route::put('setgroup/access/{id}', [Group::class, 'accessProcess'])->name('setgroup.access.process');


    // Notifications
    Route::get('api/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('api.notifications');
    Route::patch('api/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('api.notifications.read');
    Route::post('api/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');

    // Audit Log
    Route::get('auditlog', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('auditlog');

    // System Profile
    Route::get('setsystemprofile', [SystemProfile::class, 'index'])->name('setsysprofile');
    Route::get('setsystemprofile/new', [SystemProfile::class, 'create'])->name('setsysprofile.new');
    Route::post('setsystemprofile/new', [SystemProfile::class, 'store'])->name('setsysprofile.store');
    Route::get('setsystemprofile/{id}', [SystemProfile::class, 'edit'])->name('setsysprofile.edit');
    Route::put('setsystemprofile/{id}', [SystemProfile::class, 'update'])->name('setsysprofile.update');
    Route::get('setsystemprofile/upload/{id}', [SystemProfile::class, 'upload'])->name('setsysprofile.upload');
    Route::put('setsystemprofile/upload/{id}', [SystemProfile::class, 'uploadProcess'])->name('setsysprofile.upload.process');
    Route::delete('setsystemprofile/{id}', [SystemProfile::class, 'destroy'])->name('setsysprofile.destroy');
    Route::delete('setsystemprofile/logo/{id}', [SystemProfile::class, 'destroyLogo'])->name('setsysprofile.destroy.logo');

    // CRUD - Doctor
    Route::get('rawdoctor', [DoctorController::class, 'index'])->name('rawdoctor');
    Route::get('rawdoctor/new', [DoctorController::class, 'create'])->name('rawdoctor.new');
    Route::post('rawdoctor/new', [DoctorController::class, 'store'])->name('rawdoctor.store');
    Route::get('rawdoctor/{id}', [DoctorController::class, 'edit'])->name('rawdoctor.edit');
    Route::put('rawdoctor/{id}', [DoctorController::class, 'update'])->name('rawdoctor.update');
    Route::delete('rawdoctor/{id}', [DoctorController::class, 'destroy'])->name('rawdoctor.destroy');
    Route::delete('rawdoctor/{id}/photo', [DoctorController::class, 'deletePhoto'])->name('rawdoctor.photo.delete');
    Route::patch('rawdoctor/{id}/toggle-active', [DoctorController::class, 'toggleActive'])->name('rawdoctor.toggle-active');

    Route::post('rawdoctor/{doctorId}/schedule', [DoctorController::class, 'scheduleStore'])->name('rawdoctor.schedule.store');
    Route::put('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleUpdate'])->name('rawdoctor.schedule.update');
    Route::delete('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleDestroy'])->name('rawdoctor.schedule.destroy');
    Route::get('rawdoctor/{doctorId}/schedules-json', [DoctorController::class, 'schedulesJson'])->name('rawdoctor.schedules.json');
    Route::patch('rawdoctor/{doctorId}/schedule/{scheduleId}/ajax', [DoctorController::class, 'scheduleUpdateAjax'])->name('rawdoctor.schedule.ajax');
    Route::post('rawdoctor/{doctorId}/schedule-copy', [DoctorController::class, 'scheduleCopy'])->name('rawdoctor.schedule.copy');
    Route::post('rawdoctor/{doctorId}/services', [DoctorController::class, 'servicesUpdate'])->name('rawdoctor.services.update');
    Route::post('rawdoctor/{doctorId}/leave', [DoctorController::class, 'leaveStore'])->name('rawdoctor.leave.store');
    Route::delete('rawdoctor/{doctorId}/leave/{leaveId}', [DoctorController::class, 'leaveDestroy'])->name('rawdoctor.leave.destroy');

    if (config('features.flexible_scheduling')) {
        Route::post('rawdoctor/{doctorId}/exception', [DoctorController::class, 'exceptionStore'])->name('rawdoctor.exception.store');
        Route::put('rawdoctor/{doctorId}/exception/{exceptionId}', [DoctorController::class, 'exceptionUpdate'])->name('rawdoctor.exception.update');
        Route::delete('rawdoctor/{doctorId}/exception/{exceptionId}', [DoctorController::class, 'exceptionDestroy'])->name('rawdoctor.exception.destroy');
    }

    // CRUD - Reservation
    Route::get('rawreservation', [ReservationController::class, 'index'])->name('rawreservation');
    Route::get('rawreservation/new', [ReservationController::class, 'create'])->name('rawreservation.new');
    Route::post('rawreservation/new', [ReservationController::class, 'store'])->name('rawreservation.store');
    Route::post('rawreservation/quick-patient', [ReservationController::class, 'storeQuickPatient'])->name('rawreservation.quickpatient');

    // CRUD - Reservation Import (CSV/Sheets → Staging → Promote)
    // PENTING: harus di-deklarasi SEBELUM `rawreservation/{id}` supaya
    // `/rawreservation/import` tidak ke-capture wildcard `{id}` di line bawah.
    Route::get('rawreservation/import',                          [ReservationImportController::class, 'index'])->name('rawreservation.import');
    Route::post('rawreservation/import/upload',                  [ReservationImportController::class, 'upload'])->name('rawreservation.import.upload');
    Route::patch('rawreservation/import/staging/{id}',           [ReservationImportController::class, 'update'])->name('rawreservation.import.staging.update');
    Route::post('rawreservation/import/staging/{id}/save-draft', [ReservationImportController::class, 'saveDraft'])->name('rawreservation.import.staging.save-draft');
    Route::post('rawreservation/import/staging/{id}/promote',    [ReservationImportController::class, 'promoteOne'])->name('rawreservation.import.staging.promote');
    Route::post('rawreservation/import/staging/{id}/skip',       [ReservationImportController::class, 'skipOne'])->name('rawreservation.import.staging.skip');
    Route::post('rawreservation/import/bulk-promote',            [ReservationImportController::class, 'promoteBulk'])->name('rawreservation.import.bulk-promote');
    Route::post('rawreservation/import/bulk-skip',               [ReservationImportController::class, 'skipBulk'])->name('rawreservation.import.bulk-skip');
    Route::post('rawreservation/import/batch/{id}/auto-process', [ReservationImportController::class, 'autoProcess'])->name('rawreservation.import.batch.auto-process');
    Route::post('rawreservation/import/batch/{id}/rematch-doctors', [ReservationImportController::class, 'reMatchDoctors'])->name('rawreservation.import.batch.rematch-doctors');
    Route::delete('rawreservation/import/batch/{id}',            [ReservationImportController::class, 'destroyBatch'])->name('rawreservation.import.batch.destroy');

    // ── Absensi (rekonsiliasi) — segment(1) = 'absensi'. AuthGuard cek link=='absensi' ──
    Route::get('absensi',              [AbsensiUploadController::class, 'index'])->name('absensi.upload');
    Route::post('absensi/upload/file', [AbsensiUploadController::class, 'upload'])->name('absensi.upload.file');
    Route::get('absensi/recap',        [AbsensiRecapController::class, 'index'])->name('absensi.recap');
    Route::get('absensi/kehadiran',    [\App\Http\Controllers\Absensi\AbsensiKehadiranController::class, 'index'])->name('absensi.kehadiran');

    // Lembar Gaji — rekap kehadiran/lembur per karyawan per bulan (Finance/IT/admin)
    Route::get('absensi/lembar-gaji',        [\App\Http\Controllers\Absensi\LembarGajiController::class, 'index'])->name('absensi.lembargaji');
    Route::get('absensi/lembar-gaji/export', [\App\Http\Controllers\Absensi\LembarGajiController::class, 'export'])->name('absensi.lembargaji.export');

    // Cuti — rekap saldo + cuti bersama + koreksi + kontrak baru (Finance/IT/admin)
    Route::get ('absensi/cuti',               [\App\Http\Controllers\Absensi\CutiController::class, 'index'])->name('absensi.cuti');
    Route::post('absensi/cuti/bersama',       [\App\Http\Controllers\Absensi\CutiController::class, 'bersama'])->name('absensi.cuti.bersama');
    Route::post('absensi/cuti/bersama-undo',  [\App\Http\Controllers\Absensi\CutiController::class, 'bersamaUndo'])->name('absensi.cuti.bersama.undo');
    Route::post('absensi/cuti/koreksi',       [\App\Http\Controllers\Absensi\CutiController::class, 'koreksi'])->name('absensi.cuti.koreksi');
    Route::post('absensi/cuti/kontrak-baru',  [\App\Http\Controllers\Absensi\CutiController::class, 'kontrakBaru'])->name('absensi.cuti.kontrak');
    Route::get('absensi/mapping',         [AbsensiMappingController::class, 'index'])->name('absensi.mapping');
    Route::post('absensi/mapping/assign', [AbsensiMappingController::class, 'assign'])->name('absensi.mapping.assign');
    Route::post('absensi/mapping/ignore', [AbsensiMappingController::class, 'ignore'])->name('absensi.mapping.ignore');
    Route::get('absensi/periode',            [AbsensiPeriodController::class, 'index'])->name('absensi.period');
    Route::post('absensi/periode',           [AbsensiPeriodController::class, 'store'])->name('absensi.period.store');
    Route::post('absensi/periode/{id}/close', [AbsensiPeriodController::class, 'close'])->name('absensi.period.close');
    Route::get('absensi/periode/{id}/export', [AbsensiPeriodController::class, 'export'])->name('absensi.period.export');

    // ── Akun Saya (self-service karyawan, mobile) — segment(1) = 'akun' ──
    Route::get('akun', [AkunAbsensiController::class, 'index'])->name('akun.absensi');
    Route::get('akun/absensi/foto/{id}', [AkunAbsensiController::class, 'foto'])->name('akun.absensi.foto');
    Route::get('akun/pengajuan',  [AkunPengajuanController::class, 'create'])->name('akun.pengajuan');
    Route::post('akun/pengajuan', [AkunPengajuanController::class, 'store'])->name('akun.pengajuan.store');
    Route::get('akun/pengajuan/prefill-lembur', [AkunPengajuanController::class, 'prefillLembur'])->name('akun.pengajuan.prefill');
    Route::get('akun/pengajuan/{id}', [AkunPengajuanController::class, 'show'])->name('akun.pengajuan.show');
    Route::get('akun/absen',  [AkunAbsenController::class, 'index'])->name('akun.absen');
    Route::post('akun/absen', [AkunAbsenController::class, 'store'])->name('akun.absen.store');

    // ── Pengawasan Absen (review SPV) — segment(1) = 'pengawasan', node awas-* ──
    Route::get('pengawasan',                    [PengawasanController::class, 'index'])->name('pengawasan');
    Route::get('pengawasan/foto/{id}',          [PengawasanController::class, 'foto'])->name('pengawasan.foto');
    Route::post('pengawasan/{id}/dicek',        [PengawasanController::class, 'dicek'])->name('pengawasan.dicek');
    Route::post('pengawasan/{id}/selesai',      [PengawasanController::class, 'selesai'])->name('pengawasan.selesai');
    Route::post('pengawasan/grup-selesai',      [PengawasanController::class, 'selesaiGrup'])->name('pengawasan.grup');
    Route::post('pengawasan/daftarkan-ip',      [PengawasanController::class, 'daftarkanIp'])->name('pengawasan.daftarkan-ip');

    // ── WiFi Cabang (admin IP absen HP) — segment(1) = 'absensi', guard canSeeAllBranches ──
    Route::get('absensi/wifi',              [AbsensiBranchIpController::class, 'index'])->name('absensi.wifi');
    Route::post('absensi/wifi',             [AbsensiBranchIpController::class, 'store'])->name('absensi.wifi.store');
    Route::post('absensi/wifi/{id}/toggle', [AbsensiBranchIpController::class, 'toggle'])->name('absensi.wifi.toggle');

    // ── Aturan Kerja (jam kerja + penugasan + kalender libur) — gate role di
    //    controller (Head IT/Finance/Super Admin), menulis tabel yang dibaca
    //    mesin, TIDAK menyentuh reconcile. docs/rencana/rencana-aturan-kerja.md ──
    Route::get('absensi/aturan',                    [AturanKerjaController::class, 'index'])->name('absensi.aturan');
    Route::post('absensi/aturan/rule',              [AturanKerjaController::class, 'storeRule'])->name('absensi.aturan.rule.buat');
    Route::post('absensi/aturan/rule/{id}',         [AturanKerjaController::class, 'updateRule'])->name('absensi.aturan.rule.ubah');
    Route::post('absensi/aturan/rule/{id}/hapus',   [AturanKerjaController::class, 'deleteRule'])->name('absensi.aturan.rule.hapus');
    Route::post('absensi/aturan/penugasan',         [AturanKerjaController::class, 'assign'])->name('absensi.aturan.penugasan');
    Route::post('absensi/aturan/default-role',      [AturanKerjaController::class, 'defaultRole'])->name('absensi.aturan.default-role');
    Route::post('absensi/aturan/libur',             [AturanKerjaController::class, 'storeException'])->name('absensi.aturan.libur.buat');
    Route::post('absensi/aturan/libur/{id}/hapus',  [AturanKerjaController::class, 'deleteException'])->name('absensi.aturan.libur.hapus');

    // ── Persetujuan (inbox approver, mobile) — segment(1) = 'persetujuan' ──
    Route::get('persetujuan',          [PersetujuanController::class, 'index'])->name('persetujuan.inbox');
    Route::post('persetujuan/{id}/act', [PersetujuanController::class, 'act'])->name('persetujuan.act');

    Route::get('rawreservation/{id}', [ReservationController::class, 'edit'])->name('rawreservation.edit');
    Route::put('rawreservation/{id}', [ReservationController::class, 'update'])->name('rawreservation.update');
    Route::delete('rawreservation/{id}', [ReservationController::class, 'destroy'])->name('rawreservation.destroy');
    Route::patch('rawreservation/{id}/inline', [ReservationController::class, 'updateInline'])->name('rawreservation.inline');
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/approve', [ReservationController::class, 'commissionApprove'])->name('rawreservation.commission.approve');
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/pay', [ReservationController::class, 'commissionPay'])->name('rawreservation.commission.pay');
    Route::patch('rawreservation/{id}/klaim', [ReservationController::class, 'klaimPromo'])->name('rawreservation.klaim');
    Route::post('rawreservation/{reservationId}/service', [ReservationController::class, 'serviceStore'])->name('rawreservation.service.store');
    Route::delete('rawreservation/{reservationId}/service/{serviceId}', [ReservationController::class, 'serviceDestroy'])->name('rawreservation.service.destroy');
    Route::post('rawreservation/access-modes', [ReservationController::class, 'saveAccessModes'])->name('rawreservation.access-modes.save');

    // CRUD - Doctor Service
    Route::get('rawdocservice', [DocServiceController::class, 'index'])->name('rawdocservice');
    Route::get('rawdocservice/new', [DocServiceController::class, 'create'])->name('rawdocservice.new');
    Route::post('rawdocservice/new', [DocServiceController::class, 'store'])->name('rawdocservice.store');
    Route::get('rawdocservice/{id}', [DocServiceController::class, 'edit'])->name('rawdocservice.edit');
    Route::put('rawdocservice/{id}', [DocServiceController::class, 'update'])->name('rawdocservice.update');
    Route::delete('rawdocservice/{id}', [DocServiceController::class, 'destroy'])->name('rawdocservice.destroy');

    Route::post('rawdocservice/{id}/detail', [DocServiceController::class, 'detailStore'])->name('rawdocservice.detail.store');
    Route::put('rawdocservice/{id}/detail/{detailId}', [DocServiceController::class, 'detailUpdate'])->name('rawdocservice.detail.update');
    Route::delete('rawdocservice/{id}/detail/{detailId}', [DocServiceController::class, 'detailDestroy'])->name('rawdocservice.detail.destroy');
    Route::post('rawdocservice/{id}/detail/reorder', [DocServiceController::class, 'detailReorder'])->name('rawdocservice.detail.reorder');

    // CRUD - Branch
    Route::get('rawbranch', [BranchController::class, 'index'])->name('rawbranch');
    Route::get('rawbranch/new', [BranchController::class, 'create'])->name('rawbranch.new');
    Route::post('rawbranch/new', [BranchController::class, 'store'])->name('rawbranch.store');
    Route::get('rawbranch/{id}', [BranchController::class, 'edit'])->name('rawbranch.edit');
    Route::put('rawbranch/{id}', [BranchController::class, 'update'])->name('rawbranch.update');
    Route::delete('rawbranch/{id}', [BranchController::class, 'destroy'])->name('rawbranch.destroy');
    Route::post('rawbranch/{id}/target', [BranchController::class, 'saveTarget'])->name('rawbranch.target');


    // CRUD - Affiliate
    Route::get('rawaffiliate', [AffiliateController::class, 'index'])->name('rawaffiliate');
    Route::get('rawaffiliate/new', [AffiliateController::class, 'create'])->name('rawaffiliate.new');
    Route::post('rawaffiliate/new', [AffiliateController::class, 'store'])->name('rawaffiliate.store');
    Route::patch('rawaffiliate/{id}/approve', [AffiliateController::class, 'approve'])->name('rawaffiliate.approve');
    Route::patch('rawaffiliate/{id}/reject', [AffiliateController::class, 'reject'])->name('rawaffiliate.reject');
    Route::post('rawaffiliate/{id}/promo', [AffiliateController::class, 'addPromo'])->name('rawaffiliate.add-promo');
    Route::get('rawaffiliate/{id}', [AffiliateController::class, 'edit'])->name('rawaffiliate.edit');
    Route::put('rawaffiliate/{id}', [AffiliateController::class, 'update'])->name('rawaffiliate.update');
    Route::delete('rawaffiliate/{id}', [AffiliateController::class, 'destroy'])->name('rawaffiliate.destroy');
    Route::get('kol',                 [KolController::class, 'index'])->name('kol');
    Route::get('kol/existing',        [KolController::class, 'existing'])->name('kol.existing');
    Route::get('kol/csv-template',    [KolController::class, 'downloadCsvTemplate'])->name('kol.csv-template');
    Route::post('kol/import-csv',     [KolController::class, 'importCsv'])->name('kol.import-csv');
    Route::patch('kol/{id}/progress', [KolController::class, 'updateProgress'])->name('kol.update-progress');
    Route::patch('kol/{id}/rencana-perawatan', [KolController::class, 'updateRencanaPerawatan'])->name('kol.update-rencana-perawatan');
    Route::patch('kol/{id}/feedback', [KolController::class, 'updateFeedback'])->name('kol.update-feedback');
    Route::patch('kol/{id}/status-relasi', [KolController::class, 'updateStatusRelasi'])->name('kol.update-status-relasi');
    Route::get('koc', [KocController::class, 'index'])->name('koc');
    Route::get('koc/csv-template', [KocController::class, 'downloadCsvTemplate'])->name('koc.csv-template');
    Route::post('koc/import-csv',  [KocController::class, 'importCsv'])->name('koc.import-csv');
    Route::patch('koc/{id}/confirm-sosmed', [KocController::class, 'toggleSosmedConfirmed'])->name('koc.confirm-sosmed');
    Route::patch('koc/{id}/doctor', [KocController::class, 'updateDoctor'])->name('koc.update-doctor');
    Route::patch('koc/{id}/behel-type', [KocController::class, 'updateBehelType'])->name('koc.update-behel-type');
    Route::patch('koc/{id}/feedback', [KocController::class, 'updateFeedback'])->name('koc.update-feedback');
    Route::get('koc/{id}/contents', [KocController::class, 'contents'])->name('koc.contents');

   // CRUD - Petty Cash (kas kecil — imprest/dana tetap)
    Route::get('pettycash',                       [PettyCashController::class, 'index'])->name('pettycash');
    Route::get('pettycash/export',                [PettyCashController::class, 'export'])->name('pettycash.export');
    Route::post('pettycash/expense',              [PettyCashController::class, 'store'])->name('pettycash.expense');
    Route::post('pettycash/topup',                [PettyCashController::class, 'topup'])->name('pettycash.topup');
    Route::post('pettycash/topup-request',        [PettyCashController::class, 'requestTopup'])->name('pettycash.requestTopup');
    Route::post('pettycash/account',              [PettyCashController::class, 'saveAccount'])->name('pettycash.account.save');
    Route::post('pettycash/opening',              [PettyCashController::class, 'setOpening'])->name('pettycash.opening');
    Route::post('pettycash/transfer',             [PettyCashController::class, 'transfer'])->name('pettycash.transfer');
    Route::patch('pettycash/transaction/{id}',    [PettyCashController::class, 'update'])->name('pettycash.update');
    Route::delete('pettycash/transaction/{id}',   [PettyCashController::class, 'destroy'])->name('pettycash.destroy');
    Route::post('pettycash/source/destroy',       [PettyCashController::class, 'destroySource'])->name('pettycash.source.destroy');
    Route::post('pettycash/period/{id}/close',    [PettyCashController::class, 'close'])->name('pettycash.close');

    // Finance: dashboard monitoring + approval workflow top-up request
    Route::prefix('finance')->group(function () {
        Route::get ('pettycash',                            [FinanceDashboardController::class, 'index'])->name('finance.pettycash');
        // Riwayat transaksi 1 cabang (read-only, monitoring) — dibuka dari tombol
        // "Riwayat" di kartu cabang. Deklarasi SEBELUM 'pettycash/request/{id}'
        // aman karena segmen 'branch' literal (tidak tabrakan wildcard).
        Route::get ('pettycash/branch/{branchId}/riwayat',  [FinanceDashboardController::class, 'branchHistory'])->name('finance.pettycash.branch-history');
        // Reset jejak transaksi cabang+bulan (hard delete) — khusus Super Admin (dicek di controller).
        Route::post('pettycash/branch/{branchId}/riwayat/reset', [FinanceDashboardController::class, 'resetBranchHistory'])->name('finance.pettycash.branch-history.reset');
        // Reset riwayat review (hard delete pengajuan yang sudah di-review) — khusus Super Admin (dicek di controller).
        Route::post('pettycash/review/reset',               [FinanceDashboardController::class, 'resetReviewHistory'])->name('finance.pettycash.review.reset');
        Route::get ('pettycash/request/{id}',               [TopupRequestController::class, 'show'])->name('finance.topup.show');
        Route::post('pettycash/request/{id}/approve',       [TopupRequestController::class, 'approve'])->name('finance.topup.approve');
        Route::post('pettycash/request/{id}/reject',        [TopupRequestController::class, 'reject'])->name('finance.topup.reject');

        // Pengajuan / ROP — antrian & rekap Finance (zona burgundy).
        // Rute literal ('rekap', 'export') dideklarasikan SEBELUM wildcard {id}
        // supaya tidak ditelan pola angka/segmen bebas.
        Route::get ('rop',                    [RopQueueController::class, 'index'])->name('finance.rop');
        Route::get ('rop/rekap',              [RopRekapController::class, 'index'])->name('finance.rop.rekap');
        Route::get ('rop/rekap/export',       [RopRekapController::class, 'exportCsv'])->name('finance.rop.rekap.export');
        Route::get ('rop/export/accurate',    [RopRekapController::class, 'exportAccurate'])->name('finance.rop.export.accurate');
        Route::get ('rop/{id}',               [RopQueueController::class, 'show'])->name('finance.rop.show');
        Route::post('rop/{id}/setujui',       [RopQueueController::class, 'setujui'])->name('finance.rop.setujui');
        Route::post('rop/{id}/urungkan',      [RopQueueController::class, 'urungkan'])->name('finance.rop.urungkan');
        Route::post('rop/{id}/tolak',         [RopQueueController::class, 'tolak'])->name('finance.rop.tolak');
        Route::post('rop/{id}/bayar',         [RopQueueController::class, 'bayar'])->name('finance.rop.bayar');
    });

    // ═══════════════════════════════════════════════════════════════════════
    // Pengajuan / ROP — sisi PENGAJU (kepala departemen), zona cokelat, HP-first.
    // Pengganti formulir kertas "Request of Payment".
    // Rencana: docs/rencana/rencana-pengajuan-rop.md
    // ═══════════════════════════════════════════════════════════════════════
    Route::get ('pengajuan',              [PengajuanController::class, 'index'])->name('rop.pengajuan.index');
    Route::get ('pengajuan/baru',         [PengajuanController::class, 'create'])->name('rop.pengajuan.create');
    Route::post('pengajuan',              [PengajuanController::class, 'store'])->name('rop.pengajuan.store');
    Route::get ('pengajuan/{id}',         [PengajuanController::class, 'show'])->name('rop.pengajuan.show');
    Route::post('pengajuan/{id}/batal',   [PengajuanController::class, 'batal'])->name('rop.pengajuan.batal');
    Route::post('pengajuan/{id}/selesai', [PengajuanController::class, 'selesai'])->name('rop.pengajuan.selesai');

    // CRUD - Fund Sources (sumber dana: cash / bank / e-wallet) — Super Admin
    Route::get('fund-sources',                    [FundSourceController::class, 'index'])->name('fundsource');
    Route::post('fund-sources',                   [FundSourceController::class, 'store'])->name('fundsource.store');
    Route::post('fund-sources/{id}',              [FundSourceController::class, 'update'])->name('fundsource.update');
    Route::post('fund-sources/{id}/toggle',       [FundSourceController::class, 'toggle'])->name('fundsource.toggle');

    // ═══════════════════════════════════════════════════════════════════════
    // CRUD - Checklist Akreditasi (operasional klinik harian)
    //   3 layar utama:
    //     - Supervisor: Dashboard, Setup Tugas, Review, Audit Trail
    //     - Karyawan : /my-checklist (isian harian + history)
    // ═══════════════════════════════════════════════════════════════════════

    // Dashboard + Audit (Supervisor only)
    Route::get ('checklist',                        [DigitalChecklistController::class, 'dashboard'])  ->name('checklist');
    Route::get ('checklist/export',                 [DigitalChecklistController::class, 'exportDaily'])->name('checklist.export');
    Route::get ('checklist/export/weekly',          [DigitalChecklistController::class, 'exportWeekly'])->name('checklist.export.weekly');
    Route::get ('checklist/audit',                  [DigitalChecklistController::class, 'audit'])      ->name('checklist.audit');

    // Kelola Posisi Karyawan (Supervisor)
    Route::get   ('checklist/positions',            [DigitalChecklistController::class, 'positions'])       ->name('checklist.positions');
    Route::post  ('checklist/positions',            [DigitalChecklistController::class, 'positionAssign'])  ->name('checklist.positions.assign');
    Route::delete('checklist/positions/{id}',       [DigitalChecklistController::class, 'positionRemove'])  ->name('checklist.positions.remove');
    // Rolling cabang (penugasan sementara) — di halaman posisi yang sama.
    Route::post  ('checklist/positions/rolling',      [DigitalChecklistController::class, 'rollingAssign'])->name('checklist.positions.rolling');
    Route::post  ('checklist/positions/rolling/swap', [DigitalChecklistController::class, 'rollingSwap'])  ->name('checklist.positions.rolling.swap');
    Route::delete('checklist/positions/rolling/{id}', [DigitalChecklistController::class, 'rollingRemove'])->name('checklist.positions.rolling.remove');

    // Setup Tugas (Supervisor only)
    Route::get ('checklist/setup',                  [ChecklistSetupController::class, 'index'])        ->name('checklist.setup');
    Route::post('checklist/setup/category',         [ChecklistSetupController::class, 'storeCategory'])->name('checklist.setup.cat.store');
    Route::put ('checklist/setup/category/{id}',    [ChecklistSetupController::class, 'updateCategory'])->name('checklist.setup.cat.update');
    Route::delete('checklist/setup/category/{id}',  [ChecklistSetupController::class, 'destroyCategory'])->name('checklist.setup.cat.destroy');
    Route::post('checklist/setup/task',             [ChecklistSetupController::class, 'storeTask'])    ->name('checklist.setup.task.store');
    Route::post('checklist/setup/weekly-task',      [ChecklistSetupController::class, 'storeWeeklyTask'])->name('checklist.setup.weekly-task.store');
    Route::put ('checklist/setup/task/{id}',        [ChecklistSetupController::class, 'updateTask'])   ->name('checklist.setup.task.update');
    Route::delete('checklist/setup/task/{id}',      [ChecklistSetupController::class, 'destroyTask'])  ->name('checklist.setup.task.destroy');
    Route::post('checklist/setup/info',             [ChecklistSetupController::class, 'updateInfo'])   ->name('checklist.setup.info');

    // Review & Approval Harian (Supervisor)
    Route::get  ('checklist/review',                [DigitalChecklistController::class, 'reviewIndex'])->name('checklist.review');
    // Review Mingguan — taruh SEBELUM /review/{id} supaya 'weekly' tidak ditangkap sebagai {id}.
    Route::get  ('checklist/review/weekly',         [DigitalChecklistController::class, 'weeklyReviewIndex'])->name('checklist.review.weekly');
    // Riwayat & aksi Form Mingguan per karyawan (drill-down) — SEBELUM /weekly/{id}.
    Route::get  ('checklist/review/weekly/employee/{user}',                 [DigitalChecklistController::class, 'weeklyReviewEmployee'])        ->name('checklist.review.weekly.employee');
    Route::patch('checklist/review/weekly/employee/{user}/approve-pending', [DigitalChecklistController::class, 'weeklyApproveEmployeePending'])->name('checklist.review.weekly.employee.approve-pending');
    Route::get  ('checklist/review/weekly/{id}',    [DigitalChecklistController::class, 'weeklyReviewShow'])  ->name('checklist.review.weekly.show');
    Route::patch('checklist/review/weekly/{id}/approve',[DigitalChecklistController::class, 'weeklyApprove'])->name('checklist.review.weekly.approve');
    Route::patch('checklist/review/weekly/{id}/reject', [DigitalChecklistController::class, 'weeklyReject']) ->name('checklist.review.weekly.reject');
    Route::patch('checklist/review/weekly/{id}/reopen', [DigitalChecklistController::class, 'weeklyReopen']) ->name('checklist.review.weekly.reopen');
    Route::patch('checklist/review/weekly/{id}/revoke-excuse', [DigitalChecklistController::class, 'weeklyRevokeExcuse'])->name('checklist.review.weekly.excuse.revoke');
    // Review PER-HARI (mid-week): approve/tolak/reopen tiap hari + setujui semua.
    Route::patch('checklist/review/weekly/{id}/approve-all',        [DigitalChecklistController::class, 'weeklyApproveAllDays'])->name('checklist.review.weekly.approve-all');
    Route::patch('checklist/review/weekly/{id}/day/{iso}/approve',  [DigitalChecklistController::class, 'weeklyDayApprove'])->name('checklist.review.weekly.day.approve')->whereNumber('iso');
    Route::patch('checklist/review/weekly/{id}/day/{iso}/reject',   [DigitalChecklistController::class, 'weeklyDayReject']) ->name('checklist.review.weekly.day.reject')->whereNumber('iso');
    Route::patch('checklist/review/weekly/{id}/day/{iso}/reopen',   [DigitalChecklistController::class, 'weeklyDayReopen']) ->name('checklist.review.weekly.day.reopen')->whereNumber('iso');
    // Harian — pakai constraint UUID supaya tidak tabrakan dgn 'weekly' di atas.
    Route::get  ('checklist/review/{id}',           [DigitalChecklistController::class, 'reviewShow']) ->name('checklist.review.show')->where('id', '[0-9a-fA-F-]{36}');
    Route::patch('checklist/review/{id}/approve',   [DigitalChecklistController::class, 'approve'])    ->name('checklist.review.approve')->where('id', '[0-9a-fA-F-]{36}');
    Route::patch('checklist/review/{id}/reject',    [DigitalChecklistController::class, 'reject'])     ->name('checklist.review.reject')->where('id', '[0-9a-fA-F-]{36}');
    Route::patch('checklist/review/{id}/reopen',    [DigitalChecklistController::class, 'reopen'])     ->name('checklist.review.reopen')->where('id', '[0-9a-fA-F-]{36}');
    Route::patch('checklist/review/{id}/revoke-excuse', [DigitalChecklistController::class, 'revokeExcuse'])->name('checklist.review.excuse.revoke')->where('id', '[0-9a-fA-F-]{36}');
    // Riwayat & aksi per karyawan (drill-down dari mode "Riwayat Karyawan").
    Route::get  ('checklist/review/employee/{user}',                 [DigitalChecklistController::class, 'reviewEmployee'])        ->name('checklist.review.employee');
    Route::patch('checklist/review/employee/{user}/approve-pending', [DigitalChecklistController::class, 'approveEmployeePending'])->name('checklist.review.employee.approve-pending');

    // Permintaan "Buka Kembali" checklist lampau (approval atasan)
    Route::get  ('checklist/reopen-requests',              [DigitalChecklistController::class, 'reopenRequestsIndex'])->name('checklist.reopen.index');
    Route::patch('checklist/reopen-requests/{id}/approve', [DigitalChecklistController::class, 'reopenApprove'])     ->name('checklist.reopen.approve');
    Route::patch('checklist/reopen-requests/{id}/reject',  [DigitalChecklistController::class, 'reopenReject'])      ->name('checklist.reopen.reject');

    // Jadwal Cuci AC — Monitoring + kelola unit (Supervisor)
    Route::get   ('checklist/ac',                   [AcMaintenanceController::class, 'monitor'])       ->name('checklist.ac');
    Route::get   ('checklist/ac/unit/{id}/history', [AcMaintenanceController::class, 'unitHistory'])   ->name('checklist.ac.unit.history');
    Route::post  ('checklist/ac/unit',              [AcMaintenanceController::class, 'storeUnit'])     ->name('checklist.ac.unit.store');
    Route::put   ('checklist/ac/unit/{id}',         [AcMaintenanceController::class, 'updateUnit'])    ->name('checklist.ac.unit.update');
    Route::delete('checklist/ac/unit/{id}',         [AcMaintenanceController::class, 'destroyUnit'])   ->name('checklist.ac.unit.destroy');
    Route::delete('checklist/ac/service/{id}',      [AcMaintenanceController::class, 'destroyService'])->name('checklist.ac.service.destroy');
    Route::post  ('checklist/ac/service',              [AcMaintenanceController::class, 'storeServiceBySupervisor'])->name('checklist.ac.service.store');
    Route::post  ('checklist/ac/service/{id}/approve', [AcMaintenanceController::class, 'approveService'])->name('checklist.ac.service.approve');
    Route::post  ('checklist/ac/service/{id}/reject',  [AcMaintenanceController::class, 'rejectService']) ->name('checklist.ac.service.reject');

    // Pengisian Karyawan — Harian
    Route::get  ('my-checklist',                    [MyChecklistController::class, 'index'])           ->name('mychecklist');
    Route::get  ('my-checklist/weekly',             [MyChecklistController::class, 'weekly'])          ->name('mychecklist.weekly');
    Route::get  ('my-checklist/history',            [MyChecklistController::class, 'history'])         ->name('mychecklist.history');
    Route::get  ('my-checklist/history/{type}/{id}',[MyChecklistController::class, 'historyShow'])     ->name('mychecklist.history.show')->where('type', 'daily|weekly');
    Route::patch('my-checklist/item/{id}',          [MyChecklistController::class, 'toggleItem'])      ->name('mychecklist.item.toggle');
    Route::patch('my-checklist/item/{id}/note',     [MyChecklistController::class, 'updateNote'])      ->name('mychecklist.item.note');
    Route::patch('my-checklist/item/{id}/except',   [MyChecklistController::class, 'exceptItem'])      ->name('mychecklist.item.except');
    Route::patch('my-checklist/item/{id}/unexcept', [MyChecklistController::class, 'unexceptItem'])    ->name('mychecklist.item.unexcept');
    Route::post ('my-checklist/submit',             [MyChecklistController::class, 'submit'])          ->name('mychecklist.submit');
    Route::post ('my-checklist/reopen-self',        [MyChecklistController::class, 'reopenSelf'])      ->name('mychecklist.reopen-self');
    Route::post ('my-checklist/reopen-request',     [MyChecklistController::class, 'reopenRequestStore'])->name('mychecklist.reopen.request');
    Route::post ('my-checklist/excuse',             [MyChecklistController::class, 'excuseToday'])     ->name('mychecklist.excuse');
    Route::post ('my-checklist/unexcuse',           [MyChecklistController::class, 'unexcuseToday'])   ->name('mychecklist.unexcuse');

    // Pengisian Karyawan — Form Mingguan
    Route::get  ('my-checklist/weekform',                [MyChecklistController::class, 'weekForm'])      ->name('mychecklist.weekform');
    Route::patch('my-checklist/weekform/item/{id}',      [MyChecklistController::class, 'weekToggleItem'])->name('mychecklist.weekform.item.toggle');
    Route::patch('my-checklist/weekform/item/{id}/note', [MyChecklistController::class, 'weekUpdateNote'])->name('mychecklist.weekform.item.note');
    Route::patch('my-checklist/weekform/item/{id}/except',   [MyChecklistController::class, 'weekExceptItem'])  ->name('mychecklist.weekform.item.except');
    Route::patch('my-checklist/weekform/item/{id}/unexcept', [MyChecklistController::class, 'weekUnexceptItem'])->name('mychecklist.weekform.item.unexcept');
    Route::post ('my-checklist/weekform/item/{id}/photo', [MyChecklistController::class, 'weekUploadPhoto'])->name('mychecklist.weekform.item.photo.upload');
    Route::delete('my-checklist/weekform/item/{id}/photo',[MyChecklistController::class, 'weekDeletePhoto'])->name('mychecklist.weekform.item.photo.delete');
    // Foto before/after opsional (khusus divisi GS) — slot: before|after.
    Route::post ('my-checklist/weekform/item/{id}/ba-photo/{slot}',  [MyChecklistController::class, 'weekUploadBaPhoto'])->name('mychecklist.weekform.item.baphoto.upload');
    Route::delete('my-checklist/weekform/item/{id}/ba-photo/{slot}', [MyChecklistController::class, 'weekDeleteBaPhoto'])->name('mychecklist.weekform.item.baphoto.delete');
    // Submit per-hari (menggantikan submit mingguan sekaligus). Header otomatis
    // ter-finalize saat semua hari ber-tugas sudah dikirim/dikecualikan.
    Route::post ('my-checklist/weekform/day-submit',     [MyChecklistController::class, 'weekDaySubmit'])  ->name('mychecklist.weekform.day.submit');
    Route::post ('my-checklist/weekform/day-unsubmit',   [MyChecklistController::class, 'weekDayUnsubmit'])->name('mychecklist.weekform.day.unsubmit');
    Route::post ('my-checklist/weekform/day-excuse',     [MyChecklistController::class, 'dayExcuse'])     ->name('mychecklist.weekform.day.excuse');
    Route::post ('my-checklist/weekform/day-unexcuse',   [MyChecklistController::class, 'dayUnexcuse'])   ->name('mychecklist.weekform.day.unexcuse');

    // Jadwal Cuci AC — Input servis (GS cabang)
    Route::get   ('my-checklist/ac',                   [AcMaintenanceController::class, 'myIndex'])          ->name('mychecklist.ac');
    Route::post  ('my-checklist/ac/service',           [AcMaintenanceController::class, 'storeService'])     ->name('mychecklist.ac.service.store');
    Route::delete('my-checklist/ac/service/{id}',      [AcMaintenanceController::class, 'destroyOwnService'])->name('mychecklist.ac.service.destroy');
    // GS cabang menambah unit AC untuk cabangnya sendiri — langsung aktif.
    Route::post  ('my-checklist/ac/unit',              [AcMaintenanceController::class, 'storeOwnUnit'])     ->name('mychecklist.ac.unit.store');
    // GS cabang mengedit info unit AC cabangnya sendiri.
    Route::put   ('my-checklist/ac/unit/{id}',         [AcMaintenanceController::class, 'updateOwnUnit'])    ->name('mychecklist.ac.unit.update');
    // Riwayat per unit versi GS — WAJIB prefix my-checklist: AuthGuard cek RBAC
    // per segmen-1 URL, dan GS tidak punya akses halaman 'checklist'.
    Route::get   ('my-checklist/ac/unit/{id}/history', [AcMaintenanceController::class, 'unitHistory'])      ->name('mychecklist.ac.unit.history');

    // CRUD - Setoran (cash deposit dari pasien)
    Route::get('setoran',                         [CashDepositController::class, 'index'])->name('setoran');
    Route::post('setoran/payment',                [CashDepositController::class, 'storePayment'])->name('setoran.payment');
    Route::post('setoran/setor',                  [CashDepositController::class, 'storeSetor'])->name('setoran.setor');
    Route::delete('setoran/transaction/{id}',     [CashDepositController::class, 'destroy'])->name('setoran.destroy');


     // CRUD - Kode Referral (sebelumnya "Promo")
    Route::get('rawpromo', [PromoController::class, 'index'])->name('rawpromo');
    Route::get('rawpromo/new', [PromoController::class, 'create'])->name('rawpromo.new');
    Route::post('rawpromo/new', [PromoController::class, 'store'])->name('rawpromo.store');
    Route::get('rawpromo/{id}', [PromoController::class, 'edit'])->name('rawpromo.edit');
    Route::put('rawpromo/{id}', [PromoController::class, 'update'])->name('rawpromo.update');
    Route::delete('rawpromo/{id}', [PromoController::class, 'destroy'])->name('rawpromo.destroy');

    // CRUD - Marketing Promo (Promo umum dari Head Sales — Promo Bulanan, Sweet Surprise, dll)
    Route::get('marketing-promo',         [MarketingPromoController::class, 'index'])->name('marketingpromo');
    Route::get('marketing-promo/new',     [MarketingPromoController::class, 'create'])->name('marketingpromo.new');
    Route::post('marketing-promo/new',    [MarketingPromoController::class, 'store'])->name('marketingpromo.store');
    Route::get('marketing-promo/{id}',    [MarketingPromoController::class, 'edit'])->name('marketingpromo.edit');
    Route::put('marketing-promo/{id}',    [MarketingPromoController::class, 'update'])->name('marketingpromo.update');
    Route::delete('marketing-promo/{id}', [MarketingPromoController::class, 'destroy'])->name('marketingpromo.destroy');
    Route::patch('marketing-promo/{id}/status', [MarketingPromoController::class, 'updateStatus'])->name('marketingpromo.status');
    Route::get('api/marketing-promo/active', [MarketingPromoController::class, 'apiActiveByBranch'])
        ->middleware('throttle:60,1')->name('api.marketingpromo.active');

    // CRUD - Keyword
    Route::get('rawkeyword', [KeywordController::class, 'index'])->name('rawkeyword');
    Route::get('rawkeyword/new', [KeywordController::class, 'create'])->name('rawkeyword.new');
    Route::post('rawkeyword/new', [KeywordController::class, 'store'])->name('rawkeyword.store');
    Route::get('rawkeyword/{id}', [KeywordController::class, 'edit'])->name('rawkeyword.edit');
    Route::put('rawkeyword/{id}', [KeywordController::class, 'update'])->name('rawkeyword.update');
    Route::delete('rawkeyword/{id}', [KeywordController::class, 'destroy'])->name('rawkeyword.destroy');

    // CRUD - Customer
    Route::get('rawcustomer', [CustomerController::class, 'index'])->name('rawcustomer');
    Route::get('rawcustomer/new', [CustomerController::class, 'create'])->name('rawcustomer.new');
    Route::post('rawcustomer/new', [CustomerController::class, 'store'])->name('rawcustomer.store');
    Route::get('rawcustomer/{id}', [CustomerController::class, 'edit'])->name('rawcustomer.edit');
    Route::put('rawcustomer/{id}', [CustomerController::class, 'update'])->name('rawcustomer.update');
    Route::delete('rawcustomer/{id}', [CustomerController::class, 'destroy'])->name('rawcustomer.destroy');
    Route::post('rawcustomer/{id}/activity', [CustomerController::class, 'storeActivity'])->name('rawcustomer.activity');
    Route::get('api/customer/search', [CustomerController::class, 'search'])->name('api.customer.search');

    // CRUD - Reservasi Cabang
    Route::get('reservasicabang', [ReservasiCabangController::class, 'index'])->name('reservasicabang');

    // Report Referral
    Route::get('report-referral', [ReportReferralController::class, 'index'])->name('report.referral');

    // Commission Claim Batch (Fase 2D — admin/PJ side)
    Route::get('commission-claim',                   [CommissionClaimController::class, 'index'])->name('commissionclaim');
    Route::get('commission-claim/{id}',              [CommissionClaimController::class, 'show'])->name('commissionclaim.show');
    Route::patch('commission-claim/{id}/take',       [CommissionClaimController::class, 'takeReview'])->name('commissionclaim.take');
    Route::patch('commission-claim/{id}/approve',    [CommissionClaimController::class, 'approveBatch'])->name('commissionclaim.approve');
    Route::post('commission-claim/{id}/upload',      [CommissionClaimController::class, 'uploadProof'])->name('commissionclaim.upload');
    Route::patch('commission-claim/{id}/complete',   [CommissionClaimController::class, 'forceComplete'])->name('commissionclaim.complete');

    // Editorial Plan — Konten Pillar (Fase 3B — master pillar)
    Route::get('konten-pillar',         [KontenPillarController::class, 'index'])->name('kontenpillar');
    Route::get('konten-pillar/new',     [KontenPillarController::class, 'create'])->name('kontenpillar.new');
    Route::post('konten-pillar/new',    [KontenPillarController::class, 'store'])->name('kontenpillar.store');
    Route::get('konten-pillar/{id}',    [KontenPillarController::class, 'edit'])->name('kontenpillar.edit');
    Route::put('konten-pillar/{id}',    [KontenPillarController::class, 'update'])->name('kontenpillar.update');
    Route::delete('konten-pillar/{id}', [KontenPillarController::class, 'destroy'])->name('kontenpillar.destroy');

    // Editorial Plan — EP Konten Bulanan/Mingguan (Fase 3C)
    Route::get('ep-konten',                [EpKontenController::class, 'index'])->name('epkonten');
    Route::get('ep-konten/new',            [EpKontenController::class, 'create'])->name('epkonten.new');
    Route::post('ep-konten/new',           [EpKontenController::class, 'store'])->name('epkonten.store');
    Route::get('ep-konten/{id}',           [EpKontenController::class, 'edit'])->name('epkonten.edit');
    Route::put('ep-konten/{id}',           [EpKontenController::class, 'update'])->name('epkonten.update');
    Route::delete('ep-konten/{id}',        [EpKontenController::class, 'destroy'])->name('epkonten.destroy');
    Route::patch('ep-konten/{id}/status',  [EpKontenController::class, 'updateStatus'])->name('epkonten.status');
    Route::post('api/ep-konten/quick',     [EpKontenController::class, 'quickStore'])->name('epkonten.quick-store');

    // EP Konten — Upload foto + workflow review (Fase 3C+)
    // PENTING: semua URL diawali "ep-konten/" supaya inherit RBAC dari ep-konten
    // (AuthGuard cek segment(1) → kalau "ep-konten-image" akan di-redirect karena
    // tidak terdaftar di tabel group_access).
    Route::post('ep-konten/{id}/images',                  [EpKontenImageController::class, 'store'])->name('ep-konten.images.store');
    Route::get('ep-konten/{id}/images',                   [EpKontenImageController::class, 'index'])->name('ep-konten.images.index');
    Route::patch('ep-konten/image/{id}/review',           [EpKontenImageController::class, 'review'])->name('ep-konten-image.review');
    Route::delete('ep-konten/image/{id}',                 [EpKontenImageController::class, 'destroy'])->name('ep-konten-image.destroy');
    Route::get('ep-konten/image/{id}/download',           [EpKontenImageController::class, 'download'])->name('ep-konten-image.download');

    // Editorial Plan — Breakdown Konten (Fase 3D)
    Route::get('ep-breakdown',         [EpBreakdownController::class, 'index'])->name('epbreakdown');
    Route::get('ep-breakdown/new',     [EpBreakdownController::class, 'create'])->name('epbreakdown.new');
    Route::post('ep-breakdown/new',    [EpBreakdownController::class, 'store'])->name('epbreakdown.store');
    Route::get('ep-breakdown/{id}',    [EpBreakdownController::class, 'edit'])->name('epbreakdown.edit');
    Route::put('ep-breakdown/{id}',    [EpBreakdownController::class, 'update'])->name('epbreakdown.update');
    Route::delete('ep-breakdown/{id}', [EpBreakdownController::class, 'destroy'])->name('epbreakdown.destroy');

    // Editorial Plan — Campaign Framework (Fase 3E)
    Route::get('ep-campaign',         [EpCampaignController::class, 'index'])->name('epcampaign');
    Route::get('ep-campaign/new',     [EpCampaignController::class, 'create'])->name('epcampaign.new');
    Route::post('ep-campaign/new',    [EpCampaignController::class, 'store'])->name('epcampaign.store');
    Route::get('ep-campaign/{id}',    [EpCampaignController::class, 'edit'])->name('epcampaign.edit');
    Route::put('ep-campaign/{id}',    [EpCampaignController::class, 'update'])->name('epcampaign.update');
    Route::delete('ep-campaign/{id}', [EpCampaignController::class, 'destroy'])->name('epcampaign.destroy');

    // Content Review (KOC/KOL)
    Route::get('content-review', [\App\Http\Controllers\Set\ContentReviewController::class, 'index'])->name('content.review');
    Route::get('content-review/{id}/edit', [\App\Http\Controllers\Set\ContentReviewController::class, 'edit'])->name('content.review.edit');
    Route::put('content-review/{id}', [\App\Http\Controllers\Set\ContentReviewController::class, 'update'])->name('content.review.update');
    Route::patch('content-review/{id}/approve', [\App\Http\Controllers\Set\ContentReviewController::class, 'approve'])->name('content.review.approve');
    Route::patch('content-review/{id}/reject', [\App\Http\Controllers\Set\ContentReviewController::class, 'reject'])->name('content.review.reject');

    // CRUD - Sweet Mates (Program Rewards)
    // Dulu route ini berada DI LUAR blok AuthGuard (indentasinya menipu: masih
    // menjorok, tapi '});' penutup ada di atasnya) sehingga halaman daftar member
    // KOL/KOC beserta nilai komisinya bisa dibuka SIAPA PUN tanpa login.
    // Controllernya juga tak punya penjaga sendiri.
    Route::get('sweet-mates', [SweetMatesController::class, 'index'])->name('sweetmates');
});

Route::middleware([IsLoggedIn::class])->group(function () {
    Route::prefix('signin')->group(function () {
        Route::get('/', [AuthController::class, 'login'])->name('signin');
        Route::post('/', [AuthController::class, 'loginProcess'])->name('signin.process');
    });

    Route::get('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('forgot-password', [AuthController::class, 'forgotPasswordProcess'])->name('forgot-password.process');

    Route::get('reset-password/{token}', [AuthController::class, 'resetPassword'])->name('reset-password');
    Route::post('reset-password/{token}', [AuthController::class, 'resetPasswordProcess'])->name('reset-password.process');
});

Route::get('signout', [AuthController::class, 'logout'])->name('signout');

// Ganti password wajib (must_change_password) — DI LUAR AuthGuard biar tak kena
// RBAC (bukan menu); guard cukup cek session di controller. AuthGuard menggiring
// ke sini saat must_change; setelah ganti, balik ke landing normal.
Route::get('ganti-password',  [AuthController::class, 'gantiPassword'])->name('ganti.password');
Route::post('ganti-password', [AuthController::class, 'gantiPasswordProcess'])->name('ganti.password.process');