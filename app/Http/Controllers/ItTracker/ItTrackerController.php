<?php

namespace App\Http\Controllers\ItTracker;

use App\Http\Controllers\Controller;
use App\Models\ItTicket;
use App\Services\ItTracker\ItTrackerService;
use App\Services\ItTracker\PhaseEngine;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * IT Request Tracker — Fase 1: formulir permintaan, triase, papan antrean,
 * "menunggu siapa", panel beban. Rencana: docs/rencana/rencana-it-tracker.md.
 *
 * Gerbang tulis ada DI SINI (bukan sekadar menyembunyikan tombol) — uji terima
 * §14.6 no. 12: Reza mencoba triase harus ditolak controller.
 */
class ItTrackerController extends Controller
{
    /** Fase terminal yang tampil di parkiran papan (bukan kolom aktif). */
    private const FASE_PARKIRAN = ['DITUNDA', 'DITOLAK', 'SELESAI'];

    public function __construct(private ItTrackerService $svc)
    {
    }

    /**
     * Tiket aktif + bentuk mesin fasenya, sekali muat. Dipakai papan,
     * halaman permintaan, dan panel beban — SATU tempat, jangan disalin
     * (salinan yang menyimpang = papan dan halaman Head tak sepakat).
     */
    private function aktifDenganView(): array
    {
        $aktif = $this->svc->muatRelasi(
            ItTicket::whereIn('fase', array_keys(PhaseEngine::RANK))
        )->get();

        $views = $aktif->mapWithKeys(fn ($t) => [$t->id => $this->svc->viewTicket($t)]);

        return [$aktif, $views];
    }

    // ─── Halaman ───────────────────────────────────────────────────────────

    /** Papan antrean — IT / Executive / Konsultan. Head diarahkan ke halamannya. */
    public function index(Request $request)
    {
        if ($this->peran() === 'head') {
            return redirect()->route('it-tracker.permintaan');
        }

        // Hanya tiket AKTIF yang butuh relasi & mesin fase; parkiran (SELESAI/
        // DITUNDA/DITOLAK — bertambah selamanya) cukup 6 kolom skalar.
        [$aktif, $views] = $this->aktifDenganView();

        // Potret SEBELUM filter — dipakai kolom kosong untuk menyebut ke mana
        // tiketnya pergi ("3 tiket Development ada di tab Permintaan"). Tanpa ini,
        // kosong-karena-filter tak bisa dibedakan dari kosong-sungguhan.
        $aktifAwal = $aktif;

        // Filter papan (q / macet / sumber / jenjang / unit) — sama dengan mockup.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $aktif = $aktif->filter(fn ($t) => stripos($t->nomor . ' ' . $t->judul, $q) !== false);
        }
        if ($request->query('macet')) {
            $aktif = $aktif->filter(fn ($t) => PhaseEngine::waitOf($views[$t->id])['heat'] >= 1);
        }
        if ($sumber = $request->query('sumber')) {
            $aktif = $aktif->filter(fn ($t) => $sumber === 'inisiatif'
                ? $t->sumber !== 'permintaan'
                : $t->sumber === 'permintaan');
        }
        if ($jenjang = $request->query('jenjang')) {
            $aktif = $aktif->filter(fn ($t) => $t->jenjang === $jenjang);
        }
        // r3: chip "RB Las" — unit usaha (kolom it_ticket.unit)
        if ($unit = $request->query('unit')) {
            $aktif = $aktif->filter(fn ($t) => $t->unit === $unit);
        }

        // Sumbu papan = FASE, dikelompokkan Masuk/Rancang/Kerjakan/Rilis.
        $grupDef = [
            'MASUK'    => [['k' => 'REQ',      'label' => 'Requirement']],
            'RANCANG'  => [['k' => 'ANALISIS', 'label' => 'Analysis & Design']],
            'KERJAKAN' => [['k' => 'DEV',      'label' => 'Development'], ['k' => 'UJI', 'label' => 'Internal Testing']],
            'RILIS'    => [['k' => 'UAT',      'label' => 'UAT'], ['k' => 'GOLIVE', 'label' => 'Go Live'], ['k' => 'EVALUASI', 'label' => 'Evaluation']],
        ];

        $semuaView = $views->values()->all();
        // r3 panel beban: chip per tiket macet (menunggu di luar IT ≥3 hr) — klik = detail
        $macetChips = [];
        foreach ($views as $id => $vw) {
            $w = PhaseEngine::waitOf($vw);
            if ($w['heat'] >= 1) $macetChips[] = ['id' => $id, 'nomor' => $vw['id'], 'days' => $w['days'], 'heat' => $w['heat'], 'label' => $w['label']];
        }
        usort($macetChips, fn ($a, $b) => $b['days'] <=> $a['days']);
        // fase per nomor — untuk chip dep di kartu (dep yang sudah SELESAI tak ditampilkan)
        $this->faseNomor = ItTicket::pluck('fase', 'nomor')->all();
        $groups    = [];
        foreach ($grupDef as $nama => $fases) {
            $kolom = [];
            foreach ($fases as $f) {
                $cards = $aktif
                    ->filter(fn ($t) => PhaseEngine::phaseOf($t->fase) === $f['k'])
                    ->map(fn ($t) => $this->kartu($t, $views[$t->id], $semuaView))
                    ->sortBy([['heat', 'desc'], ['rank', 'asc'], ['since', 'asc']])
                    ->values();
                $kolom[] = ['k' => $f['k'], 'label' => $f['label'], 'cards' => $cards];
            }
            $groups[$nama] = $kolom;
        }

        // Berapa tiket per fase SEBELUM filter, dipecah per tab jenis — JS memakainya
        // untuk memutuskan apakah kolom kosong itu "memang kosong" atau "tersaring".
        $rawFase = [];
        foreach ($aktifAwal as $x) {
            $kf = PhaseEngine::phaseOf($x->fase);
            $kj = $x->jenjang === 'S' ? 'support' : ($x->jenjang ? 'fitur' : 'baru');
            $rawFase[$kf][$kj] = ($rawFase[$kf][$kj] ?? 0) + 1;
        }

