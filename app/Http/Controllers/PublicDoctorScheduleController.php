<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Halaman publik (tanpa login) yang menampilkan jadwal praktik dokter, bisa
 * difilter per cabang. Dua mode tampilan:
 *   - "minggu" : jadwal mingguan tetap (semua hari praktik per dokter).
 *   - "tanggal": pilih 1 tanggal (Hari ini / Besok / date picker) → hanya
 *     dokter yang praktik di tanggal itu, slot jam-nya dibuka langsung.
 * Status kesibukan ringkas (Masih tersedia / Hampir penuh / Penuh) & slot
 * dihitung dari reservasi yang sudah masuk. Read-only — tak menulis ke DB.
 */
class PublicDoctorScheduleController extends Controller
{
    use ResolvesDoctorAvailability;

    /** Ambang rasio slot terisi untuk status "Hampir penuh". */
    private const ALMOST_FULL_RATIO = 0.7;

    /** Batas tanggal yang boleh dipilih pasien (hari ke depan). */
    private const MAX_DAYS_AHEAD = 90;

    public function index(Request $request)
    {
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);

        // Cabang aktif dari ?cabang=. Bisa berupa:
        //   - "all"        → semua cabang (dokter dari seluruh cabang digabung).
        //   - <id>         → dari tombol switch dropdown.
        //   - nama/slug    → link embed per-cabang (mis. ?cabang=sunter), terkunci.
        $cabang      = $request->query('cabang');
        $lockBranch  = false;
        $allBranches = false;
        $branchId    = null;

        if ($cabang === 'all') {
            $allBranches = true;
        } elseif ($cabang) {
            if ($branches->contains('id', $cabang)) {
                $branchId = $cabang;
            } else {
                $slug  = Str::slug($cabang);
                $match = $branches->first(fn ($b) => Str::slug($b->name) === $slug)
                    ?? $branches->first(fn ($b) => Str::contains(Str::slug($b->name), $slug) && $slug !== '');
                if ($match) {
                    $branchId   = $match->id;
                    $lockBranch = true;
                }
            }
        }

        if (! $allBranches) {
            $branchId ??= $branches->first()?->id;
        }

        // Mode terkunci: sisakan hanya cabang terpilih di dropdown, dan pakai
        // slug sebagai value agar filter tanggal tetap mempertahankan kunci.
        $branchSlug = null;
        if ($lockBranch && $branchId) {
            $branches   = $branches->where('id', $branchId)->values();
            $branchSlug = Str::slug($branches->first()->name);
        }

        // Tanggal terpilih dari ?tanggal=Y-m-d (di-clamp ke [hari ini, +90 hari]).
        // Tanpa ?tanggal → mode mingguan.
        $selectedDate = $this->resolveSelectedDate($request->query('tanggal'));
        $mode = $selectedDate ? 'tanggal' : 'minggu';

        // Daftar (id, nama) cabang yang mau ditampilkan: semua cabang atau satu.
        $targetBranches = $allBranches ? $branches : $branches->where('id', $branchId);

        // Gabungkan per DOKTER. Satu dokter bisa praktik di >1 cabang → satu kartu
        // dengan beberapa grup cabang (tab cabang di dalam kartu pada view).
        $byDoctor = [];
        foreach ($targetBranches as $b) {
            $branchDoctors = $mode === 'tanggal'
                ? $this->doctorsForDate($b->id, $selectedDate)
                : $this->doctorsForWeek($b->id);

            foreach ($branchDoctors as $card) {
                if (! isset($byDoctor[$card->id])) {
                    $byDoctor[$card->id] = (object) [
                        'id'             => $card->id,
                        'name'           => $card->name,
                        'photo_url'      => $card->photo_url,
                        'specialization' => $card->specialization,
                        'branches'       => [],
                    ];
                }

                $byDoctor[$card->id]->branches[] = (object) [
                    'branch_name' => $b->name,
                    'days'        => $card->days,
                    'default_day' => $card->default_day,
                ];
            }
        }

        $doctors = collect(array_values($byDoctor))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // Data untuk filter cepat & date picker di view.
        $today    = Carbon::today();
        $todayStr = $today->format('Y-m-d');
        $tomorrowStr = $today->copy()->addDay()->format('Y-m-d');
        $selectedStr = $selectedDate?->format('Y-m-d');
        $maxStr = $today->copy()->addDays(self::MAX_DAYS_AHEAD)->format('Y-m-d');

