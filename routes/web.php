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

Route::post('/reservasi', function () {


    $patientType = request('patient_type'); // 'baru' atau 'lama'
    $isLainnya   = empty(request('services')) && !empty(request('lainnya_text'));

    // Validasi dasar (selalu wajib)
    $rules = [
        'raw_doctor_id'    => 'required|exists:raw_doctor,id',
        'raw_branch_id'    => 'required|exists:raw_branch,id',
        'reservation_date' => 'required|date|after_or_equal:today',
        'reservation_time' => 'required',
        'promo_id'         => 'nullable|exists:raw_promo,id',
        'patient_type'     => 'required|in:baru,lama',
    ];

    // Validasi perawatan: salah satu harus ada
    if ($isLainnya) {
        // Kategori Lainnya: services boleh kosong, tapi lainnya_text wajib
        $rules['lainnya_text'] = 'required|string|max:200';
        $rules['services']     = 'nullable|array';
    } else {
        // Kategori normal: services wajib ada
        $rules['services']     = 'required|array|min:1';
        $rules['lainnya_text'] = 'nullable|string|max:200';
    }

    // Validasi data diri: tergantung tipe pasien
    if ($patientType === 'lama') {
        // Pasien lama: cukup existing_customer_id
        $rules['existing_customer_id'] = 'required|exists:raw_customer,id';
    } else {
        // Pasien baru: semua field wajib diisi
        $rules['name']          = 'required|max:255';
        $rules['phone']         = 'required|max:20';
        $rules['email']         = 'required|email|max:100';
        $rules['jenis_kelamin'] = 'required|in:L,P';
        $rules['tanggal_lahir'] = 'required|date|before:today';
    }

    // Jalankan validasi
    request()->validate($rules, [
        // Pesan error yang lebih jelas
        'services.required'            => 'Pilih perawatan terlebih dahulu.',
        'lainnya_text.required'        => 'Ceritakan keluhan kamu terlebih dahulu.',
        'existing_customer_id.required'=> 'Data pasien lama tidak ditemukan. Coba cek nomor HP kamu.',
        'name.required'                => 'Nama lengkap wajib diisi.',
        'phone.required'               => 'Nomor WhatsApp wajib diisi.',
        'email.required'               => 'Email wajib diisi.',
        'jenis_kelamin.required'       => 'Jenis kelamin wajib dipilih.',
        'tanggal_lahir.required'       => 'Tanggal lahir wajib diisi.',
    ]);

    // ================================================================
    // AMBIL DATA CUSTOMER
    // ================================================================
    if ($patientType === 'lama') {
        // Pasien lama: ambil dari DB langsung
        $customer = \App\Models\RawCustomer::findOrFail(request('existing_customer_id'));
    } else {
        // Pasien baru: cari atau buat baru berdasarkan nomor HP
        $customer = \App\Models\RawCustomer::firstOrCreate(
            ['phone' => request('phone')],
            [
                'name'           => request('name'),
                'email'          => request('email'),
                'jenis_kelamin'  => request('jenis_kelamin'),
                'tanggal_lahir'  => request('tanggal_lahir'),
                'tanggal_daftar' => now()->toDateString(),
                'source_app'     => 'website',
                'channel'        => 'landing-page',
            ]
        );
    }

    // ================================================================
    // HITUNG DURASI & SERVICE RECORDS
    // ================================================================
    $totalDuration = 0;
    $serviceRecords = [];

    if ($isLainnya) {
        // Kategori lainnya: tidak ada service spesifik
        // Gunakan durasi default 30 menit
        $totalDuration = 30;
    } else {
        foreach (request('services') as $serviceId) {
            $docService = \App\Models\RawDocService::find($serviceId);
            if ($docService) {
                $totalDuration += $docService->duration_minutes;
                $serviceRecords[] = $docService;
            }
        }
    }

    // Hitung jam selesai
    $startHour = \Carbon\Carbon::parse(request('reservation_time'));
    $endHour   = $startHour->copy()->addMinutes($totalDuration ?: 30);

    // ================================================================
    // BUAT RESERVASI
    // ================================================================
    $reservation = \App\Models\RawCusReservation::create([
        'raw_customer_id' => $customer->id,
        'raw_doctor_id'   => request('raw_doctor_id'),
        'raw_branch_id'   => request('raw_branch_id'),
        'raw_promo_id'    => request('promo_id') ?: null,
        'date'            => request('reservation_date'),
        'start_hour'      => $startHour->format('H:i'),
        'end_hour'        => $endHour->format('H:i'),
        'status'          => 'pending',
        'is_waiting'      => false,
        'lainnya_text'    => $isLainnya ? request('lainnya_text') : null, 
        'created_at'      => now(),
    ]);

    // ================================================================
    // ATTACH SERVICES
    // ================================================================
    if ($isLainnya) {
        // Untuk kategori lainnya: simpan sebagai catatan
        // Cari service "Lainnya" di DB kalau ada, atau skip
        $lainnyaService = \App\Models\RawDocService::where('category', 'lainnya')->first();
        if ($lainnyaService) {
            \App\Models\RawCusResService::create([
                'raw_cus_reservation_id' => $reservation->id,
                'raw_doctor_id'          => request('raw_doctor_id'),
                'raw_doc_service_id'     => $lainnyaService->id,
                // Simpan teks keluhan jika ada field-nya
            ]);
        }
    } else {
        foreach ($serviceRecords as $docService) {
            \App\Models\RawCusResService::create([
                'raw_cus_reservation_id' => $reservation->id,
                'raw_doctor_id'          => request('raw_doctor_id'),
                'raw_doc_service_id'     => $docService->id,
            ]);
        }
    }

    // ================================================================
    // INSERT JOURNEY
    // ================================================================
    \Illuminate\Support\Facades\DB::table('raw_cus_journey')->insert([
        'id'              => \Illuminate\Support\Str::uuid()->toString(),
        'raw_customer_id' => $customer->id,
        'journey_label'   => 'Stat_Rsv',
        'created_at'      => now(),
    ]);

    $contact = \Illuminate\Support\Facades\DB::table('raw_contact')
        ->where('phone', $customer->phone)
        ->first();

    if ($contact) {
        \Illuminate\Support\Facades\DB::table('raw_cus_journey')->insert([
            'id'             => \Illuminate\Support\Str::uuid()->toString(),
            'raw_contact_id' => $contact->id,
            'journey_label'  => 'Stat_Rsv',
            'created_at'     => now(),
        ]);
    }

    // ================================================================
    // BUAT KOMISI (kalau ada promo)
    // ================================================================
    if (request('promo_id')) {
        $promo = \App\Models\RawPromo::with('affiliate')->find(request('promo_id'));
        if ($promo && $promo->affiliate) {
            $totalHarga = 0;
            foreach ($serviceRecords as $svc) {
                $totalHarga += $svc->price ?? 0;
            }

            // Pastikan kita mengambil angka dari single objek, bukan dari Collection
            $affiliate = $promo->affiliate instanceof \Illuminate\Support\Collection ? $promo->affiliate->first() : $promo->affiliate;
            $commissionRate = $affiliate ? ($affiliate->commission_rate ?? 0) : 0;
            $commissionAmount = round($totalHarga * ($commissionRate / 100), 2);

            \Illuminate\Support\Facades\DB::table('raw_commission')->insert([
                'id'                     => \Illuminate\Support\Str::uuid()->toString(),
                'raw_affiliate_id'       => $promo->affiliate->id,
                'raw_cus_reservation_id' => $reservation->id,
                'amount'                 => $commissionAmount,
                'status'                 => 'pending',
                'created_at'             => now(),
            ]);
        }
    }

    return redirect('/reservasi/riwayat?phone=' . urlencode($customer->phone))
        ->with('success', 'Reservasi berhasil dibuat! Tim kami akan menghubungi kamu untuk konfirmasi.');

})->name('landing.reservation.store');

