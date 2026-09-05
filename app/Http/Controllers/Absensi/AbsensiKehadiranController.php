<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Absensi → Kehadiran" — matriks kehadiran karyawan × tanggal (DESKTOP, view-only).
 *
 * PENGELOMPOKAN (penting — lihat memory project-absensi-lintas-cabang §1 & §4):
 *   Satu hari kerja BISA lintas cabang. `att_day.raw_branch_id` cuma cabang scan
 *   PERTAMA — BUKAN pemilik hari itu. Jadi baris dikelompokkan pakai cabang ASAL
 *   (`users.raw_branch_id`), bukan tempat scan. Orang Sunter yang nutupin Kalideres
 *   tetap tampil di Sunter, dan harinya dikasih penanda "scan di cabang lain".
 *
 *   Grup "Kantor (ABG)" DITURUNKAN dari `att_work_rule.tipe = 'fleksibel'`
 *   (per-orang lewat att_user_rule), BUKAN daftar nama hardcode dan BUKAN dari
 *   jabatan/divisi. Konsekuensinya benar dengan sendirinya: Irma & Anisa yang
 *   office-tapi-operasional (baku + hak lembur) tetap di cabangnya, sementara
 *   orang kantor fleksibel berkumpul di ABG. Nambah orang ke rule fleksibel =
 *   otomatis pindah ke ABG, tanpa sentuh kode.
 *
 * Guard canSeeAllBranches. Segment(1)='absensi' → lolos AuthGuard via node parent.
 */
class AbsensiKehadiranController extends Controller
{
    private const ABG_KEY = 'abg';
    private const ABG_LABEL = 'Kantor (ABG)';
    private const NONE_KEY = 'none';
    private const NONE_LABEL = 'Belum ada cabang';

    /** status att_day → token warna (samakan dgn handoff: ATT{bg,c}) */
    private const STATUS = [
        'hadir' => ['label' => 'Hadir', 'bg' => '#E3F4E8', 'c' => '#2F8E55'],
        'telat' => ['label' => 'Telat', 'bg' => '#FBEFD9', 'c' => '#B5701A'],
        'alpa'  => ['label' => 'Alpa',  'bg' => '#FBE3DE', 'c' => '#C0392B'],
        'cuti'  => ['label' => 'Cuti',  'bg' => '#E4ECFB', 'c' => '#3F6BAF'],
        'izin'  => ['label' => 'Izin',  'bg' => '#F4E7D6', 'c' => '#9A6614'],
        'sakit' => ['label' => 'Sakit', 'bg' => '#EDE6F4', 'c' => '#6B4C9A'],
        'libur' => ['label' => 'Libur', 'bg' => '#F1E9DF', 'c' => '#8A6A52'],
        'wfh'   => ['label' => 'WFH',   'bg' => '#DFEDE9', 'c' => '#2E7D74'],
    ];