        // Satu alasan + satu jalan keluar sekali klik. Urutan sengaja: sumber → unit
        // → macet → pencarian; yang paling sering menyembunyikan disebut lebih dulu.
        $sisa = fn (array $ubah) => route('it-tracker.index', array_filter(array_merge(
            array_filter(['q' => $q ?: null, 'macet' => $request->query('macet') ? 1 : null,
                          'sumber' => $sumber ?: null, 'jenjang' => $jenjang ?: null, 'unit' => $unit ?: null]), $ubah)));
        $saring = null;
        if ($sumber) {
            $lain = $sumber === 'permintaan' ? 'inisiatif' : 'permintaan';
            $saring = ['teks' => 'ada di tab ' . ($lain === 'permintaan' ? 'Permintaan' : 'Inisiatif IT') . '.',
                       'aksi' => 'Buka tab ' . ($lain === 'permintaan' ? 'Permintaan' : 'Inisiatif IT'),
                       'href' => $sisa(['sumber' => $lain])];
        } elseif ($unit) {
            $saring = ['teks' => 'bukan tiket ' . ItTrackerService::unitLabel($unit) . '.',
                       'aksi' => 'Lepas filter ' . ItTrackerService::unitLabel($unit), 'href' => $sisa(['unit' => null])];
        } elseif ($request->query('macet')) {
            $saring = ['teks' => 'tidak macet — penunggunya IT atau belum 3 hari.',
                       'aksi' => 'Lepas filter Macet', 'href' => $sisa(['macet' => null])];
        } elseif ($q !== '') {
            $saring = ['teks' => 'tidak cocok pencarian "' . $q . '".',
                       'aksi' => 'Hapus pencarian', 'href' => $sisa(['q' => null])];
        } elseif ($jenjang) {
            $saring = ['teks' => 'bukan jenjang ' . $jenjang . '.',
                       'aksi' => 'Lepas filter jenjang', 'href' => $sisa(['jenjang' => null])];
        }

