<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Engine rekonsiliasi: att_scan + aturan kerja → att_day (per user per hari kerja).
 *
 * Alur per (user, tanggal) — docs/rencana/rencana-rekonsiliasi-absensi.md §5.3:
 *   0a. GABUNG scan dari SEMUA cabang untuk user ini (§2.7, Rohma lintas cabang)
 *   0b. scan < ambang dini hari (default 06:00) → LEMPAR BALIK ke hari sebelumnya
 *       sebagai pulang (§2.8, Sunter jam 00:54)
 *   1.  dedup jarak <= N menit (default 5), lintas mesin
 *   2.  ambil aturan user (att_user_rule aktif) → baku / fleksibel / jadwal
 *   3.  exception (att_work_rule_exception) menang atas aturan harian —
 *       dicocokkan ke CABANG BERTUGAS hari itu (rolling menang atas cabang
 *       asal), sebab "klinik ini tutup" berlaku bagi siapa pun yang hari itu
 *       ada di sana. Cabang asal tetap dipakai untuk penguncian periode gaji.
 *   4.  (Fase C) form approved → cuti/izin/wfh; belum ada → skip
 *
 *   fleksibel : scan cuma penanda "masuk". Tak pernah alpa. Baris cuma dibuat kalau ada scan.
 *   baku      : libur → status libur; kerja + tak ada scan → ALPA; scan pertama > window
 *               check-in → telat (dorong jam pulang). Lembur HANYA dari form (Fase C).
 *   jadwal    : DOKTER. Jam kerja tidak disimpan di aturan — dibaca dari jadwal
 *               praktik (raw_doctor_schedule + jadwal khusus + cuti dokter) lewat
 *               users.raw_doctor_id, memakai mesin yang sama dengan pemesanan
 *               pasien. Tak praktik → tak ada baris (atau `libur` kalau ternyata
 *               datang). Praktik + scan → hadir; telat dicatat sebagai INFORMASI
 *               dan TIDAK menurunkan wajib_pulang (konsekuensi dokter telat itu
 *               pasien menunggu, bukan potong gaji). Praktik TANPA scan → belum
 *               divonis, cuma dihitung & dilaporkan sampai cara bayar dokter
 *               dijawab Finance. Lihat rencana-akun-dokter-absensi-jadwal.md.
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

    // Toleransi sanding klaim-vs-scan: klaim boleh melebihi dukungan scan sebesar ini
    // (pembulatan/tap telat) sebelum ditandai 'selisih'. Default 30 menit.
    private int $claimToleranceMinutes = 30;

    /**
     * Sanding klaim lembur vs scan untuk SATU (user, tanggal) — dipakai saat pengaju
     * submit form lembur (§2.11, §4). Hasilnya INFORMASI buat approver, BUKAN vonis:
     * sistem tak pernah approve/reject sendiri.
     *
     * Dukungan scan (jam):        scan_last − jam_lembur_mulai (aturan user hari itu).
     * Dukungan scan (tambah_hari): rentang scan (last − first), hari libur tak punya baseline.
     *
     * @return array{0:?string,1:array} [verifikasi (nyambung|selisih|tidak_bisa_dicek|null), scan_evidence]
     *   null = klaim menit tak diketahui (jam kosong) → evidence tetap di-snapshot.
     */
    public function checkLemburClaim(string $userId, string $date, ?int $claimedMenit, string $subTipe = 'jam'): array
    {
        $d = Carbon::parse($date);
        $lo = $d->copy()->startOfDay();
        $hi = $d->copy()->addDay()->setTime(6, 0);   // scan dini hari besok kebawa ke workday ini (§2.8)
        $scans = DB::table('att_scan')
            ->where('user_id', $userId)
            ->where('scanned_at', '>=', $lo)->where('scanned_at', '<', $hi)
            ->orderBy('scanned_at')
            ->get(['scanned_at', 'raw_branch_id']);

        $day = $this->buildWorkdays($scans)[$date] ?? ['scans' => [], 'branches' => []];
        $scanList = $day['scans'];
        $n = count($scanList);

        $evidence = [
            'work_date'   => $date,
            'scan_first'  => $n ? $scanList[0]->format('Y-m-d H:i:s') : null,
            'scan_last'   => $n ? end($scanList)->format('Y-m-d H:i:s') : null,
            'scan_count'  => $n,
            'sub_tipe'    => $subTipe,
            'supported_menit' => null,
        ];

        // tak ada scan → tak bisa dicek (WFH, lupa tap, hari libur tanpa scan): foto jadi satu-satunya bukti (§4)
        if ($n === 0) {
            return ['tidak_bisa_dicek', $evidence];
        }

        [$rules, $ruleDays] = $this->loadRules();
        $urs = DB::table('att_user_rule')->where('user_id', $userId)
            ->orderByDesc('effective_from')->get()->all();
        $rule = $this->resolveRule($urs, $d, $rules);

        // office/fleksibel tak dinilai dari scan (§3.3) → tak bisa dicek walau ada scan
        if (! $rule || $rule->tipe === 'fleksibel') {
            return ['tidak_bisa_dicek', $evidence];
        }

        $last = end($scanList);
        if ($subTipe === 'tambah_hari') {
            $supported = $n >= 2 ? $last->diffInMinutes($scanList[0]) : 0;
        } else {
            $rd = $ruleDays[$rule->id][$d->dayOfWeek] ?? null;
            $base = $rd->jam_lembur_mulai ?? $rd->jam_pulang ?? null;
            if (! $base) {
                return ['tidak_bisa_dicek', $evidence];   // aturan tak punya baseline
            }
            $baseAt = Carbon::parse($date . ' ' . $base);
            $supported = $last->gt($baseAt) ? $last->diffInMinutes($baseAt) : 0;
        }
        $evidence['supported_menit'] = $supported;

        if ($claimedMenit === null) {
            return [null, $evidence];   // klaim tanpa angka → evidence saja, verifikasi kosong
        }

        // klaim di dalam dukungan scan (+ toleransi) = nyambung; di atasnya = selisih (perlu dicek)
        $verifikasi = $claimedMenit <= $supported + $this->claimToleranceMinutes ? 'nyambung' : 'selisih';
        return [$verifikasi, $evidence];
    }

    /**
     * Saran isi form lembur dari scan (§7 Fase C — prefill). Untuk (user, tanggal):
     * jam_from = baseline lembur aturan user (GS 18:00 / FO-Asdent 19:00),
     * jam_to   = scan terakhir hari itu. NULL kalau tak ada scan, office/fleksibel,
     * atau scan belum melewati baseline (tak ada lembur untuk disarankan).
     * Form TETAP wajib diisi manual — ini cuma pintu masuk yang sudah terisi.
     *
     * @return array{jam_from:string, jam_to:string, scan_last:string}|null
     */
    public function suggestLembur(string $userId, string $date): ?array
    {
        $d = Carbon::parse($date);
        $lo = $d->copy()->startOfDay();
        $hi = $d->copy()->addDay()->setTime(6, 0);
        $scans = DB::table('att_scan')->where('user_id', $userId)
            ->where('scanned_at', '>=', $lo)->where('scanned_at', '<', $hi)
            ->orderBy('scanned_at')->get(['scanned_at', 'raw_branch_id']);

        $day = $this->buildWorkdays($scans)[$date] ?? ['scans' => []];
        if (!$day['scans']) return null;
        $last = end($day['scans']);

        [$rules, $ruleDays] = $this->loadRules();
        $urs = DB::table('att_user_rule')->where('user_id', $userId)
            ->orderByDesc('effective_from')->get()->all();
        $rule = $this->resolveRule($urs, $d, $rules);
        if (!$rule || $rule->tipe === 'fleksibel') return null;   // office tak dinilai scan (§3.3)

        $rd = $ruleDays[$rule->id][$d->dayOfWeek] ?? null;

        // ── Lembur DATANG-AWAL (keputusan Ekky 2026-08-05) ──
        // Kasus nyata operasional: jadwal masuk 10:00, orangnya absen 08:00
        // untuk persiapan. Dua jam itu kerja, dan selama ini sama sekali tak
        // terbaca — mesin saran cuma mengenal sisi pulang.
        //
        // Patokannya jam_masuk_AWAL (batas paling pagi jadwal itu), bukan
        // jam_masuk_akhir yang cuma ambang telat. Kalau memakai ambang telat,
        // orang yang datang tepat waktu pun akan tampak lembur.
        //
        // Ini USULAN, bukan vonis: datang awal tak selalu berarti bekerja —
        // bisa jadi menghindari macet atau menunggu. Yang memutuskan tetap
        // manusia lewat form + persetujuan atasan.
        $awal = null;
        $mulaiJadwal = $rd->jam_masuk_awal ?? null;
        if ($mulaiJadwal && ! ($rd->libur ?? false)) {
            $first = $day['scans'][0];
            $mulaiAt = Carbon::parse($date . ' ' . $mulaiJadwal);
            // minimal 30 menit supaya datang 5 menit lebih pagi tak jadi tawaran
            if ($first->toDateString() === $date && $first->lt($mulaiAt)
                && $first->diffInMinutes($mulaiAt) >= 30) {
                $awal = [
                    'jam_from' => $first->format('H:i'),
                    'jam_to'   => substr((string) $mulaiJadwal, 0, 5),
                    'menit'    => $first->diffInMinutes($mulaiAt),
                ];
            }
        }

        // ── Lembur PULANG-LAMBAT (perilaku lama) ──
        $lambat = null;
        $base = $rd->jam_lembur_mulai ?? $rd->jam_pulang ?? null;
        if ($base) {
            $baseAt = Carbon::parse($date . ' ' . $base);
            // scan pulang lintas tengah malam (di-pushback dari hari berikut):
            // field jam (time-only) tak bisa mewakili → biar diisi manual.
            if ($last->gt($baseAt) && $last->toDateString() === $date) {
                $lambat = [
                    'jam_from' => substr($base, 0, 5),
                    'jam_to'   => $last->format('H:i'),
                    'menit'    => $baseAt->diffInMinutes($last),
                ];
            }
        }

        if (! $awal && ! $lambat) return null;

        // Yang dikembalikan sebagai isian utama: yang paling lama. Keduanya
        // tetap dikirim supaya layar bisa menawarkan pilihan kalau hari itu
        // memang datang lebih awal DAN pulang lebih lambat.
        $utama = $lambat;
        if ($awal && (! $lambat || $awal['menit'] > $lambat['menit'])) $utama = $awal;

        return [
            'jam_from'  => $utama['jam_from'],
            'jam_to'    => $utama['jam_to'],
            'scan_last' => $last->format('H:i'),
            'sisi'      => $utama === $awal ? 'datang_awal' : 'pulang_lambat',
            'opsi'      => array_filter([
                'datang_awal'   => $awal,
                'pulang_lambat' => $lambat,
            ]),
        ];
    }

    /**
     * @param  string|null  $onlyUser  batasi ke satu user (dipakai hook ACC
     *         backdate — recalc satu orang, bukan seluruh roster). NULL = semua.
     * @return array{users:int, days:int, per_status:array<string,int>}
     */
    public function reconcile(Carbon $from, Carbon $to, ?string $onlyUser = null): array
    {
        [$rules, $ruleDays] = $this->loadRules();
        $exceptions = $this->loadExceptions();
        $requests = $this->loadRequests($from, $to);   // user_id => [approved requests]
        $closedPeriods = $this->loadClosedPeriods();   // periode di-tutup finance → att_day BEKU (D2)

        // user_rule per user (bisa >1 baris historis)
        $userRules = DB::table('att_user_rule')
            ->when($onlyUser, fn ($q) => $q->where('user_id', $onlyUser))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->sortByDesc('effective_from')->values()->all());

        // Cabang ASAL. Dipakai untuk penguncian periode gaji (isDateLocked /
        // hapusHariBasi) dan sebagai dasar cabang bertugas kalau tak ada rolling.
        $homeBranch = DB::table('users')->pluck('raw_branch_id', 'id')->all();

        // Penempatan sementara (rolling), dimuat SEKALI. Cabang bertugas itu
        // per-orang per-TANGGAL; memanggil positionBranchOn() di dalam loop
        // harian berarti jumlah-orang × jumlah-hari kueri (puluhan ribu untuk
        // sebulan penuh). Pola yang sama dengan loadRules()/loadExceptions().
        $rollingBranch = CabangBertugas::muat($onlyUser);

        // Penautan akun→dokter, dimuat SEKALI (bukan per orang per hari).
        // Kolomnya lahir bareng akun dokter; environment yang belum apply
        // SQL-nya jangan sampai bikin rekonsiliasi seluruh roster gagal.
        $dokterUser = \Illuminate\Support\Facades\Schema::hasColumn('users', 'raw_doctor_id')
            ? DoctorSchedule::petaDokterUser()
            : [];

        // Akumulator temuan yang tak menghasilkan baris att_day tapi WAJIB
        // terlihat — diam tanpa hitungan itu kegagalan-senyap.
        $laporan = (object) [
            'praktikTanpaScan'   => [],
            'hadirDiLuarJadwal'  => [],
            'cabangBedaJadwal'   => [],
            'tanpaDokter'        => [],
        ];

        $perStatus = [];
        $dayCount = 0;
        $basi = 0;
        $now = Carbon::now();

        foreach ($userRules as $userId => $urs) {
            $rows = $this->reconcileUser($userId, $urs, $rules, $ruleDays, $exceptions, $requests[$userId] ?? [], $homeBranch[$userId] ?? null, $from, $to, $now, $closedPeriods, $dokterUser[$userId] ?? null, $laporan, $rollingBranch[$userId] ?? []);
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

            $basi += $this->hapusHariBasi($userId, $rows, $homeBranch[$userId] ?? null, $from, $to, $closedPeriods);
        }

        // Orang yang PUNYA scan di rentang ini tapi tak punya att_user_rule sama
        // sekali tak pernah masuk loop di atas — scan-nya diam-diam tak pernah
        // jadi hari kerja, dan orangnya hilang total dari rekap. Ini yang dulu
        // menelan 538 scan Head IT tanpa satu pun pesan. Sekarang dilaporkan.
        // Laporan scan-yatim hanya relevan pada run penuh (scheduler). Run
        // ter-scope satu user (hook ACC) tak perlu menyapu seluruh att_scan.
        $tanpaAturan = $onlyUser ? [] : DB::table('att_scan as s')
            ->leftJoin('att_user_rule as ur', 'ur.user_id', '=', 's.user_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->whereBetween('s.scanned_at', [
                $from->copy()->startOfDay(),
                $to->copy()->addDay()->setTime(6, 0),
            ])
            ->whereNotNull('s.user_id')
            ->whereNull('ur.id')
            ->distinct()
            ->pluck('u.nama', 's.user_id')
            ->filter()
            ->values()
            ->all();

        return [
            'users' => $userRules->count(),
            'days' => $dayCount,
            'per_status' => $perStatus,
            'tanpa_aturan' => $tanpaAturan,
            'basi_dibuang' => $basi,

            // ── Temuan aturan `jadwal` (dokter) ──
            // Tak satu pun menghasilkan baris att_day, jadi tanpa dilaporkan di
            // sini mereka betul-betul tak terlihat di mana pun.
            'dokter_praktik_tanpa_scan'  => $laporan->praktikTanpaScan,
            'dokter_hadir_di_luar_jadwal'=> $laporan->hadirDiLuarJadwal,
            'dokter_cabang_beda_jadwal'  => $laporan->cabangBedaJadwal,
            'dokter_tak_tertaut'         => array_keys($laporan->tanpaDokter),
        ];
    }

    /**
     * Buang baris att_day yang SUDAH TIDAK dihasilkan lagi oleh reconcile.
     *
     * Reconciler cuma meng-upsert; jalur yang `continue` (tak meng-emit baris)
     * meninggalkan baris lama apa adanya. Akibat nyatanya:
     *
     *   - HR menambahkan libur (mis. exception Lebaran / akreditasi) SETELAH
     *     reconcile jalan. Reconcile ulang kini melewati hari itu, tapi 5 baris
     *     'alpa' lama tetap menempel di kartu karyawan dan di rekap Finance
     *     selamanya — langsung memotong gaji orang yang tidak bolos.
     *   - Karyawan dipindah ke aturan 'fleksibel' (promosi ke kantor) dengan
     *     effective_from mundur. Jalur fleksibel tak meng-emit apa-apa saat tak
     *     ada scan, jadi alpa lamanya tak pernah hilang — padahal orang
     *     fleksibel TAK BOLEH punya alpa sama sekali (§3.3).
     *
     * Tanggal di dalam periode gaji yang SUDAH DITUTUP sengaja dilewati: angka
     * yang sudah di-sign-off tak boleh berubah, meski reconcile tak lagi
     * menghasilkannya.
     *
     * @return int jumlah baris yang dibuang
     */
    private function hapusHariBasi($userId, array $rows, ?string $home, Carbon $from, Carbon $to, array $closedPeriods): int
    {
        $dihasilkan = array_flip(array_column($rows, 'work_date'));

        $adaSekarang = DB::table('att_day')
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('work_date')
            ->all();

        $hapus = [];
        foreach ($adaSekarang as $tgl) {
            $t = substr((string) $tgl, 0, 10);
            if (isset($dihasilkan[$t])) continue;                        // masih dihasilkan
            if ($this->isDateLocked($t, $home, $closedPeriods)) continue; // periode tutup = beku
            $hapus[] = $t;
        }

        if (! $hapus) return 0;

        DB::table('att_day')->where('user_id', $userId)->whereIn('work_date', $hapus)->delete();

        return count($hapus);
    }

    /**
     * @param  ?string  $home     cabang ASAL (users.raw_branch_id). Dipakai untuk
     *         penguncian periode gaji — JANGAN ditukar dengan cabang bertugas.
     * @param  array    $rolling  baris penempatan sementara milik user ini,
     *         hasil pramuat CabangBertugas::muat(). Dipakai HANYA untuk
     *         mencocokkan libur per-cabang.
     */
    private function reconcileUser($userId, array $urs, array $rules, array $ruleDays, array $exceptions, array $reqs, ?string $home, Carbon $from, Carbon $to, Carbon $now, array $closedPeriods = [], ?string $dokterId = null, ?\stdClass $laporan = null, array $rolling = []): array
    {
        $laporan ??= (object) ['praktikTanpaScan' => [], 'hadirDiLuarJadwal' => [], 'cabangBedaJadwal' => [], 'tanpaDokter' => []];
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
            // periode di-tutup finance → att_day tanggal ini BEKU: jangan emit baris
            // (upsert tak menyentuhnya) supaya angka yg sudah di-sign-off tak berubah (D2).
            if ($this->isDateLocked($key, $home, $closedPeriods)) continue;
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
                if ($n === 0) {
                    // Hari libur TERJADWAL (mis. Minggu & Senin bagi orang
                    // kantor) ditandai `libur`, bukan dibiarkan kosong. Kosong
                    // itu ambigu — tak bisa dibedakan dari "belum ada datanya",
                    // padahal Ekky minta tukar hari punya laporan yang terbaca:
                    // hari libur pengganti harus terlihat KONTRAS dengan libur
                    // biasa. Aturan fleksibel tanpa konfigurasi hari (Executive)
                    // tetap tak menghasilkan baris apa pun — memang tak
                    // dijadwalkan.
                    $rd = $ruleDays[$rule->id][$d->dayOfWeek] ?? null;
                    if ($rd && ($rd->libur ?? false)) {
                        $out[] = $this->row($userId, $key, $rule->id, $day, 'libur', 0, null, $lembur, $now);
                    }
                    continue;
                }
                $out[] = $this->row($userId, $key, $rule->id, $day, 'hadir', 0, null, $lembur, $now);
                continue;
            }

            // ── jadwal (dokter): jam kerja dibaca dari JADWAL PRAKTIK ──
            // Aturan bertipe ini tak membawa jam sendiri. Yang menentukan hari
            // ini kerja atau tidak adalah raw_doctor_schedule + jadwal khusus +
            // cuti dokter — sumber yang SAMA dengan yang dipakai pasien saat
            // memesan. Satu sumber kebenaran: jadwal diubah di halaman Dokter,
            // penilaian absensi ikut berubah hari itu juga.
            if ($rule->tipe === 'jadwal') {
                $out = array_merge($out, $this->hariDokter(
                    $userId, $key, $rule, $day, $absence, $lembur, $now, $dokterId, $laporan
                ));
                continue;
            }

            // ── baku ──
            // Libur dicocokkan ke cabang tempat orang ini BERTUGAS hari itu,
            // bukan cabang asalnya — selesai di memori, nol kueri per hari.
            $cabangKerja = CabangBertugas::pada($rolling, $key, $home);
            [$libur, $masukAkhir, $pulang] = $this->resolveDay($rule, $d, $cabangKerja, $ruleDays, $exceptions);

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
                // TAPI hari yang belum lewat tak bisa dinilai bolos: hari ini masih
                // berjalan (orangnya bisa tap nanti), besok belum terjadi. Tanpa
                // penjaga ini sisa bulan berjalan ikut ditandai ALPA — karyawan
                // lihat dirinya "bolos" di tanggal yang belum datang, dan angka
                // "tidak masuk" di rekap Finance ikut menggelembung.
                if ($key >= $now->toDateString()) continue;

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
     * Satu hari untuk aturan bertipe `jadwal` (dokter).
     *
     * Bedanya dengan `baku` bukan cuma sumber jamnya, tapi APA ARTI hari kosong.
     * Karyawan `baku` yang tak scan di hari kerja = alpa, dan alpa itu alat
     * pemotong gaji. Dokter kemungkinan dibayar per tindakan (rencana §5) —
     * kalau benar, "alpa" jadi label tanpa konsekuensi yang cuma menakut-nakuti
     * rekap Finance. Pertanyaan itu BELUM terjawab (menunggu Finance), jadi
     * sikapnya: hari praktik tanpa scan TIDAK menghasilkan baris sama sekali,
     * tapi DIHITUNG dan dilaporkan. Diam tanpa hitungan itu kegagalan-senyap;
     * diam DENGAN hitungan itu sikap yang bisa dipertanggungjawabkan.
     *
     * Begitu jawabannya datang, cukup ubah cabang `$adaJadwal && $n === 0` di
     * bawah lalu reconcile ulang — `hapusHariBasi()` sudah membereskan baris
     * lama, jadi tak ada yang perlu dibersihkan dengan tangan.
     *
     * @param  array  $laporan  akumulator lintas-hari (by reference lewat objek)
     * @return array<int,array> nol atau satu baris att_day
     */
    private function hariDokter(
        string $userId,
        string $date,
        $rule,
        array $day,
        ?string $absence,
        int $lembur,
        Carbon $now,
        ?string $dokterId,
        \stdClass $laporan
    ): array {
        $n = count($day['scans']);

        // Akun bertipe `jadwal` TAPI tak tertaut ke dokter mana pun. Tak ada
        // jadwal yang bisa dibaca, jadi hari ini tak bisa dinilai sama sekali.
        // Ini salah pasang, bukan keadaan normal — laporkan, jangan tebak.
        if (! $dokterId) {
            $laporan->tanpaDokter[$userId] = true;
            return $n ? [$this->row($userId, $date, $rule->id, $day, 'hadir', 0, null, $lembur, $now)] : [];
        }

        // Form absen resmi yang disetujui menang atas jadwal — sama seperti
        // jalur `baku` (§4: deklarasi manusia menang atas scan).
        if ($absence) {
            return [$this->row($userId, $date, $rule->id, $day, $absence, 0, null, $lembur, $now)];
        }

        $perCabang = $this->dokterSchedule()->rangesPerBranchOn($dokterId, $date);
        $adaJadwal = $perCabang !== [];

        // ── Tidak dijadwalkan praktik hari ini ──
        if (! $adaJadwal) {
            if ($lembur > 0) {
                return [$this->row($userId, $date, $rule->id, $day, 'hadir', 0, null, $lembur, $now)];
            }
            if ($n === 0) return [];   // tak praktik & tak datang → tak perlu baris
            // Datang di hari yang bukan hari prakteknya: bukan pelanggaran, tapi
            // layak terlihat apa adanya.
            $laporan->hadirDiLuarJadwal[] = ['user_id' => $userId, 'tanggal' => $date];
            return [$this->row($userId, $date, $rule->id, $day, 'libur', 0, null, 0, $now)];
        }

        // ── Dijadwalkan praktik, tapi tak ada satu pun scan ──
        if ($n === 0) {
            // Hari yang belum lewat bukan bukti apa-apa — hari ini masih
            // berjalan, besok belum terjadi (penjaga yang sama dgn jalur baku).
            if ($date >= $now->toDateString()) return [];
            $laporan->praktikTanpaScan[] = ['user_id' => $userId, 'tanggal' => $date];
            return [];                                  // ← K1 belum dijawab
        }

        // ── Dijadwalkan praktik dan ada scan → hadir ──
        // Telat DICATAT sebagai informasi, tapi TIDAK menurunkan wajib_pulang.
        // Bagi karyawan, telat mendorong jam pulang — itu logika gaji. Bagi
        // dokter konsekuensinya lain sama sekali: pasien yang sudah memesan
        // slot itu yang menunggu. Angkanya berguna, hukumannya tidak berlaku.
        $telat = 0;
        [$mulai] = $this->dokterSchedule()->rentangHarian($dokterId, $date);
        if ($n >= 2 && $mulai) {                        // 1 scan = hadir (§3.2)
            $batas = Carbon::parse($date . ' ' . $mulai);
            $first = $day['scans'][0];
            if ($first->gt($batas)) {
                $menit = $first->diffInMinutes($batas);
                if ($menit <= $this->telatCeilingMinutes) $telat = $menit;
            }
        }

        // Cabang yang didatangi vs cabang yang dijadwalkan. Sengaja TIDAK
        // dijadikan flag: flag terbuka MEMBLOKIR tutup periode gaji untuk
        // SEMUA orang di semua cabang (AbsensiPeriodController), dan memakai
        // alat sekeras itu untuk dokter yang mungkin tak bergaji bulanan —
        // atas keputusan (K2) yang belum di-ACC — bisa menyandera gajian satu
        // klinik. Dilaporkan dulu; jadikan flag setelah K1/K2 dijawab.
        $dijadwalkan = array_keys($perCabang);
        $didatangi   = array_values(array_unique(array_filter($day['branches'])));
        $asing       = array_values(array_diff($didatangi, $dijadwalkan));
        if ($asing) {
            $laporan->cabangBedaJadwal[] = [
                'user_id'     => $userId,
                'tanggal'     => $date,
                'didatangi'   => $didatangi,
                'dijadwalkan' => $dijadwalkan,
            ];
        }

        return [$this->row($userId, $date, $rule->id, $day,
            $telat > 0 ? 'telat' : 'hadir', $telat, null, $lembur, $now)];
    }

    /** Resolver jadwal praktik — satu instans dipakai ulang selama proses. */
    private ?DoctorSchedule $dokterSchedule = null;
    private function dokterSchedule(): DoctorSchedule
    {
        return $this->dokterSchedule ??= new DoctorSchedule();
    }

    /**
     * Form approved yg nutup tanggal ini.
     * @return array{0:?string, 1:int} [tipe_absen (cuti/izin/wfh/libur) atau null, total_menit_lembur]
     */
    private function requestsForDate(array $reqs, string $date): array
    {
        $absence = null; $lembur = 0; $koreksi = false;
        foreach ($reqs as $r) {
            // Pengajuan bertanggal EKSPLISIT (Tukar Hari): hanya tanggal yang
            // terdaftar yang berlaku — hari di antaranya TIDAK ikut tertandai.
            // Perannya menentukan artinya: 'libur' = hari libur pengganti,
            // 'masuk' = hari dia justru bekerja (bukan ketidakhadiran, jadi
            // sengaja tidak mengisi $absence).
            $hari = $r->hari ?? null;
            if (is_array($hari)) {
                $peran = $hari[$date] ?? null;
                if ($peran === null) continue;
                if ($peran === \App\Models\AttRequestHari::PERAN_LIBUR && $absence === null) {
                    $absence = 'libur';
                }
                continue;
            }

            if ($date < $r->tanggal_from || $date > $r->tanggal_to) continue;
            if ($r->tipe === 'lembur') {
                // Hitung HANYA di tanggal mulainya. claimed_menit itu total satu
                // form (dari sepasang jam), bukan jatah harian — kalau ditambahkan
                // di setiap tanggal rentang, klaim 2 jam untuk 3 hari jadi 6 jam
                // dibayar. Form baru sudah dipaksa 1 hari di controller; penjaga
                // ini melindungi baris lama & hasil impor yang terlanjur berentang.
                if ($date === substr((string) $r->tanggal_from, 0, 10)) {
                    $lembur += (int) $r->claimed_menit;
                }
            } elseif ($r->tipe === 'koreksi') {
                $koreksi = true;
            } elseif ($absence === null) {   // absen pertama yg cocok menang
                $absence = $r->tipe;
            }
        }

        // Koreksi bukan jenis ketidakhadiran — ini bantahan atas scan ("saya
        // masuk, cuma lupa tap"). Disetujui = orangnya HADIR, menang atas scan
        // (§4). Tapi kalah dari form absen resmi: kalau hari itu sudah ada cuti
        // /izin/wfh yang disetujui, yang resmi tetap menang — jangan sampai
        // koreksi diam-diam mengubah hari cuti jadi hari kerja.
        if ($absence === null && $koreksi) $absence = 'hadir';

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
            ->get(['id', 'user_id', 'tipe', 'tanggal_from', 'tanggal_to', 'claimed_menit']);

        // Tanggal EKSPLISIT (att_request_hari) — dipakai Tukar Hari, yang
        // tanggalnya bisa terpisah-pisah dan punya dua peran dalam satu
        // pengajuan. Kalau sebuah pengajuan punya baris di sini, hanya tanggal
        // itu yang berlaku; rentang from..to-nya diabaikan (lihat
        // requestsForDate). Pengajuan lama tak punya baris → perilaku lama utuh.
        $hari = [];
        if ($rows->isNotEmpty()) {
            foreach (DB::table('att_request_hari')
                        ->whereIn('request_id', $rows->pluck('id')->all())
                        ->get(['request_id', 'tanggal', 'peran']) as $h) {
                $hari[$h->request_id][substr((string) $h->tanggal, 0, 10)] = $h->peran;
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $r->hari = $hari[$r->id] ?? null;   // null = pakai rentang seperti biasa
            $out[$r->user_id][] = $r;
        }
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

    /**
     * @param  ?string  $cabangKerja  cabang tempat orang ini BERTUGAS tanggal itu
     *         (rolling menang atas cabang asal) — BUKAN `users.raw_branch_id`.
     *         Libur per-cabang artinya "klinik ini tutup", jadi yang terdampak
     *         adalah siapa pun yang hari itu bertugas di sana. Lihat §3
     *         docs/rencana/rencana-libur-cabang-bertugas.md.
     *
     *         ⚠️ JANGAN menyamakannya dengan `$home` yang dipakai
     *         `isDateLocked()` / `hapusHariBasi()`. Keduanya kebetulan bernilai
     *         sama untuk orang yang tak sedang rolling, tapi artinya berbeda:
     *         yang di sana "gajinya ikut cabang mana" dan HARUS tetap cabang
     *         asal — kalau ikut berpindah, penguncian periode gaji ikut
     *         berpindah mengikuti rolling.
     *
     * @return array{0:bool,1:?string,2:?string} [libur, jam_masuk_akhir, jam_pulang]
     */
    private function resolveDay($rule, Carbon $date, ?string $cabangKerja, array $ruleDays, array $exceptions): array
    {
        $dow = $date->dayOfWeek;   // 0=Minggu..6=Sabtu
        $rd = $ruleDays[$rule->id][$dow] ?? null;
        $libur = $rd ? (bool) $rd->libur : false;
        $masukAkhir = $rd?->jam_masuk_akhir;
        $pulang = $rd?->jam_pulang;

        // exception: cabang NULL (nasional) atau cabang == CABANG BERTUGAS.
        // Ambil yg terbaru cocok.
        $ds = $date->toDateString();
        foreach ($exceptions as $e) {
            if ($e->tgl_from <= $ds && $e->tgl_to >= $ds
                && ($e->raw_branch_id === null || $e->raw_branch_id === $cabangKerja)) {
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

    /** Periode yg SUDAH ditutup finance (att_day di rentangnya beku). */
    private function loadClosedPeriods(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('att_period')) return [];
        return DB::table('att_period')->where('status', 'closed')
            ->get(['raw_branch_id', 'periode_from', 'periode_to'])->all();
    }

    /**
     * True kalau tanggal ini terkunci: ada periode closed yg melingkupinya DAN
     * berlaku global (raw_branch_id NULL, default) atau match cabang home user.
     */
    private function isDateLocked(string $date, ?string $home, array $closedPeriods): bool
    {
        foreach ($closedPeriods as $p) {
            if ($date >= $p->periode_from && $date <= $p->periode_to
                && ($p->raw_branch_id === null || $p->raw_branch_id === $home)) {
                return true;
            }
        }
        return false;
    }
}