Route::get('/reservasi/riwayat', function () {
    $phone        = request('phone');
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
    Route::get('doctors-by-service/{branchId}/{serviceId}', [$ctrl, 'getDoctorsByService']);
    Route::get('doctors/{branchId}', [$ctrl, 'getDoctorsByBranch']);
    Route::get('branches/{doctorId}', [$ctrl, 'getBranchesByDoctor']);
    Route::get('services-by-doctor/{doctorId}', [$ctrl, 'getServicesByDoctor']);
    Route::get('dates/{branchId}/{doctorId}', [$ctrl, 'getAvailableDates']);
    Route::get('slots/{branchId}/{doctorId}/{date}', [$ctrl, 'getTimeSlots']);
    Route::get('check-customer', [$ctrl, 'checkCustomerByPhone']);
    Route::get('check-new', [$ctrl, 'checkNew']);
});

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

    Route::post('rawdoctor/{doctorId}/schedule', [DoctorController::class, 'scheduleStore'])->name('rawdoctor.schedule.store');
    Route::put('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleUpdate'])->name('rawdoctor.schedule.update');
    Route::delete('rawdoctor/{doctorId}/schedule/{scheduleId}', [DoctorController::class, 'scheduleDestroy'])->name('rawdoctor.schedule.destroy');
    Route::post('rawdoctor/{doctorId}/services', [DoctorController::class, 'servicesUpdate'])->name('rawdoctor.services.update');

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

    // CRUD - Reservasi Cabang
    Route::get('reservasicabang', [ReservasiCabangController::class, 'index'])->name('reservasicabang');
});
    // Report Referral
    Route::get('report-referral', [ReportReferralController::class, 'index'])->name('report.referral');

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