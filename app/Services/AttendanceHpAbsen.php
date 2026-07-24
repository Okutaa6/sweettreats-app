<?php

namespace App\Services;

use App\Models\AttBranchIp;
use App\Models\AttFlag;
use App\Models\AttHpBukti;
use App\Models\AttScan;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mesin absen HP (selfie) — "mesin ke-6" yang nulis att_scan source='mobile'.
 * Reconciler/rekap/periode TIDAK disentuh: mereka baca att_scan.user_id.
 *
 * Prinsip (docs/rencana-absen-hp.md §3):
 *   • WiFi = SAKSI bukan GERBANG — absen tak pernah ditolak karena jaringan;
 *     IP tak dikenal = flag ip_asing, bukan error.
 *   • Jam SELALU server, jangan pernah percaya jam HP.
 *   • Orang fleksibel: absen = jejak, SEMUA flag di-suppress, engine tak menilai.
 *   • Hash scan pakai formula sendiri sha1('hp|user|waktu') — formula importer
 *     (branch|device|waktu) bakal tabrakan kalau 2 orang absen di detik sama.
 */
class AttendanceHpAbsen
{
    /** Samain reconciler: scan < 06:00 milik hari kerja sebelumnya. */
    private const DAWN_MINUTES = 360;

    /** Absen masuk sebelum jam ini = flag jam_janggal. */
    private const JAM_JANGGAL_SEBELUM = '05:00';

    /** Ambang pola_ip: ke-flag ip_asing >= sekian kali sebulan. */
    private const POLA_IP_AMBANG = 3;

    /**
     * Proses satu absen. $fotoPath sudah tersimpan oleh controller
     * (relative dari public/). Return ringkasan buat UI.
     *
     * @return array{scan_id:int, bukti_id:int, taken_at:string, branch_id:?string, cocok:bool, fleksibel:bool, flags:string[]}
     */
    public function absen(string $userId, string $jenis, string $fotoPath, string $ip, ?string $branchManual, ?UserDevice $device, ?string $ua = null): array
    {
        $now      = Carbon::now();
        $workDate = $this->workDate($now);
        $deviceLabel = $ua ? \App\Services\DeviceAuth::labelFromUa($ua) : null;
        $isDesktop   = $ua ? \App\Services\DeviceAuth::isDesktopUa($ua) : false;

        $match     = AttBranchIp::matchBranch($ip);
        $branchId  = $match ? $match->raw_branch_id : $branchManual;
        $fleksibel = $this->isFleksibel($userId, $workDate);

        // raw_branch_id di att_scan NOT NULL — kalau IP tak dikenal, UI wajib
        // nyertain pilihan cabang manual. Sampai sini kosong = bug di caller.
        if (!$branchId) {
            throw new RuntimeException('Cabang tidak diketahui: IP tak terdaftar dan tidak ada pilihan manual.');
        }

        $result = DB::transaction(function () use ($userId, $jenis, $fotoPath, $ip, $branchManual, $device, $now, $workDate, $match, $branchId, $fleksibel, $deviceLabel, $isDesktop) {

            // ── att_scan: idempoten per (user, detik) — double-tap tak dobel ──
            $hash = sha1('hp|' . $userId . '|' . $now->format('Y-m-d H:i:s'));
            $scan = $this->tanpaRaceDuplikat(fn () => AttScan::firstOrCreate(
                ['hash' => $hash],
                [
                    'raw_branch_id'  => $branchId,
                    'device_user_id' => 'HP',
                    'user_id'        => $userId,
                    'scanned_at'     => $now->format('Y-m-d H:i:s'),
                    'source'         => 'mobile',
                    'raw_row'        => ['ip' => $ip, 'jenis' => $jenis, 'cocok' => (bool) $match],
                    'created_at'     => $now,
                ]
            ));

            // firstOrCreate juga di bukti: double-tap Kirim di detik sama =
            // scan ke-dedup via hash, bukti jangan sampai lahir dobel.
            $bukti = $this->tanpaRaceDuplikat(fn () => AttHpBukti::firstOrCreate(
                ['att_scan_id' => $scan->id, 'user_id' => $userId, 'jenis' => $jenis],
                [
                    'photo_path'       => $fotoPath,
                    'ip'               => $ip,
                    'branch_id_cocok'  => $match ? $match->raw_branch_id : null,
                    'branch_id_manual' => $match ? null : $branchManual,
                    'device_id'        => $device ? $device->id : null,
                    'device_label'     => $deviceLabel,
                    'is_desktop'       => $isDesktop,
                    'taken_at'         => $now,
                ]
            ));

            if ($match) {
                $match->forceFill(['last_seen_at' => $now])->save();
            }

            $flags = $fleksibel ? [] : $this->evaluasiFlags($userId, $jenis, $workDate, $now, $bukti, $device);

            return [
                'scan_id'   => $scan->id,
                'bukti_id'  => $bukti->id,
                'taken_at'  => $now->format('Y-m-d H:i:s'),
                'branch_id' => $branchId,
                'cocok'     => (bool) $match,
                'fleksibel' => $fleksibel,
                'flags'     => $flags,
            ];
        });

        return $result;
    }

    /** Hari kerja milik jam ini — scan dini hari dilempar-balik (samain reconciler). */
    public function workDate(Carbon $at): string
    {
        $mins = $at->hour * 60 + $at->minute;
        return $mins < self::DAWN_MINUTES ? $at->copy()->subDay()->toDateString() : $at->toDateString();
    }

