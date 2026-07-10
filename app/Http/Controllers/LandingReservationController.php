<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Models\RawCustomer;
use App\Models\RawCusReservation;
use App\Models\RawCusResService;
use App\Models\RawDocService;
use App\Models\RawPromo;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminNotification;
use Illuminate\Support\Str;

class LandingReservationController extends Controller
{
    use ResolvesPatientContact;
    use ResolvesDoctorAvailability;

    public function store()
    {
        // ── Validasi global ──────────────────────────────────────────────
        $rules = [
            'raw_branch_id'    => 'required|exists:raw_branch,id',
            'reservation_date' => 'required|date|after_or_equal:today',
            'default_start'    => 'required',
            'phone'            => 'required|regex:/^0[0-9]{9,14}$/',
            'promo_id'         => 'nullable|exists:raw_promo,id',
            'patients'         => 'required|array|min:1',
        ];

        $validator = Validator::make(request()->all(), $rules, [
            'phone.required'    => 'Nomor WhatsApp wajib diisi.',
            'phone.regex'       => 'Nomor tidak valid. Harus diawali 0 dan minimal 10 digit.',
            'patients.required' => 'Pilih minimal 1 pasien.',
            'patients.min'      => 'Pilih minimal 1 pasien.',
        ]);

        // ── Validasi tiap pasien (per item, supaya pesan jelas) ──────────
        $validator->after(function ($v) {
            $phoneNorm = Phone::normalize(request('phone'));
            $patients  = request('patients', []);

            if (! is_array($patients) || count($patients) === 0) {
                return;
            }

            foreach ($patients as $i => $p) {
                $label = 'Pasien #' . ($i + 1);
                $type  = $p['type'] ?? null;

                if (empty($p['raw_doctor_id'])) {
                    $v->errors()->add("patients.$i", "$label: dokter belum dipilih.");
                }

                $hasServices = ! empty($p['services']) && is_array($p['services']);
                $hasLainnya  = ! empty($p['lainnya_text']);
                if (! $hasServices && ! $hasLainnya) {
                    $v->errors()->add("patients.$i", "$label: pilih perawatan atau isi keluhan.");
                }

                if ($type === 'existing') {
                    $cust = RawCustomer::find($p['existing_customer_id'] ?? null);
                    if (! $cust) {
                        $v->errors()->add("patients.$i", "$label: data pasien tidak ditemukan.");
                    } elseif (
                        $phoneNorm === null
                        || Phone::normalize($cust->phone) !== $phoneNorm
                    ) {
                        // Anti-injeksi: pasien existing harus ber-nomor sama.
                        $v->errors()->add("patients.$i", "$label: pasien tidak terdaftar di nomor ini.");
                    }
                } elseif ($type === 'new') {
                    if (empty($p['name'])) {
                        $v->errors()->add("patients.$i", "$label: nama wajib diisi.");
                    }
                    if (empty($p['jenis_kelamin']) || ! in_array($p['jenis_kelamin'], ['L', 'P'], true)) {
                        $v->errors()->add("patients.$i", "$label: jenis kelamin wajib dipilih.");
                    }
                    if (empty($p['tanggal_lahir'])) {
                        $v->errors()->add("patients.$i", "$label: tanggal lahir wajib diisi.");
                    }
                    if (
                        ! empty($p['relasi'])
                        && ! in_array($p['relasi'], ['diri_sendiri', 'pasangan', 'anak', 'orang_tua', 'lainnya'], true)
                    ) {
                        $v->errors()->add("patients.$i", "$label: relasi tidak valid.");
                    }
                } else {
                    $v->errors()->add("patients.$i", "$label: tipe pasien tidak valid.");
                }
            }
        });

        $validator->validate();

        $branchId = request('raw_branch_id');
        $date     = request('reservation_date');
        $start    = request('default_start');
        $promoId  = request('promo_id') ?: null;
        $phone    = Phone::normalize(request('phone')) ?: request('phone');

        $result = DB::transaction(function () use ($branchId, $date, $start, $promoId, $phone) {
            // 1 raw_contact untuk seluruh submission (1 nomor). Self-healing
            // adoption pasien lama ber-nomor sama terjadi di sini.
            $contact   = $this->resolveContact($phone, null, 'website', 'landing-page');
            $contactId = $contact->id;

            // ── Resolve tiap pasien (customer) + siapkan item utk slot ──
            $rows = []; // index => [customer, doctor_id, services[], lainnya_text, duration]
            foreach (request('patients') as $i => $p) {
                $doctorId = $p['raw_doctor_id'];
                $services = array_values(array_filter($p['services'] ?? [])); // ids
                $lainnya  = empty($services) && ! empty($p['lainnya_text']);

                if (($p['type'] ?? null) === 'existing') {
                    $customer = RawCustomer::findOrFail($p['existing_customer_id']);
                    if ($customer->raw_contact_id !== $contactId) {
                        $customer->raw_contact_id = $contactId;
                        $customer->save();
                    }
                } else {
                    $customer = RawCustomer::create([
                        'raw_contact_id'   => $contactId,
                        'relasi'           => $p['relasi'] ?? 'diri_sendiri',
                        'name'             => $p['name'],
                        'phone'            => $phone,
                        'email'            => $p['email'] ?? null,
                        'jenis_kelamin'    => $p['jenis_kelamin'] ?? null,
                        'tanggal_lahir'    => $p['tanggal_lahir'] ?? null,
                        'tanggal_daftar'   => now()->toDateString(),
                        'pasien_baru_lama' => 'baru',
                        'source_app'       => 'website',
                        'channel'          => 'landing-page',
                    ]);
                }

                $rows[$i] = [
                    'customer'     => $customer,
                    'doctor_id'    => $doctorId,
                    'services'     => $services,
                    'lainnya_text' => $lainnya ? $p['lainnya_text'] : null,
                    'lainnya'      => $lainnya,
                    'duration'     => $this->patientDuration($doctorId, $services, $lainnya, $branchId),
                ];
            }

            // ── Alokasikan jam otomatis (authoritative, server-side) ─────
            $items = [];
            foreach ($rows as $i => $r) {
                $items[] = ['key' => $i, 'doctor_id' => $r['doctor_id'], 'duration' => $r['duration']];
            }
            $assigned = $this->assignFamilySlots($branchId, $date, $items, $start);

            // ── Buat reservasi per pasien ────────────────────────────────
            $reservations = [];
            $lainnyaSvc   = RawDocService::where('category', 'lainnya')->first();

            foreach ($rows as $i => $r) {
                $slot     = $assigned[$i] ?? ['start' => null, 'end' => null, 'waiting' => true];
                $isWaiting = ! empty($slot['waiting']);

                $reservation = RawCusReservation::create([
                    'raw_customer_id' => $r['customer']->id,
                    'raw_doctor_id'   => $r['doctor_id'],
                    'raw_branch_id'   => $branchId,
                    'raw_promo_id'    => $promoId,
                    'date'            => $date,
                    'start_hour'      => $isWaiting ? null : $slot['start'],
                    'end_hour'        => $isWaiting ? null : $slot['end'],
                    'status'          => 'pending',
                    'is_waiting'      => $isWaiting,
                    'lainnya_text'    => $r['lainnya_text'],
                    'created_at'      => now(),
                ]);

                // Layanan
                if ($r['lainnya']) {
                    if ($lainnyaSvc) {
                        RawCusResService::create([
                            'raw_cus_reservation_id' => $reservation->id,
                            'raw_doctor_id'          => $r['doctor_id'],
                            'raw_doc_service_id'     => $lainnyaSvc->id,
                        ]);
                    }
                } else {
                    foreach ($r['services'] as $sid) {
                        RawCusResService::create([
                            'raw_cus_reservation_id' => $reservation->id,
                            'raw_doctor_id'          => $r['doctor_id'],
                            'raw_doc_service_id'     => $sid,
                        ]);
                    }
                }

                // Journey customer-level (per pasien).
                DB::table('raw_cus_journey')->insert([
                    'id'              => Str::uuid()->toString(),
                    'raw_customer_id' => $r['customer']->id,
                    'journey_label'   => 'Stat_Rsv',
                    'created_at'      => now(),
                ]);

                // Komisi affiliate (per reservasi) kalau pakai promo.
                if ($promoId) {
                    $promo = RawPromo::with('affiliate')->find($promoId);
                    if ($promo && $promo->affiliate) {
                        $totalHarga = 0;
                        foreach ($r['services'] as $sid) {
                            $svc = RawDocService::find($sid);
                            $totalHarga += $svc->price ?? 0;
                        }
                        $affiliate = $promo->affiliate instanceof \Illuminate\Support\Collection
                            ? $promo->affiliate->first()
                            : $promo->affiliate;
                        // Nominal flat per pasien dari referral_scope (100k behel / 50k non-behel).
                        $amount = $affiliate ? $affiliate->commissionAmountPerPatient() : 0;
                        if ($amount === 0) {
                            // Fallback legacy (rate × total) kalau scope belum di-set.
                            $rate   = $affiliate ? ($affiliate->commission_rate ?? 0) : 0;
                            $amount = round($totalHarga * ($rate / 100), 2);
                        }

                        DB::table('raw_commission')->insert([
                            'id'                     => Str::uuid()->toString(),
                            'raw_affiliate_id'       => $promo->affiliate->id,
                            'raw_cus_reservation_id' => $reservation->id,
                            'amount'                 => $amount,
                            'status'                 => 'pending',
                            'created_at'             => now(),
                        ]);
                    }
                }

                $reservations[] = $reservation;
            }

            // Journey contact-level: 1x per submission (funnel nomor).
            DB::table('raw_cus_journey')->insert([
                'id'             => Str::uuid()->toString(),
                'raw_contact_id' => $contactId,
                'journey_label'  => 'Stat_Rsv',
                'created_at'     => now(),
            ]);

            return [
                'reservations' => $reservations,
                'names'        => collect($rows)->map(fn ($r) => $r['customer']->name)->all(),
            ];
        });

        $count = count($result['reservations']);
        $names = implode(', ', $result['names']);

        AdminNotification::notify(
            'reservation',
            "Reservasi Baru: {$count} pasien ({$phone})",
            "Reservasi baru untuk {$names} di nomor {$phone}",
            url('rawreservation'),
            $branchId
        );

        return redirect('/reservasi/riwayat?phone=' . urlencode($phone))
            ->with('success', "{$count} reservasi berhasil dibuat! Tim kami akan menghubungi kamu untuk konfirmasi.");
    }
}