        // Pencarian WAJIB menjangkau parkiran: tiket yang sudah selesai/ditunda tetap
        // ada, dan papan yang diam saja membuatnya seolah tak pernah ada.
        $parkir = ItTicket::whereIn('fase', self::FASE_PARKIRAN)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nomor', 'like', '%' . $q . '%')
                                                            ->orWhere('judul', 'like', '%' . $q . '%')))
            ->orderByDesc('fase_since')
            // `jenjang` ikut supaya parkiran bisa mengikuti tab jenis permintaan di papan —
            // tanpa itu, tab Support memperlihatkan tiket M/L yang ditunda dan keduanya bercerita beda.
            ->get(['id', 'nomor', 'judul', 'fase', 'fase_since', 'alasan_parkir', 'jenjang']);

        return view('it-tracker.papan', [
            'groups'  => $groups,
            'parkir'  => $parkir,
            'beban'   => $this->svc->ringkasanBeban($semuaView),
            'peran'   => $this->peran(),
            'picOpsi' => $this->peran() === 'head_it' ? $this->picOpsi() : collect(),
            'filter'  => ['q' => $q, 'macet' => (bool) $request->query('macet'),
                          'sumber' => $sumber, 'jenjang' => $jenjang, 'unit' => $unit],
            'macetChips' => $macetChips,
            'rawFase'    => $rawFase,
            'saring'     => $saring,
            'totalAktif' => $views->count(),
        ]);
    }

    /** Halaman Permintaan (Head) — formulir + milikku + ringkasan beban IT. */
    public function permintaan()
    {
        [, $views] = $this->aktifDenganView();
        $semua = $views->values()->all();

        // Milik user diambil sendiri (pakai ix_it_ticket_pemohon) — termasuk
        // yang sudah selesai/parkir, yang sengaja tak dimuat aktifDenganView.
        $milik = $this->svc->muatRelasi(
            ItTicket::where('pemohon_user_id', $this->userId())
        )->orderByDesc('created_at')->get()
            ->map(function ($t) use ($views, $semua) {
                // tiket parkir/selesai milik user tak ada di $views (aktif saja)
                $v = $views[$t->id] ?? $this->svc->viewTicket($t);
                return [
                    't'     => $t,
                    'wait'  => PhaseEngine::waitOf($v),
                    'prog'  => PhaseEngine::progressOf($v),
                    'antre' => $t->fase === 'ANTRE' ? PhaseEngine::queuePos($v, $semua) : null,
                ];
            });

        return view('it-tracker.permintaan', [
            'milik' => $milik,
            'beban' => $this->svc->ringkasanBeban($semua),
            'peran' => $this->peran(),
        ]);
    }

    /** Detail tiket. Head hanya boleh membuka miliknya (keputusan rencana §12). */
    public function show(int $id)
    {
        $t = $this->svc->muatRelasi(ItTicket::query())->findOrFail($id);
        $this->pastikanBolehLihat($t);

        $v    = $this->svc->viewTicket($t);
        $gate = PhaseEngine::gateInfo($v);

        // Posisi antrean hanya berarti di fase ANTRE, dan queuePos cuma
        // membaca id/fase/faseSince — jangan menghidrasi seluruh tabel.
        $antre = null;
        if ($t->fase === 'ANTRE') {
            $semua = ItTicket::whereIn('fase', array_keys(PhaseEngine::RANK))
                ->get(['nomor', 'fase', 'fase_since'])
                ->map(fn ($x) => ['id' => $x->nomor, 'fase' => $x->fase,
                    'faseSince' => $x->fase_since->toIso8601String()])->all();
            $antre = PhaseEngine::queuePos($v, $semua);
        }

        return view('it-tracker.detail', [
            't'      => $t,
            'v'      => $v,
            'wait'   => PhaseEngine::waitOf($v),
            'prog'   => PhaseEngine::progressOf($v),
            'antre'  => $antre,
            'gate'   => $gate,
            'jalur'  => PhaseEngine::pathFor($t->jenjang),
            'peran'  => $this->peran(),
            'bolehGerak' => $this->bolehGerakkan($t),
            'picOpsi'    => $this->peran() === 'head_it' ? $this->picOpsi() : collect(),
            // nama orang IT untuk PIC temuan (FRM-IT-04) — dibaca semua peran, ditulis IT
            'picIt'      => $this->picOpsi(),
            // Fase 2b — siapa boleh apa di empat formulir (gerbang aslinya di controller)
            'bolehAnalisis' => $this->peran() === 'head_it' && $t->fase === 'ANALISIS',
            'bolehParafBo'  => $this->peran() === 'pengawas' && $t->fase === 'ANALISIS',
            // jalan pintas bertanda: Head IT, di ANALISIS, selama belum ada paraf maupun pelewatan
            'bolehTeruskan' => $this->peran() === 'head_it' && $t->fase === 'ANALISIS'
                && empty($v['fr02']['paraf']) && empty($v['fr02']['diteruskan']),
            'bolehUji'      => $this->bolehGerakkan($t)['ok'] && $t->fase === 'UJI',
            // predikat yang SAMA dengan rute skenario() — jangan tampilkan formulir yang berujung 403
            'bolehSkenario' => $this->bolehTulisSkenario($t) && in_array($t->fase, ['DEV', 'UJI', 'UAT'], true),
            'bolehJawabUat' => $this->bolehJawabUat($t),
            'bolehLampiran' => $this->bolehLampiran($t) && $t->fase !== 'DITOLAK',
            'bolehKemajuan' => $this->bolehKemajuan($t) && isset(PhaseEngine::RANK[$t->fase]),
            'bolehEvaluasi' => $this->bolehEvaluasi($t),
            'depOpsi'       => $this->peran() === 'head_it' && $t->fase === 'ANALISIS'
                ? ItTicket::where('id', '!=', $t->id)->where('fase', '!=', 'DITOLAK')
                    ->orderByDesc('id')->get(['nomor', 'judul', 'fase'])
                : collect(),
            'depTiket'      => ItTicket::whereIn('nomor', $v['deps'])->get(['nomor', 'judul', 'fase'])->keyBy('nomor'),
            'evOpsi'        => $this->peran() === 'head_it' && $t->fase === 'EVALUASI' ? $this->pengawasOpsi() : collect(),
            'form01' => optional($t->forms->firstWhere('kode', 'FRM-IT-01'))->payload ?? [],
            'form03' => optional($t->forms->firstWhere('kode', 'FRM-IT-03'))->payload ?? [],
        ]);
    }

    // ─── Aksi ──────────────────────────────────────────────────────────────

    /** Kirim tiket baru — permintaan (semua peran) / inisiatif·insiden (IT). */
    public function store(Request $request)
    {
        $sumber = $request->input('sumber', 'permintaan');

        if ($sumber !== 'permintaan' && ! $this->adalahIt()) {
            abort(403, 'Inisiatif/insiden hanya dicatat oleh IT.');
        }

        // Insiden masuk langsung ke Development, "formulir diisi menyusul"
        // (rencana §3) — jangan paksa mengarang prosa di tengah kebakaran.
        $isiWajib = $sumber === 'insiden' ? 'nullable' : 'required';

        $d = $request->validate([
            'judul'             => 'required|string|max:255',
            'jenis_permintaan'  => $isiWajib . '|string|max:32',
            'latar'             => $isiWajib . '|string',
            'kebutuhan'         => $isiWajib . '|string',
            'terdampak'         => 'nullable|string|max:255',
            'target'            => 'nullable|string|max:120',
            'prioritas_pemohon' => ($sumber === 'permintaan' ? 'required' : 'nullable') . '|in:Rendah,Sedang,Tinggi',
            'jenjang'           => 'nullable|in:S,M,L',
            'bobot'             => 'nullable|integer|min:1|max:5',
            'prioritas_it'      => 'nullable|in:P1,P2,P3',
            // kerjaan ini untuk unit usaha yang mana (SweetTreats / RB Las)
            'unit'              => 'nullable|in:' . implode(',', array_keys(ItTrackerService::UNIT)),
        ] + $this->aturanLampiran(), $this->pesanLampiran(), [
            'judul' => 'nama modul/fitur', 'latar' => 'masalah hari ini',
            'kebutuhan' => 'hasil yang diharapkan',
            'prioritas_pemohon' => 'seberapa mendesak menurutmu',
        ]);

        $d['sumber']        = $sumber;
        $d['raw_branch_id'] = session('raw_branch_id');

        try {
            $t = $this->svc->buat($d, $this->userId());
            // Lampiran FRM-IT-01 (§7 B): bukti masalah menempel di tiket, bukan
            // dikirim lewat WhatsApp — kebiasaan yang mau digantikan sistem ini.
            $this->svc->tambahLampiran($t, $request->file('lampiran', []), $this->userId());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // "diTangan" = fase ANALISIS ke atas — cukup satu COUNT ber-indeks,
        // jangan menghidrasi seluruh tabel untuk satu angka di toast.
        $diTangan = ItTicket::whereIn('fase',
            array_keys(array_filter(PhaseEngine::RANK, fn ($r) => $r >= 2))
        )->count();

        return back()->with('success',
            $t->nomor . ' terkirim — antre di belakang ' . $diTangan . ' yang sudah di tangan IT.');
    }

    /** Triase — HANYA Head IT (uji terima no. 12: ditolak di controller). */
    public function triase(Request $request, int $id)
    {
        $this->pastikanHeadIt('Triase hanya dilakukan Head IT.');

        $d = $request->validate([
            'jenjang'      => 'required|in:S,M,L',
            'bobot'        => 'required|integer|min:1|max:5',
            'prioritas_it' => 'required|in:P1,P2,P3',
            'pic_user_id'  => 'nullable|string|exists:users,id',
        ]);

        $t = ItTicket::findOrFail($id);

        try {
            $this->svc->triase($t, $d['jenjang'], (int) $d['bobot'], $d['prioritas_it'], $this->userId(), $d['pic_user_id'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success',
            $t->nomor . ' ditriase: jenjang ' . $d['jenjang'] . ', ' . $d['prioritas_it'] . ' → Antre.');
    }

    /** Tunda / tolak beralasan — Head IT. */
    public function parkir(Request $request, int $id)
    {
        $this->pastikanHeadIt('Menunda/menolak tiket hanya dilakukan Head IT.');

        $d = $request->validate([
            'ke'     => 'required|in:DITUNDA,DITOLAK',
            'alasan' => 'required|string|max:500',
        ], [], ['alasan' => 'alasan']);

        $t = ItTicket::findOrFail($id);

        try {
            $this->svc->parkir($t, $d['ke'], $d['alasan'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('it-tracker.index')->with('success',
            $t->nomor . ($d['ke'] === 'DITUNDA'
                ? ' ditunda — tetap tercatat di papan.'
                : ' ditolak dengan penjelasan.'));
    }

    public function bukaLagi(int $id)
    {
        $this->pastikanHeadIt('Membuka lagi tiket hanya dilakukan Head IT.');

        $t = ItTicket::findOrFail($id);

        try {
            $ke = $this->svc->bukaLagi($t, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $t->nomor . ' dibuka lagi → ' . ($ke === 'ANTRE' ? 'Antre' : 'Triase') . '.');
    }

    /** Majukan lewat gerbang. Reza: hanya tiket yang dia PIC, di DEV/UJI. */
    public function maju(int $id)
    {
        $t = $this->svc->muatRelasi(ItTicket::query())->findOrFail($id);

        $boleh = $this->bolehGerakkan($t);
        // Evaluasi: evaluatornya sendiri boleh menutup tiket — "menutup tiket
        // adalah parafnya" (FRM-IT-07) tak boleh berakhir di tombol yang IT-only.
        $evaluatorMenutup = $t->fase === 'EVALUASI' && $this->bolehEvaluasi($t);
        if (! $boleh['ok'] && ! $evaluatorMenutup) {
            abort(403, $boleh['why']);
        }

        if ($t->fase === 'UAT') {
            abort(403, 'Gerbang UAT hanya dibuka pemohon — bukan IT (rencana §4).');
        }

        try {
            $ke = $this->svc->majukan($t, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $label = $ke === 'SELESAI' ? 'Selesai' : PhaseEngine::phShort($ke);

        return back()->with('success', $t->nomor . ' → ' . $label . '.');
    }

    /** Keputusan UAT — hanya pemohon tiketnya. */
    public function uat(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);

        // Gerbang UAT milik pemohon. Tiket TANPA pemohon (inisiatif/insiden —
        // pemohon_user_id NULL) diputuskan Head IT sebagai pemiliknya; tanpa
        // cabang ini tiket inisiatif M/L macet PERMANEN di UAT (tak ada satu
        // akun pun yang lolos pembanding NULL).
        $boleh = $t->pemohon_user_id
            ? $t->pemohon_user_id === $this->userId()
            : $this->peran() === 'head_it';

        if (! $boleh) {
            abort(403, $t->pemohon_user_id
                ? 'Keputusan UAT milik pemohon tiket ini.'
                : 'Tiket tanpa pemohon — keputusan UAT-nya milik Head IT.');
        }

        // validasi SESUDAH otorisasi (terbalik = 302, bukan 403)
        $d = $request->validate(['keputusan' => 'required|in:setuju,catatan,perbaikan']);

        try {
            $ke = $this->svc->uatPutuskan($t, $d['keputusan'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', match ($d['keputusan']) {
            'setuju'  => $t->nomor . ' — UAT disetujui → Go Live.',
            'catatan' => $t->nomor . ' — Disetujui dengan Catatan → Go Live; catatan jadi tindak lanjut.',
            default   => $t->nomor . ' — Perlu Perbaikan → kembali ke Development (konfirmasi FRM-IT-03 direset).',
        });
    }

    /** Catatan singkat pengerjaan (jenjang S) — IT yang mengerjakannya. */
    public function catatan(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);

        $boleh = $this->bolehGerakkan($t);
        if (! $boleh['ok']) {
            abort(403, $boleh['why']);
        }
        // Gerbang tulis selengkap UI-nya: catatan S hanya di DEV — bukan
        // menimpa catatan penutup tiket SELESAI atau tiket M/L.
        if ($t->jenjang !== 'S' || $t->fase !== 'DEV') {
            abort(403, 'Catatan singkat hanya untuk tiket jenjang S yang sedang di Development.');
        }

        // validasi SESUDAH otorisasi — kalau terbalik, orang tanpa hak dapat 302, bukan 403
        $d = $request->validate(['catatan' => 'required|string|max:1000']);

        $this->svc->catatanDev($t, $d['catatan'], $this->userId());

        return back()->with('success', 'Catatan pengerjaan tersimpan.');
    }

    /** Konfirmasi Development Record (jenjang M/L) — pembuka gerbang DEV→UJI/UAT. */
    public function konfirmasi(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);

        $boleh = $this->bolehGerakkan($t);
        if (! $boleh['ok']) {
            abort(403, $boleh['why']);
        }
        if (! in_array($t->jenjang, ['M', 'L'], true)) {
            abort(403, 'Jenjang S memakai catatan singkat, bukan konfirmasi FRM-IT-03.');
        }

        $d = $request->validate(['catatan' => 'nullable|string|max:1000']);

        try {
            $this->svc->konfirmasiDev($t, $d['catatan'] ?? null, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Development Record dikonfirmasi — gerbang berikutnya terbuka.');
    }

    // ─── FRM-IT-06 Checklist Go Live + ekspor + jejak git (Fase 2a) ────────

    /** Satu baris checklist pra-deploy: Ya / N-A. */
    public function pra(Request $request, int $id)
    {
        // Otorisasi DULU, baru validasi: yang tak berhak harus dapat 403, bukan
        // umpan balik validasi tentang tiket yang tak boleh dia sentuh.
        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

        $d = $request->validate([
            'kunci' => 'required|string|max:16',
            'nilai' => 'required|in:ya,na',
        ]);

        try {
            $this->svc->setPra($t, $d['kunci'], $d['nilai'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Checklist pra-deploy diperbarui.');
    }

    /** Satu pemeriksaan pasca Go-Live → Pass. */
    public function pasca(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

        $d = $request->validate(['kunci' => 'required|string|max:16']);

        try {
            $this->svc->setPasca($t, $d['kunci'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pemeriksaan pasca Go-Live dicatat.');
    }

    /** Catatan deployment (FRM-IT-06 §C). */
    public function deploy(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

        $d = $request->validate(['catatan' => 'nullable|string|max:2000']);

        try {
            $this->svc->setCatatanDeploy($t, $d['catatan'] ?? null, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Catatan deployment tersimpan.');
    }

    /**
     * ACC Business Owner — HANYA Executive/Konsultan.
     * ⛔ IT sengaja tidak boleh, termasuk Head IT: paraf yang bisa dibubuhkan
     * sendiri oleh pelaksana bukan kendali, cuma teater (rencana §4).
     */
    public function businessOwner(int $id)
    {
        if ($this->peran() !== 'pengawas') {
            abort(403, 'ACC Go-Live milik Business Owner (Executive / Konsultan), bukan IT.');
        }

        $t = ItTicket::findOrFail($id);

        try {
            $this->svc->setujuiBusinessOwner($t, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $t->nomor . ' — disetujui Business Owner.');
    }

    /**
     * Tombol "segarkan" jejak git (rencana §14.3).
     * Yang dipanggil perintah artisan yang sama dengan cron, DIBATASI ke satu
     * nomor tiket. Halaman sendiri tak pernah memanggil git — ia membaca
     * it_git_ref. Nomor tiket berasal dari DB, dikirim sebagai argumen argv
     * (bukan string shell), jadi tak ada permukaan injeksi.
     */
    public function syncGit(int $id)
    {
        if (! $this->adalahIt()) {
            abort(403, 'Menyegarkan jejak git dilakukan tim IT.');
        }

        $t = ItTicket::findOrFail($id);

        $kode = Artisan::call('it:sync-git', ['--ticket' => $t->nomor]);
        $out  = trim(Artisan::output());

        if ($kode !== 0) {
            return back()->with('error', 'Gagal membaca git: ' . strtok($out, "\n"));
        }

        return back()->with('success', 'Jejak git disegarkan — ' . strtok($out, "\n"));
    }

    /**
     * Ekspor FRM-IT-01 — dokumen bentuk templat resmi.
     * ⚠️ Bagian A (identitas pemohon) tampil TERISI dari akun walau layar
     * pengisian tak pernah menanyakannya: Konsultan IT & Management harus
     * mengenali dokumennya (rencana §7).
     */
    public function ekspor(int $id)
    {
        $t = $this->svc->muatRelasi(ItTicket::query())->findOrFail($id);
        $this->pastikanBolehLihat($t);

        $f1 = optional($t->forms->firstWhere('kode', 'FRM-IT-01'))->payload ?? [];

        if (! $f1) {
            return back()->with('error',
                'Tiket ini tak punya FRM-IT-01 (inisiatif/insiden IT tidak lahir dari formulir permintaan).');
        }

        // ⚠️ Tiket TANPA pemohon tetap bisa punya FRM-IT-01: insiden yang
        // latarnya diisi melahirkan formulir itu, sementara pemohon_user_id
        // NULL. Semua akses di bawah WAJIB aman-null — `optional($p->group)`
        // dulu 500 karena argumennya dievaluasi lebih dulu (`??` menekan
        // peringatan, argumen fungsi tidak).
        $p = $t->pemohon;

        return view('it-tracker.ekspor-frm01', [
            't'      => $t,
            'f1'     => $f1,
            'ident'  => [
                'Nama'                  => $p?->nama ?? ($t->sumber === 'insiden' ? '— (insiden, dicatat IT)' : '— (inisiatif IT)'),
                'Departemen / Jabatan'  => $p?->group?->nama ?? 'IT',
                'Cabang'                => $t->branch?->name ?? $p?->branch?->name ?? '—',
                'Kontak'                => $p?->email ?? '—',
                'Tanggal Pengajuan'     => $t->created_at?->translatedFormat('j F Y') ?? '—',
            ],
            'tanpaPemohon' => $p === null,
            'detail' => [
                'Jenis Permintaan'                  => $f1['jenis'] ?? ($t->jenis_permintaan ?: '—'),
                'Nama Modul / Fitur'                => $t->judul,
                'Prioritas Pemohon'                 => $t->prioritas_pemohon ?: '—',
                'Target Waktu'                      => $f1['target'] ?? '—',
                'Latar Belakang / Masalah Saat Ini' => $f1['latar'] ?? '—',
                'Kebutuhan & Hasil yang Diharapkan' => $f1['kebutuhan'] ?? '—',
                'Pengguna & Proses Terdampak'       => $f1['terdampak'] ?? '—',
            ],
            'diterima' => $t->jenjang !== null,
            // §14.16: penanda pelewatan paraf BO wajib tercetak di ekspor
            'diteruskan' => optional($t->forms->firstWhere('kode', 'FRM-IT-02'))->payload['diteruskan'] ?? null,
            'parafBo'    => optional($t->forms->firstWhere('kode', 'FRM-IT-02'))->payload['paraf'] ?? null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FASE 2b — empat formulir kerja
    // ═══════════════════════════════════════════════════════════════════════

    /** peta nomor→fase untuk kartu papan (diisi index()) */
    private array $faseNomor = [];

    // ── FRM-IT-02 ──

    public function fr(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate([
            'baris'               => 'nullable|array|max:40',
            'baris.*.kebutuhan'   => 'nullable|string|max:500',
            'baris.*.prioritas'   => 'nullable|in:Tinggi,Sedang,Rendah',
            'baris.*.kriteria'    => 'nullable|string|max:1000',
        ]);

        try {
            $n = $this->svc->simpanFr($t, $d['baris'] ?? []);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tabel FR tersimpan — ' . $n . ' baris.');
    }

    public function komponen(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate(['komponen' => 'nullable|array', 'komponen.*' => 'string|max:20']);

        try {
            $this->svc->setKomponen($t, $d['komponen'] ?? []);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Komponen terdampak diperbarui.');
    }

    public function lingkup(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate([
            'lingkup' => 'nullable|string|max:2000',
            'luar'    => 'nullable|string|max:2000',
            'desain'  => 'nullable|string|max:3000',
        ]);

        try {
            $this->svc->setLingkupL($t, $d['lingkup'] ?? null, $d['luar'] ?? null, $d['desain'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ruang lingkup & desain teknis tersimpan.');
    }

    public function dapur(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate(['estimasi' => 'nullable|string|max:120', 'risiko' => 'nullable|string|max:500']);

        try {
            $this->svc->setDapur($t, $d['estimasi'] ?? null, $d['risiko'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Catatan dapur tersimpan (internal IT).');
    }

    public function dep(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate(['nomor' => 'required|string|max:16', 'aksi' => 'nullable|in:tambah,hapus']);

        try {
            ($d['aksi'] ?? 'tambah') === 'hapus'
                ? $this->svc->hapusDep($t, $d['nomor'])
                : $this->svc->tambahDep($t, $d['nomor']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ketergantungan diperbarui.');
    }

    /** Paraf satu Business Owner — Executive ATAU Konsultan (ROLE_PENGAWAS), bukan IT. */
    public function paraf(int $id)
    {
        if ($this->peran() !== 'pengawas') {
            abort(403, 'Paraf FRM-IT-02 milik Business Owner (Executive / Konsultan), bukan IT.');
        }

        $t = ItTicket::findOrFail($id);

        try {
            $this->svc->parafBo($t, $this->userId(), (string) session('name'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $t->nomor . ' — diparaf sebagai Business Owner.');
    }

    /**
     * Jalan pintas BERTANDA (§14.16): Head IT meneruskan gerbang 02 tanpa paraf BO.
     * Bukan paraf — Reza (it_dev) 403, BO 403 (mereka punya tombol paraf sendiri).
     */
    public function teruskan(Request $request, int $id)
    {
        $this->pastikanHeadIt('Meneruskan tanpa paraf Business Owner hanya boleh dilakukan Head IT — dan tercatat.');
        $t = ItTicket::findOrFail($id);

        // validasi SESUDAH otorisasi; pesannya menyebut kenapa (P3)
        $d = $request->validate(
            ['alasan' => 'required|string|min:10|max:300', 'gerbang' => 'nullable|string|max:4'],
            ['alasan.required' => 'Tulis alasan meneruskan tanpa paraf BO — alasan itulah yang tercetak di ekspor.',
             'alasan.min'      => 'Alasan terlalu pendek — minimal 10 karakter, satu baris yang jujur (mis. "Robi cuti sampai 12 Sep").']
        );

        try {
            $this->svc->teruskanTanpaBo($t, $d['gerbang'] ?? '02', $d['alasan'], $this->userId(), (string) session('name'));
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $t->nomor . ' — diteruskan TANPA paraf Business Owner; penandanya ikut sampai Go Live dan tercetak di ekspor.');
    }

    /** Naikkan/ubah jenjang dari Analisis (fase TIDAK berubah) — Head IT. */
    public function jenjang(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanAnalisis($t);

        $d = $request->validate([
            'jenjang'      => 'required|in:S,M,L',
            'bobot'        => 'required|integer|min:1|max:5',
            'prioritas_it' => 'required|in:P1,P2,P3',
        ]);

        try {
            $this->svc->ubahJenjang($t, $d['jenjang'], (int) $d['bobot'], $d['prioritas_it']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $t->nomor . ' — jenjang diubah ke ' . $d['jenjang']
            . ($d['jenjang'] === 'L' ? '; 02 penuh terbuka.' : '.') . ' Paraf BO (kalau ada) dihapus.');
    }

    // ── FRM-IT-04 ──

    public function tc(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanUji($t);

        $d = $request->validate([
            'baris'              => 'nullable|array|max:60',
            'baris.*.kode'       => 'nullable|string|max:8',
            'baris.*.skenario'   => 'nullable|string|max:500',
            'baris.*.diharapkan' => 'nullable|string|max:500',
            'baris.*.aktual'     => 'nullable|string|max:500',
            'baris.*.status'     => 'nullable|in:pass,fail',
        ]);

        try {
            $this->svc->simpanTc($t, $d['baris'] ?? []);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hasil uji tersimpan.');
    }

    public function tcHapus(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanUji($t);

        $d = $request->validate(['kode' => 'required|string|max:8']);

        try {
            $this->svc->hapusTc($t, $d['kode']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $d['kode'] . ' dihapus.');
    }

    public function temuan(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        $this->pastikanUji($t);

        $d = $request->validate([
            'aksi'        => 'required|in:tambah,ubah,hapus',
            'row_id'      => 'nullable|integer',
            'deskripsi'   => 'nullable|string|max:500',
            'tingkat'     => 'nullable|in:Minor,Major',
            'status'      => 'nullable|in:Terbuka,Selesai',
            'pic_user_id' => 'nullable|string|exists:users,id',
        ]);

        try {
            match ($d['aksi']) {
                'tambah' => $this->svc->tambahTemuan($t, $d['deskripsi'] ?? '', $d['tingkat'] ?? 'Minor', $d['pic_user_id'] ?? null),
                'ubah'   => $this->svc->ubahTemuan($t, (int) ($d['row_id'] ?? 0), $d),
                'hapus'  => $this->svc->hapusTemuan($t, (int) ($d['row_id'] ?? 0)),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Temuan diperbarui.');
    }

    // ── FRM-IT-05 ──

    /** Skenario UAT ditulis IT (Head IT, atau IT yang jadi PIC). */
    public function skenario(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        if (! $this->bolehTulisSkenario($t)) {
            abort(403, 'Skenario UAT ditulis tim IT yang mengerjakan tiketnya.');
        }

        $d = $request->validate([
            'baris'           => 'nullable|array|max:30',
            'baris.*.proses'  => 'nullable|string|max:500',
            'baris.*.harapan' => 'nullable|string|max:500',
        ]);

        try {
            $this->svc->simpanSkenario($t, $d['baris'] ?? []);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Skenario UAT tersimpan.');
    }

    /** Pemohon menjawab skenario + masukan end user. */
    public function jawab(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        if (! $this->bolehJawabUat($t)) {
            abort(403, 'Jawaban UAT milik pemohon tiket ini.');
        }

        $d = $request->validate([
            'jawaban'           => 'nullable|array',
            'jawaban.*.sesuai'  => 'nullable|in:0,1',
            'jawaban.*.catatan' => 'nullable|string|max:500',
            'masukan'           => 'nullable|string|max:1000',
            // Tombol keputusan berada di formulir yang SAMA dengan jawaban:
            // pemohon yang langsung menekan "Disetujui …" tak kehilangan tanda
            // Sesuai/Tidak & catatannya (E2E 3 Sep: jawaban tak tersimpan = hilang).
            'keputusan'         => 'nullable|in:setuju,catatan,perbaikan',
        ]);

        try {
            $this->svc->jawabUat($t, $d['jawaban'] ?? [], $d['masukan'] ?? null);
            if (! empty($d['keputusan'])) {
                $this->svc->uatPutuskan($t, $d['keputusan'], $this->userId());

                return back()->with('success', match ($d['keputusan']) {
                    'setuju'  => $t->nomor . ' — UAT disetujui → Go Live.',
                    'catatan' => $t->nomor . ' — Disetujui dengan Catatan → Go Live; catatan jadi tindak lanjut.',
                    default   => $t->nomor . ' — Perlu Perbaikan → kembali ke Development (konfirmasi FRM-IT-03 direset).',
                });
            }
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jawabanmu tersimpan — tinggal putuskan.');
    }

    /** SATU rute untuk dua asal: butir catatan UAT atau RTL evaluasi → tiket baru di Triase. */
    public function spawn(Request $request, int $id)
    {
        if (! $this->adalahIt()) {
            abort(403, '"Jadikan tiket" dilakukan tim IT.');
        }

        $t = $this->svc->muatRelasi(ItTicket::query())->findOrFail($id);
        $d = $request->validate(['dari' => 'required|in:uat,evaluasi', 'indeks' => 'required|integer|min:0']);

        try {
            $anak = $this->svc->lahirkanTiket($t, $d['dari'], (int) $d['indeks'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tiket baru ' . $anak->nomor . ' lahir dari ' . $t->nomor
            . ' (' . ($d['dari'] === 'evaluasi' ? 'Evaluasi' : 'UAT') . ') → Triase.');
    }

    // ── FRM-IT-07 ──

    public function evaluasi(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        if (! $this->bolehEvaluasi($t)) {
            abort(403, 'Evaluasi diisi evaluator tiket ini.');
        }

        $d = $request->validate([
            'aspek'          => 'nullable|array',
            'aspek.*'        => 'nullable|integer|min:1|max:5',
            'temuan'         => 'nullable|array|max:30',
            'temuan.*.jenis' => 'nullable|in:Bug,Enhancement',
            'temuan.*.txt'   => 'nullable|string|max:500',
            'rtl'            => 'nullable|array|max:30',
            'rtl.*.id'       => 'nullable|string|max:8',
            'rtl.*.txt'      => 'nullable|string|max:500',
        ]);

        try {
            $this->svc->simpanEvaluasi($t, $d['aspek'] ?? [], $d['temuan'] ?? [], $d['rtl'] ?? []);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Evaluasi tersimpan.');
    }

    /** Alihkan evaluator — Head IT. Kosong = kembali ke pemohon. */
    public function evaluator(Request $request, int $id)
    {
        $this->pastikanHeadIt('Mengalihkan evaluator hanya dilakukan Head IT.');
        $t = ItTicket::findOrFail($id);

        $d = $request->validate(['evaluator_user_id' => 'nullable|string|exists:users,id']);

        try {
            $this->svc->alihkanEvaluator($t, $d['evaluator_user_id'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Evaluator diperbarui.');
    }

    // ── pagar 2b ──

    /** FRM-IT-02 = Kelas C, ditulis Head IT (Ekky). */
    private function pastikanAnalisis(ItTicket $t): void
    {
        $this->pastikanHeadIt('FRM-IT-02 ditulis Head IT.');
        if ($t->fase !== 'ANALISIS') {
            abort(403, 'FRM-IT-02 hanya diisi saat tiket berada di fase Analisis.');
        }
    }

    /** FRM-IT-04 = IT sendiri (Head IT, atau IT yang jadi PIC). */
    private function pastikanUji(ItTicket $t): void
    {
        $boleh = $this->bolehGerakkan($t);
        if (! $boleh['ok']) {
            abort(403, $boleh['why']);
        }
        if ($t->fase !== 'UJI') {
            abort(403, 'FRM-IT-04 hanya diisi saat tiket berada di fase Uji Internal.');
        }
    }

    /** Jawaban/keputusan UAT: pemohon; tiket tanpa pemohon → Head IT (aturan Fase 1). */
    // ── Lampiran FRM-IT-01 (rencana §7 B / §14.11) ──

    /** Tambah lampiran belakangan: pemohon selama tiket masih di TRIASE, Head IT kapan saja. */
    public function lampiran(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        if (! $this->bolehLampiran($t)) {
            abort(403, $t->pemohon_user_id === $this->userId()
                ? 'Lampiran dari pemohon hanya bisa ditambah selama tiket masih menunggu triase — sesudah itu minta Head IT menempelkannya.'
                : 'Lampiran ditambahkan pemohon tiket (saat menunggu triase) atau Head IT.');
        }

        // validasi SESUDAH otorisasi; 'lampiran' wajib di rute ini (kosong = tak ada gunanya)
        $d = $request->validate([
            'lampiran' => 'required|array|min:1|max:' . ItTrackerService::LAMPIRAN_MAKS,
            'kode'     => 'nullable|in:FRM-IT-01,FRM-IT-02',
        ] + $this->aturanLampiran(), $this->pesanLampiran());

        $kode = $d['kode'] ?? 'FRM-IT-01';
        // Bahan rancangan (mockup/alur/contoh keluaran) ditempel di FRM-IT-02 —
        // itu pekerjaan analisis, jadi izinnya izin analisis, bukan izin pemohon.
        if ($kode === 'FRM-IT-02' && ! ($this->peran() === 'head_it' && $t->fase === 'ANALISIS')) {
            abort(403, 'Lampiran rancangan (FRM-IT-02) ditempel Head IT selagi tiket di fase Analisis.');
        }

        try {
            $n = $this->svc->tambahLampiran($t, $request->file('lampiran', []), $this->userId(), $kode);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $n . ' lampiran ditempelkan ke ' . $t->nomor
            . ($kode === 'FRM-IT-02' ? ' (bahan rancangan).' : '.'));
    }

    /**
     * Catatan "sudah sampai mana" — ditulis IT (Head IT atau PIC tiketnya).
     * Bukan hanya di Go Live: bola paling sering hilang justru di tengah jalan.
     */
    public function kemajuan(Request $request, int $id)
    {
        $t = ItTicket::findOrFail($id);
        if (! $this->bolehKemajuan($t)) {
            abort(403, 'Catatan kemajuan ditulis tim IT yang mengerjakan tiketnya.');
        }

        $d = $request->validate(['kemajuan' => 'nullable|string|max:300']);

        try {
            $this->svc->setKemajuan($t, $d['kemajuan'] ?? null, $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', trim((string) ($d['kemajuan'] ?? '')) === ''
            ? 'Catatan kemajuan dihapus.'
            : 'Catatan kemajuan diperbarui — tampil di kartu papan.');
    }

    private function bolehKemajuan(ItTicket $t): bool
    {
        return $this->adalahIt() && ($this->peran() === 'head_it' || $t->pic_user_id === $this->userId());
    }

    private function bolehLampiran(ItTicket $t): bool
    {
        if ($this->peran() === 'head_it') return true;
        return $t->pemohon_user_id && $t->pemohon_user_id === $this->userId() && $t->fase === 'TRIASE';
    }

    private function aturanLampiran(): array
    {
        return [
            'lampiran'   => 'nullable|array|max:' . ItTrackerService::LAMPIRAN_MAKS,
            'lampiran.*' => 'file|mimes:' . implode(',', ItTrackerService::LAMPIRAN_MIMES) . '|max:' . ItTrackerService::LAMPIRAN_MAX_KB,
        ];
    }

    /** Pesan penolakan MENYEBUT yang diterima (pola P3) — bukan "jenis berkas tidak valid". */
    private function pesanLampiran(): array
    {
        $diterima = ItTrackerService::lampiranDiterima();

        return [
            'lampiran.*.mimes' => 'Lampiran ":input" tidak dikenali. Yang diterima: ' . $diterima . '.',
            'lampiran.*.file'  => 'Lampiran gagal terunggah. Yang diterima: ' . $diterima . '.',
            'lampiran.*.max'   => 'Lampiran ":input" terlalu besar. Yang diterima: ' . $diterima . '.',
            'lampiran.max'     => 'Maksimal ' . ItTrackerService::LAMPIRAN_MAKS . ' lampiran per tiket.',
            'lampiran.required' => 'Pilih dulu berkasnya. Yang diterima: ' . $diterima . '.',
        ];
    }

    /** Skenario UAT: Head IT, atau IT yang menjadi PIC tiketnya — dipakai layar DAN rute. */
    private function bolehTulisSkenario(ItTicket $t): bool
    {
        return $this->adalahIt() && ($this->peran() === 'head_it' || $t->pic_user_id === $this->userId());
    }

    private function bolehJawabUat(ItTicket $t): bool
    {
        if ($t->fase !== 'UAT') return false;
        return $t->pemohon_user_id
            ? $t->pemohon_user_id === $this->userId()
            : $this->peran() === 'head_it';
    }

    /** Evaluator = yang dialihkan, kalau tidak pemohon; tanpa keduanya → Head IT. */
    private function bolehEvaluasi(ItTicket $t): bool
    {
        if ($t->fase !== 'EVALUASI') return false;
        $ev = ItTrackerService::evaluatorUserId($t);
        return $ev ? $ev === $this->userId() : $this->peran() === 'head_it';
    }

    /** Kandidat evaluator alih: Executive / Konsultan (pengawas) yang aktif. */
    private function pengawasOpsi()
    {
        return \Illuminate\Support\Facades\DB::table('users as u')
            ->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->whereIn('g.nama', ItTrackerService::ROLE_PENGAWAS)
            ->where('u.status', 1)
            ->orderBy('u.nama')
            ->get(['u.id', 'u.nama', 'g.nama as role']);
    }

    // ─── Pembantu ──────────────────────────────────────────────────────────

    private function userId(): string
    {
        return (string) session('id');
    }

    private function peran(): string
    {
        return ItTrackerService::peran(session('group_name'));
    }

    private function adalahIt(): bool
    {
        return in_array($this->peran(), ['head_it', 'it_dev'], true);
    }

    /** Kandidat PIC = orang IT (Head IT + IT), untuk triase & ganti PIC. */
    private function picOpsi()
    {
        return \Illuminate\Support\Facades\DB::table('users as u')
            ->join('group_path as g', 'g.id', '=', 'u.group_id')
            ->whereIn('g.nama', ['Head IT', 'IT'])
            ->where('u.status', 1)
            ->orderBy('u.nama')
            ->get(['u.id', 'u.nama']);
    }

    /**
     * "Tarik — aku yang kerjakan": IT pelaksana menunjuk DIRINYA sendiri saat
     * tiket belum ber-PIC. Tanpa ini `bolehGerakkan` mengunci it_dev di luar
     * tiketnya sendiri sementara satu-satunya yang bisa membuka kunci itu
     * (menunjuk PIC) hanya Head IT — sistem menunggu orang yang tak perlu.
     * Tiket yang SUDAH ber-PIC tak bisa direbut; pemindahan tetap lewat Head IT.
     */
    public function tarik(int $id)
    {
        if (! $this->adalahIt()) {
            abort(403, 'Menarik tiket hanya untuk tim IT.');
        }

        $t = ItTicket::findOrFail($id);

        if (! isset(PhaseEngine::RANK[$t->fase])) {
            return back()->with('error', $t->nomor . ' tidak sedang berjalan — tak ada yang bisa ditarik.');
        }
        if ($t->pic_user_id !== null && $t->pic_user_id !== $this->userId()) {
            return back()->with('error', $t->nomor . ' sudah dipegang ' . ($t->pic->nama ?? 'orang lain')
                . '. Pemindahan PIC lewat Head IT — supaya tak ada tiket yang berpindah tangan diam-diam.');
        }

        $t->pic_user_id = $this->userId();
        $t->save();

        return back()->with('success', $t->nomor . ' ditarik — kamu PIC-nya sekarang.');
    }

    /** Ganti/penunjukan PIC — Head IT. */
    public function pic(Request $request, int $id)
    {
        $this->pastikanHeadIt('Menunjuk PIC hanya dilakukan Head IT.');

        $d = $request->validate(['pic_user_id' => 'required|string|exists:users,id']);

        $t = ItTicket::findOrFail($id);
        $t->pic_user_id = $d['pic_user_id'];
        $t->save();

        return back()->with('success', $t->nomor . ' — PIC diperbarui.');
    }

    /** Guard aksi khusus Head IT — satu tempat, jangan disalin per aksi. */

    /**
     * Petugas deploy = tim IT (Head IT, atau IT yang jadi PIC tiket ini).
     * Checklist Go-Live mencatat pekerjaan deploy, jadi yang mengisinya harus
     * orang yang benar-benar melakukannya — bukan penonton.
     */
    private function pastikanPetugasDeploy(ItTicket $t): void
    {
        $peran = $this->peran();

        if ($peran === 'head_it') return;
        if ($peran === 'it_dev' && $t->pic_user_id === $this->userId()) return;

        abort(403, 'Checklist Go-Live diisi tim IT yang mengerjakan deployment-nya.');
    }
    private function pastikanHeadIt(string $pesan): void
    {
        if ($this->peran() !== 'head_it') {
            abort(403, $pesan);
        }
    }

    /** Siapa boleh menggerakkan tiket ini (terjemahan canAct mockup). */
    private function bolehGerakkan(ItTicket $t): array
    {
        $peran = $this->peran();

        if ($peran === 'head_it') {
            return ['ok' => true, 'why' => ''];
        }
        if ($peran === 'it_dev') {
            // ANTRE ikut: "IT menarik kerjaannya" (rencana §4) — menarik tiket
            // yang memang ditunjuk jadi miliknya adalah awal Development-nya.
            if (in_array($t->fase, ['ANTRE', 'DEV', 'UJI'], true) && $t->pic_user_id === $this->userId()) {
                return ['ok' => true, 'why' => ''];
            }
            return ['ok' => false, 'why' => 'IT pelaksana hanya memindahkan item miliknya sendiri (Antre → Development → Uji).'];
        }

        return ['ok' => false, 'why' => 'Aksi ini milik IT.'];
    }

    private function pastikanBolehLihat(ItTicket $t): void
    {
        if ($this->peran() === 'head' && $t->pemohon_user_id !== $this->userId()) {
            abort(403, 'Head melihat tiketnya sendiri + ringkasan beban IT — bukan isi permintaan departemen lain.');
        }
    }

    /** Rakit data kartu papan (terjemahan cardVM mockup). */
    private function kartu(ItTicket $t, array $v, array $semua): array
    {
        $wait = PhaseEngine::waitOf($v);
        $prog = PhaseEngine::progressOf($v);
        $gate = PhaseEngine::gateInfo($v);

        $tombol = null;
        $blokir = null;
        $peran  = $this->peran();

        if ($t->fase === 'TRIASE') {
            if ($peran === 'head_it') {
                $tombol = ['label' => 'Triase — tetapkan jenjang & prioritas', 'jenis' => 'triase'];
            } elseif ($peran === 'it_dev') {
                $blokir = 'Hanya Head IT yang menetapkan triase';
            }
        } elseif ($gate && ($gate['kind'] ?? '') === 'advance' && ($gate['actor'] ?? '') === 'it' && $this->adalahIt()) {
            $boleh = $this->bolehGerakkan($t);
            if (! $boleh['ok']) {
                $blokir = $boleh['why'];
            } elseif (! empty($gate['ok'])) {
                $tombol = ['label' => $gate['label'], 'jenis' => 'maju'];
            } else {
                $blokir = 'Masih perlu: ' . $gate['missing'][0];
            }
        }

        // Ronde 3: "Masih perlu …" hanya naik ke kartu kalau yang harus bertindak
        // BUKAN IT (mis. paraf Business Owner). Kekurangan yang IT sendiri bisa
        // beresi cukup di detail — kalau tidak, hampir tiap kartu tumbuh dua baris.
        $blokirLuar = $blokir !== null && ($wait['luar']
            || str_contains(mb_strtolower($blokir), 'business owner'));

        return [
            't'      => $t,
            'wait'   => $wait,
            'prog'   => $prog,
            'antre'  => $t->fase === 'ANTRE' ? PhaseEngine::queuePos($v, $semua) : null,
            'anak'   => count($v['children']),
            // Ronde 2: chip "⛓ menunggu IT-…" (hanya dep yang belum selesai) & "↳ dari IT-…"
            'deps'   => array_values(array_filter($v['deps'], fn ($n) => ($this->faseNomor[$n] ?? null) !== 'SELESAI')),
            'parent' => $v['parent'],
            // revisi pasca-demo: nama PIC, unit usaha, dan "sudah sampai mana" di kartu
            'pic'    => $t->pic?->nama,
            'dept'   => $t->pemohon?->group?->nama,
            'unit'   => $t->unit,
            'kemajuan' => $t->kemajuan ? ['txt' => $t->kemajuan, 'at' => $t->kemajuan_at] : null,
            // penanda pelewatan paraf BO (§14.16) — chip di kartu, tak boleh disembunyikan
            'diteruskan' => $v['fr02']['diteruskan'] ?? null,
            'tombol' => $tombol,
            'blokir' => $blokir,
            'blokirLuar' => $blokirLuar,
            // kunci sortir
            'heat'   => $wait['heat'],
            'rank'   => PhaseEngine::rankOf($t->fase),
            'since'  => $t->fase_since->timestamp,
        ];
    }
}
