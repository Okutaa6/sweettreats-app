<?php

namespace App\Http\Controllers\ItTracker;

use App\Http\Controllers\Controller;
use App\Models\ItModul;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\KatalogModul;
use Illuminate\Http\Request;

/**
 * Katalog Modul — "apa saja yang kami punya" (rencana §14.24–14.25).
 *
 * Halaman SAUDARA papan, bukan tab keempat: papan menjawab apa yang sedang
 * bergerak, katalog menjawab apa yang dimiliki. Keduanya dua tampilan atas
 * satu data — angka tiket di sini bisa diklik dan mendarat di papan yang
 * sudah tersaring ke modul itu.
 *
 * ⛔ Tak ada penulisan katalog dari halaman ini kecuali SATU: Head IT menambah
 *    entri modul. Fitur diturunkan dari menu; angka tiket dari tabel tiket.
 */
class KatalogController extends Controller
{
    private const TAMPILAN = ['daftar', 'kartu', 'matriks'];
    private const LENSA    = ['dept', 'jenis'];

    public function __construct(private ItTrackerService $svc, private KatalogModul $katalog)
    {
    }

    public function index(Request $request)
    {
        $peran = $this->peran();

        // Tampilan bawaan MENGIKUTI PERAN: Executive/Konsultan/Head datang
        // sebulan sekali dan tak akan belajar navigasi → Matriks (33 modul
        // sekali lihat). IT memakainya sebagai daftar induk saat triase → Daftar.
        $bawaan   = in_array($peran, ['head_it', 'it_dev'], true) ? 'daftar' : 'matriks';
        $tampilan = in_array($request->query('tampilan'), self::TAMPILAN, true) ? $request->query('tampilan') : $bawaan;
        $lensa    = in_array($request->query('lensa'), self::LENSA, true) ? $request->query('lensa') : 'dept';
        $jenisSel = in_array($request->query('jenis'), KatalogModul::JENIS, true) ? $request->query('jenis') : null;
        $q        = trim((string) $request->query('q', ''));

        $semua = $this->katalog->modul();
        $cacah = $this->katalog->cacahTiket();
        $tiket = $this->katalog->tiketTerbuka($this->svc);

        // ⛔ Head departemen hanya boleh melihat ISI tiketnya sendiri (rencana §12 —
        // aturan yang sama sudah dipegang detail tiket & panel ketergantungan
        // FRM-IT-02). Di katalog ia tetap melihat ANGKA semua modul — beban IT
        // memang harus terbaca — tapi judul tiket orang lain disensor, bukan
        // dibiarkan bocor lewat panel ringkasan.
        $bolehIsi = fn ($t) => $peran !== 'head' || $t->pemohon_user_id === session('id');

        // fitur diturunkan dari access_path lewat DAFTAR LINK modul (§14.27),
        // dedup per link — satu halaman dihitung sekali walau labelnya banyak.
        $fitur = [];
        foreach ($semua as $m) $fitur[$m->id] = $this->katalog->fiturModul($m);

        // penyaring: pencarian menyentuh nama modul, departemen, jenis, DAN nama fiturnya
        $tampil = $semua->filter(function ($m) use ($q, $fitur) {
            if ($q === '') return true;
            $teks = $m->nama . ' ' . $m->departemen . ' ' . $m->jenis . ' '
                . implode(' ', array_column($fitur[$m->id], 'nama'));
            return stripos($teks, $q) !== false;
        });
        if ($lensa === 'jenis' && $jenisSel && $tampilan !== 'matriks') {
            $tampil = $tampil->filter(fn ($m) => $m->jenis === $jenisSel);
        }

        // kelompok: per departemen (bawaan) atau per jenis — lensa tegak lurus tampilan
        $kunci  = fn ($m) => $lensa === 'dept' ? $m->departemen : $m->jenis;
        $nama   = $lensa === 'dept' ? KatalogModul::DEPARTEMEN : ($jenisSel ? [$jenisSel] : KatalogModul::JENIS);
        $groups = [];
        foreach ($nama as $g) {
            $rows = $tampil->filter(fn ($m) => $kunci($m) === $g)->values();
            if ($rows->isEmpty() && $q !== '') continue;   // pencarian: kelompok kosong tak perlu tampil

            $semuaDiKelompok = $semua->filter(fn ($m) => $kunci($m) === $g);
            $berjalan = 0; $utang = 0;
            foreach ($semuaDiKelompok as $m) {
                $berjalan += $cacah[$m->id]['berjalan'] ?? 0;
                $utang    += $cacah[$m->id]['terbuka'] ?? 0;
            }

            $groups[] = [
                'nama'     => $g,
                'rows'     => $rows,
                'nHidup'   => $semuaDiKelompok->where('status', 'hidup')->count(),
                'nBerjalan' => $berjalan,
                'nUtang'   => $utang,
                'nEntri'   => $semuaDiKelompok->count(),
                'nDept'    => $semuaDiKelompok->pluck('departemen')->unique()->count(),
                'kosong'   => $semuaDiKelompok->where('status', 'hidup')->count() === 0,
            ];
        }

        // matriks 8×4 — 33 modul dalam satu layar, untuk yang datang sebulan sekali
        $matriks = [];
        foreach (KatalogModul::DEPARTEMEN as $d) {
            $baris = $tampil->filter(fn ($m) => $m->departemen === $d);
            $sel   = [];
            foreach (KatalogModul::JENIS as $j) $sel[$j] = $baris->filter(fn ($m) => $m->jenis === $j)->values();
            $utangDept = 0;
            foreach ($semua->where('departemen', $d) as $m) $utangDept += $cacah[$m->id]['terbuka'] ?? 0;
            $matriks[] = ['nama' => $d, 'sel' => $sel, 'utang' => $utangDept,
                'nHidup' => $semua->where('departemen', $d)->where('status', 'hidup')->count(),
                'nEntri' => $semua->where('departemen', $d)->count()];
        }

        // rekonsiliasi: tiket terbuka yang TIDAK menempel ke modul mana pun.
        // Wajib ada + berjumlah eksplisit — angka yang bisa dicocokkan dengan
        // papan, bukan dipercaya. (Tak ada tebakan dari judul; lihat KatalogModul.)
        $yatim    = $tiket[0] ?? [];
        $terbuka  = array_sum(array_map(fn ($c) => $c['terbuka'], $cacah));
        $bertaut  = $terbuka - count($yatim);

        return view('it-tracker.katalog', [
            'peran'     => $peran,
            'tampilan'  => $tampilan,
            'lensa'     => $lensa,
            'jenisSel'  => $jenisSel,
            'q'         => $q,
            'groups'    => $groups,
            'matriks'   => $matriks,
            'semua'     => $semua,
            'fitur'     => $fitur,
            'cacah'     => $cacah,
            'tiket'     => $tiket,
            'yatim'     => $yatim,
            'bolehIsi'  => $bolehIsi,
            'jumlah'    => [
                'modul'    => $semua->count(),
                'dept'     => count(KatalogModul::DEPARTEMEN),
                'fitur'    => array_sum(array_map('count', $fitur)),
                'hidup'    => $semua->where('status', 'hidup')->count(),
                'dibangun' => $semua->where('status', 'dibangun')->count(),
                'rencana'  => $semua->where('status', 'rencana')->count(),
                'berjalan' => $this->berjalanBertaut($cacah),
                'terbuka'  => $terbuka,
                'bertaut'  => $bertaut,
                'yatim'    => count($yatim),
            ],
        ]);
    }

