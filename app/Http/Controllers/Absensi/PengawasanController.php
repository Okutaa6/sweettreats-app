<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttBranchIp;
use App\Models\AttFlag;
use App\Models\AttHpBukti;
use App\Models\RawBranch;
use App\Services\AttendanceHpAbsen;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengawasan Absen — layar review SPV (Anisa). Prinsip: kejanggalan harus
 * kelihatan TANPA dicari (strip foto latar-seragam + antrian flag + gruping
 * serentak). Akses via node 'pengawasan' (AuthGuard, grant Supervisor/Finance/
 * admin — SQL 2026_07_24_absen_hp_access.sql).
 * Lihat docs/rencana/rencana-absen-hp.md §7 layar 2 + handoff_absen_hp.
 */
class PengawasanController extends Controller
{
    /** Flag serentak sejenis di satu cabang ≥ sekian → dianggap grup (WiFi gangguan). */
    private const GRUP_MIN = 3;

    /**
     * Jenis yang boleh diselesaikan BORONGAN (centang banyak): cerita-jaringan,
     * satu sebab menjelaskan banyak baris. single_scan & perangkat_desktop
     * sengaja TIDAK ikut — ceritanya per-orang, wajib dibuka satu-satu.
     * Keputusan Ekky 2026-08-11.
     */
    public const JENIS_BORONGAN = ['ip_asing', 'pola_ip', 'luar_seharian'];

