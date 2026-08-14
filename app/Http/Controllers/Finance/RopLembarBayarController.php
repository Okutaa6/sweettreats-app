<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\RopPaymentSheet;
use App\Models\RopPaymentSheetItem;
use App\Models\RopRequest;
use App\Models\SysProfile;
use App\Services\RopAttachment;
use App\Services\RopCoaCatalog;
use App\Services\RopPratinjauPdf;
use App\Services\RopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RopLembarBayarController — LEMBAR BAYAR ROP (zona burgundy, desktop).
 *
 * Menggantikan kebiasaan Finance mengirim ROP ke bos satu per satu di WhatsApp:
 * satu berkas ROP + satu invoice per item, 15 pembayaran = 30 berkas. Di sini
 * jadi SATU lembar cetak: daftar + total + terbilang + lampiran.
 *
 * Fiturnya SEPASANG, dan yang kedua yang paling mudah terlewat: bos sering
 * membayar beberapa ROP dalam SATU transfer, tapi `paid_proof_path` per-ROP dan
 * RopQueueController::bayar() menerima satu id. Tanpa "tandai dibayar
 * sekaligus", kerja Finance cuma pindah tempat — dari kirim 30 berkas jadi
 * unggah 1 berkas 15 kali.
 *
 * Endpoint (semua di bawah prefix finance/ — lihat catatan RUTE di bawah):
 *   GET  finance/rop/lembar-bayar                    → pilih item + daftar lembar
 *   POST finance/rop/lembar-bayar                    → buat lembar (= Finance menyetujui)
 *   GET  finance/rop/lembar-bayar/{id}               → isi lembar + form bayar sekaligus
 *   GET  finance/rop/lembar-bayar/{id}/cetak         → halaman cetak (berdiri sendiri)
 *   POST finance/rop/lembar-bayar/{id}/dikirim       → cap "sudah dikirim ke bos"
 *   POST finance/rop/lembar-bayar/{id}/bayar-banyak  → satu bukti untuk beberapa ROP
 *
 * ⚠️ RUTE: semua harus di bawah segmen `finance/` — segmen itu ada di
 * AuthGuard::SKIP_RBAC. Segmen-1 BARU (mis. `lembar-bayar/...`) akan
 * dipantulkan ke beranda untuk SEMUA orang, Super Admin sekalipun, dan
 * gejalanya 302 ke beranda BUKAN 403 — terlihat seperti tombol yang tak
 * berfungsi. Itu bug yang mematikan fitur kategori petty cash.
 *
 * Rencana: docs/rencana/rencana-lembar-bayar-rop.md
 */
class RopLembarBayarController extends Controller
{
    public function __construct(
        private RopService $rop,
        private RopCoaCatalog $coa,
        private RopAttachment $lampiran,
        private RopPratinjauPdf $pratinjau,
    ) {}

    /**
     * Gerbang yang sama dengan antrian ROP — daftar role-nya hidup di
     * RopService::ROLE_ANTRIAN supaya sinkron dengan grant menu di SQL.
     * Menu yang tampil tapi berujung 403 adalah kegagalan senyap.
     */
    private function guard(): void
    {
        if (! canSeeAllBranches() && ! in_array(session('group_name'), RopService::ROLE_ANTRIAN, true)) {
            abort(403, 'Halaman ini untuk Finance.');
        }
    }

    /**
     * `back()` polos jatuh ke '/' kalau request datang tanpa Referer — pesan
     * sukses ikut hilang ke halaman yang salah. Fallback selalu eksplisit.
     */
    private function kembali(?int $sheetId = null)
    {
        return back(302, [], $sheetId
            ? route('finance.rop.lembar.show', $sheetId)
            : route('finance.rop.lembar'));
    }

    /** Status ROP yang layak masuk lembar bayar. */
    private const LAYAK = [RopRequest::STATUS_DIAJUKAN, RopRequest::STATUS_DISETUJUI];

    /* ═════════════════════ Layar pilih & daftar ═════════════════════ */

