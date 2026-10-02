<?php

namespace App\Services\Ortho;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Models\OrtCase;
use App\Models\RawDoctor;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Hari praktik dokter pasien ini — beserta jamnya dan seberapa penuh.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * KENAPA KELAS INI ADA
 * ────────────────────────────────────────────────────────────────────────────
 * Lembar "Buat Reservasi" di modul ini dulu menanyakan SATU KOTAK TANGGAL
 * kosong, lalu mengoper apa pun yang diketik ke modul Reservasi — di mana
 * Sales ditanyai tanggal lagi, kali ini dengan jadwal dokter yang sebenarnya.
 * Dua kali ditanya, dan yang pertama tanpa tahu apa-apa.
 *
 * Akibatnya Sales menelepon pasien sambil menebak: "Rabu bisa, Bu?" tanpa tahu
 * dokternya praktik hari apa, jam berapa, dan sudah berapa penuh.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * MEMAKAI ULANG, BUKAN MENULIS ULANG
 * ────────────────────────────────────────────────────────────────────────────
 * Seluruh jawabannya sudah ada di trait ResolvesDoctorAvailability milik modul
 * Reservasi: ia memotong jam istirahat (`raw_doctor_schedule` type=break),
 * cuti (`raw_doctor_leave`), dan pengecualian per tanggal
 * (`raw_doctor_schedule_exception`). Kelas ini TIDAK menghitung satu pun dari
 * itu sendiri — ia memanggil trait yang sama persis dengan yang dipakai form
 * Reservasi dan API slot.
 *
 * ⚠ Itu keputusan yang sengaja. Repo ini SUDAH punya tiga rumus kapasitas yang
 *   saling berbeda (DoctorController memakai `durasi_shift × 2` tanpa memotong
 *   istirahat dan tanpa menyaring reservasi batal). Menambah rumus keempat di
 *   sini akan membuat angka di layar ortho berbeda dari angka di layar
 *   Reservasi untuk hari yang sama — dan Sales melihat keduanya.
 *
 * Angka penuh diambil dari `sessions[*].available_count/total_count`, bukan
 * dari rumus kapasitas mana pun, supaya ia selalu cocok dengan grid slot yang
 * akan dilihat Sales satu layar kemudian.
 *
 * ────────────────────────────────────────────────────────────────────────────
 * DOKTER YANG TIDAK TERPETAKAN BUKAN KASUS PINGGIRAN
 * ────────────────────────────────────────────────────────────────────────────
 * Diukur di DB pengembangan: dari 42 kasus, hanya 12 yang `doctor_ref_id`-nya
 * bisa dipetakan lewat `raw_doctor.omnicare_doc_id`, dan 13 tidak terpetakan
 * sama sekali. Jadi dua dari tiga kali, layar ini TIDAK akan punya dokter.
 *
 * Karena itu `untuk()` mengembalikan alasan yang bisa dibaca manusia, bukan
 * sekadar daftar kosong. Lembarnya wajib tetap berguna tanpa dokter — kotak
 * tanggal bebas tetap ada, dan alasannya dikatakan apa adanya.
 */
class OrtJadwalDokter
{
    use ResolvesDoctorAvailability;

    /** Berapa hari praktik ke depan yang ditawarkan. */
    private const JUMLAH_HARI = 6;

    /**
     * Sejauh apa menengok ke depan. Dibatasi karena computeAvailableDates()
     * menjelajah 535 hari dan menanyai DB untuk tiap tanggal yang lolos —
     * kita cuma butuh enam yang pertama.
     */
    private const JELAJAH_HARI = 70;

    public function __construct(private OrtBranches $branches)
    {
    }

