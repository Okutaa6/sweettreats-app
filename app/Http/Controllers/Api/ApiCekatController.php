<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawDocService;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Open API untuk vendor AI WhatsApp (Cekat) — dan vendor lain ke depan.
 *
 * Satu controller melayani LIVE (apicekat/*) & SANDBOX (apicekatdev/*).
 * Flag $sandbox berasal dari middleware VerifyApiClientToken (mode auth =
 * mode data). Endpoint TULIS (customer_profile, reservation) di mode sandbox
 * menulis ke tabel `_dev`; endpoint BACA (service_range, jadwal_dokter) selalu
 * baca data asli (read-only, aman).
 */
class ApiCekatController extends Controller
{
    use ResolvesDoctorAvailability;

    /** Tabel write-sink yang punya kembaran _dev untuk sandbox. */
    private const DEV_SINKS = [
        'raw_contact',
        'raw_customer',
        'raw_cus_journey',
        'raw_cus_reservation',
        'raw_cus_res_service',
    ];

    protected bool $sandbox = false;

    /** Set flag sandbox dari attribute yang ditanam middleware. */
    protected function boot(Request $request): void
    {
        $this->sandbox = (bool) $request->attributes->get('cekat_sandbox', false);
    }

    /** Nama tabel target: tambah suffix _dev bila sandbox & termasuk write-sink. */
    protected function tableName(string $base): string
    {
        return ($this->sandbox && in_array($base, self::DEV_SINKS, true))
            ? $base . '_dev'
            : $base;
    }

    protected function ok(array $data, int $code = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $data], $code);
    }

    protected function fail(string $code, string $message, int $http = 422, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status'  => 'error',
            'code'    => $code,
            'message' => $message,
        ], $extra), $http);
    }

    /**
     * Validator dengan pesan error Bahasa Indonesia & nama field apa adanya
     * (mis. "no_telpon", bukan "no telpon"). Dipakai customer_profile & reservation.
     */
    protected function makeValidator(array $data, array $rules): \Illuminate\Validation\Validator
    {
        $messages = [
            'required'       => 'Field :attribute wajib diisi.',
            'string'         => 'Field :attribute harus berupa teks.',
            'max'            => 'Field :attribute maksimal :max karakter.',
            'date_format'    => 'Field :attribute harus berformat :format.',
            'after_or_equal' => 'Field :attribute harus tanggal hari ini atau setelahnya.',
            'after'          => 'Field :attribute harus lebih besar dari start_hour.',
        ];

        $attributes = [
            'cabang'        => 'cabang',
            'nama'          => 'nama',
            'no_telpon'     => 'no_telpon',
            'treatment'     => 'treatment',
            'intent'        => 'intent',
            'date'          => 'date',
            'start_hour'    => 'start_hour',
            'end_hour'      => 'end_hour',
            'raw_doctor_id' => 'raw_doctor_id',
        ];

        return Validator::make($data, $rules, $messages, $attributes);
    }

    /**
     * Normalisasi nomor telepon ke format lokal 08xxxxxxxxx.
     * Tangani +62 / 62 / 0062 / spasi / strip / kurung. Null bila tak valid.
     */
    protected function normalizePhone(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '0062')) {
            $digits = '0' . substr($digits, 4);
        } elseif (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif ($digits[0] !== '0') {
            $digits = '0' . $digits;
        }

        return preg_match('/^0[0-9]{9,14}$/', $digits) ? $digits : null;
    }

    /** Cari cabang by id ATAU nama (case-insensitive). */
    protected function resolveBranch(?string $cabang): ?RawBranch
    {
        $cabang = trim((string) $cabang);
        if ($cabang === '') {
            return null;
        }

        return RawBranch::where('id', $cabang)
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($cabang)])
            ->first();
    }

    /** "selasa"/"Selasa"/"2" → 2 (1=Senin .. 7=Minggu). Null bila tak valid. */
    protected function parseHari(string $raw, array $dayNames): ?int
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            $n = (int) $raw;
            return ($n >= 1 && $n <= 7) ? $n : null;
        }

        $raw = str_replace("'", '', $raw); // jum'at → jumat
        foreach ($dayNames as $num => $name) {
            if (strtolower($name) === $raw) {
                return $num;
            }
        }

        return null;
    }

    // =====================================================================
    // POST customer_profile  — Cekat kirim {cabang,nama,no_telpon,treatment,intent}
    // =====================================================================
    public function customerProfile(Request $request): JsonResponse
    {
        $this->boot($request);

        $v = $this->makeValidator($request->all(), [
            'cabang'    => 'required|string',
            'nama'      => 'required|string|max:255',
            'no_telpon' => 'required|string',
            'treatment' => 'nullable|string',
            'intent'    => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->fail('VALIDATION_ERROR', $v->errors()->first(), 422, ['errors' => $v->errors()->toArray()]);
        }

        $phone = $this->normalizePhone($request->input('no_telpon'));
        if (! $phone) {
            return $this->fail('VALIDATION_ERROR', 'Nomor telepon tidak valid (gunakan format 08xxxxxxxxx).', 422);
        }

        $name   = trim($request->input('nama'));
        $intent = trim((string) $request->input('intent')) ?: null;

        $branch         = $this->resolveBranch($request->input('cabang'));
        $branchMatched  = (bool) $branch;

        $service        = RawDocService::resolveByTreatment((string) $request->input('treatment', ''));
        $treatmentMatch = (bool) $service;

        $contactTable  = $this->tableName('raw_contact');
        $customerTable = $this->tableName('raw_customer');
        $journeyTable  = $this->tableName('raw_cus_journey');

        $result = DB::transaction(function () use (
            $contactTable, $customerTable, $journeyTable,
            $phone, $name, $intent, $branch, $service
        ) {
            // --- raw_contact: upsert by phone (pola MaxChat) ---
            $contact = DB::table($contactTable)->where('phone', $phone)->first();
            if ($contact) {
                $contactId = $contact->id;
                DB::table($contactTable)->where('id', $contactId)->update(['name' => $name]);
            } else {
                $contactId = (string) Str::uuid();
                DB::table($contactTable)->insert([
                    'id'         => $contactId,
                    'source_app' => 'cekat',
                    'name'       => $name,
                    'phone'      => $phone,
                    'channel'    => 'whatsapp',
                    'created_at' => now(),
                ]);
            }

            // --- raw_customer: upsert by phone ---
            $customer = DB::table($customerTable)->where('phone', $phone)->first();
            if ($customer) {
                $customerId = $customer->id;
                $upd = ['name' => $name];
                if ($branch)  $upd['raw_branch_id']      = $branch->id;
                if ($service) $upd['raw_doc_service_id'] = $service->id;
                if ($intent) {
                    $upd['catatan'] = trim(($customer->catatan ? $customer->catatan . ' | ' : '') . 'Intent: ' . $intent);
                }
                DB::table($customerTable)->where('id', $customerId)->update($upd);
            } else {
                $customerId = (string) Str::uuid();
                DB::table($customerTable)->insert([
                    'id'                 => $customerId,
                    'source_app'         => 'cekat',
                    'channel'            => 'whatsapp',
                    'name'               => $name,
                    'phone'              => $phone,
                    'raw_branch_id'      => $branch?->id,
                    'raw_doc_service_id' => $service?->id,
                    'pasien_baru_lama'   => 'baru',
                    'catatan'            => $intent ? 'Intent: ' . $intent : null,
                    'created_at'         => now(),
                ]);
            }

            // --- raw_cus_journey: catat intent ---
            DB::table($journeyTable)->insert([
                'id'              => (string) Str::uuid(),
                'raw_contact_id'  => $contactId,
                'raw_customer_id' => $customerId,
                'journey_label'   => $intent ?: 'Stat_New',
                'created_at'      => now(),
            ]);

            return ['contact_id' => $contactId, 'customer_id' => $customerId];
        });

        return $this->ok([
            'raw_contact_id'    => $result['contact_id'],
            'raw_customer_id'   => $result['customer_id'],
            'branch_matched'    => $branchMatched,
            'treatment_matched' => $treatmentMatch,
            'sandbox'           => $this->sandbox,
        ]);
    }

    // =====================================================================
    // GET service_range?codes=SVC001,SVC002  (read-only; sama live & sandbox)
    // =====================================================================
    public function serviceRange(Request $request): JsonResponse
    {
        $this->boot($request);

        $raw = $request->input('codes');
        if (is_string($raw)) {
            $codes = explode(',', $raw);
        } elseif (is_array($raw)) {
            $codes = $raw;
        } else {
            $codes = [];
        }
        $codes = array_filter(array_map('trim', $codes));

        if (empty($codes)) {
            return $this->fail('VALIDATION_ERROR', 'Parameter "codes" wajib diisi (mis. ?codes=SVC001,SVC002).', 422);
        }

        return $this->ok([
            'services' => RawDocService::priceRangeByCodes($codes),
        ]);
    }

    // =====================================================================
    // GET jadwal_dokter?cabang=&tanggal?&service?&doctor_id?  (read-only)
    // =====================================================================
    public function jadwalDokter(Request $request): JsonResponse
    {
        $this->boot($request);

        $branch = $this->resolveBranch($request->input('cabang'));
        if (! $branch) {
            return $this->fail('BRANCH_NOT_FOUND', 'Cabang tidak ditemukan: ' . $request->input('cabang'), 422);
        }

        $tanggal = $request->input('tanggal');
        if ($tanggal && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return $this->fail('VALIDATION_ERROR', 'Format tanggal harus YYYY-MM-DD.', 422);
        }

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

        // Filter hari (opsional): nama (senin..minggu) atau angka 1-7
        $hariNum = null;
        if ($request->filled('hari')) {
            $hariNum = $this->parseHari((string) $request->input('hari'), $dayNames);
            if (! $hariNum) {
                return $this->fail('VALIDATION_ERROR', 'Parameter hari tidak valid. Pakai nama (senin..minggu) atau angka 1-7.', 422);
            }
        }

        // Service (opsional) → untuk durasi slot
        $serviceId = null;
        if ($request->filled('service')) {
            $serviceId = optional(RawDocService::resolveByTreatment((string) $request->input('service')))->id;
        }

        // Jadwal recurring semua dokter di cabang ini → sumber practice_days
        $schedules = RawDoctorSchedule::where('raw_branch_id', $branch->id)
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('raw_doctor_id', $request->input('doctor_id')))
            ->get(['raw_doctor_id', 'day']);

        $daysByDoctor = $schedules->groupBy('raw_doctor_id')
            ->map(fn ($g) => $g->pluck('day')->unique()->sort()->values()->all());

        $doctors = RawDoctor::whereIn('id', $daysByDoctor->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'photo']);

        $data = $doctors->map(function ($doc) use ($branch, $tanggal, $serviceId, $hariNum, $daysByDoctor, $dayNames) {
            $days = $daysByDoctor[$doc->id] ?? [];

            // filter hari: lewati dokter yang tidak praktik di hari itu
            if ($hariNum && ! in_array($hariNum, $days, true)) {
                return null;
            }

            $dates = $this->computeAvailableDates($branch->id, $doc->id);
            if ($hariNum) {
                $dates = array_values(array_filter($dates, fn ($d) => (int) $d['day'] === $hariNum));
            }

            $row = [
                'id'            => $doc->id,
                'name'          => $doc->name,
                'photo_url'     => $doc->photo_url,
                'practice_days' => array_map(fn ($d) => $dayNames[$d] ?? (string) $d, $days),
            ];

            if ($hariNum && ! $tanggal) {
                // Mode ringkas: jawab "siapa praktik hari X" tanpa daftar tanggal panjang
                $row['next_date']   = $dates[0]['date'] ?? null;
                $row['total_dates'] = count($dates);
            } else {
                $row['available_dates'] = $dates;
            }

            if ($tanggal) {
                $row['slots'] = $this->computeTimeSlots($branch->id, $doc->id, $tanggal, [
                    'service_id' => $serviceId,
                ]);
            }

            return $row;
        })->filter()->values();

        return $this->ok([
            'branch'  => ['id' => $branch->id, 'name' => $branch->name],
            'hari'    => $hariNum ? $dayNames[$hariNum] : null,
            'doctors' => $data,
        ]);
    }

    // =====================================================================
    // POST reservation — Cekat kirim reservasi (pasien baru/lama)
    // =====================================================================
    public function reservation(Request $request): JsonResponse
    {
        $this->boot($request);

        $v = $this->makeValidator($request->all(), [
            'cabang'        => 'required|string',
            'nama'          => 'required|string|max:255',
            'no_telpon'     => 'required|string',
            'treatment'     => 'required|string',
            'date'          => 'required|date_format:Y-m-d|after_or_equal:today',
            'start_hour'    => 'required|date_format:H:i',
            'end_hour'      => 'required|date_format:H:i|after:start_hour',
            'raw_doctor_id' => 'nullable|string',
            'intent'        => 'nullable|string|max:255',
        ]);
        if ($v->fails()) {
            return $this->fail('VALIDATION_ERROR', $v->errors()->first(), 422, ['errors' => $v->errors()->toArray()]);
        }

        $phone = $this->normalizePhone($request->input('no_telpon'));
        if (! $phone) {
            return $this->fail('VALIDATION_ERROR', 'Nomor telepon tidak valid (gunakan format 08xxxxxxxxx).', 422);
        }

        $branch = $this->resolveBranch($request->input('cabang'));
        if (! $branch) {
            return $this->fail('BRANCH_NOT_FOUND', 'Cabang tidak ditemukan: ' . $request->input('cabang'), 422);
        }

        $service = RawDocService::resolveByTreatment((string) $request->input('treatment'));
        if (! $service) {
            return $this->fail('TREATMENT_NOT_FOUND', 'Treatment tidak dikenali: ' . $request->input('treatment'), 422);
        }

        $date      = $request->input('date');
        $startHour = $request->input('start_hour');
        $endHour   = $request->input('end_hour');

        // Tentukan dokter: pakai yang dikirim, atau pilih kandidat pertama
        // yang bisa treatment ini + punya jadwal di cabang + slotnya kosong.
        $doctorId = $request->input('raw_doctor_id');
        $candidateIds = RawDoctorService::where('raw_doc_service_id', $service->id)
            ->pluck('raw_doctor_id');
        $candidateIds = RawDoctorSchedule::where('raw_branch_id', $branch->id)
            ->whereIn('raw_doctor_id', $candidateIds)
            ->when($doctorId, fn ($q) => $q->where('raw_doctor_id', $doctorId))
            ->distinct()
            ->pluck('raw_doctor_id');

        if ($candidateIds->isEmpty()) {
            return $this->fail('SLOT_UNAVAILABLE', 'Tidak ada dokter yang melayani treatment ini di cabang tersebut.', 409);
        }

        $reservationTable = $this->tableName('raw_cus_reservation');
        $chosenDoctorId   = null;

        foreach ($candidateIds as $cid) {
            $result = $this->computeTimeSlots($branch->id, $cid, $date, [
                'service_id'        => $service->id,
                'reservation_table' => $reservationTable,
            ]);
            foreach ($result['slots'] as $slot) {
                if ($slot['time'] === $startHour && $slot['available']) {
                    $chosenDoctorId = $cid;
                    break 2;
                }
            }
        }

        if (! $chosenDoctorId) {
            return $this->fail('SLOT_UNAVAILABLE', 'Slot ' . $startHour . ' pada ' . $date . ' tidak tersedia / di luar jadwal dokter.', 409);
        }

        $customerTable = $this->tableName('raw_customer');
        $intent        = trim((string) $request->input('intent')) ?: null;
        $name          = trim($request->input('nama'));

        // Pasien lama vs baru (lookup variasi format nomor)
        $variants = array_values(array_unique([
            $phone,
            ltrim($phone, '0'),
            '62' . ltrim($phone, '0'),
        ]));
        $customer = DB::table($customerTable)->whereIn('phone', $variants)->first();

        $payload = DB::transaction(function () use (
            $customerTable, $reservationTable, $customer,
            $phone, $name, $intent, $branch, $service, $chosenDoctorId,
            $date, $startHour, $endHour
        ) {
            if ($customer) {
                $customerId      = $customer->id;
                $pasienBaruLama  = 'lama';
                DB::table($customerTable)->where('id', $customerId)->update(['name' => $name]);
            } else {
                $customerId     = (string) Str::uuid();
                $pasienBaruLama = 'baru';
                DB::table($customerTable)->insert([
                    'id'                 => $customerId,
                    'source_app'         => 'cekat',
                    'channel'            => 'whatsapp',
                    'name'               => $name,
                    'phone'              => $phone,
                    'raw_branch_id'      => $branch->id,
                    'raw_doc_service_id' => $service->id,
                    'pasien_baru_lama'   => 'baru',
                    'catatan'            => $intent ? 'Intent: ' . $intent : null,
                    'created_at'         => now(),
                ]);
            }

            $reservationId = (string) Str::uuid();
            DB::table($reservationTable)->insert([
                'id'              => $reservationId,
                'raw_branch_id'   => $branch->id,
                'raw_customer_id' => $customerId,
                'raw_doctor_id'   => $chosenDoctorId,
                'date'            => $date,
                'start_hour'      => $startHour,
                'end_hour'        => $endHour,
                'status'          => 'pending',
                'is_waiting'      => 0,
                'keterangan'      => 'Reservasi via Cekat' . ($intent ? ' - ' . $intent : ''),
                'created_at'      => now(),
            ]);

            DB::table($this->tableName('raw_cus_res_service'))->insert([
                'id'                        => (string) Str::uuid(),
                'raw_cus_reservation_id'    => $reservationId,
                'raw_doctor_id'             => $chosenDoctorId,
                'raw_doc_service_id'        => $service->id,
                'raw_doc_service_detail_id' => null,
            ]);

            return [
                'reservation_id'   => $reservationId,
                'customer_id'      => $customerId,
                'pasien_baru_lama' => $pasienBaruLama,
            ];
        });

        return $this->ok([
            'reservation_id'   => $payload['reservation_id'],
            'status'           => 'pending',
            'customer_id'      => $payload['customer_id'],
            'pasien_baru_lama' => $payload['pasien_baru_lama'],
            'raw_doctor_id'    => $chosenDoctorId,
            'sandbox'          => $this->sandbox,
        ], 201);
    }
}
