<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Engine rekonsiliasi: att_scan + aturan kerja → att_day (per user per hari kerja).
 *
 * Alur per (user, tanggal) — docs/rencana-rekonsiliasi-absensi.md §5.3:
 *   0a. GABUNG scan dari SEMUA cabang untuk user ini (§2.7, Rohma lintas cabang)
 *   0b. scan < ambang dini hari (default 06:00) → LEMPAR BALIK ke hari sebelumnya
 *       sebagai pulang (§2.8, Sunter jam 00:54)
 *   1.  dedup jarak <= N menit (default 5), lintas mesin
 *   2.  ambil aturan user (att_user_rule aktif) → baku / fleksibel
 *   3.  exception (att_work_rule_exception) menang atas aturan harian
 *   4.  (Fase C) form approved → cuti/izin/wfh; belum ada → skip
 *
 *   fleksibel : scan cuma penanda "masuk". Tak pernah alpa. Baris cuma dibuat kalau ada scan.
 *   baku      : libur → status libur; kerja + tak ada scan → ALPA; scan pertama > window
 *               check-in → telat (dorong jam pulang). Lembur HANYA dari form (Fase C).
 *   1 scan    → dianggap hadir, tak dinilai telat (§3.2).
 *
 * Idempoten: upsert per (user_id, work_date). Aman dijalankan ulang kapan saja.
 */
class AttendanceReconciler
{
    private int $dawnMinutes = 360;   // 06:00 — scan sebelum ini = pulang hari sebelumnya
    private int $dedupMinutes = 5;

    // Plafon telat: kalau scan pertama > window + plafon ini, dianggap BUKAN telat pagi
    // (lebih mungkin shift sore / lupa tap pagi) → status hadir, tak dihukum. Sama
    // filosofi "1 scan = hadir". Angka ini KEPUTUSAN KEBIJAKAN (§8) — default 180 menit.
    private int $telatCeilingMinutes = 180;

    /** @return array{users:int, days:int, per_status:array<string,int>} */
    public function reconcile(Carbon $from, Carbon $to): array
    {
        [$rules, $ruleDays] = $this->loadRules();
        $exceptions = $this->loadExceptions();
        $requests = $this->loadRequests($from, $to);   // user_id => [approved requests]

        // user_rule per user (bisa >1 baris historis)
        $userRules = DB::table('att_user_rule')->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->sortByDesc('effective_from')->values()->all());

        // home branch (buat resolve exception cabang)
        $homeBranch = DB::table('users')->pluck('raw_branch_id', 'id')->all();

        $perStatus = [];
        $dayCount = 0;
        $now = Carbon::now();

