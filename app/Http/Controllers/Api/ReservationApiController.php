<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Http\Controllers\Concerns\ResolvesPatientContact;
use App\Models\RawContact;
use App\Models\RawCustomer;
use App\Support\Phone;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorService;
use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;
use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationApiController extends Controller
{
    use ResolvesDoctorAvailability;
    use ResolvesPatientContact;

    // FLOW BARU: Cabang → Perawatan → Dokter → Jadwal
  
    /*
     * GET /api/reservation/services
     */
    public function getServices()
    {
        $services = RawDocService::orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'category', 'sort_order']);

        return response()->json($services);
    }

    /*
     * GET /api/reservation/doctors-by-service/{branchId}/{serviceId}
     */
    public function getDoctorsByService($branchId, $serviceId)
    {
        // Ambil doctor_id yang bisa handle service ini
        $doctorIdsWithService = RawDoctorService::where('raw_doc_service_id', $serviceId)
            ->pluck('raw_doctor_id');

        // Dari daftar tadi, filter yang punya jadwal di cabang ini
        $doctorIdsAtBranch = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->whereIn('raw_doctor_id', $doctorIdsWithService)
            ->distinct()
            ->pluck('raw_doctor_id');

        $doctors = RawDoctor::whereIn('id', $doctorIdsAtBranch)
            ->orderBy('name')
            ->get(['id', 'name', 'photo']);

        // Tambahkan info hari praktek di cabang ini
        $dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
        $schedules = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->whereIn('raw_doctor_id', $doctorIdsAtBranch)
            ->orderBy('day')
            ->get();

        $result = $doctors->map(function ($doc) use ($schedules, $dayNames) {
            $days = $schedules->where('raw_doctor_id', $doc->id)
                ->pluck('day')
                ->unique()
                ->map(fn($d) => $dayNames[$d] ?? '')
                ->filter()
                ->values()
                ->toArray();

            return [
                'id'        => $doc->id,
                'name'      => $doc->name,
                'days'      => $days,
                'photo_url' => $doc->photo_url,
            ];
        });

        return response()->json($result);
    }
    /**

     * GET /api/reservation/dates/{branchId}/{doctorId}
     */
    public function getAvailableDates($branchId, $doctorId)
    {
        $scheduleDays = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->pluck('day')
            ->unique()
            ->toArray();

        // Ambil semua cuti dokter
        $leaves = \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $doctorId)
            ->get(['start_date', 'end_date']);

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $dates = [];

        // Batasi tanggal mulai minimal 1 Mei tahun ini
        $minDate = Carbon::create(Carbon::today()->year, 5, 1);
        $startDate = Carbon::today()->max($minDate);

        for ($i = 0; $i < 90; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso; // 1=Senin ... 7=Minggu

            if (in_array($dayOfWeek, $scheduleDays)) {
                // Skip kalau dokter sedang cuti di tanggal ini
                $isOnLeave = false;
                foreach ($leaves as $leave) {
                    if ($date->between(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                        $isOnLeave = true;
                        break;
                    }
                }
                if ($isOnLeave) continue;

                $dates[] = [
                    'date'  => $date->format('Y-m-d'),
                    'label' => $dayNames[$dayOfWeek] . ', ' . $date->format('d M Y'),
                    'day'   => $dayOfWeek,
                ];
            }
        }

        return response()->json($dates);
    }

    /**
     * GET /api/reservation/slots/{branchId}/{doctorId}/{date}
     */
    public function getTimeSlots($branchId, $doctorId, $date)
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek  = $carbonDate->dayOfWeekIso;

        $schedules = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->where('day', $dayOfWeek)
            ->orderBy('start_hour')
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([]);
        }

        // Interval slot 30 menit (fixed, tidak tergantung service)
        $slotInterval = 30;

        // Reservasi yang udah ada untuk dokter ini di tanggal ini
        // exclude_id: untuk reschedule, kecualikan reservasi yang sedang diedit
        $excludeId = request('exclude_id');

        $reservations = RawCusReservation::where('raw_doctor_id', $doctorId)
            ->where('date', $date)
            ->whereNotNull('start_hour')
            ->whereNotNull('end_hour')
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get();

        $bookedRanges = $reservations->map(fn($r) => [
            'start' => Carbon::parse($r->start_hour)->format('H:i'),
            'end'   => Carbon::parse($r->end_hour)->format('H:i'),
        ])->toArray();

        $slots = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($schedule->start_hour);
            $end   = Carbon::parse($schedule->end_hour);

            while ($start->copy()->addMinutes($slotInterval)->lte($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd   = $start->copy()->addMinutes($slotInterval)->format('H:i');

                // Cek apa slot ini udah terisi
                $isBooked = false;
                foreach ($bookedRanges as $range) {
                    if ($slotStart < $range['end'] && $slotEnd > $range['start']) {
                        $isBooked = true;
                        break;
                    }
                }

                // Cek apa slot udah lewat (buat hari ini)
                $isPast = $carbonDate->isToday() && Carbon::now()->format('H:i') > $slotStart;

                $slots[] = [
                    'time'      => $slotStart,
                    'end_time'  => $slotEnd,
                    'label'     => $slotStart,
                    'is_booked' => $isBooked,
                    'is_past'   => $isPast,
                    'available' => !$isBooked && !$isPast,
                ];

                $start->addMinutes($slotInterval);
            }
        }

        return response()->json([
            'slots'         => $slots,
            'slot_interval' => $slotInterval,
        ]);
    }

    /**
     * POST /api/reservation/plan-schedule
     * Hitung jam otomatis untuk SEKUMPULAN pasien (1 nomor / keluarga).
     * Dipakai form publik untuk preview jam sebelum konfirmasi.
     *
     * Body: { branch_id, date, default_start, patients:[
     *   { key, doctor_id, services:[id..], lainnya:bool } ] }
     */
    public function planSchedule(Request $request)
    {
        $data = $request->validate([
            'branch_id'            => 'required',
            'date'                 => 'required|date',
            'default_start'        => 'nullable',
            'patients'             => 'required|array|min:1',
            'patients.*.key'       => 'required',
            'patients.*.doctor_id' => 'required',
            'patients.*.services'  => 'nullable|array',
            'patients.*.lainnya'   => 'nullable',
        ]);

        $items = [];
        $durations = [];
        foreach ($data['patients'] as $p) {
            $dur = $this->patientDuration(
                $p['doctor_id'], $p['services'] ?? [], !empty($p['lainnya'])
            );
            $durations[$p['key']] = $dur;
            $items[] = [
                'key'       => $p['key'],
                'doctor_id' => $p['doctor_id'],
                'duration'  => $dur,
            ];
        }

        $assigned = $this->assignFamilySlots(
            $data['branch_id'], $data['date'], $items, $data['default_start'] ?? null
        );

        $out = [];
        foreach ($assigned as $key => $a) {
            $out[] = array_merge(
                ['key' => $key, 'duration' => $durations[$key] ?? null], $a
            );
        }

        return response()->json(['patients' => $out]);
    }

    /**
     * GET /api/reservation/doctors/{branchId}
     */
    public function getDoctorsByBranch($branchId)
    {
        $doctorIds = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->distinct()
            ->pluck('raw_doctor_id');

        $doctors = RawDoctor::whereIn('id', $doctorIds)
            ->orderBy('name')
            ->get(['id', 'name', 'photo']);

        $result = $doctors->map(fn($doc) => [
            'id'        => $doc->id,
            'name'      => $doc->name,
            'photo_url' => $doc->photo_url,
        ]);

        return response()->json($result);
    }

    /**
     * GET /api/reservation/services-by-doctor/{doctorId}
     * Kembalikan layanan yang bisa dilakukan dokter ini (via pivot raw_doctor_service)
     * Termasuk sub-detail tiap layanan
     */
    public function getServicesByDoctor($doctorId)
    {
        $serviceIds = RawDoctorService::where('raw_doctor_id', $doctorId)
            ->pluck('raw_doc_service_id');

        $services = RawDocService::whereIn('id', $serviceIds)
            ->with(['details' => function($q) {
                $q->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price']);

        return response()->json($services);
    }

    /**
     * GET /api/reservation/branches/{doctorId}
     * Kembalikan daftar cabang tempat dokter ini punya jadwal
     */
    public function getBranchesByDoctor($doctorId)
    {
        $branchIds = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->distinct()
            ->pluck('raw_branch_id');

        $branches = RawBranch::whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($branches);
    }
    /**
     * GET /api/reservation/check-customer?phone=08xxx
     * Kembalikan daftar pasien di nomor itu (1 nomor banyak pasien).
     * Termasuk pasien orphan (raw_contact_id NULL) yang belum ter-backfill
     * tapi nomornya match — supaya tetap kelihatan & bisa dipilih.
     */
    public function checkCustomerByPhone(Request $request)
    {
        $raw  = trim($request->get('phone', ''));
        $norm = Phone::normalize($raw);

        if ($norm === null) {
            return response()->json([
                'found'   => false,
                'message' => 'Nomor HP tidak valid. Silakan daftar sebagai Pasien Baru.',
            ]);
        }

        $variants = $this->phoneVariants($norm);

        // Semua contact yang cocok (bisa >1 karena data lama beda format).
        $contacts   = RawContact::whereIn('phone', $variants)
            ->orderBy('created_at')->orderBy('id')->get();
        $contact    = $contacts->first();
        $contactIds = $contacts->pluck('id')->all();

        // Pasien di nomor ini = ter-anchor ke contact mana pun yang cocok,
        // ATAU orphan (raw_contact_id NULL) ber-nomor sama (legacy belum
        // ter-backfill / pasien buatan Cekat). Union ini WAJIB.
        $patients = RawCustomer::query()
            ->where(function ($q) use ($contactIds, $variants) {
                if (! empty($contactIds)) {
                    $q->whereIn('raw_contact_id', $contactIds);
                }
                $q->orWhere(function ($qq) use ($variants) {
                    $qq->whereNull('raw_contact_id')->whereIn('phone', $variants);
                });
            })
            ->orderBy('created_at')
            ->get(['id', 'name', 'relasi', 'tanggal_lahir', 'jenis_kelamin', 'email']);

        if ($patients->isEmpty()) {
            return response()->json([
                'found'   => false,
                'message' => 'Nomor HP tidak ditemukan. Silakan daftar sebagai Pasien Baru.',
            ]);
        }

        return response()->json([
            'found'   => true,
            'contact' => $contact
                ? ['id' => $contact->id, 'phone' => $contact->phone, 'name' => $contact->name]
                : ['id' => null, 'phone' => $norm, 'name' => $patients->first()->name],
            'patients' => $patients->map(fn ($c) => [
                'customer_id'   => $c->id,
                'name'          => $c->name,
                'relasi'        => $c->relasi ?: 'diri_sendiri',
                'tanggal_lahir' => $c->tanggal_lahir,
                'jenis_kelamin' => $c->jenis_kelamin,
                'email'         => $c->email,
            ])->values(),
        ]);
    }

    /**
     * GET /api/reservation/check-new
     * Cek apakah ada reservasi baru masuk sejak pengecekan terakhir
     */
    public function checkNew(Request $request)
    {
        $serverTime = now();
        $since = $request->get('since', $serverTime->toDateTimeString());

        $query = RawCusReservation::with('customer', 'branch')
            ->where('created_at', '>', $since);

        // Filter berdasarkan cabang user jika ada
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch) {
            $query->where('raw_branch_id', $sessionBranch);
        }

        $newReservations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'new_count'   => $newReservations->count(),
            'latest'      => $newReservations->first(),
            'server_time' => $serverTime->toDateTimeString(),
        ]);
    }
}