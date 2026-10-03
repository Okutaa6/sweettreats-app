<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\OrtCase;
use App\Models\OrtControl;
use App\Models\OrtFollowUp;
use App\Models\OrtReminder;
use App\Models\OrtTransfer;
use App\Services\Ortho\OrtBranches;
use App\Services\Ortho\OrtQuery;
use App\Services\Ortho\OrtRollup;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Tracking Pasien Ortho — menggantikan Google Sheets per cabang yang dipakai
 * Sales untuk menagih pasien behel kembali kontrol.
 *
 * PEKERJAAN SALES DI HALAMAN INI CUMA DUA: follow-up dan buat reservasi.
 * Sisanya turunan — kolom Datang diisi `ort:pull-visits` dari cermin rekam
 * medis tiap malam, dan jadwal berikutnya diusulkan sistem (+30 hari) lalu
 * cukup dikonfirmasi satu klik. Tidak ada tombol "Catat Kunjungan" di mana pun.
 *
 * GERBANG AKSES. Segmen URL-nya `tracking-ortho` — segmen sendiri, BUKAN di
 * bawah `finance`/`report` yang ada di AuthGuard::SKIP_RBAC. Jadi node
 * access_path ber-link 'tracking-ortho' itulah gerbangnya; tidak perlu guard
 * tambahan di sini.
 *
 * CABANG TERKUNCI. Cabang diambil dari session, dan `?cabang=` tidak pernah
 * mengubahnya — parameter itu hanya memilih TAB cabang lain, yang lihat-saja
 * dan hanya memuat pasien yang cabang pasangnya kita.
 *
 * Konvensi repo: controller CRUD di app/Http/Controllers/Crud/, view di
 * resources/views/crud/<modul>/, halaman daftar bernama list.blade.php.
 *
 * @see database/sql/2026_09_23_ort_tracking_schema.sql
 * @see resources/views/crud/ortho/list.blade.php
 */
class OrthoController extends Controller
{
    /** Tab tingkat atas. Divalidasi supaya query string tidak bisa mengarang tab. */
    private const TABS = ['list', 'kalender', 'pasien'];

    /** Kamus `urgensi` saat antrean penjadwalan yang ditampilkan. */
    private const URGENSI_ANTREAN = ['semua', 'hilang', 'jarang', 'baru'];

    /** Sub-tab kegentingan di dalam tab List. */
    private const URGENSI = ['semua', 'terlewat', 'hari-ini', 'minggu-ini', 'mendatang'];

    public function __construct(
        private OrtBranches $branches,
        private OrtQuery $q,
        private OrtRollup $rollup,
    ) {
    }

    public function index(Request $request)
    {
        // Guard env yang belum menjalankan SQL-nya — pola CustomerController::edit():368.
        // Lebih baik halaman menjelaskan diri sendiri daripada 500 tanpa petunjuk.
        if (! Schema::hasTable('ort_case')) {
            return view('crud.ortho.belum-siap');
        }

        [$placeId, $terkunci, $namaCabang] = $this->resolveBranch();

        if ($placeId === null) {
            return view('crud.ortho.belum-siap', ['alasan' => 'cabang']);
        }

        /*
         * `frag` dicabut dari query SEBELUM konteks dibangun.
         *
         * Paginator memakai withQueryString(), yang menyalin SELURUH query ke
         * tautan halaman. Kalau `frag` masih di sana, tombol "2" di dalam
         * fragmen mengarah ke ?frag=tabel&page=2 — dan pengguna mendarat di
         * tabel telanjang tanpa kerangka halaman. Dicabut di satu tempat ini,
         * semua paginator (daftar, roster, cabang lain) ikut bersih.
         */
        $frag = $request->query('frag');
        $request->query->remove('frag');

        $ctx = $this->konteksList($request, $placeId, $terkunci, $namaCabang);

        if ($frag !== null && ($lapisan = $this->fragmen($frag, $ctx))) {
            return $lapisan;
        }

        return view('crud.ortho.list', $ctx);
    }

    /**
     * ?frag=<lapisan> → kirim LAPISAN-nya saja, tanpa shell halaman, supaya JS
     * bisa memasangnya tanpa memuat ulang. Null artinya bukan permintaan
     * fragmen; pemanggilnya lanjut merender halaman penuh.
     *
     * ⚠ WAJIB dipanggil dari index() DAN drawerDetail(). Lembar yang dibuka
     *   dari dalam drawer memakai route DETAIL (lihat _drawer_detail.blade.php),
     *   jadi kalau hanya index() yang mengenalinya, permintaan itu dijawab
     *   halaman penuh — dan JS memasang isi drawer ke dalam cangkang lembar.
     *
     * Tanpa JS, ?frag tidak pernah muncul di URL mana pun, jadi jalur ini mati
     * dengan sendirinya dan halaman penuh yang dirender.
     */
    private function fragmen(string $frag, array $ctx)
    {
        $daftar = [
            'sheet'  => ['crud.ortho._sheet',         fn () => ($ctx['sheet'] ?? null) && ($ctx['sheetKasus'] ?? null)],
            'tambah' => ['crud.ortho._drawer_tambah', fn () => (bool) ($ctx['tambah'] ?? false)],
            'hari'   => ['crud.ortho._drawer_hari',   fn () => ($ctx['hariDrawer'] ?? null) !== null],

            // Tabel daftar kerja, untuk memperbarui di tempat tanpa memuat
            // ulang halaman. Hanya sah di daftar kerja biasa — antrean
            // penjadwalan dan tab cabang lain merender partial yang berbeda.
            'tabel'  => ['crud.ortho._frag_tabel', fn () => ($ctx['tab'] ?? '') === 'list'
                && ! ($ctx['hanyaLihat'] ?? false)
                && ($ctx['kartuAktif'] ?? null) !== 'backlog'],
        ];

        if (! isset($daftar[$frag])) {
            return null;
        }

        [$view, $syarat] = $daftar[$frag];

        // Subjeknya tidak sah (mis. kasus milik cabang lain) → 404, bukan panel
        // kosong. JS-nya jatuh ke navigasi biasa, dan halaman penuh yang
        // menjelaskan keadaannya.
        abort_unless($syarat(), 404);

        // $adaFilter lahir di list.blade.php, jadi fragmen harus membawanya
        // sendiri — tanpa ini state kosong di _tabel salah kalimat.
        return response()->view($view, $ctx + $this->penautFragmen() + [
            'adaFilter' => ! empty($ctx['filter'] ?? []),
        ]);
    }

    /**
     * Dua penaut yang biasanya lahir di list.blade.php.
     *
     * Partial mewarisi variabel induknya saat di-@include, tapi fragmen dirender
     * SENDIRI — tanpa ini, $tautan() di dalamnya undefined. 'frag' dibuang dari
     * query supaya tautan di dalam lapisan tidak mewariskannya dan menghasilkan
     * halaman telanjang tanpa kerangka.
     */
    private function penautFragmen(): array
    {
        return [
            'tautan' => fn (array $ubah = []) => route('ortho.index', array_merge(
                request()->except(['page', 'frag']), $ubah
            )),
            'tautanDetail' => function (string $id) {
                $sisa = request()->except(['sheet', 'kasus', 'kontrol', 'tambah', 'hari', 'frag']);
                return route('ortho.detail', $id) . ($sisa ? '?' . http_build_query($sisa) : '');
            },
        ];
    }

    /**
     * Seluruh konteks halaman List. Dipakai index() DAN drawerDetail(), karena
     * drawer dirender sebagai lapisan di atas List — halaman di belakangnya
     * harus tetap utuh.
     */
    private function konteksList(Request $request, string $placeId, bool $terkunci, string $namaCabang): array
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'list';

        // Kartu ke-3 mengganti tabel dengan antrean penjadwalan, bukan menyaringnya.
        $kartuAktif = $request->query('kartu');
        $kartuAktif = in_array($kartuAktif, ['minggu-ini', 'terlewat', 'backlog'], true) ? $kartuAktif : null;

        /*
         * Kartu ringkas menyaring DAFTAR KERJA — tidak lebih.
         *
         * Tab Kalender disusun per tanggal dan tab Pasien Ortho per orang;
         * tidak satu pun dari keduanya disaring oleh `kartu`. Membiarkannya
         * hidup di sana menimbulkan halaman yang bertengkar dengan dirinya
         * sendiri: `?tab=pasien&kartu=backlog` mengganti pita kiri jadi sub-tab
         * ANTREAN, sementara isi halamannya tetap roster — dan semua angka di
         * sub-tab itu nol, karena hitungAntrean memang tidak pernah dihitung
         * untuk cabang kode tab Pasien.
         *
         * Kartu-kartunya sendiri sekarang selalu memulangkan ke tab List
         * (lihat _cards.blade.php), jadi menekan kartu tetap bekerja dari tab
         * mana pun — ia memindahkan Anda ke tempat yang memang menyaringnya.
         */
        if ($tab !== 'list') {
            $kartuAktif = null;
        }

