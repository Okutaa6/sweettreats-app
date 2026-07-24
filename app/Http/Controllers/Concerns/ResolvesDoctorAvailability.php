<?php

namespace App\Http\Controllers\Concerns;

use App\Models\RawDocService;
use App\Models\RawDocServiceDetail;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika ketersediaan jadwal & slot dokter (recurring + exception + cuti).
 *
 * Dipisah jadi trait supaya dipakai bersama oleh ReservationApiController
 * (route /api/reservation/*) dan ApiCekatController (endpoint vendor) tanpa
 * duplikasi. Method compute* mengembalikan array MENTAH — pemanggil yang
 * membungkus jadi JSON response sesuai kebutuhannya.
 */
trait ResolvesDoctorAvailability
{
    /**
     * Tanggal tersedia 90 hari ke depan (min 1 Mei tahun ini), sudah
     * memperhitungkan recurring schedule, exception, dan cuti dokter.
     *
     * @return array<int, array{date:string,label:string,day:int}>
     */
    protected function computeAvailableDates($branchId, $doctorId): array
    {
        // Hanya hari yang punya WORK row yang dianggap "praktik" — hari yang
        // cuma berisi baris istirahat tidak boleh muncul sebagai bookable date.
        $scheduleDays = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)
            ->pluck('day')
            ->unique()
            ->toArray();

        // Ambil semua cuti dokter
        $leaves = DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $doctorId)
            ->get(['start_date', 'end_date']);

        $exceptions = collect();
        if (config('features.flexible_scheduling')) {
            $exceptions = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->whereIn('type', ['add', 'replace'])
                ->pluck('type', 'date');
        }

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $dates = [];

        // Batasi tanggal mulai minimal 1 Mei tahun ini
        $minDate = Carbon::create(Carbon::today()->year, 5, 1);
        $startDate = Carbon::today()->max($minDate);

        for ($i = 0; $i < 535; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayOfWeek = $date->dayOfWeekIso; // 1=Senin ... 7=Minggu
            $dateStr = $date->format('Y-m-d');

            $hasRecurring  = in_array($dayOfWeek, $scheduleDays);
            $hasException  = $exceptions->has($dateStr);

            if (!$hasRecurring && !$hasException) {
                continue;
            }

            // Skip kalau dokter sedang cuti di tanggal ini (leave menang)
            $isOnLeave = false;
            foreach ($leaves as $leave) {
                if ($date->between(Carbon::parse($leave->start_date), Carbon::parse($leave->end_date))) {
                    $isOnLeave = true;
                    break;
                }
            }
            if ($isOnLeave) continue;

            $dates[] = [
                'date'  => $dateStr,
                'label' => $dayNames[$dayOfWeek] . ', ' . $date->format('d M Y'),
                'day'   => $dayOfWeek,
            ];
        }

        return $dates;
    }

    /**
     * Slot waktu untuk (cabang, dokter, tanggal) tertentu.
     *
     * $opts: service_id, duration (int explicit), exclude_id,
     *        reservation_table (default 'raw_cus_reservation' — sandbox
     *        kirim 'raw_cus_reservation_dev' supaya cek bentrok ke data dev).
     *
     * @return array{slots:array,sessions:array,slot_interval:int}
     */
    protected function computeTimeSlots($branchId, $doctorId, $date, array $opts = []): array
    {
        $carbonDate = Carbon::parse($date);

        $serviceId        = $opts['service_id'] ?? null;
        $detailId         = $opts['detail_id'] ?? null;
        $explicitDuration = (int) ($opts['duration'] ?? 0);
        $excludeId        = $opts['exclude_id'] ?? null;
        $reservationTable = $opts['reservation_table'] ?? 'raw_cus_reservation';

        // === 1. Resolve working ranges untuk tanggal ini ===
        $ranges = $this->resolveWorkingRanges($branchId, $doctorId, $date);

        if ($ranges->isEmpty()) {
            return [
                'slots'         => [],
                'sessions'      => [],
                'slot_interval' => 0,
            ];
        }

        // === 2. Resolve durasi slot (durasi override per-cabang + sub-detail) ===
        $duration = $this->resolveDuration($doctorId, $serviceId, $explicitDuration ?: null, $branchId, $detailId);

        // === 3. Reservasi yang udah ada di tanggal ini ===
        $bookedRanges = $this->resolveBookedRanges($doctorId, $date, $reservationTable, $excludeId);

        // === 4. Generate slot ===
        $slots = [];

        foreach ($ranges as $range) {
            $start = Carbon::parse($range['start_hour']);
            $end   = Carbon::parse($range['end_hour']);

            while ($start->copy()->addMinutes($duration)->lte($end)) {
                $slotStart = $start->format('H:i');
                $slotEnd   = $start->copy()->addMinutes($duration)->format('H:i');

                $isBooked = false;
                foreach ($bookedRanges as $r) {
                    if ($slotStart < $r['end'] && $slotEnd > $r['start']) {
                        $isBooked = true;
                        break;
                    }
                }

                $isPast = $carbonDate->isToday() && Carbon::now()->format('H:i') > $slotStart;

                $slots[] = [
                    'time'      => $slotStart,
                    'end_time'  => $slotEnd,
                    'label'     => $slotStart,
                    'is_booked' => $isBooked,
                    'is_past'   => $isPast,
                    'available' => !$isBooked && !$isPast,
                ];

                $start->addMinutes($duration);
            }
        }

        // === 5. Group per sesi (Pagi / Siang / Sore) ===
        $sessions = [
            'pagi'  => ['label' => 'Pagi',  'range' => 'sebelum 12:00', 'slots' => []],
            'siang' => ['label' => 'Siang', 'range' => '12:00 – 16:59', 'slots' => []],
            'sore'  => ['label' => 'Sore',  'range' => '17:00 ke atas',  'slots' => []],
        ];

        foreach ($slots as $slot) {
            $h = (int) substr($slot['time'], 0, 2);
            if ($h < 12) {
                $sessions['pagi']['slots'][] = $slot;
            } elseif ($h < 17) {
                $sessions['siang']['slots'][] = $slot;
            } else {
                $sessions['sore']['slots'][] = $slot;
            }
        }

        foreach ($sessions as $key => $s) {
            $sessions[$key]['available_count'] = collect($s['slots'])->where('available', true)->count();
            $sessions[$key]['total_count']     = count($s['slots']);
        }

        return [
            'slots'         => $slots,
            'sessions'      => $sessions,
            'slot_interval' => $duration,
        ];
    }

    /**
     * Resolve duration efektif untuk (doctor, service[, detail]) di SEBUAH CABANG.
     * Prioritas: explicit duration → override per-dokter PER-CABANG
     * (raw_doctor_branch_service_duration) → service.duration_minutes → 30.
     *
     * Bila $detailId diisi, prioritas chain SUB-LAYANAN dipakai dulu:
     *  1. explicit duration
     *  2. override sub-layanan per-dokter PER-CABANG (raw_doctor_branch_detail_duration) — fitur flexible
     *  3. override sub-layanan per-dokter (raw_doctor_service_detail)
     *  4. raw_doc_service_detail.duration_minutes
     * lalu fallback ke chain SERVICE INDUK:
     *  5. override service per-dokter PER-CABANG (raw_doctor_branch_service_duration) — fitur flexible
     *  6. raw_doc_service.duration_minutes
     *  7. 30
     *
     * $branchId WAJIB diisi supaya override per-cabang terbaca; kalau null
     * (caller belum punya konteks cabang) langsung fallback ke durasi
     * default service/detail — aman, tidak bocor antar cabang.
     */
    protected function resolveDuration($doctorId, $serviceId = null, $explicitDuration = null, $branchId = null, $detailId = null): int
    {
        if ($explicitDuration !== null && $explicitDuration > 0) {
            return (int) $explicitDuration;
        }

        // === SUB-LAYANAN chain (kalau $detailId diisi) ===
        if ($detailId) {
            if (config('features.flexible_scheduling') && $branchId) {
                $detailBranchDur = DB::table('raw_doctor_branch_detail_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->where('raw_doc_service_detail_id', $detailId)
                    ->value('duration_minutes');

                if ($detailBranchDur !== null) {
                    return (int) round($detailBranchDur);
                }
            }

            // legacy fallback: override sub-layanan per-dokter (tanpa cabang)
            $detailDoctorDur = DB::table('raw_doctor_service_detail')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_doc_service_detail_id', $detailId)
                ->value('duration_minutes');

            if ($detailDoctorDur !== null) {
                return (int) round($detailDoctorDur);
            }

            $detail = RawDocServiceDetail::find($detailId);
            if ($detail && $detail->duration_minutes !== null) {
                return (int) round($detail->duration_minutes);
            }
        }

        if (!$serviceId) {
            return 30;
        }

        if (config('features.flexible_scheduling') && $branchId) {
            $branchDur = DB::table('raw_doctor_branch_service_duration')
                ->where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->where('raw_doc_service_id', $serviceId)
                ->value('duration_minutes');

            if ($branchDur !== null) {
                return (int) round($branchDur);
            }
        }

        $service = RawDocService::find($serviceId);
        if ($service && $service->duration_minutes !== null) {
            return (int) round($service->duration_minutes);
        }

        return 30;
    }

    /**
     * Durasi total perawatan seorang pasien (jumlah durasi tiap service;
     * "Lainnya"/kosong = 30 menit). Prioritas durasi: override per-dokter
     * PER-CABANG → service.duration_minutes → 30 (via resolveDuration).
     *
     * @param array<int,mixed> $serviceIds
     */
    protected function patientDuration($doctorId, array $serviceIds, bool $lainnya, $branchId = null): int
    {
        if ($lainnya) {
            return 30;
        }
        $total = 0;
        foreach ($serviceIds as $sid) {
            if (! $sid) {
                continue;
            }
            $total += $this->resolveDuration($doctorId, $sid, null, $branchId);
        }
        return $total > 0 ? $total : 30;
    }

    /**
     * Working ranges (jam praktik) dokter di sebuah tanggal — recurring
     * schedule + exception add/replace (kalau fitur flexible aktif).
     *
     * @return \Illuminate\Support\Collection<int,array{start_hour:string,end_hour:string}>
     */
    protected function resolveWorkingRanges($branchId, $doctorId, $date)
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        // Ambil SEMUA row jadwal (work + break) untuk (dokter, cabang, hari).
        // Work range di-subtract dengan break range supaya jam istirahat tidak
        // masuk pool slot bookable — otomatis efektif di admin CRUD & public
        // /reservasi karena keduanya lewat computeTimeSlots() → sini.
        $rows = RawDoctorSchedule::where('raw_branch_id', $branchId)
            ->where('raw_doctor_id', $doctorId)
            ->where('day', $dayOfWeek)
            ->orderBy('start_hour')
            ->get();

        $workRows  = $rows->where('type', '!=', RawDoctorSchedule::TYPE_BREAK);
        $breakRows = $rows->where('type', RawDoctorSchedule::TYPE_BREAK);

        $ranges = collect();
        foreach ($workRows as $w) {
            foreach ($this->subtractBreaks($w->start_hour, $w->end_hour, $breakRows) as $r) {
                $ranges->push($r);
            }
        }

        if (config('features.flexible_scheduling')) {
            $exceptions = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branchId)
                ->where('date', $date)
                ->get();

            $replaceExceptions = $exceptions->where('type', RawDoctorScheduleException::TYPE_REPLACE);
            $addExceptions     = $exceptions->where('type', RawDoctorScheduleException::TYPE_ADD);

            if ($replaceExceptions->isNotEmpty()) {
                $ranges = $replaceExceptions->map(fn($e) => [
                    'start_hour' => $e->start_hour,
                    'end_hour'   => $e->end_hour,
                ]);
            }

            $ranges = $ranges->concat(
                $addExceptions->map(fn($e) => [
                    'start_hour' => $e->start_hour,
                    'end_hour'   => $e->end_hour,
                ])
            )->sortBy('start_hour')->values();
        }

        return $ranges;
    }

    /**
     * Subtract kumpulan break dari 1 range work → list of sub-range yang
     * masih bookable. Break yang di luar range diabaikan. Support N break
     * (bisa overlap parsial atau nested — semua di-normalize dulu).
     *
     * @param  \Illuminate\Support\Collection|iterable  $breakRows
     * @return array<int,array{start_hour:string,end_hour:string}>
     */
    protected function subtractBreaks($workStart, $workEnd, $breakRows)
    {
        $ws = Carbon::parse($workStart)->format('H:i:s');
        $we = Carbon::parse($workEnd)->format('H:i:s');

        // Collect break intervals yang beririsan dengan work, di-clamp ke
        // batas work supaya subtract-nya aman.
        $breaks = [];
        foreach ($breakRows as $b) {
            $bs = Carbon::parse($b->start_hour)->format('H:i:s');
            $be = Carbon::parse($b->end_hour)->format('H:i:s');
            if ($be <= $ws || $bs >= $we) continue;         // di luar work
            $breaks[] = [max($bs, $ws), min($be, $we)];
        }

        if (empty($breaks)) {
            return [['start_hour' => $workStart, 'end_hour' => $workEnd]];
        }

        // Merge overlap antar break supaya subtract cukup 1x pass.
        usort($breaks, fn($a, $b) => strcmp($a[0], $b[0]));
        $merged = [$breaks[0]];
        for ($i = 1; $i < count($breaks); $i++) {
            $last = &$merged[count($merged) - 1];
            if ($breaks[$i][0] <= $last[1]) {
                $last[1] = max($last[1], $breaks[$i][1]);
            } else {
                $merged[] = $breaks[$i];
            }
        }

        // Iterasi: potong work di setiap break.
        $result = [];
        $cursor = $ws;
        foreach ($merged as [$bs, $be]) {
            if ($cursor < $bs) {
                $result[] = ['start_hour' => $cursor, 'end_hour' => $bs];
            }
            $cursor = max($cursor, $be);
        }
        if ($cursor < $we) {
            $result[] = ['start_hour' => $cursor, 'end_hour' => $we];
        }
        return $result;
    }

    /**
     * Rentang [start,end] (H:i) yang sudah ter-booking utk dokter+tanggal.
     *
     * @return array<int,array{start:string,end:string}>
     */
    protected function resolveBookedRanges($doctorId, $date, string $reservationTable = 'raw_cus_reservation', $excludeId = null): array
    {
        return DB::table($reservationTable)
            ->where('raw_doctor_id', $doctorId)
            ->where('date', $date)
            ->whereNotNull('start_hour')
            ->whereNotNull('end_hour')
            // Reservasi yang DIBATALKAN atau masuk WAITING LIST tidak lagi menahan
            // slot — jamnya otomatis kembali tersedia untuk pasien lain. Baris dgn
            // status NULL (data lama) tetap dianggap terisi supaya tidak salah bebas.
            ->where(function ($q) {
                $q->whereNotIn('status', [
                    \App\Models\RawCusReservation::STATUS_CANCELLED,
                    \App\Models\RawCusReservation::STATUS_WAITLIST,
                ])->orWhereNull('status');
            })
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->get()
            ->map(fn($r) => [
                'start' => Carbon::parse($r->start_hour)->format('H:i'),
                'end'   => Carbon::parse($r->end_hour)->format('H:i'),
            ])->toArray();
    }

    /**
     * Alokasikan jam untuk SEKUMPULAN pasien (1 nomor / keluarga) dalam 1
     * tanggal. Tiap pasien punya dokter & durasi sendiri.
     *
     * Aturan:
     *  - Pasien dgn dokter SAMA → disusun berurutan (tidak saling tabrak).
     *  - Pasien dgn dokter BEDA → dihitung dari jadwal dokter itu sendiri
     *    (timeline antar dokter independen / paralel).
     *  - Tidak pernah menabrak reservasi existing (resolveBookedRanges) atau
     *    anggota keluarga lain yang sudah dialokasikan.
     *  - Default mulai = $defaultStart; kalau dokter itu tak punya slot di
     *    >= $defaultStart, ambil slot paling awal hari itu; kalau dokter tak
     *    praktik / penuh → waiting=true (tim konfirmasi).
     *
     * @param array<int,array{key:mixed,doctor_id:string,duration:int}> $items
     * @return array<mixed,array{start:?string,end:?string,waiting:bool,reason:?string}>
     */
    protected function assignFamilySlots(
        $branchId,
        string $date,
        array $items,
        ?string $defaultStart = null,
        string $reservationTable = 'raw_cus_reservation'
    ): array {
        $isToday = Carbon::parse($date)->isToday();
        $nowHi   = Carbon::now()->format('H:i');
        $result  = [];

        // Cache per dokter: ranges, booked (existing), consumed (keluarga ini),
        // dan cursor (jam selesai pasien terakhir dokter itu).
        $byDoctor = [];

        foreach ($items as $item) {
            $doctorId = $item['doctor_id'];
            $duration = max(1, (int) $item['duration']);
            $key      = $item['key'];

            if (!isset($byDoctor[$doctorId])) {
                $byDoctor[$doctorId] = [
                    'ranges'   => $this->resolveWorkingRanges($branchId, $doctorId, $date),
                    'booked'   => $this->resolveBookedRanges($doctorId, $date, $reservationTable),
                    'consumed' => [],
                    'cursor'   => null,
                ];
            }
            $D = &$byDoctor[$doctorId];

            if ($D['ranges']->isEmpty()) {
                $result[$key] = ['start' => null, 'end' => null, 'waiting' => true,
                    'reason' => 'Dokter tidak praktik di tanggal ini'];
                continue;
            }

            $lowerBound = $D['cursor'] ?: ($defaultStart ?: '00:00');
            if ($isToday && $nowHi > $lowerBound) {
                $lowerBound = $nowHi;
            }

            $chosen = $this->firstFreeStart($D, $duration, $lowerBound);

            // Fallback: tidak ada slot >= lowerBound → coba paling awal hari
            // itu (tetap hormati now kalau hari ini).
            if ($chosen === null && $D['cursor'] === null) {
                $earliest = ($isToday && $nowHi > '00:00') ? $nowHi : '00:00';
                $chosen   = $this->firstFreeStart($D, $duration, $earliest);
            }

            if ($chosen === null) {
                $result[$key] = ['start' => null, 'end' => null, 'waiting' => true,
                    'reason' => 'Slot dokter penuh di tanggal ini'];
                continue;
            }

            $end = Carbon::parse($chosen)->addMinutes($duration)->format('H:i');
            $D['consumed'][] = ['start' => $chosen, 'end' => $end];
            $D['cursor']     = $end;

            $result[$key] = ['start' => $chosen, 'end' => $end, 'waiting' => false, 'reason' => null];
        }

        return $result;
    }

    /**
     * Cari start paling awal (>= $lowerBound) dengan durasi $duration yang
     * muat di salah satu working range dan tidak tabrakan booked/consumed.
     * Kandidat di-generate dgn step = $duration (konsisten dgn grid slot).
     */
    private function firstFreeStart(array $D, int $duration, string $lowerBound): ?string
    {
        foreach ($D['ranges'] as $range) {
            $cursor = Carbon::parse($range['start_hour']);
            $rangeEnd = Carbon::parse($range['end_hour']);

            while ($cursor->copy()->addMinutes($duration)->lte($rangeEnd)) {
                $s = $cursor->format('H:i');
                $e = $cursor->copy()->addMinutes($duration)->format('H:i');

                if ($s >= $lowerBound && !$this->overlaps($s, $e, $D['booked'], $D['consumed'])) {
                    return $s;
                }
                $cursor->addMinutes($duration);
            }
        }
        return null;
    }

    /** True kalau [$s,$e) menabrak salah satu rentang booked/consumed. */
    private function overlaps(string $s, string $e, array $booked, array $consumed): bool
    {
        foreach (array_merge($booked, $consumed) as $r) {
            if ($s < $r['end'] && $e > $r['start']) {
                return true;
            }
        }
        return false;
    }
}