        return view('public.doctor-schedule', compact(
            'branches', 'branchId', 'doctors', 'mode', 'lockBranch', 'allBranches', 'branchSlug',
            'selectedDate', 'selectedStr', 'todayStr', 'tomorrowStr', 'maxStr'
        ));
    }

    /**
     * Parse & clamp ?tanggal. Return null bila kosong / tak valid (→ mode minggu).
     */
    private function resolveSelectedDate(?string $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }

        $min = Carbon::today();
        $max = Carbon::today()->addDays(self::MAX_DAYS_AHEAD);

        if ($date->lt($min)) {
            $date = $min->copy();
        }
        if ($date->gt($max)) {
            $date = $max->copy();
        }

        return $date;
    }

    /**
     * Mode mingguan: semua dokter dgn jadwal WORK di cabang ini, dikelompokkan
     * per hari praktik. Slot dihitung untuk tanggal terdekat tiap hari.
     */
    private function doctorsForWeek($branchId)
    {
        $rawDoctors = RawDoctor::active()
            ->whereHas('schedules', fn ($q) => $q
                ->where('raw_branch_id', $branchId)
                ->where('type', RawDoctorSchedule::TYPE_WORK))
            ->with(['schedules' => fn ($q) => $q
                ->where('raw_branch_id', $branchId)
                ->where('type', RawDoctorSchedule::TYPE_WORK)
                ->orderBy('day')
                ->orderBy('start_hour')])
            ->orderBy('name')
            ->get();

        $todayIso = Carbon::today()->dayOfWeekIso; // 1=Senin ... 7=Minggu

        return $rawDoctors->map(function ($doctor) use ($branchId, $todayIso) {
            // Kelompokkan jam praktik per hari.
            $byDay = [];
            foreach ($doctor->schedules as $s) {
                $byDay[(int) $s->day][] = substr($s->start_hour, 0, 5) . '–' . substr($s->end_hour, 0, 5);
            }
            ksort($byDay);

            $days = [];
            foreach ($byDay as $day => $ranges) {
                // Tanggal terdekat yang jatuh pada hari ini (0 = hari ini).
                $daysAhead = ($day - $todayIso + 7) % 7;
                $date = Carbon::today()->addDays($daysAhead)->format('Y-m-d');

                $days[] = $this->buildDayEntry($branchId, $doctor->id, (int) $day, $date, $ranges, $daysAhead);
            }

            return $this->buildDoctorCard($doctor->id, $doctor->name, $doctor->photo_url, $days);
        });
    }

    /**
     * Mode tanggal: hanya dokter yang praktik di $date (hari-nya cocok, tidak
     * sedang cuti, dan punya slot). Tiap dokter hanya punya 1 "day entry"
     * yang slot-nya dibuka langsung.
     */
    private function doctorsForDate($branchId, Carbon $date)
    {
        $iso = $date->dayOfWeekIso;
        $dateStr = $date->format('Y-m-d');

        $rawDoctors = RawDoctor::active()
            ->whereHas('schedules', fn ($q) => $q
                ->where('raw_branch_id', $branchId)
                ->where('type', RawDoctorSchedule::TYPE_WORK)
                ->where('day', $iso))
            ->with(['schedules' => fn ($q) => $q
                ->where('raw_branch_id', $branchId)
                ->where('type', RawDoctorSchedule::TYPE_WORK)
                ->where('day', $iso)
                ->orderBy('start_hour')])
            ->orderBy('name')
            ->get();

        $daysAhead = (int) Carbon::today()->diffInDays($date);

        return $rawDoctors
            ->map(function ($doctor) use ($branchId, $iso, $dateStr, $daysAhead) {
                // Dokter cuti di tanggal ini → tidak ditampilkan.
                if ($this->isOnLeave($doctor->id, $dateStr)) {
                    return null;
                }

                $ranges = $doctor->schedules
                    ->map(fn ($s) => substr($s->start_hour, 0, 5) . '–' . substr($s->end_hour, 0, 5))
                    ->all();

                $entry = $this->buildDayEntry($branchId, $doctor->id, $iso, $dateStr, $ranges, $daysAhead);

                // Tanpa slot sama sekali (mis. semua jam kena istirahat) → skip.
                if (empty($entry['slots'])) {
                    return null;
                }

                return $this->buildDoctorCard($doctor->id, $doctor->name, $doctor->photo_url, [$entry]);
            })
            ->filter()
            ->values();
    }

    /**
     * Bangun 1 entry hari: format jam, status kesibukan, dan daftar slot.
     * Slot computeTimeSlots dihitung sekali di sini lalu dipakai untuk status
     * sekaligus ditampilkan ke pasien.
     *
     * @param  array<int,string>  $ranges      Rentang jam sudah diformat "HH:MM–HH:MM".
     * @param  int                $daysAhead   Jarak hari dari hari ini (0 = hari ini) — untuk urutan "terdekat".
     */
    private function buildDayEntry($branchId, $doctorId, int $day, string $date, array $ranges, int $daysAhead): array
    {
        $slots = $this->computeTimeSlots($branchId, $doctorId, $date)['slots'];

        return [
            'day'        => $day,
            'ranges'     => $ranges,
            'status'     => $this->statusFromSlots($slots),
            'days_ahead' => $daysAhead,
            'slots'      => array_map(fn ($s) => [
                'time'      => $s['time'],
                'available' => $s['available'],
                'is_booked' => $s['is_booked'],
                'is_past'   => $s['is_past'],
            ], $slots),
        ];
    }

    /**
     * Susun data kartu dokter untuk flow "pilih hari → lihat jam":
     *  - days        : semua hari praktik (urut hari-of-week) beserta slot-nya.
     *  - default_day : hari yang dibuka otomatis — hari terdekat yang masih
     *                  punya jam kosong; kalau semua penuh, ambil terdekat.
     *
     * @param  array<int,array>  $days  Entry hari dari buildDayEntry.
     */
    private function buildDoctorCard($id, string $name, ?string $photoUrl, array $days): object
    {
        // Default: hari terdekat yang masih ada slot kosong (fallback terdekat).
        usort($days, fn ($a, $b) => $a['days_ahead'] <=> $b['days_ahead']);
        $defaultDay = null;
        foreach ($days as $d) {
            if (collect($d['slots'])->contains('available', true)) {
                $defaultDay = $d['day'];
                break;
            }
        }
        $defaultDay ??= $days[0]['day'] ?? null;

        // Tombol hari ditampilkan urut hari-of-week (Sen..Min) biar natural.
        usort($days, fn ($a, $b) => $a['day'] <=> $b['day']);

        return (object) [
            'id'             => $id,
            'name'           => $name,
            'photo_url'      => $photoUrl,
            'specialization' => $this->doctorSpecialization($name),
            'days'           => $days,
            'default_day'    => $defaultDay,
        ];
    }

    /**
     * Label spesialisasi diturunkan dari suffix nama (mis. "drg. X, Sp.Ort" →
     * "Spesialis Ortodonti"). Tanpa suffix "Sp." dianggap dokter gigi umum.
     * Ini heuristik — sumber sebenarnya cuma string nama; belum ada kolom khusus.
     */
    private function doctorSpecialization(string $name): string
    {
        // Suffix di akhir nama, mentoleransi penanda konsultan "(K)" & titik.
        if (preg_match('/\bsp\.?\s*([a-z]+)\s*(?:\([a-z]+\))?\s*\.?\s*$/iu', $name, $m)) {
            $code = mb_strtolower($m[1]);
            $map = [
                'ort'    => 'Spesialis Ortodonti',
                'kg'     => 'Spesialis Konservasi Gigi',
                'kga'    => 'Spesialis Kedokteran Gigi Anak',
                'bm'     => 'Spesialis Bedah Mulut',
                'bmm'    => 'Spesialis Bedah Mulut & Maksilofasial',
                'perio'  => 'Spesialis Periodonsia',
                'pros'   => 'Spesialis Prostodonsia',
                'prosto' => 'Spesialis Prostodonsia',
                'pm'     => 'Spesialis Penyakit Mulut',
                'rkg'    => 'Spesialis Radiologi Kedokteran Gigi',
            ];

            return $map[$code] ?? ('Spesialis ' . mb_strtoupper($m[1]));
        }

        return 'Dokter Gigi Umum';
    }

    /** True kalau dokter sedang cuti di tanggal $date (Y-m-d). */
    private function isOnLeave($doctorId, string $date): bool
    {
        return DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $doctorId)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Status kesibukan ringkas dari daftar slot yang sudah dihitung:
     * 'available' | 'almost' | 'full', atau null bila tak ada slot.
     * Slot berasal dari computeTimeSlots (sudah memperhitungkan istirahat
     * & pembatalan), dihitung sekali lalu dipakai ulang di sini.
     */
    private function statusFromSlots(array $slots): ?string
    {
        $total = count($slots);
        if ($total === 0) {
            return null;
        }

        $booked = 0;
        foreach ($slots as $slot) {
            if ($slot['is_booked']) {
                $booked++;
            }
        }
        $ratio = $booked / $total;

        if ($ratio >= 1.0) {
            return 'full';
        }
        if ($ratio >= self::ALMOST_FULL_RATIO) {
            return 'almost';
        }

        return 'available';
    }
}