        /*
         * `urgensi` punya DUA kamus, tergantung yang sedang ditampilkan.
         *
         * Daftar kerja menyaring per tanggal (terlewat / hari ini / minggu
         * ini / mendatang). Antrean penjadwalan justru berisi pasien yang
         * belum punya tanggal sama sekali, jadi kamus itu tak berarti di sana;
         * yang berlaku tingkat kegentingannya sendiri (hilang / jarang).
         * Satu parameter, dua kamus — supaya tab-nya terasa satu hal yang sama
         * bagi pengguna, dan tautannya tetap sederhana.
         */
        $kamus   = $kartuAktif === 'backlog' ? self::URGENSI_ANTREAN : self::URGENSI;
        $urgensi = in_array($request->query('urgensi'), $kamus, true)
            ? $request->query('urgensi')
            : 'semua';

        // Tab cabang lain: hanya boleh cabang yang memang memegang pasien kita.
        $perCabangLain = $this->q->hitungPerCabangLain($placeId);
        $cabangDilihat = $request->query('cabang');
        if (! isset($perCabangLain[$cabangDilihat])) {
            $cabangDilihat = null;
        }

        $filter = [
            'q'      => $request->query('q'),
            'dokter' => $request->query('dokter'),
            'status' => $request->query('status'),
            /* Saringan "Status Pasien" dari prompt: Semua / Active / Inactive.
               Kunci TERPISAH dari 'status' di atas — prompt memintanya bekerja
               BARENG filter yang sudah ada, bukan menggantikannya. Nilai di luar
               kedua kata itu diabaikan OrtQuery, jadi ?rutin=ngawur = Semua. */
            'rutin'  => $request->query('rutin'),
            // Bukan masukan pengguna: dipakai saringan "Saya FU hari ini"
            // untuk mencocokkan ort_control.fu_by. Sengaja dibuang lagi dari
            // konteks di bawah supaya tidak terhitung sebagai filter aktif —
            // kalau ikut, panel filter terbuka terus dan chip "Reset" muncul
            // di halaman yang sebetulnya tidak difilter apa pun.
            'saya'   => session('name'),
            'dari'   => $request->query('dari'),
            'sampai' => $request->query('sampai'),
        ];

        /*
         * Jumlah baris per halaman. Pola dan pilihannya sama dengan modul
         * Reservasi (ReservationController:45) supaya dua halaman tidak terasa
         * punya aturan sendiri-sendiri.
         *
         * ⚠ WHITELIST, bukan (int) mentah. `?per_page=99999` akan menarik
         *   seluruh tabel dalam satu kueri — 1.639 baris di prod — dan itu
         *   cara halaman ini mati pelan-pelan tanpa ada yang melapor.
         *
         * Bawaannya tetap PER_HAL (50). Ia naik dari 10 atas permintaan audit
         * HUB: 959 kasus Bintaro jadi 96 halaman.
         */
        $perHal = in_array((int) $request->query('per_page'), [10, 25, 50, 100], true)
            ? (int) $request->query('per_page')
            : OrtQuery::PER_HAL;

        // Side sheet (§15): jenisnya dari ?sheet=, subjeknya dari ?kontrol= atau ?kasus=.
        $sheet = in_array($request->query('sheet'), ['reservasi', 'pindahkan', 'geser', 'jadwalkan', 'follow-up'], true)
            ? $request->query('sheet') : null;
        $sheetKontrol = $sheetKasus = null;
        if ($sheet) {
            if ($request->query('kontrol')) {
                $sheetKontrol = \App\Models\OrtControl::with('case')->find($request->query('kontrol'));
                $sheetKasus   = $sheetKontrol?->case;
            } elseif ($request->query('kasus')) {
                $sheetKasus = OrtCase::find($request->query('kasus'));
            }
            // Hanya untuk pasien yang dipegang cabang ini — cabang lain lihat-saja.
            if (! $sheetKasus || (string) $sheetKasus->owner_place_id !== (string) $placeId) {
                $sheet = $sheetKontrol = $sheetKasus = null;
            }
        }

        $kartuRingkas = $this->q->kartu($placeId);

        /*
         * Dua nilai ini yang membuat tab cabang berlaku di KETIGA tab.
         *
         *   $lihatPlace — cabang yang isinya ditampilkan. Normalnya cabang
         *                 sendiri; kalau tab cabang lain dipilih, cabang itu.
         *   $asalPasien — saringan "pasien siapa". Hanya diisi di mode cabang
         *                 lain, supaya yang tampil cuma pasien KITA yang
         *                 kebetulan dipegang di sana — bukan seluruh pasien
         *                 cabang itu, yang bukan urusan kita dan tidak boleh
         *                 bocor lewat kunci cabang.
         *
         * Semua endpoint TULIS tetap memakai $placeId, tidak pernah
         * $lihatPlace — mode cabang lain wajib lihat-saja.
         */
        $lihatPlace = $cabangDilihat ?? $placeId;
        $asalPasien = $cabangDilihat !== null ? $placeId : null;

        $ctx = [
            'sheet'        => $sheet,
            'sheetKontrol' => $sheetKontrol,
            'sheetKasus'   => $sheetKasus,
            'tambah'       => (bool) $request->query('tambah'),
            // Hari pertama: ada pasien, tapi TIDAK ADA satu pun yang punya jadwal.
            'hariPertama'  => $kartuRingkas['aktif'] > 0 && $kartuRingkas['backlog'] === $kartuRingkas['aktif'],
            'placeId'       => $placeId,
            'namaCabang'    => $namaCabang,
            'terkunci'      => $terkunci,
            // Isi pemilih cabang utama. Dihitung HANYA untuk role lintas cabang
            // — user yang terkunci ke satu cabang tidak pernah melihat pemilih
            // itu, jadi tidak perlu membayar kuerinya. resolveBranch() sudah
            // menerima ?utama= sejak awal; yang belum ada cuma kontrolnya.
            'hitungPerCabang' => $terkunci ? [] : $this->q->hitungPerCabang(),
            // Dipakai banner "kolom ini belum pernah dipakai" — lihat
            // OrtQuery::kolomBelumDipakai(). Dua EXISTS, dilayani
            // idx_ort_control_worklist; murah dan cuma di tab List.
            'kolomKosong'     => $tab === 'list' ? $this->q->kolomBelumDipakai($placeId) : ['fu' => false, 'rsv' => false],
            'tab'           => $tab,
            'urgensi'       => $urgensi,
            'kartuAktif'    => $kartuAktif,
            'cabangDilihat' => $cabangDilihat,
            'hanyaLihat'    => $cabangDilihat !== null,
            'peta'          => $this->branches->all(),
            'perCabangLain' => $perCabangLain,
            'filter'        => array_filter(Arr::except($filter, ['saya'])),
            'kartu'         => $kartuRingkas,
            'dokterPilihan' => $this->q->dokterDiCabang($placeId),
            'hariIni'       => $this->q->hariIni(),
            // Dipakai _paginasi untuk menandai pilihan yang sedang aktif.
            'perHal'        => $perHal,
            'detail'        => null,
        ];

        // ── Tab Kalender: grid bulanan jadwal kontrol + drawer harian.
        if ($tab === 'kalender') {
            $bulan = $request->query('bulan') && preg_match('/^\d{4}-\d{2}$/', $request->query('bulan'))
                ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $request->query('bulan') . '-01')
                : $this->q->hariIni();