    public function index(Request $request)
    {
        $this->guard();

        // Yang layak masuk lembar: yang belum dibayar. `disetujui` ikut karena
        // dari 15 item bos sering membayar 12 — tiga sisanya (sudah disetujui
        // lewat lembar sebelumnya) harus bisa masuk lembar berikutnya.
        $layak = RopRequest::whereIn('status', self::LAYAK)
            ->with(['requester', 'vendor', 'costCentre'])
            ->orderByDesc('is_urgent')
            ->orderByRaw('needed_by IS NULL, needed_by ASC')
            ->orderBy('created_at')
            ->get();

        // Akun beban hanya dihitung untuk baris yang kolomnya masih kosong —
        // yang sudah terisi tidak perlu daftar pilihan, dan tiap baris hemat
        // dua query. Daftar akun sendiri di-cache per buku oleh RopCoaCatalog.
        $akun  = [];
        $saran = [];
        foreach ($layak as $r) {
            if ($r->coa_no) {
                continue;
            }
            $akun[$r->id]  = $this->coa->untukCostCentre($r->cost_centre);
            $saran[$r->id] = $this->rop->bahanAkun($r)['saran'];
        }

        $lembar = RopPaymentSheet::with(['items.rop', 'pembuat'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('finance.rop.lembar-bayar.index', [
            'layak'  => $layak,
            'akun'   => $akun,
            'saran'  => $saran,
            'lembar' => $lembar,
        ]);
    }

    /* ═════════════════════ Buat lembar ═════════════════════ */

    /**
     * Membuat lembar bayar = Finance MENYETUJUI item di dalamnya (rencana §5).
     *
     * Status `disetujui` selama ini praktis tak pernah terpakai — Finance
     * memang memeriksa pengajuan sebelum meminta uang ke bos, jadi kejadian
     * nyata itu yang dicatat, dan lembar bayar punya arti tegas: "sudah lolos
     * Finance, menunggu uang bos".
     *
     * Akun beban tetap dipaksa di sini, bukan dilewati: drawer antrian hanya
     * menampilkan pilihan akun untuk status `diajukan`, jadi item yang naik ke
     * `disetujui` tanpa akun tidak akan pernah bisa dilengkapi dari layar mana
     * pun dan rekap Accurate-nya bolong selamanya. Satu-satunya kelonggaran:
     * kalau staging Accurate belum ada di server ini daftar akunnya memang
     * kosong — di situ lembar tetap boleh dibuat, dan RopService::lengkapiAkun()
     * yang menambalnya kelak.
     */
    public function store(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'pilih'   => 'required|array|min:1',
            'pilih.*' => 'integer',
            'akun'    => 'nullable|array',
            'catatan' => 'nullable|string|max:500',
        ], [
            'pilih.required' => 'Pilih dulu pengajuan yang mau dimasukkan ke lembar.',
            'pilih.min'      => 'Pilih dulu pengajuan yang mau dimasukkan ke lembar.',
        ]);

        $akunKiriman = (array) ($data['akun'] ?? []);

        // Kiriman kembar dibuang SEBELUM apa pun disentuh. Layar normal tidak
        // mengirim id yang sama dua kali, tapi kiriman apa pun bisa dibuat
        // tangan — dan tanpa ini baris kedua menabrak UNIQUE(sheet_id, rop_id)
        // dan meledak jadi 500 (QueryException bukan HttpException, jadi lolos
        // dari penangkap di bawah). Selain itu jumlah_item & total ikut
        // menggelembung. array_values supaya kuncinya rapat lagi.
        $data['pilih'] = array_values(array_unique(array_map('intval', $data['pilih'])));

        try {
            $lembar = DB::transaction(function () use ($data, $akunKiriman) {
                /** @var \Illuminate\Support\Collection<int,RopRequest> $terpilih */
                $terpilih = RopRequest::whereIn('id', $data['pilih'])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // Urutan mengikuti urutan kiriman layar, supaya baris di lembar
                // cetak sama dengan yang dilihat Finance saat mencentang.
                $urut = [];
                foreach ($data['pilih'] as $id) {
                    $r = $terpilih->get((int) $id);
                    if (! $r) {
                        abort(422, 'Ada pengajuan yang sudah tidak ada — muat ulang halaman.');
                    }
                    if (! in_array($r->status, self::LAYAK, true)) {
                        abort(409, $r->number . ' sudah berstatus "' . $r->labelStatus()
                            . '" dan tidak bisa masuk lembar bayar. Muat ulang halaman.');
                    }
                    $urut[] = $r;
                }

                // Akun beban diselesaikan SEBELUM apa pun ditulis: kalau ada
                // satu baris yang belum lengkap, lebih baik tidak ada lembar
                // sama sekali daripada lembar setengah jadi.
                $akunFinal = [];
                foreach ($urut as $r) {
                    if ($r->coa_no) {
                        continue;   // sudah punya, jangan ditimpa
                    }

                    $no = trim((string) ($akunKiriman[$r->id] ?? ''));
                    if ($no === '') {
                        // Daftar kosong = staging Accurate belum ada di server
                        // ini. Itu keadaan server, bukan kelalaian Finance.
                        if ($this->coa->untukCostCentre($r->cost_centre) !== []) {
                            abort(422, 'Akun beban ' . $r->number . ' belum dipilih.');
                        }
                        continue;
                    }

                    $nama = $this->coa->namaAkun($r->cost_centre, $no);
                    if ($nama === null) {
                        abort(422, 'Akun beban ' . $no . ' tidak ada di buku ' . $r->cost_centre
                            . ' (' . $r->number . ').');
                    }
                    $akunFinal[$r->id] = [$no, $nama];
                }

                $lembar = RopPaymentSheet::create([
                    'number'      => $this->rop->nomorLembarBayar(),
                    'dibuat_oleh' => (string) session('id'),
                    'dibuat_at'   => now(),
                    'jumlah_item' => count($urut),
                    'total'       => array_sum(array_map(fn ($r) => (float) $r->amount, $urut)),
                    'catatan'     => $data['catatan'] ?? null,
                    'updated_at'  => now(),
                ]);

                foreach ($urut as $i => $r) {
                    RopPaymentSheetItem::create([
                        'sheet_id'   => $lembar->id,
                        'rop_id'     => $r->id,
                        'amount'     => $r->amount,   // SNAPSHOT — yang tercetak = yang tersimpan
                        'urutan'     => $i + 1,
                        'created_at' => now(),
                    ]);

                    [$no, $nama] = $akunFinal[$r->id] ?? [null, null];

                    if ($r->status === RopRequest::STATUS_DIAJUKAN) {
                        $this->rop->setujui($r, [
                            'coa_no'   => $no ?: $r->coa_no,
                            'coa_name' => $nama ?: $r->coa_name,
                            'catatan'  => 'Disetujui lewat lembar bayar ' . $lembar->number,
                        ], (string) session('id'));
                    } elseif ($no) {
                        // Sudah disetujui tapi akunnya kosong (lembar lama dibuat
                        // saat staging Accurate belum ada) — ditambal di sini.
                        $this->rop->lengkapiAkun($r, $no, $nama, (string) session('id'));
                    }
                }

                return $lembar;
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->kembali()->with('error', $e->getMessage() ?: 'Gagal membuat lembar bayar.');
        }

        return redirect()->route('finance.rop.lembar.show', $lembar->id)
            ->with('success', 'Lembar ' . $lembar->number . ' dibuat — ' . $lembar->jumlah_item
                . ' pengajuan disetujui dan siap dikirim ke bos.');
    }

    /* ═════════════════════ Isi lembar ═════════════════════ */

    public function show($id)
    {
        $this->guard();

        $lembar = RopPaymentSheet::with([
            'items.rop.requester', 'items.rop.vendor', 'items.rop.costCentre',
            'pembuat', 'pengirim',
        ])->findOrFail($id);

        return view('finance.rop.lembar-bayar.show', ['lembar' => $lembar]);
    }

    /* ═════════════════════ Halaman cetak ═════════════════════ */

    /**
     * Halaman cetak — Blade BERDIRI SENDIRI, sengaja TIDAK @extends
     * layouts.app: sidebar dan chrome aplikasi akan ikut tercetak.
     */
    public function cetak($id)
    {
        $this->guard();

        $lembar = RopPaymentSheet::with([
            'items.rop.requester', 'items.rop.vendor', 'items.rop.costCentre', 'pembuat',
        ])->findOrFail($id);

        // Lampiran dipilah di sini, bukan di view.
        //
        // PDF TIDAK lagi otomatis "dikirim terpisah": kalau server bisa
        // merendernya jadi gambar (Ghostscript + Imagick), halamannya ikut
        // tercetak seperti foto biasa — satu berkas untuk bos, tanpa kiriman
        // kedua. Kalau render gagal, barulah kembali ke perilaku lama.
        $lampiran = [];
        foreach ($lembar->items as $item) {
            $path = $item->rop?->attachment_path;
            $ext  = $path ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : null;

            if ($path === null || $path === '') {
                $lampiran[$item->rop_id] = ['jenis' => 'kosong', 'halaman' => [], 'dari_pdf' => false];
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $lampiran[$item->rop_id] = ['jenis' => 'gambar', 'halaman' => [$path], 'dari_pdf' => false];
                continue;
            }

            $halaman = $this->pratinjau->halaman($path);
            $lampiran[$item->rop_id] = $halaman
                ? ['jenis' => 'gambar', 'halaman' => $halaman, 'dari_pdf' => true]
                : ['jenis' => 'pdf',    'halaman' => [],       'dari_pdf' => true];
        }

        return view('finance.rop.lembar-bayar.cetak', [
            'lembar'    => $lembar,
            'lampiran'  => $lampiran,
            'profil'    => SysProfile::select('systitle', 'sysname', 'syslogo')->first(),
            'terbilang' => terbilang($lembar->total),
        ]);
    }

    /* ═════════════════════ Cap "sudah dikirim" ═════════════════════ */

    public function dikirim($id)
    {
        $this->guard();

        $lembar = RopPaymentSheet::findOrFail($id);

        if ($lembar->sudahDikirim()) {
            return $this->kembali($lembar->id)->with('error',
                'Lembar ini sudah ditandai dikirim pada ' . $lembar->dikirim_at->translatedFormat('d M Y, H:i') . '.');
        }

        $lembar->update([
            'dikirim_at'   => now(),
            'dikirim_oleh' => (string) session('id'),
            'updated_at'   => now(),
        ]);

        return $this->kembali($lembar->id)->with('success',
            'Lembar ' . $lembar->number . ' ditandai sudah dikirim ke bos.');
    }

    /* ═════════════════════ Tandai dibayar SEKALIGUS ═════════════════════ */

    /**
     * Satu bukti transfer untuk beberapa ROP.
     *
     * Berkasnya disimpan SEKALI lewat RopAttachment, lalu path yang sama
     * dipakai untuk tiap ROP terpilih dengan memanggil
     * RopService::tandaiDibayar() per item di dalam satu transaksi — bukan
     * update langsung ke rop_requests, supaya validasi transisi, rop_status_log
     * dan notifikasi ke pengaju tetap jalan seperti jalur satu-per-satu.
     */
    public function bayarBanyak(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'pilih'   => 'required|array|min:1',
            'pilih.*' => 'integer',
            'paid_at' => 'nullable|date',
            'bukti'   => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            // 450, bukan 500: catatan ini masih ditempeli "(lembar BAYAR/…)"
            // sebelum masuk rop_status_log.catatan yang varchar(500). Dengan 500
            // di sini, catatan terpanjang membuat MySQL menolak dan seluruh
            // pembayaran gagal jadi 500 — sudah terbukti, bukan hipotesis.
            'catatan' => 'nullable|string|max:450',
        ], [
            'pilih.required' => 'Pilih dulu item yang sudah dibayar.',
            'pilih.min'      => 'Pilih dulu item yang sudah dibayar.',
            'catatan.max'    => 'Catatan maksimal 450 huruf.',
            'bukti.mimes'    => 'Bukti transfer harus berupa foto (JPG/PNG/WebP) atau PDF.',
            'bukti.max'      => 'Ukuran bukti transfer maksimal 5 MB.',
        ]);

        $lembar = RopPaymentSheet::with('items.rop')->findOrFail($id);

        // Diperiksa dulu, disimpan kemudian: berkas yang sudah dipindah ke disk
        // tidak ikut ter-rollback kalau transaksinya batal, jadi jangan
        // menyimpan apa pun sebelum yakin datanya masuk akal.
        $idTerpilih = array_map('intval', $data['pilih']);
        $itemBayar  = $lembar->items->filter(fn ($i) => in_array($i->rop_id, $idTerpilih, true));

        if ($itemBayar->isEmpty()) {
            return $this->kembali($lembar->id)->with('error', 'Item yang dipilih tidak ada di lembar ini.');
        }

        foreach ($itemBayar as $i) {
            if (! $i->rop || $i->rop->status !== RopRequest::STATUS_DISETUJUI) {
                return $this->kembali($lembar->id)->with('error',
                    ($i->rop?->number ?: 'Salah satu item') . ' sudah berstatus "'
                    . ($i->rop?->labelStatus() ?: 'tidak diketahui')
                    . '" — muat ulang halaman sebelum menandai pembayaran.');
            }
        }

        try {
            $bukti = $this->lampiran->simpan($request, 'bukti', 'bukti-transfer');

            // Dipangkas ke 500 (lebar kolom rop_status_log.catatan) sebagai
            // sabuk kedua di samping batas 450 di validasi: kalau kelak nomor
            // lembar memanjang atau sufiksnya berubah, yang terjadi adalah
            // catatan terpotong — bukan pembayaran yang gagal total.
            $catatan = mb_substr(
                trim(($data['catatan'] ?? '') . ' (lembar ' . $lembar->number . ')'),
                0, 500
            );

            DB::transaction(function () use ($itemBayar, $bukti, $data, $catatan) {
                foreach ($itemBayar as $i) {
                    $this->rop->tandaiDibayar($i->rop, [
                        'paid_at'         => $data['paid_at'] ?? now(),
                        'paid_proof_path' => $bukti,
                        'catatan'         => $catatan,
                    ], (string) session('id'));
                }
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->kembali($lembar->id)->with('error',
                $e->getMessage() ?: 'Gagal menandai pembayaran.');
        }

        return $this->kembali($lembar->id)->with('success',
            $itemBayar->count() . ' pengajuan ditandai sudah dibayar dengan satu bukti transfer.');
    }
}
