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

        // Filter papan (q / macet / sumber / jenjang) — sama dengan mockup.
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

        // Sumbu papan = FASE, dikelompokkan Masuk/Rancang/Kerjakan/Rilis.
        $grupDef = [
            'MASUK'    => [['k' => 'REQ',      'label' => 'Requirement']],
            'RANCANG'  => [['k' => 'ANALISIS', 'label' => 'Analysis & Design']],
            'KERJAKAN' => [['k' => 'DEV',      'label' => 'Development'], ['k' => 'UJI', 'label' => 'Internal Testing']],
            'RILIS'    => [['k' => 'UAT',      'label' => 'UAT'], ['k' => 'GOLIVE', 'label' => 'Go Live'], ['k' => 'EVALUASI', 'label' => 'Evaluation']],
        ];

        $semuaView = $views->values()->all();
        $groups    = [];
        foreach ($grupDef as $nama => $fases) {
            $kolom = [];
            foreach ($fases as $f) {
                $cards = $aktif
                    ->filter(fn ($t) => PhaseEngine::phaseOf($t->fase) === $f['k'])
                    ->map(fn ($t) => $this->kartu($t, $views[$t->id], $semuaView))
                    ->sortBy([['heat', 'desc'], ['rank', 'asc'], ['since', 'asc']])
                    ->values();
                $kolom[] = ['label' => $f['label'], 'cards' => $cards];
            }
            $groups[$nama] = $kolom;
        }

        $parkir = ItTicket::whereIn('fase', self::FASE_PARKIRAN)
            ->orderByDesc('fase_since')
            ->get(['id', 'nomor', 'judul', 'fase', 'fase_since', 'alasan_parkir']);

        return view('it-tracker.papan', [
            'groups'  => $groups,
            'parkir'  => $parkir,
            'beban'   => $this->svc->ringkasanBeban($semuaView),
            'peran'   => $this->peran(),
            'picOpsi' => $this->peran() === 'head_it' ? $this->picOpsi() : collect(),
            'filter'  => ['q' => $q, 'macet' => (bool) $request->query('macet'),
                          'sumber' => $sumber, 'jenjang' => $jenjang],
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
        ], [], [
            'judul' => 'nama modul/fitur', 'latar' => 'masalah hari ini',
            'kebutuhan' => 'hasil yang diharapkan',
            'prioritas_pemohon' => 'seberapa mendesak menurutmu',
        ]);

        $d['sumber']        = $sumber;
        $d['raw_branch_id'] = session('raw_branch_id');

        try {
            $t = $this->svc->buat($d, $this->userId());
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
        if (! $boleh['ok']) {
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
        $d = $request->validate(['keputusan' => 'required|in:setuju,perbaikan']);

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

        try {
            $ke = $this->svc->uatPutuskan($t, $d['keputusan'], $this->userId());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ke === 'GOLIVE'
            ? $t->nomor . ' — UAT disetujui → Go Live.'
            : $t->nomor . ' — dikembalikan ke Development untuk perbaikan.');
    }

    /** Catatan singkat pengerjaan (jenjang S) — IT yang mengerjakannya. */
    public function catatan(Request $request, int $id)
    {
        $d = $request->validate(['catatan' => 'required|string|max:1000']);

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

        $this->svc->catatanDev($t, $d['catatan'], $this->userId());

        return back()->with('success', 'Catatan pengerjaan tersimpan.');
    }

    /** Konfirmasi Development Record (jenjang M/L) — pembuka gerbang DEV→UJI/UAT. */
    public function konfirmasi(Request $request, int $id)
    {
        $d = $request->validate(['catatan' => 'nullable|string|max:1000']);

        $t = ItTicket::findOrFail($id);

        $boleh = $this->bolehGerakkan($t);
        if (! $boleh['ok']) {
            abort(403, $boleh['why']);
        }
        if (! in_array($t->jenjang, ['M', 'L'], true)) {
            abort(403, 'Jenjang S memakai catatan singkat, bukan konfirmasi FRM-IT-03.');
        }

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
        $d = $request->validate([
            'kunci' => 'required|string|max:16',
            'nilai' => 'required|in:ya,na',
        ]);

        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

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
        $d = $request->validate(['kunci' => 'required|string|max:16']);

        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

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
        $d = $request->validate(['catatan' => 'nullable|string|max:2000']);

        $t = ItTicket::findOrFail($id);
        $this->pastikanPetugasDeploy($t);

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

        $p = $t->pemohon;

        return view('it-tracker.ekspor-frm01', [
            't'      => $t,
            'f1'     => $f1,
            'ident'  => [
                'Nama'                  => $p->nama ?? '—',
                'Departemen / Jabatan'  => optional($p->group)->nama ?? '—',
                'Cabang'                => optional($t->branch)->name ?? optional(optional($p)->branch)->name ?? '—',
                'Kontak'                => $p->email ?? '—',
                'Tanggal Pengajuan'     => optional($t->created_at)->translatedFormat('j F Y') ?? '—',
            ],
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
        ]);
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

        return [
            't'      => $t,
            'wait'   => $wait,
            'prog'   => $prog,
            'antre'  => $t->fase === 'ANTRE' ? PhaseEngine::queuePos($v, $semua) : null,
            'anak'   => count($v['children']),
            'tombol' => $tombol,
            'blokir' => $blokir,
            // kunci sortir
            'heat'   => $wait['heat'],
            'rank'   => PhaseEngine::rankOf($t->fase),
            'since'  => $t->fase_since->timestamp,
        ];
    }
}