            $hariDrawer = null;
            if ($request->query('hari') && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->query('hari'))) {
                $hariDrawer = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $request->query('hari'));
            }

            $kal = $this->q->kalender($lihatPlace, $bulan, $filter, $urgensi, $asalPasien);

            return $ctx + [
                'kalender'      => $kal,
                // Hitungan sub-tab datang dari baris grid yang sama dengan yang
                // dirender, jadi angkanya selalu cocok dengan isi layar.
                'hitungUrgensi' => $kal['hitung'],
                'hariDrawer'    => $hariDrawer,
                'hariData'      => $hariDrawer
                    ? $this->q->hari($lihatPlace, $hariDrawer, $filter, $asalPasien)
                    : null,
            ];
        }

        // ── Tab Pasien Ortho: roster per orang, Daftar ⇄ Kartu, Aktif ⇄ Selesai.
        if ($tab === 'pasien') {
            // `rawat` = sumbu status perawatan. Di mode Selesai sub-tab
            // kegentingan tidak berlaku — pasien yang perawatannya tuntas
            // tidak punya jadwal yang bisa terlewat — jadi urgensi dipaksa
            // 'semua' dan pita kirinya disembunyikan oleh list.blade.
            $rawat = $request->query('rawat') === 'selesai' ? 'selesai' : 'aktif';

            return $ctx + [
                'baris'         => $this->q->roster($lihatPlace, $filter, $perHal, $rawat === 'selesai' ? 'semua' : $urgensi, $asalPasien, $rawat),
                'hitungUrgensi' => $this->q->hitungUrgensiKasus($lihatPlace, $filter, $asalPasien),
                'hitungRoster'  => $this->q->hitungRoster($lihatPlace, $filter, $asalPasien),
                'tampil'        => $request->query('tampil') === 'kartu' ? 'kartu' : 'daftar',
                'rawat'         => $rawat,
            ];
        }

        // ── Tab cabang lain: lihat-saja, kolomnya berbeda dari tab sendiri.
        if ($cabangDilihat !== null) {
            $baris = $this->q->diCabangLain($placeId, $cabangDilihat, $perHal, $urgensi, $filter);

            return $ctx + [
                'baris'         => $baris,
                // Sub-tab kegentingan sekarang berlaku di sini juga, jadi
                // angkanya harus dihitung atas populasi yang sama: pasien kita
                // yang dipegang cabang itu.
                'hitungUrgensi' => $this->q->hitungUrgensiKasus($cabangDilihat, $filter, $placeId),
                'pengingat'     => $this->q->pengingatTerakhir($baris->pluck('id')->all()),
                'tertinggal' => collect($baris->items())
                    ->mapWithKeys(fn ($c) => [$c->id => $this->q->tertinggal($c)])->all(),
            ];
        }

        // ── Kartu ke-3 dipilih: tabel diganti antrean penjadwalan.
        if ($kartuAktif === 'backlog') {
            $baris = $this->q->antrean($placeId, $filter, $perHal, $urgensi);

            return $ctx + [
                'baris'         => $baris,
                'kelompok'      => $this->q->kelompokkanAntrean($baris->items()),
                'hitungAntrean' => $this->q->hitungAntrean($placeId, $filter),
                // Panel baris antrean menampilkan 4 kontrol terakhir, sama
                // seperti panel di daftar kerja. Diambil sekali untuk seluruh
                // halaman, bukan per baris.
                'riwayat'       => $this->q->kontrolTerakhir(
                    collect($baris->items())->pluck('id')->all()
                ),
            ];
        }

        // ── Daftar kerja harian.
        if ($kartuAktif === 'terlewat') {
            $urgensi = 'terlewat';
        } elseif ($kartuAktif === 'minggu-ini') {
            $urgensi = 'minggu-ini';
        }

        $baris   = $this->q->daftar($placeId, $urgensi, $filter, $perHal);
        $caseIds = collect($baris->items())->pluck('ort_case_id')->unique()->all();

        /*
         * Hari praktik dokter untuk SELURUH halaman dalam 5 kueri — bukan 27
         * per baris. Dipanggil di sini, bukan di Blade, karena satu panggilan
         * per halaman itulah seluruh pokok rancangannya; memanggilnya dari
         * dalam @foreach mengembalikan N+1 yang sudah dibayar mahal untuk
         * dihapus. Lihat OrtJadwalDokter::untukBanyak().
         */
        $jadwalDokter = app(\App\Services\Ortho\OrtJadwalDokter::class)
            ->untukBanyak($baris->items(), $placeId, $this->q->hariIni());

        /*
         * array_merge, BUKAN `+`. Operator `+` MEMPERTAHANKAN nilai kiri untuk
         * kunci yang sudah ada, dan konteksList() sudah menyetel 'urgensi'.
         * Akibatnya penyetelan ulang di atas ("kartu Terlewat berarti sub-tab
         * Terlewat") tidak pernah sampai ke layar: daftarnya memang tersaring
         * ke terlewat — `daftar()` memakai variabel lokal — tapi sub-tab yang
         * MENYALA tetap "Semua". Isi dan penandanya bertentangan, dan tautan
         * halaman berikutnya ikut membawa kegentingan yang salah.
         */
        return array_merge($ctx, [
            'urgensi'       => $urgensi,
            'baris'         => $baris,
            'hitungUrgensi' => $this->q->hitungUrgensi($placeId, $filter),
            'riwayat'       => $this->q->kontrolTerakhir($caseIds),
            'masukDari'     => $this->q->masukDari($caseIds, $placeId),
            'usulanSelesai' => $this->q->usulanSelesai($baris->items()),
            // Hari praktik per pasien — dikunci ort_case_id. Baris yang belum
            // masuk H-3 TIDAK ada di map ini (bukan entri kosong), jadi Blade
            // bisa membedakan "belum waktunya" dari "sudah waktunya tapi tidak
            // ada dokternya".
            'jadwalDokter'  => $jadwalDokter,
            /* Dua angka untuk badge "· N Inactive, di bawah". Tanpa badge ini,
               mengurutkan Inactive ke dasar sama dengan menyembunyikannya:
               dengan 50 baris per halaman, 400 Inactive berarti delapan halaman
               terakhir yang tidak akan pernah dibuka siapa pun. */
            'salesHitung'   => $this->q->hitungSalesAktif($placeId, $urgensi, $filter),
            // Dua daftar bernomor di panel baris (Phase 2). Diambil SEKALI untuk
            // seluruh halaman, bukan per baris — pola yang sama dengan $riwayat.
            'riwayatVisit'  => $this->q->riwayatKontrol($caseIds),
            'riwayatFu'     => $this->q->riwayatFollowUp($caseIds),
        ]);
    }

    /**
     * Drawer detail satu pasien — matriks kontrol lengkap.
     *
     * Dirender sebagai LAPISAN DI ATAS halaman List, bukan halaman tersendiri:
     * itu yang dituntut rancangan, dan dengan cara ini URL-nya tetap bisa
     * dibagikan dan tombol Kembali browser bekerja tanpa JavaScript apa pun.
     */
    public function drawerDetail(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_case'), 404);

        $case = OrtCase::with('controls')->findOrFail($caseId);

        [$placeId, $terkunci, $namaCabang] = $this->resolveBranch();

        // Pasien milik cabang lain hanya boleh DILIHAT, dan hanya kalau cabang
        // pasangnya memang kita. Di luar itu, bukan urusan cabang ini.
        // (string) di kedua sisi: place_id itu kolom varchar, sementara nilai
        // dari sisi PHP bisa datang sebagai int (lihat catatan di resolveBranch).
        $milikKita  = (string) $case->owner_place_id  === (string) $placeId;
        $pernahKita = (string) $case->install_place_id === (string) $placeId;
        abort_unless($milikKita || $pernahKita, 403);

        // array_merge, BUKAN `+`: konteksList() sudah menyetel 'detail' => null,
        // dan operator `+` mempertahankan sisi kiri — drawer tidak akan pernah muncul.
        $dasar = [
            'detail'      => $case,
            'detailLihat' => ! $milikKita,
            'detailIngat' => $this->q->pengingatTerakhir([$case->id])[$case->id] ?? null,
        ];

        // ?frag=drawer → kirim PANEL-nya saja, tanpa shell halaman. Dipakai JS
        // supaya drawer meluncur masuk tanpa memuat ulang halaman. URL penuh
        // tetap dilayani seperti biasa, jadi tanpa JS pun tetap jalan.
        //
        // Konteksnya dirakit SENDIRI, bukan lewat konteksList(): panel ini cuma
        // butuh peta cabang (ter-cache), tanggal hari ini, dan nama cabang.
        // konteksList() menjalankan seluruh kueri halaman daftar — tabel, kartu,
        // antrean, kalender, roster — yang semuanya langsung dibuang di sini.
        // Itu seperempat detik yang dibayar untuk apa-apa, dan terasa sebagai
        // jeda sebelum drawer muncul.
        if ($request->query('frag') === 'drawer') {
            return response()->view('crud.ortho._drawer_detail', $dasar + [
                'peta'       => $this->branches->all(),
                'hariIni'    => $this->q->hariIni(),
                'namaCabang' => $namaCabang,
            ])->header('X-Ort-Judul', $case->patient_name);
        }

        // Alasan sama seperti di index(): dicabut supaya paginator tidak
        // menyeret `frag` ke tautan halaman di dalam fragmen.
        $frag = $request->query('frag');
        $request->query->remove('frag');

        $ctx = array_merge(
            $this->konteksList($request, $placeId, $terkunci, $namaCabang),
            $dasar
        );

        // Lembar yang dibuka DARI drawer memakai route ini, bukan index().
        if ($frag !== null && ($lapisan = $this->fragmen($frag, $ctx))) {
            return $lapisan;
        }

        return view('crud.ortho.list', $ctx);
    }

    /**
     * Catat follow-up pada satu kontrol — design brief §10 (tangga tindakan).
     *
     * Sekali klik, langsung simpan, lalu toast + Urungkan (§11). BUKAN dialog
     * "Anda yakin?" — itu yang diminta rancangan, dan memang lebih cepat untuk
     * pekerjaan yang diulang puluhan kali sehari.
     *
     * Setelah tersimpan, baris naik satu anak tangga dengan sendirinya:
     * $aksi di _tabel.blade.php memilih 'buat_reservasi' begitu fu_date terisi.
     */
    public function catatFollowUp(Request $request, string $id)
    {
        abort_unless(Schema::hasTable('ort_control'), 404);

        $ctl = OrtControl::findOrFail($id);
        $this->pastikanMilikCabang($ctl);

        // Kontrol yang sudah ditutup tidak boleh di-follow-up lagi: riwayat
        // ditulis oleh tarikan malam, bukan oleh tombol.
        if ($ctl->closed_at !== null) {
            return back()->with('error', 'Kontrol ini sudah ditutup — follow-up tidak bisa dicatat.');
        }

        $data = $request->validate([
            'kanal'   => 'nullable|in:wa,telepon,langsung,lainnya',
            'tanggal' => 'nullable|date|before_or_equal:today',

            /*
             * HASIL follow-up (4 Okt 2026). Nullable supaya jalur lama (POST
             * tanpa form, mis. dari uji) tetap sah — tapi kalau dikirim, wajib
             * salah satu kunci OrtControl::FU_HASIL, bukan teks bebas. Teks
             * bebasnya di `catatan`.
             */
            'hasil'   => 'nullable|in:' . implode(',', array_keys(OrtControl::FU_HASIL)),
            'catatan' => 'nullable|string|max:255',

            /*
             * Tanggal yang DISEPAKATI pasien di telepon — beda dari `tanggal`
             * di atas, yang adalah tanggal follow-up-nya sendiri.
             *
             * Opsional dengan sengaja. Menelepon lalu tidak mendapat tanggal
             * adalah hasil yang sah: pasien sedang di luar kota, minta
             * ditelepon lagi pekan depan. Mewajibkannya akan mendorong Sales
             * mengarang tanggal supaya formnya mau ditutup — dan karangan itu
             * masuk ke daftar kerja sebagai janji.
             *
             * Dua medan karena lembarnya menawarkan dua jalan: chip hari
             * praktik dokter, atau isian tanggal bebas. Yang bebas hanya
             * dipakai kalau chip-nya tidak dipilih.
             */
            'sch_tanggal'      => 'nullable|date|after_or_equal:today',
            'sch_tanggal_lain' => 'nullable|date|after_or_equal:today',
        ]);

        // ?? bukan ?: — validate() TIDAK memasukkan kunci yang tidak dikirim
        // sama sekali, jadi $data['sch_tanggal'] bisa tidak ada, bukan sekadar
        // kosong. Lembar ini memang mengirim salah satu saja.
        $schBaru = ($data['sch_tanggal'] ?? null) ?: ($data['sch_tanggal_lain'] ?? null);

        $sebelum = [
            'fu_date'    => optional($ctl->fu_date)->toDateString(),
            'fu_channel' => $ctl->fu_channel,
            'fu_by'      => $ctl->fu_by,
            // Potret Urungkan WAJIB memuat keduanya — kalau tidak, Urungkan
            // memulihkan tanggal tapi meninggalkan hasil yang salah.
            'fu_result'  => $ctl->fu_result,
            'fu_note'    => $ctl->fu_note,
        ];

        $isian = [
            'fu_date'    => $data['tanggal'] ?? now()->toDateString(),
            /* Lembar tidak lagi menanyakan kanal (5 Okt 2026) — jadi JANGAN
               menebak 'wa'. Kolomnya dipertahankan untuk baris lama dan untuk
               pemanggil yang masih mengirimnya; yang tidak mengirim dapat NULL,
               dan layar menampilkan kanal hanya kalau ia memang ada. */
            'fu_channel' => $data['kanal'] ?? null,
            'fu_by'      => session('name') ?: 'Sales',
            // ?? bukan ?: — kunci tidak ada bila tidak dikirim (jalur lama).
            // Kalau dikirim kosong, biarkan kosong: jangan mengarang hasil.
            'fu_result'  => $data['hasil'] ?? null,
            'fu_note'    => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
            'updated_by' => session('id'),
        ];

        /*
         * Satu panggilan, satu penulisan.
         *
         * Kalau pasien menyebut tanggal, jadwalnya dipasang di penyimpanan yang
         * SAMA — bukan tindakan kedua. Begitu tanggalnya datang dari pasien, ia
         * bukan usulan mesin lagi, jadi is_proposal dilepas dan sumbernya jadi
         * 'manual'; persis yang dilakukan geserUsulan(). Keadaan sebelumnya ikut
         * direkam supaya satu Urungkan memulihkan KEDUANYA — kalau tidak,
         * mengurungkan follow-up meninggalkan jadwal yang sudah telanjur
         * dikonfirmasi, dan itu lebih membingungkan daripada tidak ada Urungkan.
         */
        if ($schBaru !== null) {
            $sebelum['sch_date']    = optional($ctl->sch_date)->toDateString();
            $sebelum['is_proposal'] = (int) $ctl->is_proposal;
            $sebelum['sch_source']  = $ctl->sch_source;

            $isian['sch_date']    = $schBaru;
            $isian['is_proposal'] = 0;
            $isian['sch_source']  = OrtControl::SCH_MANUAL;
        }

        $ctl->fill($isian)->save();

        // ── Riwayat: satu baris per PASIEN per HARI ──────────────────────────
        // ort_control cuma menyimpan follow-up TERAKHIR, jadi tanpa ini daftar
        // bernomor yang diminta prompt tidak punya sumber. Lihat OrtFollowUp.
        $undoRiwayat = $this->tulisRiwayatFollowUp($ctl, $isian);

        $pesan = 'Follow-up dicatat ' . $ctl->fu_date->translatedFormat('j M') . '.';
        if ($schBaru !== null) {
            $pesan = 'Follow-up dicatat, jadwal dipasang '
                   . $ctl->sch_date->translatedFormat("j M 'y") . '.';
        }

        return $this->selesai($ctl, $sebelum, $pesan, $undoRiwayat);
    }

    /**
     * Batalkan follow-up yang terlanjur tercatat.
     *
     * Kenapa ada, padahal setiap aksi tulis sudah punya Urungkan: jendela
     * Urungkan cuma 90 detik dan hidup di toast. Begitu toast-nya lewat,
     * satu salah klik pada "Catat Follow-up" mengubah tombol baris itu jadi
     * "Buat Reservasi" secara permanen, dan tidak ada jalan pulang. Ini
     * jalan pulangnya — tetap lewat mekanisme Urungkan yang sama, jadi
     * membatalkan pembatalan pun bisa.
     *
     * Bukan penghapusan riwayat: fu_date memang catatan kerja Sales hari
     * ini, bukan fakta dari rekam medis. Fakta kunjungan ditulis tarikan
     * malam ke kolom lain dan tidak tersentuh dari sini.
     */
    public function batalFollowUp(Request $request, string $id)
    {
        $ctl = OrtControl::findOrFail($id);
        $this->pastikanMilikCabang($ctl);

        if ($ctl->closed_at !== null) {
            return back()->with('error', 'Kontrol ini sudah ditutup — follow-up tidak bisa diubah.');
        }

        if (! $ctl->fu_date) {
            return back()->with('error', 'Belum ada follow-up yang bisa dibatalkan.');
        }

        $sebelum = [
            'fu_date'    => optional($ctl->fu_date)->toDateString(),
            'fu_channel' => $ctl->fu_channel,
            'fu_by'      => $ctl->fu_by,
            'fu_result'  => $ctl->fu_result,
            'fu_note'    => $ctl->fu_note,
        ];

        /*
         * Baris riwayatnya ikut dibuang — dan ini bukan pilihan sepele.
         * "Panggilan itu tidak dihitung" harus berarti hal yang SAMA di kedua
         * tempat; kalau ort_control dikosongkan tapi ort_followup dibiarkan,
         * tombolnya kembali hijau sementara riwayat masih mengaku pasien
         * ditelepon hari itu. Dua layar, dua jawaban, untuk satu pertanyaan.
         *
         * Keadaannya direkam dulu supaya Urungkan memulihkan barisnya utuh.
         */
        $undoRiwayat = $this->hapusRiwayatFollowUp($ctl, (string) $sebelum['fu_date']);

        // Hasil dan catatan ikut dikosongkan: membatalkan follow-up berarti
        // "panggilan itu tidak dihitung", termasuk apa pun yang dicatat darinya.
        $ctl->fill([
            'fu_date'    => null,
            'fu_channel' => null,
            'fu_by'      => null,
            'fu_result'  => null,
            'fu_note'    => null,
            'updated_by' => session('id'),
        ])->save();

        return $this->selesai($ctl, $sebelum, 'Follow-up dibatalkan.', $undoRiwayat);
    }

    /**
     * Geser tanggal usulan ke tanggal yang sudah disepakati dengan pasien.
     *
     * Menggeser SEKALIGUS mengonfirmasi: begitu Sales mengetik tanggal
     * sendiri, itu bukan usulan mesin lagi. Tanpa ini baris tetap memakai
     * gaya "usulan" (garis putus-putus) padahal sudah disepakati manusia.
     */
    public function geserUsulan(Request $request, string $id)
    {
        abort_unless(Schema::hasTable('ort_control'), 404);

        $ctl = OrtControl::findOrFail($id);
        $this->pastikanMilikCabang($ctl);

        if ($ctl->closed_at !== null) {
            return back()->with('error', 'Kontrol ini sudah ditutup.');
        }

        $batas = $this->q->hariIni()->copy()->addDays(OrtControl::MIN_MAJU_HARI);

        $data = $request->validate([
            'tanggal' => 'required|date|after_or_equal:' . $batas->toDateString(),
            'catatan' => 'nullable|string|max:255',
        ], [
            'tanggal.after_or_equal' => 'Jadwal paling cepat '
                . $batas->translatedFormat("j M 'y") . ' — beri jeda minimal '
                . OrtControl::MIN_MAJU_HARI . ' hari untuk mengabari pasien.',
        ]);

        $sebelum = [
            'sch_date'    => $ctl->sch_date?->toDateString(),
            'sch_note'    => $ctl->sch_note,
            'sch_source'  => $ctl->sch_source,
            'is_proposal' => (int) $ctl->is_proposal,
        ];

        $ctl->fill([
            'sch_date'    => $data['tanggal'],
            'sch_note'    => $data['catatan'] ?? null,
            'sch_source'  => OrtControl::SCH_MANUAL,
            'is_proposal' => 0,
            'updated_by'  => session('id'),
        ])->save();

        return $this->selesai($ctl, $sebelum,
            'Jadwal digeser ke ' . $ctl->sch_date->translatedFormat('j M') . '.');
    }

    /**
     * Tandai pasien tidak hadir — menutup kontrol tanpa kunjungan.
     *
     * ⚠ SENGAJA MANUAL, menyimpang dari HANDOFF.md §1 yang menyebut ini
     *   otomatis. Keputusan pemilik produk: mesin hanya mengisi FAKTA
     *   (kunjungan yang benar-benar terjadi); ketidakhadiran ditutup manusia.
     *   Tarikan malam yang menebak "tidak hadir" akan salah setiap kali
     *   pasien datang di cabang lain atau rekam medisnya telat masuk.
     *
     * Setelah ditutup, kasusnya kehilangan kontrol terbuka dan jatuh ke
     * antrean penjadwalan — itu memang yang diinginkan: harus dijadwalkan ulang.
     */
    public function tandaiTidakHadir(Request $request, string $id)
    {
        abort_unless(Schema::hasTable('ort_control'), 404);

        $ctl = OrtControl::findOrFail($id);
        $this->pastikanMilikCabang($ctl);

        if ($ctl->closed_at !== null) {
            return back()->with('error', 'Kontrol ini sudah ditutup.');
        }
        if ($ctl->visit_date !== null) {
            return back()->with('error', 'Pasien tercatat datang '
                . $ctl->visit_date->translatedFormat('j M') . ' — tidak bisa ditandai tidak hadir.');
        }
        if ($ctl->sch_date && $ctl->sch_date->gt($this->q->hariIni())) {
            return back()->with('error', 'Jadwalnya masih ' . $ctl->sch_date->translatedFormat('j M')
                . ' — belum bisa ditandai tidak hadir.');
        }

        $sebelum = [
            'outcome'   => $ctl->outcome,
            'closed_at' => optional($ctl->closed_at)->toDateTimeString(),
            'closed_by' => $ctl->closed_by,
        ];

        $ctl->fill([
            'outcome'    => OrtControl::OUT_TIDAK_HADIR,
            'closed_at'  => now(),
            'closed_by'  => session('name') ?: 'Sales',
            'updated_by' => session('id'),
        ])->save();

        return $this->selesai($ctl, $sebelum, 'Ditandai tidak hadir.');
    }

    /**
     * Jadwalkan kontrol berikutnya — membuat baris ort_control BARU.
     *
     * Ini aksi untuk pasien yang TIDAK BISA ditolong mesin: belum pernah
     * datang, atau kontrol terakhirnya ditutup "tidak hadir". Tarikan malam
     * hanya melahirkan usulan SETELAH ada kunjungan; tanpa kunjungan tidak ada
     * dasar apa pun untuk menebak tanggal, jadi manusia yang menentukan.
     *
     * Aturan satu-kontrol-terbuka dijaga di sini — tidak ada UNIQUE parsial di
     * MySQL yang bisa menjaganya di lapisan data (lihat risiko no. 5 di rencana).
     */
    public function jadwalkanKontrol(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_control'), 404);

        $case = OrtCase::findOrFail($caseId);

        [$placeId] = $this->resolveBranch();
        abort_unless((string) $case->owner_place_id === (string) $placeId, 403);

        if ($case->status !== 'aktif') {
            return back()->with('error', 'Perawatan pasien ini sudah ' . $case->status . '.');
        }

        // Pagar aturan satu-kontrol-terbuka. Dibaca dari tabel, bukan dari
        // kolom rollup: rollup adalah turunan, dan kalau ia yang melenceng,
        // pagar ini ikut bocor tanpa suara.
        $terbuka = OrtControl::where('ort_case_id', $case->id)->whereNull('closed_at')->first();
        if ($terbuka) {
            return back()->with('error', 'Pasien ini sudah punya kontrol terbuka ('
                . $terbuka->sch_date?->translatedFormat("j M 'y") . ') — tutup dulu yang itu.');
        }

        $batas = $this->q->hariIni()->copy()->addDays(OrtControl::MIN_MAJU_HARI);

        $data = $request->validate([
            'tanggal' => 'required|date|after_or_equal:' . $batas->toDateString(),
            'catatan' => 'nullable|string|max:255',
        ], [
            'tanggal.after_or_equal' => 'Jadwal paling cepat '
                . $batas->translatedFormat("j M 'y") . ' — beri jeda minimal '
                . OrtControl::MIN_MAJU_HARI . ' hari untuk mengabari pasien.',
        ]);

        $seq = (int) OrtControl::where('ort_case_id', $case->id)->max('seq') + 1;

        $ctl = OrtControl::create([
            'id'          => (string) Str::uuid(),
            'ort_case_id' => $case->id,
            'seq'         => $seq,
            'place_id'    => (string) $placeId,
            'is_proposal' => 0,
            'sch_date'    => $data['tanggal'],
            'sch_source'  => OrtControl::SCH_MANUAL,
            'sch_note'    => $data['catatan'] ?? null,
            'created_by'  => session('id'),
            'updated_by'  => session('id'),
        ]);

        // sebelum = null → Urungkan berarti HAPUS baris ini, bukan pulihkan kolom.
        return $this->selesai($ctl, null,
            'Kontrol #' . $seq . ' dijadwalkan ' . $ctl->sch_date->translatedFormat('j M') . '.');
    }

    /**
     * Ekor yang sama untuk setiap aksi tulis pada satu kontrol: segarkan
     * rollup, simpan keadaan sebelumnya, kembalikan toast + token Urungkan.
     */
    private function selesai(OrtControl $ctl, ?array $sebelum, string $pesan, array $tambahan = [])
    {
        $this->rollup->refresh($ctl->ort_case_id);

        return $this->toast($pesan, $this->simpanUrungkan($ctl->ort_case_id, [
            'kontrol' => ['id' => $ctl->id, 'sebelum' => $sebelum],
        ] + $tambahan));
    }

    /**
     * Pindahkan pasien ke cabang lain — design brief §11.
     *
     * Yang berpindah adalah KONTROL-nya, bukan kasusnya: `place_id` kontrol
     * terbuka ditulis ke cabang tujuan, lalu OrtRollup menghitung ulang
     * `owner_place_id` dari situ. Kasus tidak punya kolom cabang yang ditulis
     * langsung — satu sumber kebenaran, tidak ada dua angka yang bisa berbeda.
     *
     * Alasan WAJIB. Tanpa alasan tercatat, perpindahan pasien tidak bisa
     * ditelusuri belakangan, dan itulah satu-satunya nilai tabel ort_transfer:
     * ia mencatat KEJADIAN, bukan keadaan.
     */
    public function pindahkan(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_transfer'), 404);

        $case = OrtCase::findOrFail($caseId);
        [$placeId] = $this->resolveBranch();
        abort_unless((string) $case->owner_place_id === (string) $placeId, 403);

        if ($case->status !== OrtCase::STATUS_AKTIF) {
            return back()->with('error', 'Perawatan pasien ini sudah ' . $case->status . '.');
        }

        $data = $request->validate([
            'tujuan'    => 'required|string',
            'alasan'    => 'required|in:' . implode(',', array_keys(OrtTransfer::REASONS)),
            'catatan'   => 'nullable|string|max:255',
            'sementara' => 'nullable',
        ], [
            'alasan.required' => 'Alasan wajib dipilih — tanpa itu perpindahan tidak bisa ditelusuri.',
        ]);

        $tujuan = (string) $data['tujuan'];
        if (! $this->branches->ada($tujuan)) {
            return back()->with('error', 'Cabang tujuan tidak dikenal.');
        }
        if ($tujuan === (string) $placeId) {
            return back()->with('error', 'Cabang tujuan sama dengan cabang sekarang.');
        }

        $terbuka = OrtControl::where('ort_case_id', $case->id)->whereNull('closed_at')->first();
        if (! $terbuka) {
            return back()->with('error', 'Pasien ini belum punya jadwal kontrol — jadwalkan dulu sebelum dipindahkan.');
        }

        $sebelum  = ['place_id' => $terbuka->place_id];
        $sementara = (bool) ($data['sementara'] ?? false);
        $transferId = (string) Str::uuid();

        DB::transaction(function () use ($case, $terbuka, $tujuan, $placeId, $data, $sementara, $transferId) {
            $terbuka->fill(['place_id' => $tujuan, 'updated_by' => session('id')])->save();

            OrtTransfer::create([
                'id'                      => $transferId,
                'ort_case_id'             => $case->id,
                'ort_control_id'          => $terbuka->id,
                'from_place_id'           => (string) $placeId,
                'to_place_id'             => $tujuan,
                'transfer_date'           => $this->q->hariIni()->toDateString(),
                'reason'                  => $data['alasan'],
                'reason_note'             => $data['catatan'] ?? null,
                'is_temporary'            => $sementara,
                // Transfer sementara: begitu kontrol INI ditutup, pasien kembali
                // ke cabang asal. Yang menutup lingkarannya OrtRollup::refresh()
                // — lihat pulangkanTransferSementara() di sana.
                'return_after_control_id' => $sementara ? $terbuka->id : null,
                'detected'                => OrtTransfer::DETECTED_MANUAL,
                'created_by'              => session('id'),
            ]);
        });

        $this->rollup->refresh($case->id);

        return $this->toast(
            \Illuminate\Support\Str::title($case->patient_name) . ' dipindahkan ke ' . $this->branches->nama($tujuan) . '.',
            $this->simpanUrungkan($case->id, [
                'kontrol' => ['id' => $terbuka->id, 'sebelum' => $sebelum],
                'hapus'   => [OrtTransfer::class => $transferId],
            ])
        );
    }

    /**
     * Ingatkan cabang yang sedang memegang pasien kita.
     *
     * Dipakai dari tab cabang lain dan dari drawer lihat-saja. SATU-SATUNYA
     * aksi tulis yang boleh menyentuh kasus milik cabang lain — dan ia tidak
     * mengubah data pasien sama sekali, hanya mencatat bahwa kita sudah
     * menegur. Sisanya di tab itu benar-benar lihat-saja.
     */
    public function ingatkan(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_reminder'), 404);

        $case = OrtCase::findOrFail($caseId);
        [$placeId] = $this->resolveBranch();

        // Syaratnya: pasien ini DIPASANG di cabang kita, tapi sedang dipegang
        // cabang lain. Di luar itu bukan urusan kita.
        $milikKita = (string) $case->install_place_id === (string) $placeId;
        $dipegangLain = (string) $case->owner_place_id !== (string) $placeId;
        abort_unless($milikKita && $dipegangLain, 403);

        // Jeda: jangan memberondong cabang tujuan dengan pesan yang sama.
        $terakhir = OrtReminder::where('ort_case_id', $case->id)
            ->orderByDesc('reminded_at')->first();

        if ($terakhir && ! $terakhir->bolehDiingatkanLagi()) {
            return back()->with('error', 'Sudah diingatkan '
                . $terakhir->reminded_at->translatedFormat('j M')
                . ' — tunggu ' . OrtReminder::JEDA_HARI . ' hari sebelum mengingatkan lagi.');
        }

        $data = $request->validate([
            'kanal'   => 'nullable|in:wa,telepon,lainnya',
            'catatan' => 'nullable|string|max:255',
        ]);

        $id = (string) Str::uuid();

        OrtReminder::create([
            'id'            => $id,
            'ort_case_id'   => $case->id,
            'from_place_id' => (string) $placeId,
            'to_place_id'   => (string) $case->owner_place_id,
            'reminded_at'   => now(),
            'reminded_by'   => session('name') ?: 'Sales',
            'channel'       => $data['kanal'] ?? 'wa',
            'note'          => $data['catatan'] ?? null,
        ]);

        return $this->toast(
            $this->branches->nama((string) $case->owner_place_id) . ' sudah diingatkan.',
            $this->simpanUrungkan($case->id, ['hapus' => [OrtReminder::class => $id]])
        );
    }

    /**
     * Tandai perawatan selesai — bracket dilepas.
     *
     * Jalur MANUSIA untuk hal yang biasanya datang dari tarikan malam (item
     * "Lepas Bracket"). Ada karena rekam medis bisa telat, atau pasien
     * berhenti tanpa transaksi apa pun.
     *
     * Jadwal yang masih terbuka ikut ditutup sebagai 'batal': perawatan sudah
     * berakhir, jadi janji berikutnya tidak berlaku lagi. Dibiarkan terbuka, ia
     * akan selamanya dilaporkan menggantung oleh ort:rollup --verify.
     */
    public function tandaiSelesai(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_case'), 404);

        $case = OrtCase::findOrFail($caseId);
        [$placeId] = $this->resolveBranch();
        abort_unless((string) $case->owner_place_id === (string) $placeId, 403);

        if ($case->status !== OrtCase::STATUS_AKTIF) {
            return back()->with('error', 'Perawatan pasien ini sudah ' . $case->status . '.');
        }

        $sebelumKasus = [
            'status'      => $case->status,
            'finished_at' => optional($case->finished_at)->toDateString(),
        ];

        $terbuka = OrtControl::where('ort_case_id', $case->id)->whereNull('closed_at')->first();
        $sebelumKontrol = $terbuka ? [
            'outcome'   => $terbuka->outcome,
            'closed_at' => optional($terbuka->closed_at)->toDateTimeString(),
            'closed_by' => $terbuka->closed_by,
        ] : null;

        DB::transaction(function () use ($case, $terbuka) {
            $case->fill([
                'status'      => OrtCase::STATUS_SELESAI,
                'finished_at' => $this->q->hariIni()->toDateString(),
                'updated_by'  => session('id'),
            ])->save();

            if ($terbuka) {
                $terbuka->fill([
                    'outcome'    => OrtControl::OUT_BATAL,
                    'closed_at'  => now(),
                    'closed_by'  => session('name') ?: 'Sales',
                    'updated_by' => session('id'),
                ])->save();
            }
        });

        $this->rollup->refresh($case->id);

        $undo = ['kasusKolom' => $sebelumKasus];
        if ($terbuka) {
            $undo['kontrol'] = ['id' => $terbuka->id, 'sebelum' => $sebelumKontrol];
        }

        return $this->toast(
            \Illuminate\Support\Str::title($case->patient_name) . ' ditandai selesai.',
            $this->simpanUrungkan($case->id, $undo)
        );
    }

    /** Jawaban seragam untuk setiap aksi tulis yang berhasil. */
    private function toast(string $pesan, string $token)
    {
        return redirect()->to($this->pulang())->with([
            'ort_toast' => $pesan,
            'ort_undo'  => $token,
        ]);
    }

    /**
     * Ke mana pulang setelah aksi tulis BERHASIL.
     *
     * back() saja tidak cukup: kalau aksinya datang dari sebuah lembar, URL
     * asalnya masih membawa ?sheet=... — jadi lembar yang sama terbuka lagi
     * begitu halaman dimuat ulang, menutupi toast di belakangnya. Kunci
     * lapisan dibuang; PATH-nya sengaja dipertahankan, supaya lembar yang
     * dibuka dari atas drawer detail meninggalkan drawernya tetap terbuka.
     *
     * Aksi yang GAGAL tetap memakai back() — di situ lembar memang harus
     * tetap terbuka supaya isinya bisa diperbaiki.
     */
    private function pulang(): string
    {
        $ref = request()->headers->get('referer');
        if (! $ref) {
            return route('ortho.index');
        }

        $bagian = parse_url($ref);

        // Hanya URL di dalam aplikasi ini — referer datang dari peramban dan
        // tidak boleh dipercaya sebagai tujuan redirect begitu saja.
        if (! empty($bagian['host']) && $bagian['host'] !== request()->getHost()) {
            return route('ortho.index');
        }
        $path = $bagian['path'] ?? '';
        if (! str_starts_with($path, '/tracking-ortho')) {
            return route('ortho.index');
        }

        parse_str($bagian['query'] ?? '', $q);
        unset($q['sheet'], $q['kontrol'], $q['kasus'], $q['tambah'], $q['frag']);

        return $path . ($q ? '?' . http_build_query($q) : '');
    }

    /**
     * Urungkan aksi tulis terakhir — design brief §17.
     *
     * Yang disimpan di cache adalah KEADAAN SEBELUMNYA, bukan perintah balik.
     * Memulihkan nilai lama selalu benar; menghitung kebalikan sebuah operasi
     * mudah meleset begitu ada dua orang menyentuh baris yang sama.
     */
    public function urungkan(Request $request, string $token)
    {
        $kunci = 'ort:undo:' . $token;
        $simpan = Cache::get($kunci);

        // Token milik satu orang saja, dan hanya sekali pakai.
        if (! $simpan || ($simpan['user'] ?? null) !== session('id')) {
            return back()->with('error', 'Sudah lewat waktunya untuk diurungkan.');
        }

        // ⚠ Gerbang cabang dibaca dari TOKEN, bukan dari baris yang disentuh.
        //   Sesudah transfer, kontrolnya sudah MILIK CABANG TUJUAN — memeriksa
        //   pastikanMilikCabang() pada baris itu akan menolak Urungkan-nya
        //   sendiri dengan 403. Yang benar: cabang yang melakukan aksi, itulah
        //   yang boleh membatalkannya.
        [$placeId] = $this->resolveBranch();
        if ((string) ($simpan['cabang'] ?? '') !== (string) $placeId) {
            abort(403);
        }

        Cache::forget($kunci);

        // Satu jalur untuk SEMUA aksi. Tiga bentuk pemulihan, boleh digabung:
        //   kontrol.sebelum = array → kembalikan kolomnya
        //   kontrol.sebelum = null  → barisnya baru dibuat, hapus
        //   kasusKolom              → kembalikan kolom ort_case
        //   hapus                   → buang baris jejak (ort_transfer/ort_reminder)
        if ($k = ($simpan['kontrol'] ?? null)) {
            $ctl = OrtControl::find($k['id']);
            if ($ctl) {
                if ($k['sebelum'] === null) {
                    $ctl->delete();
                } else {
                    $ctl->fill($k['sebelum'] + ['updated_by' => session('id')])->save();
                }
            }
        }

        /*
         * Baris riwayat follow-up. Tiga bentuk, dan ketiganya perlu:
         *   sebelum === null → barisnya BARU dibuat aksi itu, hapus
         *   sebelum = array  → barisnya sudah ada lalu diperbarui, kembalikan
         *   (tanpa id)       → tidak ada riwayat yang tersentuh
         * Bentuk kedua terjadi saat Sales mencatat follow-up KEDUA di hari yang
         * sama: UNIQUE (ort_case_id, fu_date) melarang baris kedua, jadi yang
         * lama diperbarui — dan Urungkan harus memulihkan nilai lamanya, bukan
         * menghapus barisnya.
         */
        if ($r = ($simpan['fuRiwayat'] ?? null)) {
            if ($r['sebelum'] === null) {
                OrtFollowUp::where('id', $r['id'])->delete();
            } else {
                $baris = OrtFollowUp::find($r['id']);
                if ($baris) {
                    $baris->fill($r['sebelum'])->save();
                } else {
                    // Dihapus oleh batalFollowUp — bangun ulang dari potretnya.
                    OrtFollowUp::create($r['sebelum'] + ['id' => $r['id']]);
                }
            }
        }

        if ($kolom = ($simpan['kasusKolom'] ?? null)) {
            $case = OrtCase::find($simpan['kasus']);
            if ($case) {
                $case->fill($kolom + ['updated_by' => session('id')])->save();
            }
        }

        foreach (($simpan['hapus'] ?? []) as $model => $id) {
            $model::where('id', $id)->delete();
        }

        $this->rollup->refresh($simpan['kasus']);

        return back()->with('ort_toast', 'Dikembalikan seperti semula.');
    }

    /**
     * Tulis satu baris riwayat follow-up untuk (pasien, hari ini).
     *
     * UNIQUE (ort_case_id, fu_date) di DB melarang dua baris per hari — itu
     * aturan prompt ("UNIQUE pasien+tanggal"), dan ia sekaligus yang membuat
     * tombol hijau lagi besok tanpa cron. Jadi follow-up kedua di hari yang
     * sama MEMPERBARUI baris yang ada, bukan menambah.
     *
     * ⚠ Dibungkus Schema::hasTable: SQL modul ini dijalankan manual, jadi ada
     *   jendela di mana kodenya sudah naik tapi tabelnya belum ada. Follow-up
     *   tetap harus bisa dicatat di jendela itu — kehilangan riwayatnya jauh
     *   lebih murah daripada tombolnya mati 500.
     *
     * @param  array<string,mixed>  $isian  nilai yang baru ditulis ke ort_control
     * @return array  muatan Urungkan — [] kalau tidak ada yang tersentuh
     */
    private function tulisRiwayatFollowUp(OrtControl $ctl, array $isian): array
    {
        if (! Schema::hasTable('ort_followup')) {
            return [];
        }

        $tgl = optional($ctl->fu_date)->toDateString();
        if (! $tgl) {
            return [];
        }

        $nilai = [
            'ort_case_id'    => $ctl->ort_case_id,
            'ort_control_id' => $ctl->id,
            'place_id'       => (string) $ctl->place_id,
            'fu_date'        => $tgl,
            'fu_channel'     => $isian['fu_channel'] ?? null,
            'fu_result'      => $isian['fu_result'] ?? null,
            'fu_note'        => $isian['fu_note'] ?? null,
            'fu_by'          => $isian['fu_by'] ?? null,
            'created_by'     => session('id'),
        ];

        $ada = OrtFollowUp::where('ort_case_id', $ctl->ort_case_id)
            ->where('fu_date', $tgl)
            ->first();

        if ($ada) {
            $sebelum = [
                'ort_control_id' => $ada->ort_control_id,
                'place_id'       => $ada->place_id,
                'fu_channel'     => $ada->fu_channel,
                'fu_result'      => $ada->fu_result,
                'fu_note'        => $ada->fu_note,
                'fu_by'          => $ada->fu_by,
            ];
            $ada->fill($nilai)->save();

            return ['fuRiwayat' => ['id' => $ada->id, 'sebelum' => $sebelum]];
        }

        $id = (string) Str::uuid();
        OrtFollowUp::create($nilai + ['id' => $id, 'created_at' => now()]);

        return ['fuRiwayat' => ['id' => $id, 'sebelum' => null]];
    }

    /** Buang baris riwayat satu hari, dengan potret untuk Urungkan. */
    private function hapusRiwayatFollowUp(OrtControl $ctl, string $tanggal): array
    {
        if (! Schema::hasTable('ort_followup') || $tanggal === '') {
            return [];
        }

        $baris = OrtFollowUp::where('ort_case_id', $ctl->ort_case_id)
            ->where('fu_date', $tanggal)
            ->first();

        if (! $baris) {
            return [];
        }

        $potret = [
            'ort_case_id'    => $baris->ort_case_id,
            'ort_control_id' => $baris->ort_control_id,
            'place_id'       => $baris->place_id,
            'fu_date'        => $tanggal,
            'fu_channel'     => $baris->fu_channel,
            'fu_result'      => $baris->fu_result,
            'fu_note'        => $baris->fu_note,
            'fu_by'          => $baris->fu_by,
            'created_by'     => $baris->created_by,
        ];

        $id = $baris->id;
        $baris->delete();

        return ['fuRiwayat' => ['id' => $id, 'sebelum' => $potret]];
    }

    /**
     * Tandai pasien Active / Inactive — penilaian Sales, bukan fakta klinis.
     *
     * ────────────────────────────────────────────────────────────────────────
     * KENAPA KOLOMNYA SENDIRI, BUKAN `status`
     * ────────────────────────────────────────────────────────────────────────
     * `ort_case.status` diisi MESIN: OrtPullVisits menandai 'selesai' begitu
     * bracket terdeteksi lepas dari rekam medis. Kalau penilaian Sales
     * ditumpangkan ke sana, tarikan malam akan menimpa keputusan manusia tanpa
     * jejak — dan pasien yang sengaja dilepas kembali sendiri tiap malam.
     *
     * Gerbangnya KEPEMILIKAN CABANG, bukan role. Modul ini sengaja nol
     * pemeriksaan role; alasannya ditulis panjang di resolveBranch().
     */
    public function salesAktif(Request $request, string $caseId)
    {
        abort_unless(Schema::hasTable('ort_case'), 404);

        $case = OrtCase::findOrFail($caseId);
        [$placeId] = $this->resolveBranch();
        abort_unless((string) $case->owner_place_id === (string) $placeId, 403);

        $data = $request->validate([
            'aktif'   => 'required|in:0,1',
            'catatan' => 'nullable|string|max:255',
        ]);

        $mau = (int) $data['aktif'] === OrtCase::SALES_AKTIF;

        if ((bool) $case->sales_active === $mau) {
            return back()->with('error', 'Pasien ini sudah ' . ($mau ? 'Active' : 'Inactive') . '.');
        }

        $sebelum = [
            'sales_active'      => (int) $case->sales_active,
            'sales_active_at'   => optional($case->sales_active_at)->toDateTimeString(),
            'sales_active_by'   => $case->sales_active_by,
            'sales_active_note' => $case->sales_active_note,
        ];

        $case->fill([
            'sales_active'      => $mau ? OrtCase::SALES_AKTIF : OrtCase::SALES_PASIF,
            'sales_active_at'   => now(),
            'sales_active_by'   => session('name') ?: 'Sales',
            'sales_active_note' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
            'updated_by'        => session('id'),
        ])->save();

        $pesan = $mau
            ? 'Pasien ditandai Active — kembali masuk urutan kerja.'
            : 'Pasien ditandai Inactive — turun ke bawah daftar, tetap ditampilkan.';

        return $this->toast($pesan, $this->simpanUrungkan($case->id, [
            'kasus'      => $case->id,
            'kasusKolom' => $sebelum,
        ]));
    }

    /**
     * Gerbang tulis: hanya cabang PEMEGANG kontrol yang boleh mengubahnya.
     *
     * Sengaja memakai place_id kontrol, bukan owner_place_id kasus — kontrol
     * itulah satuan yang berpindah saat transfer. Tab cabang lain lihat-saja,
     * jadi tidak ada satu pun endpoint tulis yang menerima kasus milik orang.
     */
    private function pastikanMilikCabang(OrtControl $ctl): void
    {
        [$placeId] = $this->resolveBranch();
        abort_unless((string) $ctl->place_id === (string) $placeId, 403);
    }

    /** Simpan keadaan sebelum aksi, 90 detik — toast hidup 8 detik, sisanya margin. */
    private function simpanUrungkan(string $caseId, array $isi): string
    {
        [$placeId] = $this->resolveBranch();
        $token = Str::random(32);

        Cache::put('ort:undo:' . $token, $isi + [
            'kasus'  => $caseId,
            'user'   => session('id'),
            'cabang' => (string) $placeId,
        ], now()->addSeconds(90));

        return $token;
    }

    /**
     * Cabang modul ini, dalam place_id Omnicare.
     *
     * Menjiplak KomisiDokterController::resolveBranch() (baris 553–580): kalau
     * user ter-scope ke cabang, kembali LEBIH AWAL supaya `?cabang=` tidak
     * pernah dilirik sebagai pengganti cabang utama. Bedanya, di sini tidak ada
     * mode "Semua Cabang" — halaman ini selalu berpijak pada satu cabang,
     * karena "siapa yang menagih" adalah pertanyaan per cabang.
     *
     * @return array{0:?string,1:bool,2:string} [placeId, terkunci, namaCabang]
     */
    private function resolveBranch(): array
    {
        $branchUuid = session('raw_branch_id');

        if ($branchUuid) {
            $placeId = $this->branches->placeIdDariBranchId((string) $branchUuid);
            if ($placeId !== null) {
                return [$placeId, true, $this->branches->nama($placeId)];
            }
        }

        /*
         * SIAPA YANG SAMPAI DI SINI: hanya orang yang TIDAK punya cabang.
         * Yang punya `raw_branch_id` sudah pulang di blok atas, dan `?utama=`
         * bahkan tidak pernah dilirik untuk mereka — itu pagar yang menahan
         * Sales/Front Office menembus ke cabang lain lewat URL. Jangan
         * dilonggarkan.
         *
         * ⚠ TIDAK ADA pemeriksaan role di sini, dan itu DISENGAJA.
         *
         * Versi sebelumnya menuntut canSeeAllBranches(). Akibatnya orang tanpa
         * cabang yang tidak masuk daftar itu — Admin, Head IT, IT, Supervisor —
         * dipaku ke cabang PERTAMA (Bintaro, 927 kasus) dan pemilihnya tidak
         * dirender sama sekali. Tanpa galat, tanpa penjelasan: mereka melihat
         * satu dari lima cabang dan mengira itu seluruh datanya. Tiga dari
         * empat orang itu justru yang menguji modul ini.
         *
         * Membuka pemilih untuk mereka BUKAN pelonggaran akses: mereka sudah
         * melihat satu cabang penuh: yang berubah cuma cabang MANA, bukan boleh
         * atau tidaknya melihat. Halamannya sendiri sudah dijaga RBAC menu.
         *
         * Dan sengaja BUKAN daftar role baru: daftar akan basi lagi begitu ada
         * role berikutnya tanpa cabang, dan jebakan yang sama terpasang ulang.
         * Menambahkannya ke canSeeAllBranches() lebih buruk lagi — konstanta itu
         * dipakai 47 tempat dan ikut membuka unggah sidik jari, koreksi absen,
         * periode gaji, dan petty cash lintas cabang.
         *
         * Keadaan pinggir yang ikut benar: user yang PUNYA raw_branch_id tapi
         * cabangnya belum dipetakan ke omnicare_place_id juga mendarat di sini.
         * Ia memang tidak punya cabang DI MODUL INI, jadi memilih adalah
         * jawaban yang benar — bukan kelalaian.
         */
        $diminta = (string) request('utama', '');
        if ($this->branches->ada($diminta)) {
            return [$diminta, false, $this->branches->nama($diminta)];
        }

        // ⚠ PHP mengubah kunci array numerik jadi int, jadi array_key_first()
        //   di sini mengembalikan 29957 (int) — bukan '29957'. Tanpa (string),
        //   semua perbandingan === terhadap kolom varchar place_id jadi false
        //   dan user tanpa cabang (Super Admin) kena 403 di drawer detail.
        $pertama = array_key_first($this->branches->all());

        // terkunci = false: lihat catatan di atas. Yang sampai ke sini tidak
        // punya cabang, jadi mengunci mereka ke cabang pertama berarti memaku
        // orang ke tempat yang bukan miliknya, tanpa jalan keluar.
        return $pertama === null
            ? [null, false, '—']
            : [(string) $pertama, false, $this->branches->nama((string) $pertama)];
    }
}
