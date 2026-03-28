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
use App\Http\Controllers\Crud\AffiliateController; //new
use App\Http\Controllers\Crud\PromoController; //new
use App\Http\Controllers\Crud\KeywordController; //new  



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    $services = \App\Models\RawDocService::orderBy('name')->get();
    $branches = \App\Models\RawBranch::orderBy('name')->get();
    $branchCount = $branches->count();
    return view('landing', compact('services', 'branches', 'branchCount'));
})->name('home');

Route::get('/reservasi', function () {
    $branches = \App\Models\RawBranch::orderBy('name')->get();
    return view('reservation-form', compact('branches'));
})->name('landing.reservation');

Route::post('/reservasi', function () {
    request()->validate([
    'name'             => 'required|max:255',
    'phone'            => 'required|max:20',
    'jenis_kelamin'    => 'required|in:L,P', //new
    'tanggal_lahir'    => 'required|date|before:today', //new
    'email'            => 'required|email|max:100',  //new
    'raw_doctor_id'    => 'required|exists:raw_doctor,id',
    'raw_branch_id'    => 'required|exists:raw_branch,id',
    'reservation_date' => 'required|date|after_or_equal:today',
    'reservation_time' => 'required',
    'services'         => 'required|array|min:1',
    'promo_id' => 'nullable|exists:raw_promo,id', //new
]);

    // Calculate total duration from selected services
    $totalDuration = 0;
    $serviceRecords = [];
    foreach (request('services') as $serviceId) {
        $docService = \App\Models\RawDocService::find($serviceId);
        if ($docService) {
            $totalDuration += $docService->duration_minutes;
            $serviceRecords[] = $docService;
        }
    }

    // Compute end_hour from start + total duration
    $startHour = \Carbon\Carbon::parse(request('reservation_time'));
    $endHour = $startHour->copy()->addMinutes($totalDuration);

    // Find or create customer
    $customer = \App\Models\RawCustomer::firstOrCreate(
    ['phone' => request('phone')],
    [
        'name'           => request('name'),
        'email'          => request('email'),
        'jenis_kelamin'  => request('jenis_kelamin'), //new
        'tanggal_lahir'  => request('tanggal_lahir'), //new
        'tanggal_daftar' => now()->toDateString(),
        'source_app'     => 'website',
        'channel'        => 'landing-page',
    ]
);

    // Create reservation with start and end hour
    $reservation = \App\Models\RawCusReservation::create([
        'raw_customer_id' => $customer->id,
        'raw_doctor_id'   => request('raw_doctor_id'),
        'raw_branch_id'   => request('raw_branch_id'),
        'raw_promo_id'    => request('promo_id') ?: null,  //new
        'date'            => request('reservation_date'),
        'start_hour'      => $startHour->format('H:i'),
        'end_hour'        => $endHour->format('H:i'),
        'status'          => 'pending',
        'is_waiting'      => false,
        'created_at'      => now(),
    ]);

    // Attach selected services
    foreach ($serviceRecords as $docService) {
        \App\Models\RawCusResService::create([
            'raw_cus_reservation_id' => $reservation->id,
            'raw_doctor_id' => request('raw_doctor_id'),
            'raw_doc_service_id' => $docService->id,
        ]);
    }

        // Insert journey: status reservasi masuk (lewat raw_customer)
        \Illuminate\Support\Facades\DB::table('raw_cus_journey')->insert([
            'id'              => \Illuminate\Support\Str::uuid()->toString(),
            'raw_customer_id' => $customer->id,
            'journey_label'   => 'Stat_Rsv',
            'created_at'      => now(),
        ]);

        // Cek nomor ini juga ada di raw_contact
        // Kalau ada, insert Stat_Rsv juga dari sisi contact
        $contact = \Illuminate\Support\Facades\DB::table('raw_contact')
            ->where('phone', request('phone'))
            ->first();

        if ($contact) {
            // Stat_New & Stat_Leads mungkin udah ada dari webhook
            // Di sini kita tambahin Stat_Rsv dari sisi contact
            \Illuminate\Support\Facades\DB::table('raw_cus_journey')->insert([
                'id'             => \Illuminate\Support\Str::uuid()->toString(),
                'raw_contact_id' => $contact->id,
                'journey_label'  => 'Stat_Rsv',
                'created_at'     => now(),
            ]);
        }
        // Buat komisi kalo ada promo sama koc/afiiliate
        if (request('promo_id')) {
            $promo = \App\Models\RawPromo::with('affiliate')->find(request('promo_id'));
            if ($promo && $promo->affiliate) {
                // Hitung total harga dari services
                $totalHarga = 0;
                foreach ($serviceRecords as $svc) {
                    $totalHarga += $svc->price ?? 0;
                }

                // Hitung komisi dari commis    sion_rate affiliate
                $commissionAmount = round($totalHarga * ($promo->affiliate->commission_rate / 100), 2);

                \Illuminate\Support\Facades\DB::table('raw_commission')->insert([
                    'id'                      => \Illuminate\Support\Str::uuid()->toString(),
                    'raw_affiliate_id'        => $promo->affiliate->id,
                    'raw_cus_reservation_id'  => $reservation->id,
                    'amount'                  => $commissionAmount,
                    'status'                  => 'pending',
                    'created_at'              => now(),
                ]);
            }
        }

    return redirect('/reservasi/riwayat?phone=' . urlencode(request('phone')))
        ->with('success', 'Reservasi berhasil dibuat! Tim kami akan menghubungi kamu untuk konfirmasi.');
})->name('landing.reservation.store');