    /** Entri modul baru — Head IT saja. Satu-satunya penulisan katalog dari layar. */
    public function store(Request $request)
    {
        if ($this->peran() !== 'head_it') {
            abort(403, 'Menambah entri katalog hanya dilakukan Head IT.');
        }

        $d = $request->validate([
            'nama'       => 'required|string|max:120|unique:it_modul,nama',
            'departemen' => 'required|in:' . implode(',', KatalogModul::DEPARTEMEN),
            'jenis'      => 'required|in:' . implode(',', KatalogModul::JENIS),
            'status'     => 'required|in:' . implode(',', array_keys(KatalogModul::STATUS)),
            'catatan'    => 'nullable|string|max:2000',
        ]);

        // sejak & access_id sengaja TIDAK ditanyakan di sini: tanggal harus bisa
        // dibuktikan (jangan mengarang), dan node menu diturunkan lewat seeder
        // saat modulnya benar-benar punya menu.
        $m = ItModul::create($d + ['sejak_terbukti' => false, 'urutan' => (int) (ItModul::max('urutan') ?? 0) + 10]);

        return redirect()->route('it-tracker.katalog', ['modul' => $m->id])
            ->with('success', 'Entri katalog "' . $m->nama . '" dibuat — status ' . KatalogModul::statusLabel($m->status)
                . '. Tanggal "sejak" diisi nanti kalau ada tabel yang membuktikannya.');
    }

    /** Tiket BERTAUT yang sedang berjalan (terbuka & tidak ditunda) — yang yatim tak ikut. */
    private function berjalanBertaut(array $cacah): int
    {
        $n = 0;
        foreach ($cacah as $mid => $c) if ($mid !== 0) $n += $c['berjalan'];
        return $n;
    }

    private function peran(): string
    {
        return ItTrackerService::peran(session('group_name'));
    }
}