    /** singkatan hari: 0=Minggu .. 6=Sabtu */
    private const DOW = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];

    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Rekap kehadiran hanya untuk Finance / Admin.');
        }
    }

    /**
     * Kelompok baris: ABG (aturan fleksibel) / cabang asal / belum bercabang.
     *
     * Dipakai layar DAN ekspor. Aturannya pendek, dan justru karena pendek ia
     * gampang disalin lalu berselisih: ekspor yang mengelompokkan beda dari
     * layar akan memberi Finance angka yang tak cocok dengan yang dilihatnya,
     * tanpa satu pun tanda bahwa keduanya menjawab pertanyaan berbeda.
     *
     * @return array{0:string,1:string}  [kunci, label]
     */
    private static function kelompok(bool $isFlex, ?string $homeId, $branchName): array
    {
        if ($isFlex) return [self::ABG_KEY, self::ABG_LABEL];
        if ($homeId && isset($branchName[$homeId])) return [$homeId, $branchName[$homeId]];

        return [self::NONE_KEY, self::NONE_LABEL];
    }

    public function index(Request $request)
    {
        $this->guard();

        $month = $request->input('m');
        $anchor = $month ? Carbon::parse($month . '-01') : Carbon::now()->startOfMonth();
        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();
        $daysInMonth = $anchor->daysInMonth;
        $branchFilter = $request->input('branch');

        // Ambil SEMUA att_day periode ini. Filter cabang dilakukan setelah grup
        // dihitung — karena grup datang dari cabang ASAL orang, bukan tempat scan.
        $days = DB::table('att_day')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'raw_branch_id', 'work_date', 'status', 'telat_menit',
                   'single_scan', 'lembur_menit_disetujui', 'cabang_terlibat',
                   'scan_first', 'scan_last']);

        $branchName = RawBranch::pluck('name', 'id');

        // Foto selfie absen HP periode ini — dipetakan (user_id, work_date) → bukti,
        // buat ditampilkan saat sel kalender diklik. Rentang ikut dawn-pushback:
        // ambil s/d (to+1) 06:00 supaya scan dini hari besoknya kebawa hari 'to'.
        $buktiMap = [];
        $buktis = DB::table('att_hp_bukti')
            ->where('taken_at', '>=', $from->copy()->setTime(6, 0))
            ->where('taken_at', '<', $to->copy()->addDay()->setTime(6, 0))
            ->orderBy('taken_at')
            ->get(['id', 'user_id', 'jenis', 'taken_at']);
        foreach ($buktis as $b) {
            $t = Carbon::parse($b->taken_at);
            $mins = $t->hour * 60 + $t->minute;
            $wd = $mins < 360 ? $t->copy()->subDay()->toDateString() : $t->toDateString();
            $buktiMap[$b->user_id][$wd][] = ['id' => $b->id, 'jenis' => $b->jenis, 'jam' => $t->format('H:i')];
        }

        // ROSTER absensi = orang yang punya aturan kerja aktif di periode ini.
        // Baris diambil dari roster, BUKAN dari att_day — supaya orang yang bulan
        // ini tak punya scan sama sekali TETAP tampil (barisnya "belum ada data").
        // Krusial buat orang kantor: tak nge-tap itu normal, bukan berarti tak kerja
        // (§3.3) — kalau baris ikut att_day, mereka hilang diam-diam dari rekap.
        $roster = DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->leftJoin('group_path as gp', 'gp.id', '=', 'u.group_id')
            ->where('ur.effective_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $from->toDateString());
            })
            // Satu orang BISA punya lebih dari satu baris aturan aktif (penugasan
            // baru yang lupa menutup yang lama — terjadi pada 8 orang di prod,
            // 5→6 Agu 2026). keyBy() menyimpan baris TERAKHIR, jadi tanpa urutan
            // yang menang ditentukan MySQL: orang yang sama bisa terbaca
            // 'fleksibel' sekali muat dan 'baku' di muat berikutnya — dan yang
            // 'baku' bisa memunculkan alpa. Diurutkan supaya yang TERBARU selalu
            // menang, sama seperti tipeAturanKerja(). Ini penambal, bukan obat:
            // baris rangkapnya tetap harus dibereskan di data.
            ->orderBy('ur.effective_from')
            ->orderBy('ur.id')
            ->get(['u.id', 'u.nama', 'u.raw_branch_id', 'wr.id as rule_id', 'wr.tipe', 'gp.nama as role'])
            ->keyBy('id');

        // Hari libur DIBACA dari aturan kerja, bukan diasumsikan akhir pekan.
        // Klinik tutup SENIN, bukan Minggu — sebelumnya kolom merah di-hardcode
        // `dow === 0` sehingga matriks menyorot hari yang justru buka.
        $liburByRule = DB::table('att_work_rule_day')
            ->where('libur', 1)
            ->get(['rule_id', 'day'])
            ->groupBy('rule_id')
            ->map(fn ($rows) => $rows->pluck('day')->map(fn ($d) => (int) $d)->sort()->values()->all())
            ->all();

        // ── KALENDER LIBUR (att_work_rule_exception) ──
        // Sampai 2026-08-21 tabel ini ditulis oleh satu layar (Aturan Kerja) dan
        // dibaca oleh SATU pembaca saja: mesin rekonsiliasi. Tak ada layar mana
        // pun yang menampilkannya, jadi salah catat baru ketahuan setelah
        // harinya lewat — persis bagaimana kasus 25 Agu 2026 lolos.
        //
        // Sumbernya SENGAJA terpisah dari `hariLiburTerjadwal()`: fungsi itu
        // khusus pola mingguan dan dipakai gerbang absen; menambalnya supaya
        // ikut membaca kalender libur akan mengubah perilaku gerbang. Lihat §4
        // docs/rencana/rencana-libur-cabang-bertugas.md.
        $liburTgl = [];   // 'Y-m-d' => [rows]
        foreach (DB::table('att_work_rule_exception')
            ->where('libur', 1)
            ->where('tgl_from', '<=', $to->toDateString())
            ->where('tgl_to', '>=', $from->toDateString())
            ->get(['raw_branch_id', 'tgl_from', 'tgl_to', 'alasan']) as $e) {
            $a = Carbon::parse($e->tgl_from)->max($from);
            $b = Carbon::parse($e->tgl_to)->min($to);
            for ($x = $a->copy(); $x->lte($b); $x->addDay()) {
                $liburTgl[$x->toDateString()][] = $e;
            }
        }

        ksort($liburTgl);

        // Penempatan sementara, dipramuat sekali — libur per-cabang berlaku bagi
        // siapa pun yang hari itu BERTUGAS di cabang tersebut, jadi layar ini
        // harus memakai resolusi yang sama dengan mesin rekonsiliasi. Kalau
        // tidak, layar dan gaji akan bercerita dua hal berbeda.
        $rollingBranch = $liburTgl ? \App\Services\CabangBertugas::muat() : [];

        // jaring-pengaman: ada att_day tapi orangnya tak punya aturan (data lama)
        $orphan = $days->pluck('user_id')->unique()->diff($roster->keys());
        if ($orphan->isNotEmpty()) {
            foreach (DB::table('users as u')
                ->leftJoin('group_path as gp', 'gp.id', '=', 'u.group_id')
                ->whereIn('u.id', $orphan)
                ->get(['u.id', 'u.nama', 'u.raw_branch_id', 'gp.nama as role']) as $o) {
                $o->tipe = 'baku';
                $o->rule_id = null;          // tanpa aturan → tak ada hari libur yang bisa diklaim
                $roster->put($o->id, $o);
            }
        }

        $flex = $roster->where('tipe', 'fleksibel')->keys()->flip();

        // KOLOM MERAH = hari klinik tutup, diambil dari aturan `baku` (yang scan-nya
        // memang dinilai). Sengaja BUKAN gabungan semua aturan: rule "Fleksibel"
        // dipakai 9 orang tapi nol hari libur, jadi irisan seluruh aturan selalu
        // kosong dan tak ada yang tersorot. Irisan antar-aturan baku = hari yang
        // benar-benar tutup untuk semua yang dinilai.
        $bakuRules = $roster->pluck('rule_id')->filter()->unique()
            ->filter(fn ($rid) => ($roster->firstWhere('rule_id', $rid)->tipe ?? null) === 'baku')
            ->values();
        $tutupDow = [];
        foreach ($bakuRules as $i => $rid) {
            $hari = $liburByRule[$rid] ?? [];
            $tutupDow = $i === 0 ? $hari : array_values(array_intersect($tutupDow, $hari));
        }

        // header hari (dow + tanggal + hari tutup)
        $head = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = $anchor->copy()->day($d)->dayOfWeek;
            $head[$d] = ['n' => $d, 'dow' => self::DOW[$dow], 'tutup' => in_array($dow, $tutupDow, true)];
        }

        // semua baris dibuat dulu dari roster (termasuk yg nol scan bulan ini)
        $matrix = [];
        foreach ($roster as $u) {
            $nama = $u->nama ?? '—';
            $homeId = $u->raw_branch_id ?? null;
            $isFlex = isset($flex[$u->id]);

            [$key, $label] = self::kelompok($isFlex, $homeId, $branchName);

            $matrix[$u->id] = [
                'id'      => $u->id,
                'nama'    => $nama,
                'initial' => mb_strtoupper(mb_substr(trim($nama), 0, 1)),
                'role'    => $u->role ?? null,
                'home_id' => $homeId,
                'key'     => $key,
                'label'   => $label,
                'flex'    => $isFlex,
                // hari libur milik ORANG INI (aturannya sendiri) — orang kantor
                // libur Minggu+Senin, yang lain Senin saja.
                'libur'   => $liburByRule[$u->rule_id ?? ''] ?? [],
                // TANGGAL libur kalender bagi orang ini: cabang tempat dia
                // BERTUGAS hari itu sedang tutup. Per-orang, sebab orang yang
                // sedang rolling ikut cabang tempatnya bertugas — 25 Agu 2026
                // Verini libur (bertugas di Cinere yang tutup) sementara rekan
                // se-cabang-asalnya di Sunter tetap masuk.
                'libur_tgl' => $this->liburKalender(
                    $liburTgl, $rollingBranch[$u->id] ?? [], $homeId, $from, $daysInMonth
                ),
                'cells'   => [],
                'h' => 0, 't' => 0, 'a' => 0,
            ];
        }

        // Menit DODO per orang per tanggal — satu kueri, dari LemburDodo yang
        // sama dengan yang dipakai Lembar Gaji. Dua layar yang memutuskan
        // sendiri-sendiri "mana yang DODO" adalah cara angka mulai berselisih.
        $dodoHari = \App\Services\LemburDodo::menitPerHari($from->toDateString(), $to->toDateString());

        foreach ($days as $d) {
            $uid = $d->user_id;
            if (! isset($matrix[$uid])) continue;

            // "scan di cabang lain": hari ini melibatkan cabang selain cabang ASAL.
            // Pakai cabang_terlibat (semua mesin hari itu), bukan raw_branch_id saja.
            $home = $matrix[$uid]['home_id'];
            $lain = [];
            if ($home) {
                $terlibat = json_decode($d->cabang_terlibat ?: '[]', true) ?: [];
                if (! $terlibat && $d->raw_branch_id) $terlibat = [$d->raw_branch_id];
                foreach ($terlibat as $bid) {
                    if ($bid !== $home && isset($branchName[$bid])) $lain[$branchName[$bid]] = true;
                }
            }

            $day = (int) Carbon::parse($d->work_date)->day;
            $matrix[$uid]['cells'][$day] = [
                'status' => $d->status,
                'telat'  => (int) $d->telat_menit,
                'single' => (int) $d->single_scan,
                'lembur' => (int) $d->lembur_menit_disetujui,
                // Porsi DODO (lembur di hari libur — tarifnya beda). PECAHAN
                // dari 'lembur' di atas, bukan tambahan: reconciler menjumlah
                // semua lembur di-ACC tanpa memeriksa jenisnya. Yang dijumlah
                // layar sebagai "lembur biasa" = lembur − dodo.
                'dodo'   => min((int) ($dodoHari[$uid][$d->work_date] ?? 0), (int) $d->lembur_menit_disetujui),
                'lain'   => array_keys($lain),
                'jam_in' => $d->scan_first ? substr($d->scan_first, 11, 5) : null,
                // beda detik saja (scan dobel) tetap dianggap satu jam — jangan tampil "10:22–10:22"
                'jam_out'=> $d->scan_last && substr($d->scan_last, 11, 5) !== substr((string) $d->scan_first, 11, 5)
                    ? substr($d->scan_last, 11, 5) : null,
                'foto'   => $buktiMap[$uid][$d->work_date] ?? [],
            ];

            if ($d->status === 'hadir') $matrix[$uid]['h']++;
            elseif ($d->status === 'telat') $matrix[$uid]['t']++;
            elseif ($d->status === 'alpa') $matrix[$uid]['a']++;
        }

        // Filter cabang TIDAK dilakukan di server — semua grup dikirim, blade
        // menyaring client-side (per GRUP cabang asal / ABG, bukan tempat scan).
        // ?branch= hanya dipakai sebagai nilai awal dropdown; kalau difilter di
        // sini, pindah ke "Semua Cabang" di layar tak bisa tanpa reload.

        // urut: klinik dulu (abjad), lalu Kantor (ABG), lalu yang belum bercabang
        $rank = fn ($k) => $k === self::NONE_KEY ? 2 : ($k === self::ABG_KEY ? 1 : 0);
        uasort($matrix, fn ($a, $b) =>
            [$rank($a['key']), $a['label'], $a['nama']] <=> [$rank($b['key']), $b['label'], $b['nama']]
        );

        // kelompokkan (pembatas per cabang)
        $groups = [];
        foreach ($matrix as $row) {
            $k = $row['key'];
            if (! isset($groups[$k])) $groups[$k] = ['label' => $row['label'], 'flex' => $row['flex'], 'rows' => []];
            $groups[$k]['rows'][] = $row;
        }
        $groups = array_values($groups);

        $summary = [
            'karyawan' => count($matrix),
            'hadir'    => array_sum(array_column($matrix, 'h')),
            'telat'    => array_sum(array_column($matrix, 't')),
            'alpa'     => array_sum(array_column($matrix, 'a')),
        ];

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);

        // Ringkasan libur bulan ini untuk STRIP di atas matriks. Ini alat audit,
        // bukan hiasan: cukup dibaca sekali untuk tahu cabang mana yang tutup
        // tanggal berapa — pertanyaan yang sebelumnya cuma bisa dijawab dengan
        // membuka tabel di database atau menunggu harinya lewat.
        $liburRingkas = [];
        foreach ($liburTgl as $ds => $rows) {
            $nama = [];
            $nasional = false;
            foreach ($rows as $e) {
                if ($e->raw_branch_id === null) { $nasional = true; continue; }
                $nama[] = $branchName[$e->raw_branch_id] ?? $e->raw_branch_id;
            }
            sort($nama);
            $liburRingkas[] = [
                'tgl'    => (int) Carbon::parse($ds)->day,
                'label'  => Carbon::parse($ds)->translatedFormat('j M'),
                'alasan' => $rows[0]->alasan,
                'cabang' => $nasional ? 'semua cabang' : implode(' · ', $nama),
            ];
        }
        $statusMeta = self::STATUS;
        $monthShort = $anchor->translatedFormat('M');
        $abgKey = self::ABG_KEY;
        $abgLabel = self::ABG_LABEL;
        $today = Carbon::now();
        $selDay = (int) $request->input('d') ?: null;   // tanggal terpilih (view pekan/hari) saat pindah bulan
        $initView = in_array($request->input('view'), ['bulan', 'pekan', 'hari'], true) ? $request->input('view') : 'bulan';

        return view('absensi.kehadiran', compact(
            'groups', 'summary', 'daysInMonth', 'head', 'anchor', 'monthShort',
            'branches', 'branchFilter', 'statusMeta', 'abgKey', 'abgLabel',
            'today', 'selDay', 'initView', 'tutupDow', 'liburRingkas'
        ));
    }

    /**
     * Ekspor CSV — RENTANG TANGGAL BEBAS, bukan terikat periode gaji.
     *
     * Ekspor yang sudah ada hidup di halaman Periode Gaji dan hanya bisa
     * mengeluarkan satu periode utuh. HR/Finance yang cuma ingin "1–15 Agustus"
     * harus membuat periode dulu — mengubah data hanya untuk bisa membacanya.
     *
     * ⚠️ Ikut penyaring cabang yang sedang aktif di layar. Kalau tidak, tombol
     * ekspor akan mengeluarkan sesuatu yang berbeda dari yang sedang dilihat,
     * dan selisih semacam itu baru ketahuan setelah dipakai rapat.
     *
     * Kolomnya SUPERSET dari ekspor periode; DODO dipisah dari lembur biasa
     * dengan nama kolom yang eksplisit, mengikuti keputusan yang sama seperti
     * Lembar Gaji (kolom yang isinya menyusut TIDAK boleh memakai nama lama).
     */
    public function export(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'from'   => 'required|date',
            'to'     => 'required|date|after_or_equal:from',
            'branch' => 'nullable|string|max:64',
        ]);

        $from = Carbon::parse($data['from'])->startOfDay();
        $to   = Carbon::parse($data['to'])->startOfDay();

        // Pagar kewarasan: rentang selebar tahun-tahun biasanya salah ketik
        // tahun, dan menyapu att_day sebesar itu memperlambat semua orang.
        if ($from->diffInDays($to) > 366) {
            return back()->with('error', 'Rentang maksimal 366 hari — persempit tanggalnya.');
        }

        $branchFilter = $data['branch'] ?? null;
        $branchName   = RawBranch::pluck('name', 'id');

        // Roster disusun sama seperti layar: dari att_user_rule, bukan att_day
        // — supaya orang yang di rentang ini tak punya satu pun baris TETAP
        // muncul dengan angka nol, bukan hilang diam-diam.
        $roster = DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->leftJoin('group_path as gp', 'gp.id', '=', 'u.group_id')
            ->where('ur.effective_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $from->toDateString());
            })
            ->orderBy('ur.effective_from')
            ->orderBy('ur.id')
            ->get(['u.id', 'u.nama', 'u.raw_branch_id', 'wr.tipe', 'gp.nama as role'])
            ->keyBy('id');

        $agg = DB::table('att_day')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->select('user_id',
                // ⚠️ Hadir dan Telat SALING LEPAS — sama seperti matriks di layar
                // (`if/elseif` per sel) dan ekspor Periode Gaji. Lembar Gaji memakai
                // arti yang lain (hadir SUDAH termasuk telat) dan menyebutkannya di
                // labelnya. Kalau ekspor ini ikut arti Lembar Gaji, angkanya tak akan
                // cocok dengan tabel tepat di atas tombolnya — dan tak ada satu pun
                // tanda bahwa keduanya menjawab pertanyaan berbeda.
                DB::raw("SUM(status = 'hadir') AS hadir"),
                DB::raw("SUM(status = 'telat') AS telat_hari"),
                DB::raw("SUM(telat_menit) AS telat_menit"),
                DB::raw("SUM(lembur_menit_disetujui) AS lembur_menit"),
                DB::raw("SUM(status = 'alpa') AS alpa"),
                DB::raw("SUM(status = 'cuti') AS cuti"),
                DB::raw("SUM(status = 'izin') AS izin"),
                DB::raw("SUM(status = 'sakit') AS sakit"),
                DB::raw("SUM(status = 'libur') AS libur"),
                DB::raw("SUM(status = 'wfh') AS wfh"),
                DB::raw("SUM(single_scan) AS single_scan"),
                DB::raw("COUNT(*) AS total_hari"))
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        // Jaring-pengaman yang sama dengan layar: ada att_day tapi orangnya tak
        // punya aturan kerja (data lama). Tanpa ini mereka tampil di matriks tapi
        // HILANG dari ekspor — tepat jenis selisih yang baru ketahuan sesudah
        // angkanya dipakai rapat. Diperlakukan `baku`, sama seperti di index().
        $orphan = $agg->keys()->diff($roster->keys());
        if ($orphan->isNotEmpty()) {
            foreach (DB::table('users as u')
                ->leftJoin('group_path as gp', 'gp.id', '=', 'u.group_id')
                ->whereIn('u.id', $orphan)
                ->get(['u.id', 'u.nama', 'u.raw_branch_id', 'gp.nama as role']) as $o) {
                $o->tipe = 'baku';
                $roster->put($o->id, $o);
            }
        }

        // DODO = pecahan dari lembur, BUKAN tambahan. Dikurangkan, tak pernah
        // dijumlahkan — supaya "Lembur Biasa + DODO" selalu sama persis dengan
        // seluruh lembur yang di-ACC (lihat App\Services\LemburDodo).
        $dodo = \App\Services\LemburDodo::menitPerOrang($from->toDateString(), $to->toDateString());

        $baris = [];
        foreach ($roster as $u) {
            [$key, $label] = self::kelompok(($u->tipe ?? null) === 'fleksibel', $u->raw_branch_id ?? null, $branchName);
            if ($branchFilter && $branchFilter !== $key) continue;

            $a = $agg[$u->id] ?? null;
            $lemburTotal = (int) ($a->lembur_menit ?? 0);
            $dodoMenit   = min((int) ($dodo[$u->id] ?? 0), $lemburTotal);

            $baris[] = [
                $u->nama, $u->role ?? 'Karyawan', $label,
                (int) ($a->hadir ?? 0), (int) ($a->telat_hari ?? 0), (int) ($a->telat_menit ?? 0),
                round(($lemburTotal - $dodoMenit) / 60, 2), round($dodoMenit / 60, 2),
                (int) ($a->alpa ?? 0), (int) ($a->cuti ?? 0), (int) ($a->izin ?? 0),
                (int) ($a->sakit ?? 0), (int) ($a->libur ?? 0), (int) ($a->wfh ?? 0),
                (int) ($a->single_scan ?? 0), (int) ($a->total_hari ?? 0),
            ];
        }

        usort($baris, fn ($x, $y) => [$x[2], $x[0]] <=> [$y[2], $y[0]]);

        $nama = 'kehadiran_' . $from->toDateString() . '_sd_' . $to->toDateString() . '.csv';

        return response()->streamDownload(function () use ($baris) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");   // BOM UTF-8 biar Excel benar
            fputcsv($out, ['Nama', 'Role', 'Cabang', 'Hadir', 'Telat (hari)', 'Telat (menit)',
                'Lembur Biasa (jam)', 'DODO (jam)', 'Alpa', 'Cuti', 'Izin', 'Sakit', 'Libur', 'WFH',
                'Single-scan', 'Total Hari']);
            foreach ($baris as $b) fputcsv($out, $b);
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
    /**
     * Tanggal-tanggal bulan ini yang KLINIK-nya tutup bagi satu orang.
     *
     * Murni memori — `$rolling` sudah dipramuat, jadi tak ada kueri di dalam
     * loop harian meski dipanggil sekali per orang.
     *
     * @param  array   $liburTgl  'Y-m-d' => baris exception
     * @param  array   $rolling   baris penempatan sementara milik orang ini
     * @param  ?string $home      cabang asal
     * @return array<int,string>  tanggal (1..31) => alasan
     */
    private function liburKalender(array $liburTgl, array $rolling, ?string $home, Carbon $from, int $daysInMonth): array
    {
        if (! $liburTgl) return [];

        $out = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $ds = $from->copy()->day($d)->toDateString();
            if (! isset($liburTgl[$ds])) continue;

            $cabangKerja = \App\Services\CabangBertugas::pada($rolling, $ds, $home);
            foreach ($liburTgl[$ds] as $e) {
                // NULL = nasional (berlaku untuk semua, termasuk orang kantor
                // yang cabang asalnya memang kosong).
                if ($e->raw_branch_id === null || $e->raw_branch_id === $cabangKerja) {
                    $out[$d] = $e->alasan;
                    break;
                }
            }
        }

        return $out;
    }
}
