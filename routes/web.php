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
use App\Http\Controllers\Crud\ReservasiCabangController;
use App\Http\Controllers\DashboardController;
use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\IsLoggedIn;
use App\Http\Controllers\Crud\AffiliateController;
use App\Http\Controllers\Crud\PromoController;
use App\Http\Controllers\Crud\KeywordController;
use App\Http\Controllers\Crud\ReportReferralController;


Route::get('/', function () {
    $services    = \App\Models\RawDocService::orderBy('name')->get();
    $branches    = \App\Models\RawBranch::orderBy('name')->get();
    $branchCount = $branches->count();
    return view('landing', compact('services', 'branches', 'branchCount'));
})->name('home');

Route::get('/reservasi', function () {
    $branches = \App\Models\RawBranch::orderBy('name')->get();
    return view('reservation-form', compact('branches'));
})->name('landing.reservation');

Route::post('/reservasi', [\App\Http\Controllers\LandingReservationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('landing.reservation.store');

// Affiliate Portal
Route::prefix('affiliate')->group(function () {
    $portal = \App\Http\Controllers\AffiliatePortalController::class;

    // Public
    Route::get('login',  [$portal, 'login'])->name('affiliate.login');
    Route::post('login', [$portal, 'loginProcess'])->name('affiliate.login.process');
    Route::get('register',  [$portal, 'register'])->name('affiliate.register');
    Route::post('register', [$portal, 'registerProcess'])->name('affiliate.register.process');
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
    Route::get('check-customer', [$ctrl, 'checkCustomerByPhone']);
    Route::get('check-new', [$ctrl, 'checkNew']);
});

Route::get('api/promo/validate', [PromoController::class, 'validate_promo'])->middleware('throttle:30,1')->name('api.promo.validate');

Route::middleware([AuthGuard::class])->group(function () {

    // Complaint (Sales → kirim + lihat miliknya; Super Admin/Admin General → lihat semua + kelola status)
    Route::prefix('complaint')->group(function () {
        $complaint = \App\Http\Controllers\ComplaintController::class;
        Route::get('/',               [$complaint, 'index'])->name('complaint.index');
        Route::post('/',              [$complaint, 'store'])->name('complaint.store');
        Route::patch('{id}/advance',  [$complaint, 'advanceStatus'])->name('complaint.advance');
        Route::patch('{id}/reply',    [$complaint, 'reply'])->name('complaint.reply');
        Route::delete('{id}',         [$complaint, 'destroy'])->name('complaint.destroy');
    });

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

    // Export
    Route::prefix('export')->group(function () {
        Route::get('reservasi', [\App\Http\Controllers\ExportController::class, 'reservations'])->name('export.reservasi');
        Route::get('komisi', [\App\Http\Controllers\ExportController::class, 'commissions'])->name('export.komisi');
    });

    // Access
    Route::get('setaccess', [Access::class, 'index'])->name('setaccess');
    Route::get('setaccess/new', [Access::class, 'create'])->name('setaccess.new');
    Route::post('setaccess/new', [Access::class, 'store'])->name('setaccess.store');
    Route::get('setaccess/{id}', [Access::class, 'edit'])->name('setaccess.edit');
    Route::put('setaccess/{id}', [Access::class, 'update'])->name('setaccess.update');
    Route::delete('setaccess/{id}', [Access::class, 'destroy'])->name('setaccess.destroy');

    // User Management
    Route::get('setusermanagement', [UserManagement::class, 'index'])->name('setusermanagement');
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

    Route::post('rawdoctor/{doctorId}/schedule', [DoctorController::class, 'scheduleStore'])->name('rawdoctor.schedule.store');
    Route::put('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleUpdate'])->name('rawdoctor.schedule.update');
    Route::delete('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleDestroy'])->name('rawdoctor.schedule.destroy');
    Route::get('rawdoctor/{doctorId}/schedules-json', [DoctorController::class, 'schedulesJson'])->name('rawdoctor.schedules.json');
    Route::patch('rawdoctor/{doctorId}/schedule/{scheduleId}/ajax', [DoctorController::class, 'scheduleUpdateAjax'])->name('rawdoctor.schedule.ajax');
    Route::post('rawdoctor/{doctorId}/services', [DoctorController::class, 'servicesUpdate'])->name('rawdoctor.services.update');
    Route::post('rawdoctor/{doctorId}/leave', [DoctorController::class, 'leaveStore'])->name('rawdoctor.leave.store');
    Route::delete('rawdoctor/{doctorId}/leave/{leaveId}', [DoctorController::class, 'leaveDestroy'])->name('rawdoctor.leave.destroy');

    // CRUD - Reservation
    Route::get('rawreservation', [ReservationController::class, 'index'])->name('rawreservation');
    Route::get('rawreservation/new', [ReservationController::class, 'create'])->name('rawreservation.new');
    Route::post('rawreservation/new', [ReservationController::class, 'store'])->name('rawreservation.store');
    Route::get('rawreservation/{id}', [ReservationController::class, 'edit'])->name('rawreservation.edit');
    Route::put('rawreservation/{id}', [ReservationController::class, 'update'])->name('rawreservation.update');
    Route::delete('rawreservation/{id}', [ReservationController::class, 'destroy'])->name('rawreservation.destroy');
    Route::patch('rawreservation/{id}/inline', [ReservationController::class, 'updateInline'])->name('rawreservation.inline');
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/approve', [ReservationController::class, 'commissionApprove'])->name('rawreservation.commission.approve');
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/pay', [ReservationController::class, 'commissionPay'])->name('rawreservation.commission.pay');
    Route::patch('rawreservation/{id}/klaim', [ReservationController::class, 'klaimPromo'])->name('rawreservation.klaim');
    Route::post('rawreservation/{reservationId}/service', [ReservationController::class, 'serviceStore'])->name('rawreservation.service.store');
    Route::delete('rawreservation/{reservationId}/service/{serviceId}', [ReservationController::class, 'serviceDestroy'])->name('rawreservation.service.destroy');

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
    Route::get('rawaffiliate/{id}', [AffiliateController::class, 'edit'])->name('rawaffiliate.edit');
    Route::put('rawaffiliate/{id}', [AffiliateController::class, 'update'])->name('rawaffiliate.update');
    Route::delete('rawaffiliate/{id}', [AffiliateController::class, 'destroy'])->name('rawaffiliate.destroy');

    // CRUD - Promo
    Route::get('rawpromo', [PromoController::class, 'index'])->name('rawpromo');
    Route::get('rawpromo/new', [PromoController::class, 'create'])->name('rawpromo.new');
    Route::post('rawpromo/new', [PromoController::class, 'store'])->name('rawpromo.store');
    Route::get('rawpromo/{id}', [PromoController::class, 'edit'])->name('rawpromo.edit');
    Route::put('rawpromo/{id}', [PromoController::class, 'update'])->name('rawpromo.update');
    Route::delete('rawpromo/{id}', [PromoController::class, 'destroy'])->name('rawpromo.destroy');

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
    Route::get('api/customer/search', [CustomerController::class, 'search'])->name('api.customer.search');

    // CRUD - Reservasi Cabang
    Route::get('reservasicabang', [ReservasiCabangController::class, 'index'])->name('reservasicabang');

    // Report Referral
    Route::get('report-referral', [ReportReferralController::class, 'index'])->name('report.referral');

    // Content Review (KOC/KOL)
    Route::get('content-review', [\App\Http\Controllers\Set\ContentReviewController::class, 'index'])->name('content.review');
    Route::get('content-review/{id}/edit', [\App\Http\Controllers\Set\ContentReviewController::class, 'edit'])->name('content.review.edit');
    Route::put('content-review/{id}', [\App\Http\Controllers\Set\ContentReviewController::class, 'update'])->name('content.review.update');
    Route::patch('content-review/{id}/approve', [\App\Http\Controllers\Set\ContentReviewController::class, 'approve'])->name('content.review.approve');
    Route::patch('content-review/{id}/reject', [\App\Http\Controllers\Set\ContentReviewController::class, 'reject'])->name('content.review.reject');
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