        foreach ($userRules as $userId => $urs) {
            $rows = $this->reconcileUser($userId, $urs, $rules, $ruleDays, $exceptions, $requests[$userId] ?? [], $homeBranch[$userId] ?? null, $from, $to, $now);
            foreach ($rows as $r) $perStatus[$r['status']] = ($perStatus[$r['status']] ?? 0) + 1;
            $dayCount += count($rows);
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('att_day')->upsert(
                    $chunk,
                    ['user_id', 'work_date'],
                    ['raw_branch_id', 'rule_id', 'scan_first', 'scan_last', 'scan_count', 'single_scan',
                     'cabang_terlibat', 'status', 'telat_menit', 'wajib_pulang', 'lembur_menit_disetujui', 'computed_at']
                );
            }
        }

        return ['users' => $userRules->count(), 'days' => $dayCount, 'per_status' => $perStatus];
    }

    private function reconcileUser($userId, array $urs, array $rules, array $ruleDays, array $exceptions, array $reqs, ?string $home, Carbon $from, Carbon $to, Carbon $now): array
    {
        // ambil scan [from 00:00 .. (to+1) 06:00) supaya scan dini hari (to+1) kebawa ke workday `to`
        $lo = $from->copy()->startOfDay();
        $hi = $to->copy()->addDay()->setTime(6, 0);
        $scans = DB::table('att_scan')
            ->where('user_id', $userId)
            ->where('scanned_at', '>=', $lo)->where('scanned_at', '<', $hi)
            ->orderBy('scanned_at')
            ->get(['scanned_at', 'raw_branch_id']);

        $workdays = $this->buildWorkdays($scans);   // 'Y-m-d' => ['scans'=>[Carbon], 'branches'=>[branch per scan]]

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $rule = $this->resolveRule($urs, $d, $rules);
            if (!$rule) continue;

            $day = $workdays[$key] ?? ['scans' => [], 'branches' => []];
            $scanList = $day['scans'];
            $n = count($scanList);

            // form approved yg nutup tanggal ini (§4: deklarasi manusia menang atas scan)
            [$absence, $lembur] = $this->requestsForDate($reqs, $key);

            // ── fleksibel: cuma penanda, tak pernah alpa ──
            if ($rule->tipe === 'fleksibel') {
                if ($absence) { $out[] = $this->row($userId, $key, $rule->id, $day, $absence, 0, null, $lembur, $now); continue; }
                if ($n === 0) continue;
                $out[] = $this->row($userId, $key, $rule->id, $day, 'hadir', 0, null, $lembur, $now);
                continue;
            }

            // ── baku ──
            [$libur, $masukAkhir, $pulang] = $this->resolveDay($rule, $d, $home, $ruleDays, $exceptions);

            if ($libur) {
                // kerja di hari libur cuma sah kalau ada form lembur (tambah_hari) → hadir + lembur
                if ($lembur > 0) { $out[] = $this->row($userId, $key, $rule->id, $day, 'hadir', 0, null, $lembur, $now); continue; }
                if ($n === 0) continue;               // hari tutup & tak ada scan → tak perlu baris
                $out[] = $this->row($userId, $key, $rule->id, $day, 'libur', 0, null, 0, $now);
                continue;
            }

            // hari kerja: form absen (cuti/izin/wfh/libur) menang → resolve alpa
            if ($absence) {
                $out[] = $this->row($userId, $key, $rule->id, $day, $absence, 0, null, $lembur, $now);
                continue;
            }

            if ($n === 0) {                            // hari kerja, tak ada scan & tak ada form → ALPA
                $out[] = $this->row($userId, $key, $rule->id, $day, 'alpa', 0, null, 0, $now);
                continue;
            }

            // ada scan di hari kerja
            $first = $scanList[0];
            $status = 'hadir'; $telat = 0; $wajibPulang = null;
            // telat hanya kalau ada >=2 scan (1 scan = hadir, §3.2) & scan pertama plausibel pagi
            if ($n >= 2 && $masukAkhir && $pulang && $first->format('H:i:s') <= $pulang) {
                $batas = Carbon::parse($key . ' ' . $masukAkhir);
                if ($first->gt($batas)) {
                    $menit = $first->diffInMinutes($batas);
                    // di atas plafon = scan pertama terlalu sore buat disebut telat pagi → hadir
                    if ($menit <= $this->telatCeilingMinutes) {
                        $telat = $menit;
                        $status = 'telat';
                        $wajibPulang = Carbon::parse($key . ' ' . $pulang)->addMinutes($telat)->format('H:i:s');
                    }
                }
            }
            $out[] = $this->row($userId, $key, $rule->id, $day, $status, $telat, $wajibPulang, $lembur, $now);
        }
        return $out;
    }

    /**
     * Form approved yg nutup tanggal ini.
     * @return array{0:?string, 1:int} [tipe_absen (cuti/izin/wfh/libur) atau null, total_menit_lembur]
     */
    private function requestsForDate(array $reqs, string $date): array
    {
        $absence = null; $lembur = 0;
        foreach ($reqs as $r) {
            if ($date < $r->tanggal_from || $date > $r->tanggal_to) continue;
            if ($r->tipe === 'lembur') {
                $lembur += (int) $r->claimed_menit;
            } elseif ($absence === null) {   // absen pertama yg cocok menang
                $absence = $r->tipe;
            }
        }
        return [$absence, $lembur];
    }

    /** Bangun workday dari scan mentah: lempar-balik dini hari + dedup. */
    private function buildWorkdays($scans): array
    {
        $wd = [];
        foreach ($scans as $s) {
            $at = Carbon::parse($s->scanned_at);
            $mins = $at->hour * 60 + $at->minute;
            $date = $mins < $this->dawnMinutes ? $at->copy()->subDay()->toDateString() : $at->toDateString();
            $wd[$date]['raw'][] = ['at' => $at, 'branch' => $s->raw_branch_id];
        }
        $out = [];
        foreach ($wd as $date => $day) {
            usort($day['raw'], fn ($a, $b) => $a['at'] <=> $b['at']);
            $keptAt = []; $keptBr = [];
            foreach ($day['raw'] as $r) {
                if ($keptAt && $r['at']->diffInMinutes(end($keptAt)) <= $this->dedupMinutes) continue;
                $keptAt[] = $r['at']; $keptBr[] = $r['branch'];
            }
            $out[$date] = ['scans' => $keptAt, 'branches' => $keptBr];
        }
        return $out;
    }

    private function row($userId, string $date, $ruleId, array $day, string $status, int $telat, ?string $wajibPulang, int $lemburMenit, Carbon $now): array
    {
        $scans = $day['scans']; $branches = $day['branches'];
        $n = count($scans);
        return [
            'user_id' => $userId,
            'raw_branch_id' => $branches[0] ?? null,
            'work_date' => $date,
            'rule_id' => $ruleId,
            'scan_first' => $n ? $scans[0]->format('Y-m-d H:i:s') : null,
            'scan_last' => $n ? end($scans)->format('Y-m-d H:i:s') : null,
            'scan_count' => $n,
            'single_scan' => $n === 1 ? 1 : 0,
            'cabang_terlibat' => json_encode(array_values(array_unique($branches))),
            'status' => $status,
            'telat_menit' => $telat,
            'wajib_pulang' => $wajibPulang,
            'lembur_menit_disetujui' => $lemburMenit,
            'computed_at' => $now->format('Y-m-d H:i:s'),
        ];
    }

    /** Form APPROVED yg overlap rentang, grup per user. */
    private function loadRequests(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('att_request')
            ->where('status', 'approved')
            ->where('tanggal_from', '<=', $to->toDateString())
            ->where('tanggal_to', '>=', $from->toDateString())
            ->get(['user_id', 'tipe', 'tanggal_from', 'tanggal_to', 'claimed_menit']);
        $out = [];
        foreach ($rows as $r) $out[$r->user_id][] = $r;
        return $out;
    }

    private function resolveRule(array $urs, Carbon $date, array $rules)
    {
        foreach ($urs as $ur) {   // sudah diurut effective_from DESC
            if ($ur->effective_from <= $date->toDateString()
                && ($ur->effective_to === null || $ur->effective_to >= $date->toDateString())) {
                return $rules[$ur->rule_id] ?? null;
            }
        }
        return null;
    }

    /** @return array{0:bool,1:?string,2:?string} [libur, jam_masuk_akhir, jam_pulang] */
    private function resolveDay($rule, Carbon $date, ?string $home, array $ruleDays, array $exceptions): array
    {
        $dow = $date->dayOfWeek;   // 0=Minggu..6=Sabtu
        $rd = $ruleDays[$rule->id][$dow] ?? null;
        $libur = $rd ? (bool) $rd->libur : false;
        $masukAkhir = $rd?->jam_masuk_akhir;
        $pulang = $rd?->jam_pulang;

        // exception: cabang NULL (nasional) atau cabang == home. Ambil yg terbaru cocok.
        $ds = $date->toDateString();
        foreach ($exceptions as $e) {
            if ($e->tgl_from <= $ds && $e->tgl_to >= $ds
                && ($e->raw_branch_id === null || $e->raw_branch_id === $home)) {
                if ($e->libur) return [true, null, null];
                return [false, $e->jam_masuk ?? $masukAkhir, $e->jam_pulang ?? $pulang];
            }
        }
        return [$libur, $masukAkhir, $pulang];
    }

    private function loadRules(): array
    {
        $rules = DB::table('att_work_rule')->get()->keyBy('id')->all();
        $ruleDays = [];
        foreach (DB::table('att_work_rule_day')->get() as $rd) {
            $ruleDays[$rd->rule_id][$rd->day] = $rd;
        }
        return [$rules, $ruleDays];
    }

    private function loadExceptions(): array
    {
        return DB::table('att_work_rule_exception')->orderByDesc('tgl_from')->get()->all();
    }
}
