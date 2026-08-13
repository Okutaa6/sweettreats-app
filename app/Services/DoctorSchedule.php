<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use Illuminate\Support\Facades\DB;

/**
 * "Dokter ini seharusnya praktik di mana dan jam berapa pada tanggal X" —
 * LINTAS CABANG.
 *
 * Alasan kelas ini ada (docs/rencana/rencana-akun-dokter-absensi-jadwal.md §3):
 * mesin absensi butuh jawaban untuk SATU HARI tanpa tahu cabangnya lebih dulu,
 * sedangkan `ResolvesDoctorAvailability::resolveWorkingRanges()` — mesin yang
 * dipakai pasien saat memesan — WAJIB diberi satu cabang.
 *
 * Logika jadwalnya TIDAK ditulis ulang di sini. Kelas ini cuma menemukan cabang
 * mana saja yang relevan di tanggal itu, lalu memanggil mesin yang sudah ada
 * untuk tiap cabang. Itu disengaja: aturan halusnya sudah mahal didapat —
 * istirahat dipotong dari jam kerja, `replace` mengganti jam reguler DAN
 * menembus cuti, `add` membuka hari walau dokter cuti atau hari itu biasanya
 * libur. Menyalinnya ke sini berarti dua salinan yang pasti berselisih, dan
 * yang kalah selalu yang tak dipakai pasien — yaitu absensi.
 *
 * ⚠️ PENOMORAN HARI. `raw_doctor_schedule.day` memakai ISO (1=Senin..7=Minggu),
 * sedangkan `att_work_rule_day.day` memakai Carbon dayOfWeek (0=Minggu..6=Sabtu).
 * Kelas ini sepenuhnya berada di sisi ISO dan TIDAK pernah menyentuh sisi
 * Carbon — aturan bertipe `jadwal` sengaja tak punya baris hari sama sekali.
 * Kalau kelak dua sisi itu dijembatani, konversinya WAJIB eksplisit: salah
 * sedikit menggeser jadwal semua dokter satu hari, dan gagalnya diam.
 */
class DoctorSchedule
{
    use ResolvesDoctorAvailability;

    /**
     * Jam praktik dokter di sebuah tanggal, dikelompokkan per cabang.
     * Cabang tanpa jam praktik tidak muncul — jadi array kosong = hari itu
     * dokternya memang tidak praktik di mana pun.
     *
     * @return array<string, array<int, array{start_hour:string,end_hour:string}>>
     *         raw_branch_id => daftar rentang
     */
    public function rangesPerBranchOn(string $doctorId, string $date): array
    {
        $out = [];
        foreach ($this->kandidatCabang($doctorId, $date) as $branchId) {
            $ranges = $this->resolveWorkingRanges($branchId, $doctorId, $date);
            if ($ranges->isNotEmpty()) {
                $out[$branchId] = $ranges->values()->all();
            }
        }
        return $out;
    }

    /** Dokter ini praktik di suatu tempat pada tanggal itu? */
    public function praktikPada(string $doctorId, string $date): bool
    {
        return $this->rangesPerBranchOn($doctorId, $date) !== [];
    }

    /**
     * Jam praktik paling awal & paling akhir hari itu, digabung lintas cabang.
     * Dipakai untuk menilai keterlambatan.
     *
     * @return array{0:?string,1:?string} [mulai H:i:s, selesai H:i:s]
     */
    public function rentangHarian(string $doctorId, string $date): array
    {
        $mulai = null;
        $usai  = null;
        foreach ($this->rangesPerBranchOn($doctorId, $date) as $ranges) {
            foreach ($ranges as $r) {
                $s = $this->jam($r['start_hour']);
                $e = $this->jam($r['end_hour']);
                if ($mulai === null || $s < $mulai) $mulai = $s;
                if ($usai  === null || $e > $usai)  $usai  = $e;
            }
        }
        return [$mulai, $usai];
    }

    /**
     * Cabang yang MUNGKIN punya jam praktik di tanggal ini — hasil saringan
     * kasar, bukan jawaban akhir. Yang menentukan tetap resolveWorkingRanges()
     * per cabang; ini cuma memperkecil daftar yang perlu ditanya.
     *
     * Dua sumber, dan keduanya wajib:
     *   • jadwal rutin di hari-dalam-seminggu yang sama (ISO 1..7)
     *   • pengecualian bertanggal — `add` bisa membuka cabang yang HARI ITU tak
     *     punya jadwal rutin sama sekali, jadi kalau hanya sumber pertama yang
     *     dipakai, hari tambahan dokter hilang tanpa jejak.
     *
     * @return array<int, string>
     */
    private function kandidatCabang(string $doctorId, string $date): array
    {
        $iso = \Carbon\Carbon::parse($date)->dayOfWeekIso;   // 1=Senin .. 7=Minggu

        $rutin = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->where('day', $iso)
            ->pluck('raw_branch_id');

        $khusus = collect();
        // Gerbang yang sama dengan mesin pemesanan: kalau fitur jadwal fleksibel
        // dimatikan, pengecualian TIDAK berlaku di sana — dan absensi harus ikut,
        // kalau tidak dua layar menjawab beda untuk tanggal yang sama.
        if (config('features.flexible_scheduling')) {
            $khusus = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
                ->where('date', $date)
                ->whereIn('type', [
                    RawDoctorScheduleException::TYPE_ADD,
                    RawDoctorScheduleException::TYPE_REPLACE,
                ])
                ->pluck('raw_branch_id');
        }

        return $rutin->merge($khusus)->filter()->unique()->values()->all();
    }

    /** Normalkan jam ke H:i:s supaya perbandingan string aman. */
    private function jam($v): string
    {
        return \Carbon\Carbon::parse($v)->format('H:i:s');
    }

    /**
     * Dokter yang ditautkan ke sekumpulan user — user_id => raw_doctor_id.
     * Dimuat sekali di awal rekonsiliasi, bukan per hari per orang.
     *
     * @param  array<int,string>|null  $userIds  NULL = semua yang tertaut
     * @return array<string,string>
     */
    public static function petaDokterUser(?array $userIds = null): array
    {
        return DB::table('users')
            ->whereNotNull('raw_doctor_id')
            ->when($userIds, fn ($q) => $q->whereIn('id', $userIds))
            ->pluck('raw_doctor_id', 'id')
            ->all();
    }
}