Route::get('/reservasi/riwayat', function () {
    $phone = request('phone');
    $reservations = collect();

    if ($phone) {
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
Route::prefix('api/reservation')->group(function () {
    $ctrl = \App\Http\Controllers\Api\ReservationApiController::class;
    Route::get('services', [$ctrl, 'getServices']);
    Route::get('doctors-by-service/{branchId}/{serviceId}', [$ctrl, 'getDoctorsByService']); // baru
    Route::get('doctors/{branchId}', [$ctrl, 'getDoctorsByBranch']);
    Route::get('branches/{doctorId}', [$ctrl, 'getBranchesByDoctor']); // cabang by dokter
    Route::get('services-by-doctor/{doctorId}', [$ctrl, 'getServicesByDoctor']); // layanan by dokter
    Route::get('dates/{branchId}/{doctorId}', [$ctrl, 'getAvailableDates']);
    Route::get('slots/{branchId}/{doctorId}/{date}', [$ctrl, 'getTimeSlots']);
});

//new
Route::get('api/promo/validate', [PromoController::class, 'validate_promo'])->name('api.promo.validate');


Route::middleware([AuthGuard::class])->group(function () {

    // Employee Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/reservasi', [DashboardController::class, 'reservasi'])->name('dashboard.reservasi');
        Route::get('/waiting-list', [DashboardController::class, 'waitingList'])->name('dashboard.waiting-list');
        Route::get('/berlangsung', [DashboardController::class, 'berlangsung'])->name('dashboard.berlangsung');
        Route::get('/menunggu', [DashboardController::class, 'menungguKonfirmasi'])->name('dashboard.menunggu');
        Route::patch('/reservasi/{id}/status', [DashboardController::class, 'updateStatus'])->name('dashboard.update-status');
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

    // CRUD - Doctor Schedule (nested under Doctor)
    Route::post('rawdoctor/{doctorId}/schedule', [DoctorController::class, 'scheduleStore'])->name('rawdoctor.schedule.store');
    Route::put('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleUpdate'])->name('rawdoctor.schedule.update');
    Route::delete('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleDestroy'])->name('rawdoctor.schedule.destroy');
    Route::post('rawdoctor/{doctorId}/services', [DoctorController::class, 'servicesUpdate'])->name('rawdoctor.services.update'); //new


    // CRUD - Reservation
    Route::get('rawreservation', [ReservationController::class, 'index'])->name('rawreservation');
    Route::get('rawreservation/new', [ReservationController::class, 'create'])->name('rawreservation.new');
    Route::post('rawreservation/new', [ReservationController::class, 'store'])->name('rawreservation.store');
    Route::get('rawreservation/{id}', [ReservationController::class, 'edit'])->name('rawreservation.edit');
    Route::put('rawreservation/{id}', [ReservationController::class, 'update'])->name('rawreservation.update');
    Route::delete('rawreservation/{id}', [ReservationController::class, 'destroy'])->name('rawreservation.destroy');
    Route::patch('rawreservation/{id}/inline', [ReservationController::class, 'updateInline'])->name('rawreservation.inline'); //new
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/approve', [ReservationController::class, 'commissionApprove'])->name('rawreservation.commission.approve');//new
    Route::patch('rawreservation/{reservationId}/commission/{commissionId}/pay', [ReservationController::class, 'commissionPay'])->name('rawreservation.commission.pay'); //new

    // CRUD - Reservation Service (nested under Reservation)
    Route::post('rawreservation/{reservationId}/service', [ReservationController::class, 'serviceStore'])->name('rawreservation.service.store');
    Route::delete('rawreservation/{reservationId}/service/{serviceId}', [ReservationController::class, 'serviceDestroy'])->name('rawreservation.service.destroy');

    // CRUD - Doctor Service
    Route::get('rawdocservice', [DocServiceController::class, 'index'])->name('rawdocservice');
    Route::get('rawdocservice/new', [DocServiceController::class, 'create'])->name('rawdocservice.new');
    Route::post('rawdocservice/new', [DocServiceController::class, 'store'])->name('rawdocservice.store');
    Route::get('rawdocservice/{id}', [DocServiceController::class, 'edit'])->name('rawdocservice.edit');
    Route::put('rawdocservice/{id}', [DocServiceController::class, 'update'])->name('rawdocservice.update');
    Route::delete('rawdocservice/{id}', [DocServiceController::class, 'destroy'])->name('rawdocservice.destroy');

    // routes sub-detail perawatan
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

    // CRUD - Affiliate new
    Route::get('rawaffiliate', [AffiliateController::class, 'index'])->name('rawaffiliate');
    Route::get('rawaffiliate/new', [AffiliateController::class, 'create'])->name('rawaffiliate.new');
    Route::post('rawaffiliate/new', [AffiliateController::class, 'store'])->name('rawaffiliate.store');
    Route::get('rawaffiliate/{id}', [AffiliateController::class, 'edit'])->name('rawaffiliate.edit');
    Route::put('rawaffiliate/{id}', [AffiliateController::class, 'update'])->name('rawaffiliate.update');
    Route::delete('rawaffiliate/{id}', [AffiliateController::class, 'destroy'])->name('rawaffiliate.destroy');

    // CRUD - Promo new
    Route::get('rawpromo', [PromoController::class, 'index'])->name('rawpromo');
    Route::get('rawpromo/new', [PromoController::class, 'create'])->name('rawpromo.new');
    Route::post('rawpromo/new', [PromoController::class, 'store'])->name('rawpromo.store');
    Route::get('rawpromo/{id}', [PromoController::class, 'edit'])->name('rawpromo.edit');
    Route::put('rawpromo/{id}', [PromoController::class, 'update'])->name('rawpromo.update');
    Route::delete('rawpromo/{id}', [PromoController::class, 'destroy'])->name('rawpromo.destroy');

    // CRUD - Keyword //new
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

    //CRUD - reservasicabang
        Route::get('reservasicabang', [ReservasiCabangController::class, 'index'])->name('reservasicabang');

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