    /**
     * @return array{
     *     hari: list<array{tanggal:string, label:string, jam:?string, terisi:int, total:int, penuh:bool}>,
     *     dokter: ?string,
     *     sebab: ?string
     * }
     *     sebab terisi = daftar `hari` kosong, dan ini alasannya:
     *       'tanpa_dokter'   pasien belum punya doctor_ref_id
     *       'tak_terpetakan' ada kodenya tapi tidak ketemu di raw_doctor
     *       'tak_praktik'    dokternya tidak punya jadwal di cabang ini
     *       'tak_ada_hari'   terpetakan & berjadwal, tapi 10 minggu ke depan kosong
     */
    public function untuk(OrtCase $kasus, string $placeId, ?CarbonInterface $mulai = null): array
    {
        $kosong = ['hari' => [], 'dokter' => null, 'sebab' => null];

        $branchId = $this->branches->branchId($placeId);
        if (! $branchId) {
            return $kosong + ['sebab' => 'tak_praktik'];
        }

        if (! filled($kasus->doctor_ref_id)) {
            return array_merge($kosong, ['sebab' => 'tanpa_dokter']);
        }

        /*
         * Dua kegagalan yang BERBEDA, dan membedakannya penting: yang satu
         * diperbaiki tim IT (memetakan kode dokter), yang satu diperbaiki
         * operasional (mengisi jadwal praktik). Satu pesan untuk keduanya
         * mengirim Sales mengejar perbaikan yang salah.
         */
        $dokter = RawDoctor::where('omnicare_doc_id', (string) $kasus->doctor_ref_id)->first();
        if (! $dokter) {
            return array_merge($kosong, ['sebab' => 'tak_terpetakan']);
        }

        if (! $this->berjadwalDi($dokter->id, $branchId)) {
            return array_merge($kosong, ['dokter' => $dokter->name, 'sebab' => 'tak_praktik']);
        }

        /*
         * computeAvailableDates() mulai dari hari ini dan memberi SEMUA tanggal
         * praktik sampai 535 hari ke depan. Kita saring ke jendela yang masuk
         * akal lalu ambil enam pertama — menghitung jam untuk 70-an tanggal
         * berarti 70-an putaran kueri untuk sesuatu yang tak akan dilihat.
         */
        $dari   = Carbon::instance($mulai ?: Carbon::today())->startOfDay();
        $sampai = $dari->copy()->addDays(self::JELAJAH_HARI);

        $tanggal = collect($this->computeAvailableDates($branchId, $dokter->id))
            ->filter(fn ($d) => $d['date'] >= $dari->toDateString() && $d['date'] <= $sampai->toDateString())
            ->take(self::JUMLAH_HARI);

        if ($tanggal->isEmpty()) {
            return array_merge($kosong, [
                'dokter' => $dokter->name,
                'sebab'  => 'tak_ada_hari',
            ]);
        }

        $hari = [];
        foreach ($tanggal as $t) {
            $slot = $this->computeTimeSlots($branchId, $dokter->id, $t['date']);

            // Dijumlahkan dari SESI, bukan dihitung ulang dari jadwal mentah —
            // inilah angka yang akan dilihat Sales di grid slot nanti.
            $total  = 0;
            $luang  = 0;
            foreach (($slot['sessions'] ?? []) as $sesi) {
                $total += (int) ($sesi['total_count'] ?? 0);
                $luang += (int) ($sesi['available_count'] ?? 0);
            }

            $hari[] = [
                'tanggal' => $t['date'],
                'label'   => $t['label'],
                'jam'     => $this->rentangJam($slot['slots'] ?? []),
                'terisi'  => max(0, $total - $luang),
                'total'   => $total,
                'penuh'   => $total > 0 && $luang === 0,
            ];
        }

        return ['hari' => $hari, 'dokter' => $dokter->name, 'sebab' => null];
    }

    /** Kalimat siap pakai untuk tiap sebab — supaya layar tidak mengarang sendiri. */
    public function penjelasan(?string $sebab, ?string $namaDokter = null): ?string
    {
        $dok = $namaDokter ?: 'Dokter pasien ini';

        return match ($sebab) {
            'tanpa_dokter'   => 'Pasien ini belum punya dokter ortho tercatat, jadi hari praktiknya tidak bisa ditampilkan.',
            'tak_terpetakan' => 'Dokter pasien ini belum dipetakan ke data dokter kami, jadi hari praktiknya belum bisa ditampilkan.',
            'tak_praktik'    => $dok . ' belum punya jadwal praktik tercatat di cabang ini.',
            'tak_ada_hari'   => $dok . ' tidak punya hari praktik dalam 10 minggu ke depan di cabang ini.',
            default          => null,
        };
    }

    // ════════════════════════════════════════════════════════════════════════

    /**
     * Punyakah dokter ini jadwal apa pun di cabang itu?
     *
     * ⚠ Pencarian kodenya sengaja `->first()`, dan itu AMAN karena
     *   `raw_doctor.omnicare_doc_id` punya index UNIQUE
     *   (`uq_raw_doctor_omnicare`) — satu kode tidak mungkin menunjuk dua
     *   baris. Nama dokter MEMANG berkembar di dev (drg. Nugroho dan drg.
     *   Putri Moortiningsih masing-masing dua baris), tapi kembarannya
     *   ber-omnicare_doc_id NULL, jadi tak pernah ikut terjaring lewat kode.
     *
     *   Jembatan reservasi di ReservationController memilih di antara
     *   kandidat JAMAK karena ia juga punya cadangan pencocokan NAMA, dan di
     *   sanalah kembaran itu muncul. Kelas ini sengaja tidak memakai cadangan
     *   nama: menebak dokter berarti menebak hari praktik, dan hari praktik
     *   yang salah akan disebut ke pasien lewat telepon.
     */
    private function berjadwalDi(string $doctorId, string $branchId): bool
    {
        return \Illuminate\Support\Facades\DB::table('raw_doctor_schedule')
            ->where('raw_doctor_id', $doctorId)
            ->where('raw_branch_id', $branchId)
            ->exists();
    }

    /** "10:00 – 20:00" dari slot pertama dan terakhir. */
    private function rentangJam(array $slots): ?string
    {
        if (! $slots) {
            return null;
        }

        $awal  = $slots[0]['time'] ?? null;
        $akhir = $slots[count($slots) - 1]['end_time'] ?? null;

        return $awal && $akhir ? $awal . ' – ' . $akhir : null;
    }
}
