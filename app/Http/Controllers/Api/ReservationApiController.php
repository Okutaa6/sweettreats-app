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
use App\Models\RawDoctorScheduleException;
use App\Models\RawDoctorService;
use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;
use App\Models\RawCusReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->where('is_active', 1)
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
        return response()->json($this->computeAvailableDates($branchId, $doctorId));
    }

    /**
     * GET /api/reservation/slots/{branchId}/{doctorId}/{date}
     *
     * ROUTE PUBLIK — dipakai form pasien di /reservasi. TIDAK BOLEH mengirim
     * 'with_booking_detail': nama pasien lain tak boleh terlihat oleh pasien.
     * Versi berdetail untuk staf ada di getTimeSlotsAdmin() di bawah.
     */
    public function getTimeSlots($branchId, $doctorId, $date)
    {
        return response()->json($this->computeTimeSlots($branchId, $doctorId, $date, [
            'service_id' => request('service_id'),
            'duration'   => (int) request('duration', 0),
            'exclude_id' => request('exclude_id'),
        ]));
    }

    /**
     * GET /rawreservation/slots/{branchId}/{doctorId}/{date}  (di balik AuthGuard)
     *
     * Kembaran getTimeSlots() untuk STAF: slot terisi ikut membawa cabang, jam
     * & nama pasien, plus daftar janji dokter di cabang lain pada tanggal itu.
     * Sengaja route terpisah, bukan query-param di route publik — supaya
     * detailnya tidak pernah ada di permukaan yang bisa disentuh pasien.
     *
     * Nomor HP tidak diambil, tidak dikirim, tidak dirender: koordinasinya ke
     * admin cabang, bukan ke pasien orang lain.
     */
    public function getTimeSlotsAdmin($branchId, $doctorId, $date)
    {
        return response()->json($this->computeTimeSlots($branchId, $doctorId, $date, [
            'service_id'          => request('service_id'),
            'duration'            => (int) request('duration', 0),
            'exclude_id'          => request('exclude_id'),
            'with_booking_detail' => true,
        ]));
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
                $p['doctor_id'], $p['services'] ?? [], !empty($p['lainnya']),
                $data['branch_id']
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
            ->where('is_active', 1)
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
     * GET /api/reservation/services-by-doctor/{doctorId}?branch_id=...
     * Kembalikan layanan yang bisa dilakukan dokter ini (capability universal
     * via pivot raw_doctor_service). Durasi efektif dihitung PER-CABANG bila
     * branch_id dikirim — override durasi dokter berbeda tiap cabang.
     */
    public function getServicesByDoctor($doctorId, Request $request)
    {
        $branchId = $request->query('branch_id');

        $serviceIds = RawDoctorService::where('raw_doctor_id', $doctorId)
            ->pluck('raw_doc_service_id');

        $services = RawDocService::whereIn('id', $serviceIds)
            ->with(['details' => function($q) {
                $q->where('is_active', 1)->orderBy('sort_order')->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes', 'price']);

        // Override durasi PER-CABANG (cuma kalau fitur fleksibel aktif &
        // branch diketahui). Tanpa branch → pakai durasi default service.
        $svcOverrides    = collect();
        $detailOverrides = collect();
        if (config('features.flexible_scheduling') && $branchId) {
            $svcOverrides = DB::table('raw_doctor_branch_service_duration')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->pluck('duration_minutes', 'raw_doc_service_id');
            $detailOverrides = DB::table('raw_doctor_branch_detail_duration')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->pluck('duration_minutes', 'raw_doc_service_detail_id');
        }

        $services = $services->map(function ($svc) use ($svcOverrides, $detailOverrides) {
            $override = $svcOverrides[$svc->id] ?? null;
            $svc->effective_duration_minutes = $override ?? $svc->duration_minutes;

            $svc->details->transform(function ($detail) use ($detailOverrides) {
                $detailOverride = $detailOverrides[$detail->id] ?? null;
                $detail->effective_duration_minutes = $detailOverride ?? $detail->duration_minutes;
                return $detail;
            });

            return $svc;
        });

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
 * Cek apakah nomor HP sudah terdaftar sebagai pasien
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