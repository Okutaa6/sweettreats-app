<?php

namespace App\Http\Controllers;

use App\Models\RawCustomer;
use App\Models\RawCusReservation;
use App\Models\RawCusResService;
use App\Models\RawDocService;
use App\Models\RawPromo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\AdminNotification;
use Illuminate\Support\Str;

class LandingReservationController extends Controller
{
    public function store()
    {
        $patientType = request('patient_type');
        $isLainnya   = empty(request('services')) && !empty(request('lainnya_text'));

        $rules = [
            'raw_doctor_id'    => 'required|exists:raw_doctor,id',
            'raw_branch_id'    => 'required|exists:raw_branch,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'reservation_time' => 'required',
            'promo_id'         => 'nullable|exists:raw_promo,id',
            'patient_type'     => 'required|in:baru,lama',
        ];

        if ($isLainnya) {
            $rules['lainnya_text'] = 'required|string|max:200';
            $rules['services']     = 'nullable|array';
        } else {
            $rules['services']     = 'required|array|min:1';
            $rules['lainnya_text'] = 'nullable|string|max:200';
        }

        if ($patientType === 'lama') {
            $rules['existing_customer_id'] = 'required|exists:raw_customer,id';
        } else {
            $rules['name']          = 'required|max:255';
            $rules['phone']         = 'required|regex:/^0[0-9]{9,14}$/';
            $rules['email']         = 'required|email|max:100';
            $rules['jenis_kelamin'] = 'required|in:L,P';
            $rules['tanggal_lahir'] = 'required|date|before:today';
        }

        request()->validate($rules, [
            'services.required'            => 'Pilih perawatan terlebih dahulu.',
            'lainnya_text.required'        => 'Ceritakan keluhan kamu terlebih dahulu.',
            'existing_customer_id.required'=> 'Data pasien lama tidak ditemukan. Coba cek nomor HP kamu.',
            'name.required'                => 'Nama lengkap wajib diisi.',
            'phone.required'               => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'                  => 'Nomor tidak valid. Harus diawali 0 dan minimal 10 digit.',
            'email.required'               => 'Email wajib diisi.',
            'jenis_kelamin.required'       => 'Jenis kelamin wajib dipilih.',
            'tanggal_lahir.required'       => 'Tanggal lahir wajib diisi.',
        ]);

        $result = DB::transaction(function () use ($patientType, $isLainnya) {
            // Get or create customer
            if ($patientType === 'lama') {
                $customer = RawCustomer::findOrFail(request('existing_customer_id'));
            } else {
                $customer = RawCustomer::firstOrCreate(
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

            // Calculate duration & collect service records
            $totalDuration = 0;
            $serviceRecords = [];

            if ($isLainnya) {
                $totalDuration = 30;
            } else {
                foreach (request('services') as $serviceId) {
                    $docService = RawDocService::find($serviceId);
                    if ($docService) {
                        $totalDuration += $docService->duration_minutes;
                        $serviceRecords[] = $docService;
                    }
                }
            }

            $startHour = Carbon::parse(request('reservation_time'));
            $endHour   = $startHour->copy()->addMinutes($totalDuration ?: 30);

            // Create reservation
            $reservation = RawCusReservation::create([
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

            // Attach services
            if ($isLainnya) {
                $lainnyaService = RawDocService::where('category', 'lainnya')->first();
                if ($lainnyaService) {
                    RawCusResService::create([
                        'raw_cus_reservation_id' => $reservation->id,
                        'raw_doctor_id'          => request('raw_doctor_id'),
                        'raw_doc_service_id'     => $lainnyaService->id,
                    ]);
                }
            } else {
                foreach ($serviceRecords as $docService) {
                    RawCusResService::create([
                        'raw_cus_reservation_id' => $reservation->id,
                        'raw_doctor_id'          => request('raw_doctor_id'),
                        'raw_doc_service_id'     => $docService->id,
                    ]);
                }
            }

            // Insert journey
            DB::table('raw_cus_journey')->insert([
                'id'              => Str::uuid()->toString(),
                'raw_customer_id' => $customer->id,
                'journey_label'   => 'Stat_Rsv',
                'created_at'      => now(),
            ]);

            $contact = DB::table('raw_contact')
                ->where('phone', $customer->phone)
                ->first();

            if ($contact) {
                DB::table('raw_cus_journey')->insert([
                    'id'             => Str::uuid()->toString(),
                    'raw_contact_id' => $contact->id,
                    'journey_label'  => 'Stat_Rsv',
                    'created_at'     => now(),
                ]);
            }

            // Create commission if promo used
            if (request('promo_id')) {
                $promo = RawPromo::with('affiliate')->find(request('promo_id'));
                if ($promo && $promo->affiliate) {
                    $totalHarga = 0;
                    foreach ($serviceRecords as $svc) {
                        $totalHarga += $svc->price ?? 0;
                    }

                    $affiliate = $promo->affiliate instanceof \Illuminate\Support\Collection
                        ? $promo->affiliate->first()
                        : $promo->affiliate;
                    $commissionRate = $affiliate ? ($affiliate->commission_rate ?? 0) : 0;
                    $commissionAmount = round($totalHarga * ($commissionRate / 100), 2);

                    DB::table('raw_commission')->insert([
                        'id'                     => Str::uuid()->toString(),
                        'raw_affiliate_id'       => $promo->affiliate->id,
                        'raw_cus_reservation_id' => $reservation->id,
                        'amount'                 => $commissionAmount,
                        'status'                 => 'pending',
                        'created_at'             => now(),
                    ]);
                }
            }

            return ['customer' => $customer, 'reservation' => $reservation];
        });

        $customer    = $result['customer'];
        $reservation = $result['reservation'];

        // Push admin notification for new reservation
        AdminNotification::notify(
            'reservation',
            'Reservasi Baru: ' . $customer->name,
            'Reservasi baru dari ' . $customer->name . ' (' . $customer->phone . ')',
            url('rawreservation?reservation_id=' . $reservation->id),
            request('raw_branch_id')
        );

        return redirect('/reservasi/riwayat?phone=' . urlencode($customer->phone))
            ->with('success', 'Reservasi berhasil dibuat! Tim kami akan menghubungi kamu untuk konfirmasi.');
    }
}