    /** Aturan aktif user di tanggal ini bertipe fleksibel? (att_user_rule per-ORANG, §3.3) */
    public function isFleksibel(string $userId, string $date): bool
    {
        return DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->where('ur.user_id', $userId)
            ->where('ur.effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $date);
            })
            ->where('wr.tipe', 'fleksibel')
            ->exists();
    }

    /**
     * Flag real-time saat submit. single_scan (lupa pulang) TIDAK di sini —
     * dia baru ketahuan besoknya, dievaluasi sweep harian terpisah.
     *
     * @return string[] jenis flag yang dinaikkan
     */
    private function evaluasiFlags(string $userId, string $jenis, string $workDate, Carbon $now, AttHpBukti $bukti, ?UserDevice $device): array
    {
        $raised = [];

        // IP tak dikenal → ip_asing
        if (!$bukti->branch_id_cocok) {
            AttFlag::raise($userId, $workDate, 'ip_asing', 'att_hp_bukti', $bukti->id);
            $raised[] = 'ip_asing';

            // pola: >= ambang kali sebulan (kalender) → pola_ip
            // Bulan dihitung dari WORK_DATE, bukan jam server — absen dini hari
            // tanggal 1 milik hari kerja bulan sebelumnya (dawn-pushback).
            $mulaiBulan = Carbon::parse($workDate)->startOfMonth()->toDateString();
            $n = AttFlag::where('user_id', $userId)->where('jenis', 'ip_asing')
                ->where('work_date', '>=', $mulaiBulan)->count();
            if ($n >= self::POLA_IP_AMBANG) {
                AttFlag::raise($userId, $workDate, 'pola_ip', 'att_hp_bukti', $bukti->id);
                $raised[] = 'pola_ip';
            }

            // pulang juga dari luar, dan masuk hari ini pun dari luar → luar_seharian
            if ($jenis === 'pulang') {
                [$dari, $sampai] = $this->rentangWorkDate($workDate);
                $masukLuar = AttHpBukti::where('user_id', $userId)->where('jenis', 'masuk')
                    ->whereNull('branch_id_cocok')
                    ->where('taken_at', '>=', $dari)->where('taken_at', '<', $sampai)
                    ->where('id', '!=', $bukti->id)
                    ->exists();
                if ($masukLuar) {
                    AttFlag::raise($userId, $workDate, 'luar_seharian', 'att_hp_bukti', $bukti->id);
                    $raised[] = 'luar_seharian';
                }
            }
        }

        // masuk kepagian ekstrem → jam_janggal
        if ($jenis === 'masuk' && $now->format('H:i') < self::JAM_JANGGAL_SEBELUM) {
            AttFlag::raise($userId, $workDate, 'jam_janggal', 'att_hp_bukti', $bukti->id);
            $raised[] = 'jam_janggal';
        }

        // absen dari laptop/PC (bukan HP) → perangkat_desktop (keputusan user
        // 2026-07-24: flag keras, bukan cuma info). Laptop di cabang + WiFi cocok
        // tetap absah — Anisa yang menilai; ini menyeret ke antrian, bukan blokir.
        if ($bukti->is_desktop) {
            AttFlag::raise($userId, $workDate, 'perangkat_desktop', 'att_hp_bukti', $bukti->id);
            $raised[] = 'perangkat_desktop';
        }

        // perangkat "diingat" yang sama dipakai akun LAIN di hari kerja sama → perangkat_ganda
        // DUA-DUANYA di-flag: yang absen duluan sama mencurigakannya dengan
        // yang belakangan — biar SPV lihat pasangannya utuh. KECUALI pasangan
        // yang fleksibel: invariant "fleksibel tak pernah di-flag" berlaku juga
        // di jalur tidak langsung ini (flag nyangkut = periode gaji kekunci).
        if ($device) {
            [$dari, $sampai] = $this->rentangWorkDate($workDate);
            $lain = AttHpBukti::where('device_id', $device->id)
                ->where('user_id', '!=', $userId)
                ->where('taken_at', '>=', $dari)->where('taken_at', '<', $sampai)
                ->get(['id', 'user_id']);
            if ($lain->isNotEmpty()) {
                AttFlag::raise($userId, $workDate, 'perangkat_ganda', 'att_hp_bukti', $bukti->id);
                $raised[] = 'perangkat_ganda';
                foreach ($lain->unique('user_id') as $b) {
                    if (! $this->isFleksibel($b->user_id, $workDate)) {
                        AttFlag::raise($b->user_id, $workDate, 'perangkat_ganda', 'att_hp_bukti', $b->id);
                    }
                }
            }
        }

        return $raised;
    }

    /**
     * Rentang datetime milik satu hari kerja (ikut dawn-pushback):
     * [06:00, besok 06:00) — HALF-OPEN, samakan reconciler. Jangan pakai
     * whereBetween (inklusif dua sisi): bukti tepat 06:00:00 bakal dimiliki
     * dua hari sekaligus.
     */
    public function rentangWorkDate(string $workDate): array
    {
        $from = Carbon::parse($workDate)->setTime(6, 0);
        return [$from, $from->copy()->addDay()];
    }

    /**
     * firstOrCreate tidak atomik — dua request paralel bisa dua-duanya lolos
     * cek lalu insert kedua nabrak UNIQUE. Tangkap duplicate-key (SQLSTATE
     * 23000) dan baca ulang: hasil akhirnya sama dengan "yang pertama menang".
     */
    private function tanpaRaceDuplikat(callable $op)
    {
        try {
            return $op();
        } catch (\Illuminate\Database\QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23000') {
                return $op(); // baris sudah ada → firstOrCreate kini menemukan
            }
            throw $e;
        }
    }
}