    /**
     * Halaman BOLEH dilihat semua pemegang node 'pengawasan' (termasuk Finance —
     * flag-lah yang mengunci tutup periode gaji, dia berhak tahu apa yang
     * ngeblok). Tapi AKSI (dicek/selesai/daftarkan IP) = reviewer saja:
     * Supervisor (Anisa) + admin. Keputusan user 2026-07-24.
     */
    private function guardAksi(): void
    {
        if (! in_array(session('group_name'), ['Supervisor', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true)) {
            abort(403, 'Penyelesaian flag hanya untuk Supervisor.');
        }
    }

    public function index(Request $request, AttendanceHpAbsen $svc)
    {
        $date = $request->input('tanggal') ?: $svc->workDate(Carbon::now());
        $branchFilter = $request->input('cabang');
        $statusFilter = $request->input('status');

        [$from, $to] = [Carbon::parse($date)->setTime(6, 0), Carbon::parse($date)->setTime(6, 0)->addDay()];

        // ── Banner kunci-gaji: SEMUA flag terbuka (lintas tanggal, karena
        //    yang ngeblok tutup periode bukan cuma hari ini) ──
        $openTotal = AttFlag::where('status', '!=', 'selesai')->count();
        $nBaru     = AttFlag::where('status', 'baru')->count();
        $nDicek    = AttFlag::where('status', 'dicek')->count();

        // ── Strip foto: semua selfie hari itu, per cabang ──
        // half-open [06:00, besok 06:00) — samakan reconciler & service
        $buktis = AttHpBukti::with(['user', 'branchCocok', 'branchManual'])
            ->where('taken_at', '>=', $from)->where('taken_at', '<', $to)
            ->orderBy('taken_at')
            ->get();

        // ── Antrian flag: hari terpilih, atau LINTAS TANGGAL (?semua=1) ──
        // Mode beres-beres: rotasi prefix WiFi / pendaftaran jaringan yang telat
        // melahirkan flag serumpun yang tersebar berhari-hari; menyisirnya per
        // tanggal berarti bolak-balik date-picker. Lintas tanggal menampilkan
        // semua yang belum selesai sekaligus, diselesaikan lewat centang.
        $lintasHari = $request->boolean('semua');

        // Batas 90 hari HANYA untuk mode lintas + filter 'selesai': flag terbuka
        // jumlahnya wajar (justru layar ini yang menghabiskannya), tapi yang
        // SUDAH selesai menumpuk selamanya — tanpa batas, layar ini makin berat
        // tiap bulan sampai suatu hari berhenti terbuka.
        $batasArsip = $lintasHari && $statusFilter === 'selesai'
            ? Carbon::parse($date)->subDays(90)->toDateString() : null;

        $flags = AttFlag::with('user')
            ->when(! $lintasHari, fn ($q) => $q->where('work_date', $date))
            ->when($lintasHari && ! $statusFilter, fn ($q) => $q->where('status', '!=', 'selesai'))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->when($batasArsip, fn ($q) => $q->where('work_date', '>=', $batasArsip))
            ->orderBy('work_date')
            ->orderByRaw("FIELD(status,'baru','dicek','selesai')")
            ->orderBy('created_at')
            ->get();

        // Bukti terkait flag (foto + sinyal buat panel detail)
        $buktiById = $buktis->keyBy('id');
        $refIds = $flags->where('ref_type', 'att_hp_bukti')->pluck('ref_id')->diff($buktiById->keys());
        if ($refIds->isNotEmpty()) {
            AttHpBukti::with(['user', 'branchCocok', 'branchManual'])->whereIn('id', $refIds)->get()
                ->each(fn ($b) => $buktiById->put($b->id, $b));
        }

        // Riwayat singkat per orang (flag bulan berjalan) buat panel —
        // gabungan orang di ANTRIAN + orang di STRIP: klik selfie siapa pun
        // harus dapat riwayat benar, bukan "belum ada" yang menyesatkan.
        $mulaiBulan = Carbon::parse($date)->startOfMonth()->toDateString();
        $orangTerlihat = $flags->pluck('user_id')->merge($buktis->pluck('user_id'))->filter()->unique();
        $riwayat = AttFlag::whereIn('user_id', $orangTerlihat)
            ->where('work_date', '>=', $mulaiBulan)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($g) => [
                'total'       => $g->count(),
                'lupa_pulang' => $g->where('jenis', 'single_scan')->count(),
            ]);

        // ── "Catatan Kehadiran Hari Itu" (att_day) per orang terlihat — untuk
        //    blok KartuAbsenHarian di panel kanan (bahasa yang sama dengan
        //    layar Kehadiran & Kehadiran Saya) ──
        $infoOrang = DB::table('users as u')
            ->leftJoin('group_path as gp', 'gp.id', '=', 'u.group_id')
            ->whereIn('u.id', $orangTerlihat)
            ->get(['u.id', 'u.raw_branch_id', 'gp.nama as role'])
            ->keyBy('id');
        $flexIds = DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->whereIn('ur.user_id', $orangTerlihat)
            ->where('wr.tipe', 'fleksibel')
            ->where('ur.effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $date))
            ->pluck('ur.user_id')->flip();
        $branchNameAll = RawBranch::pluck('name', 'id');
        // Kartu "kehadiran hari itu" dikunci per ORANG + TANGGAL, bukan per orang
        // saja. Di mode lintas tanggal satu layar memuat flag dari banyak hari;
        // kunci per-orang membuat flag 5 Agu disodori kehadiran 11 Agu — salah
        // hari, tapi tampil meyakinkan seolah benar.
        $tanggalDilihat = $flags->pluck('work_date')
            ->map(fn ($d) => $d instanceof Carbon ? $d->toDateString() : (string) $d)
            ->push($date)->unique()->values()->all();

        $hariMap = [];
        $attRows = DB::table('att_day')
            ->whereIn('work_date', $tanggalDilihat)
            ->whereIn('user_id', $orangTerlihat)
            ->get(['user_id', 'work_date', 'status', 'scan_first', 'scan_last', 'telat_menit',
                   'single_scan', 'lembur_menit_disetujui', 'cabang_terlibat', 'raw_branch_id']);
        foreach ($attRows as $r) {
            $info = $infoOrang->get($r->user_id);
            $home = $info->raw_branch_id ?? null;
            $lain = [];
            if ($home) {
                $terlibat = json_decode($r->cabang_terlibat ?: '[]', true) ?: [];
                if (! $terlibat && $r->raw_branch_id) $terlibat = [$r->raw_branch_id];
                foreach ($terlibat as $bid) {
                    if ($bid !== $home && isset($branchNameAll[$bid])) $lain[$branchNameAll[$bid]] = true;
                }
            }
            $hariMap[$r->user_id . '|' . substr((string) $r->work_date, 0, 10)] = [
                'st'     => $r->status,
                'in'     => $r->scan_first ? substr($r->scan_first, 11, 5) : null,
                'out'    => $r->scan_last && substr($r->scan_last, 11, 5) !== substr((string) $r->scan_first, 11, 5)
                    ? substr($r->scan_last, 11, 5) : null,
                'late'   => (int) $r->telat_menit,
                'lembur' => (int) $r->lembur_menit_disetujui,
                'single' => (int) $r->single_scan,
                'lain'   => array_keys($lain),
                'home'   => $home && isset($branchNameAll[$home]) ? $branchNameAll[$home] : null,
                'flex'   => isset($flexIds[$r->user_id]),
            ];
        }
        // role · cabang untuk sub-judul panel (walau att_day belum ada)
        $subOrang = [];
        foreach ($infoOrang as $uid => $info) {
            $cab = ($info->raw_branch_id ?? null) && isset($branchNameAll[$info->raw_branch_id])
                ? $branchNameAll[$info->raw_branch_id] : null;
            $subOrang[$uid] = trim(($info->role ?: 'Karyawan') . ($cab ? ' · ' . $cab : ''), ' ·');
        }

        // ── Gruping: ip_asing serentak per cabang-manual ≥ GRUP_MIN ──
        $cabangDariBukti = function ($flag) use ($buktiById) {
            $b = $flag->ref_type === 'att_hp_bukti' ? $buktiById->get($flag->ref_id) : null;
            if (!$b) return null;
            return $b->branch_id_cocok ?: $b->branch_id_manual;
        };

        // Filter cabang berlaku juga ke ANTRIAN (bukan cuma strip) — flag
        // tanpa bukti (mis. single_scan) jatuh ke cabang asal orangnya.
        if ($branchFilter) {
            $flags = $flags->filter(function ($f) use ($cabangDariBukti, $branchFilter) {
                $cab = $cabangDariBukti($f) ?: optional($f->user)->raw_branch_id;
                return $cab === $branchFilter;
            })->values();
        }

        // Auto-grup HANYA di mode per-hari: kunci grupnya "serentak di hari yang
        // sama" — lintas tanggal, dua kejadian beda hari bisa tampak serentak
        // palsu. Di mode lintas, centang borongan yang mengambil peran ini.
        $grup = [];
        $flagTunggal = [];
        foreach ($flags as $f) {
            if (! $lintasHari && $f->jenis === 'ip_asing' && $f->status !== 'selesai') {
                $key = $cabangDariBukti($f) ?: 'tanpa-cabang';
                $grup[$key][] = $f;
            } else {
                $flagTunggal[] = $f;
            }
        }
        foreach ($grup as $key => $items) {
            if (count($items) < self::GRUP_MIN) {
                foreach ($items as $f) $flagTunggal[] = $f;
                unset($grup[$key]);
            }
        }

        // Filter cabang (strip + antrian) — nama cabang buat label
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $branchName = $branches->keyBy('id')->map(fn ($b) => $b->name);

        $stripPerCabang = $buktis
            ->when($branchFilter, fn ($c) => $c->filter(fn ($b) => ($b->branch_id_cocok ?: $b->branch_id_manual) === $branchFilter))
            ->groupBy(fn ($b) => $branchName->get($b->branch_id_cocok ?: $b->branch_id_manual, 'Tak diketahui'));

        // Bukti yang orangnya punya flag terbuka hari itu → ring merah di strip
        $userFlagged = $flags->where('status', '!=', 'selesai')->pluck('user_id')->unique()->flip();

        // ── Banner pintar "sebabnya sudah beres": flag ip_asing terbuka LINTAS
        //    tanggal yang IP-nya SEKARANG cocok ke jaringan terdaftar — tipikal
        //    pendaftaran WiFi telat / rotasi prefix (3× kejadian 5–11 Agu).
        //    Sistem hanya menawarkan yang bisa ia jelaskan; vonis + catatan
        //    tetap lewat tangan reviewer. ──
        $saranBeres = ['ids' => [], 'n' => 0, 'jaringan' => []];
        $openIpAsing = AttFlag::where('jenis', 'ip_asing')->where('status', '!=', 'selesai')
            ->get(['id', 'ref_type', 'ref_id']);
        if ($openIpAsing->isNotEmpty()) {
            $buktiSaran = AttHpBukti::whereIn('id', $openIpAsing->where('ref_type', 'att_hp_bukti')->pluck('ref_id'))
                ->get(['id', 'ip'])->keyBy('id');
            $matchCache = [];   // matchBranch query per alamat unik, bukan per flag
            foreach ($openIpAsing as $f) {
                $b = $buktiSaran->get($f->ref_id);
                if (! $b) continue;
                $key = strtolower((string) $b->ip);
                if (! array_key_exists($key, $matchCache)) {
                    $matchCache[$key] = AttBranchIp::matchBranch($b->ip);
                }
                if ($matchCache[$key]) {
                    $saranBeres['ids'][] = $f->id;
                    $saranBeres['jaringan'][$branchName->get($matchCache[$key]->raw_branch_id, '?')] = true;
                }
            }
            $saranBeres['n'] = count($saranBeres['ids']);
            $saranBeres['jaringan'] = array_keys($saranBeres['jaringan']);
        }

        return view('absensi.pengawasan', [
            'bolehAksi'     => in_array(session('group_name'), ['Supervisor', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true),
            'date'          => $date,
            'branchFilter'  => $branchFilter,
            'statusFilter'  => $statusFilter,
            'openTotal'     => $openTotal,
            'nBaru'         => $nBaru,
            'nDicek'        => $nDicek,
            'stripPerCabang'=> $stripPerCabang,
            'userFlagged'   => $userFlagged,
            'grup'          => $grup,
            'flagTunggal'   => $flagTunggal,
            'buktiById'     => $buktiById,
            'riwayat'       => $riwayat,
            'branches'      => $branches,
            'branchName'    => $branchName,
            'hariMap'       => $hariMap,
            'subOrang'      => $subOrang,
            'tanggalLabel'  => Carbon::parse($date)->translatedFormat('l, d F Y'),
            'lintasHari'    => $lintasHari,
            'saranBeres'    => $saranBeres,
            'batasArsip'    => $batasArsip,
            // Kartu riwayat menghitung flag di bulan TANGGAL TERPILIH. Di mode
            // lintas, panel bisa memuat flag dari bulan lain — labelnya harus
            // menyebut bulannya, bukan "bulan ini" yang jadi ambigu.
            'bulanRiwayat'  => Carbon::parse($date)->translatedFormat('F'),
        ]);
    }

    /**
     * Sajikan foto selfie dari storage (BUKAN public/) — hanya bisa diakses
     * pemegang akses segment 'pengawasan' (AuthGuard). Jangan pernah pindah
     * foto ke public/: wajah karyawan bukan aset publik.
     */
    public function foto($id)
    {
        $bukti = AttHpBukti::findOrFail($id);
        $path = storage_path('app/' . $bukti->photo_path);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** Tandai flag sedang dicek (dibuka SPV). */
    public function dicek($id)
    {
        $this->guardAksi();
        $flag = AttFlag::findOrFail($id);
        if ($flag->status === 'baru') {
            $flag->update(['status' => 'dicek']);
        }
        return back();
    }

    /** Penyelesaian flag — hasil WAJIB (wajar / perlu_koreksi / pelanggaran). */
    public function selesai(Request $request, $id)
    {
        $this->guardAksi();
        $data = $request->validate([
            'hasil'   => 'required|in:wajar,perlu_koreksi,pelanggaran',
            'catatan' => 'nullable|string|max:500',
        ]);

        AttFlag::findOrFail($id)->update([
            'status'        => 'selesai',
            'hasil'         => $data['hasil'],
            'catatan'       => $data['catatan'],
            'ditindak_oleh' => (string) session('id') ?: null,
            'ditindak_at'   => Carbon::now(),
        ]);

        return back()->with('success', 'Flag diselesaikan.');
    }

    /**
     * Selesaikan banyak flag sekaligus — grup serentak sehari, atau centang
     * borongan lintas tanggal.
     *
     * Dua pagar (keputusan Ekky 2026-08-11), dua-duanya DITEGAKKAN DI SERVER
     * karena centang di layar gampang dilewati:
     *   1. Catatan WAJIB. Flag adalah satu-satunya jejak audit absensi —
     *      "kenapa 14 flag ditutup sekaligus" pasti ditanya berbulan kemudian.
     *   2. Hanya JENIS_BORONGAN (cerita-jaringan) yang boleh diborong.
     *      single_scan & perangkat_desktop ceritanya per-orang → tetap satuan.
     */
    public function selesaiGrup(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'ids'     => 'required|string',
            'hasil'   => 'required|in:wajar,perlu_koreksi,pelanggaran',
            'catatan' => 'required|string|min:3|max:500',
        ], [
            'catatan.required' => 'Catatan wajib diisi saat menyelesaikan banyak flag sekaligus.',
            'catatan.min'      => 'Catatan terlalu pendek — tulis sebabnya singkat saja.',
        ]);

        $ids = array_filter(array_map('intval', explode(',', $data['ids'])));

        // Buang yang jenisnya tak boleh diborong SEBELUM update, lalu laporkan
        // apa adanya — jangan diam-diam menutup yang seharusnya dilihat satuan.
        //
        // "salah jenis" dan "sudah selesai" DIHITUNG TERPISAH: menggabungkannya
        // membuat pesan berbohong — flag yang tadi sudah ditutup orang lain
        // akan dilaporkan sebagai "harus diperiksa satu per satu", dan reviewer
        // mengira ada yang salah padahal tidak ada.
        $kandidat = AttFlag::whereIn('id', $ids)->get(['id', 'jenis', 'status']);
        $boleh = $kandidat->whereIn('jenis', self::JENIS_BORONGAN)
            ->where('status', '!=', 'selesai')->pluck('id')->all();
        $salahJenis = $kandidat->reject(fn ($f) => in_array($f->jenis, self::JENIS_BORONGAN, true))->count();
        $sudahBeres = $kandidat->whereIn('jenis', self::JENIS_BORONGAN)
            ->where('status', 'selesai')->count();

        $sisipan = [];
        if ($salahJenis) $sisipan[] = "{$salahJenis} dilewati (jenisnya harus diperiksa satu per satu)";
        if ($sudahBeres) $sisipan[] = "{$sudahBeres} sudah selesai sebelumnya";
        $ekor = $sisipan ? ' ' . ucfirst(implode(', ', $sisipan)) . '.' : '';

        if (! $boleh) {
            return back()->with('success', trim('Tidak ada yang diselesaikan.' . $ekor));
        }
        $ids = $boleh;

        $n = AttFlag::whereIn('id', $ids)->where('status', '!=', 'selesai')->update([
            'status'        => 'selesai',
            'hasil'         => $data['hasil'],
            'catatan'       => $data['catatan'],
            'ditindak_oleh' => (string) session('id') ?: null,
            'ditindak_at'   => Carbon::now(),
        ]);

        // Update massal tidak menyalakan observer audit — catat manual.
        // Catatan reviewer ikut masuk jejak: itu inti kenapa ia diwajibkan.
        if ($n > 0) {
            \App\Models\AuditLog::record('update',
                "Selesaikan {$n} flag absensi sekaligus ({$data['hasil']}) — \"{$data['catatan']}\" — id: " . implode(',', $ids));
        }

        return back()->with('success', "{$n} flag diselesaikan sekaligus." . $ekor);
    }

    /**
     * "Daftarkan IP ini ke cabang…" — lingkaran belajar: jalur WiFi sah yang
     * belum terdaftar, didaftarkan dari tempat kejadian (flag), besok tak
     * ke-flag lagi.
     */
    public function daftarkanIp(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'ip'            => 'required|ip',
            'raw_branch_id' => 'required|exists:raw_branch,id',
        ]);

