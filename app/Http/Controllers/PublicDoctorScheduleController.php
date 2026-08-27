<?php

namespace App\Http\Controllers;

use App\Models\RawBranch;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Halaman publik (tanpa login) yang menampilkan jadwal praktik dokter dalam
 * bentuk KALENDER MINGGUAN: satu kolom per hari (mis. Selasa–Minggu), tiap
 * kolom berisi daftar dokter yang praktik di hari itu beserta jam praktiknya.
 * Bisa difilter per cabang. Jadwal bersifat mingguan berulang (pola tetap tiap
 * minggu). Read-only — tak menulis ke DB.
 */
class PublicDoctorScheduleController extends Controller
{
    public function index(Request $request)
    {
        $branches = RawBranch::orderBy('name')->get(['id', 'name', 'slug']);

        // Satu skema slug dengan kolom raw_branch.slug (dipakai form Pasien
        // Baru). Fallback ke Str::slug(name) supaya link embed lama tetap jalan
        // untuk cabang yang slug-nya belum terisi.
        $slugOf = fn ($b) => $b->slug ?: Str::slug($b->name);

        // Cabang aktif dari ?cabang=. Bisa berupa:
        //   - "all"        → semua cabang (dokter dari seluruh cabang digabung).
        //   - <id>         → dari tombol switch dropdown.
        //   - nama/slug    → link embed per-cabang (mis. ?cabang=sunter), terkunci.
        $cabang     = $request->query('cabang');
        $lockBranch = false;
        $branchId   = null;

        if ($cabang) {
            if ($branches->contains('id', $cabang)) {
                $branchId = $cabang;
            } else {
                $slug  = Str::slug($cabang);
                $match = $branches->first(fn ($b) => $slugOf($b) === $slug)
                    ?? $branches->first(fn ($b) => Str::contains($slugOf($b), $slug) && $slug !== '');
                if ($match) {
                    $branchId   = $match->id;
                    $lockBranch = true;
                }
            }
        }

        // Default: cabang Bintaro (kalau ada), fallback ke cabang pertama.
        if (! $branchId) {
            $default  = $branches->first(fn ($b) => Str::contains(Str::lower($b->name), 'bintaro'));
            $branchId = ($default ?? $branches->first())?->id;
        }

        // Mode terkunci: sisakan hanya cabang terpilih di dropdown, dan pakai
        // slug sebagai value agar navigasi tetap mempertahankan kunci.
        $branchSlug = null;
        if ($lockBranch && $branchId) {
            $branches   = $branches->where('id', $branchId)->values();
            $branchSlug = $slugOf($branches->first());
        }

        // Cabang yang ditampilkan: selalu satu cabang.
        $targetBranches = $branches->where('id', $branchId);

        // Grid mingguan: array hari (urut hari-of-week) → daftar dokter praktik.
        $week = $this->buildWeekGrid($targetBranches);

        // Jumlah dokter unik yang praktik dalam seminggu (untuk baris ringkasan).
        $doctorCount = collect($week)
            ->flatMap(fn ($day) => collect($day->doctors)->pluck('id'))
            ->unique()
            ->count();

        $branchName = optional($branches->firstWhere('id', $branchId))->name;

        // Navigasi minggu via ?minggu= (offset dalam minggu; 0/kosong = minggu ini,
        // -1 = minggu lalu, 1 = minggu depan). Pola jadwal berulang tiap minggu,
        // jadi offset hanya menggeser tanggal yang tampil — dokter & jamnya tetap.
        $weekOffset = (int) $request->query('minggu', 0);
        $weekOffset = max(-52, min(52, $weekOffset));
        $weekStart  = now()->startOfWeek()->addWeeks($weekOffset);

        return view('public.doctor-schedule', compact(
            'branches', 'branchId', 'branchName', 'week', 'doctorCount',
            'lockBranch', 'branchSlug', 'weekOffset', 'weekStart'
        ));
    }

    /**
     * Bangun grid mingguan: kelompokkan dokter yang praktik per HARI.
     *
     * Untuk cabang target diambil semua dokter aktif dengan jadwal WORK, lalu
     * tiap (dokter, hari) jadi satu entry di kolom hari itu. Hanya hari yang
     * benar-benar ada praktiknya yang menjadi kolom — tidak ada kolom kosong
     * bawaan.
     *
     * @return array<int,object{iso:int,doctors:array}>
     */
    private function buildWeekGrid($targetBranches): array
    {
        $byDay = []; // iso => list of entry object

        foreach ($targetBranches as $b) {
            $rawDoctors = RawDoctor::active()
                ->whereHas('schedules', fn ($q) => $q
                    ->where('raw_branch_id', $b->id)
                    ->where('type', RawDoctorSchedule::TYPE_WORK))
                ->with(['schedules' => fn ($q) => $q
                    ->where('raw_branch_id', $b->id)
                    ->where('type', RawDoctorSchedule::TYPE_WORK)
                    ->orderBy('day')
                    ->orderBy('start_hour')])
                ->orderBy('name')
                ->get();

            foreach ($rawDoctors as $doctor) {
                // Kelompokkan rentang jam praktik per hari (dukung split shift).
                $rangesByDay = [];
                foreach ($doctor->schedules as $s) {
                    $rangesByDay[(int) $s->day][] = substr($s->start_hour, 0, 5) . '–' . substr($s->end_hour, 0, 5);
                }

                foreach ($rangesByDay as $day => $ranges) {
                    $byDay[$day][] = (object) [
                        'id'             => $doctor->id,
                        'name'           => $doctor->name,
                        'photo_url'      => $doctor->photo_url,
                        'specialization' => $this->doctorSpecialization($doctor->name),
                        'branch_name'    => $b->name,
                        'ranges'         => $ranges,
                    ];
                }
            }
        }

        // Urutkan kolom per hari-of-week, dan dokter dalam tiap kolom per nama.
        ksort($byDay);

        $week = [];
        foreach ($byDay as $iso => $entries) {
            usort($entries, fn ($a, $b) => strnatcasecmp($a->name, $b->name));
            $week[] = (object) [
                'iso'     => $iso,
                'doctors' => $entries,
            ];
        }

        return $week;
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
}