        $sudahAda = AttBranchIp::where('ip', $data['ip'])->first();
        if ($sudahAda) {
            return back()->with('error', 'IP ' . $data['ip'] . ' sudah terdaftar (cabang ' .
                optional($sudahAda->branch)->name . ', status ' . $sudahAda->status . ').');
        }

        // Pagar yang sama dengan AbsensiBranchIpController::store(). WAJIB ada
        // di KEDUA pintu: sejak pencocokan IPv6 melebar ke /48, satu /48 yang
        // dipegang dua cabang membuat absen keduanya tertukar diam-diam. Pintu
        // ini justru yang lebih rawan — dipegang SPV dari layar flag, bukan IT
        // yang paham peta jaringan.
        $bentrok48 = AttBranchIp::pemilik48Lain($data['ip'], $data['raw_branch_id']);
        if ($bentrok48) {
            return back()->with('error',
                'Blok /48 milik ' . $data['ip'] . ' sudah dipakai cabang ' .
                optional($bentrok48->branch)->name . ' (lewat ' . $bentrok48->ip . '). ' .
                'Karena pencocokan IPv6 memakai /48, mendaftarkan ini akan membuat absen ' .
                'kedua cabang tertukar. Minta IT memeriksanya dulu di halaman WiFi Cabang.');
        }

        AttBranchIp::create([
            'raw_branch_id' => $data['raw_branch_id'],
            'ip'            => $data['ip'],
            'label'         => 'didaftarkan dari Pengawasan',
            'status'        => 'aktif',
            'created_by'    => (string) session('id') ?: null,
        ]);

        return back()->with('success', 'IP ' . $data['ip'] . ' terdaftar ke ' .
            optional(RawBranch::find($data['raw_branch_id']))->name . '. Absen berikutnya dari jaringan ini tidak akan ke-flag.');
    }
}
