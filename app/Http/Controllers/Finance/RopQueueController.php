<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\RopRequest;
use App\Services\RopAttachment;
use App\Services\RopCoaCatalog;
use App\Services\RopService;
use Illuminate\Http\Request;

/**
 * RopQueueController — sisi FINANCE (zona burgundy, desktop).
 *
 * Layar kerja harian Finance: "hari ini bayar apa dulu?". Urutan antrian
 * menjawab itu — darurat dulu, lalu yang paling cepat dibutuhkan, lalu yang
 * paling lama mengantre (lihat RopRequest::scopeAntrian).
 *
 * Endpoint (semua di bawah prefix finance/ → otomatis dapat zona burgundy
 * dari layouts/app.blade.php):
 *   GET  finance/rop                → antrian (tab: menunggu | disetujui | riwayat)
 *   GET  finance/rop/{id}           → JSON detail untuk panel samping
 *   POST finance/rop/{id}/setujui   → ACC + tetapkan akun beban
 *   POST finance/rop/{id}/tolak     → tolak + alasan (wajib)
 *   POST finance/rop/{id}/bayar     → tandai sudah ditransfer + bukti
 *
 * Akses: segment 'finance' ada di AuthGuard::SKIP_RBAC, jadi RBAC menu tidak
 * berlaku di sini — gerbangnya canSeeAllBranches(), pola yang sama dengan
 * FinanceDashboardController & TopupRequestController.
 */
class RopQueueController extends Controller
{
    public function __construct(
        private RopService $rop,
        private RopCoaCatalog $coa,
        private RopAttachment $lampiran,
    ) {}

    /**
     * Gerbang layar antrian. Daftar role-nya hidup di RopService::ROLE_ANTRIAN
     * supaya sinkron dengan grant menu di SQL — kalau salah satu diubah tanpa
     * yang lain, menunya muncul tapi halamannya 403.
     */
    private function guard(): void
    {
        if (! canSeeAllBranches() && ! in_array(session('group_name'), RopService::ROLE_ANTRIAN, true)) {
            abort(403, 'Halaman ini untuk Finance.');
        }
    }

    /**
     * Kembali ke halaman asal, dengan antrian sebagai jaring pengaman.
     *
     * `back()` polos jatuh ke '/' kalau request datang tanpa Referer — pesan
     * sukses & toast "Urungkan" ikut hilang ke halaman yang salah. Fallback
     * eksplisit membuat aksi tetap mendarat di tempat yang masuk akal.
     */
    private function kembali()
    {
        return back(302, [], route('finance.rop'));
    }

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['disetujui', 'riwayat'], true)
            ? $request->query('tab')
            : 'menunggu';

        $this->guard();

        $cari = trim((string) $request->query('q'));
        $cc   = $request->query('cc');
        $urut = in_array($request->query('urut'), ['nominal', 'lama'], true) ? $request->query('urut') : 'prioritas';

        $saring = fn ($q) => $q
            ->when($cc, fn ($w) => $w->where('cost_centre', $cc))
            ->when($cari, fn ($w) => $w->where(function ($s) use ($cari) {
                $s->where('number', 'like', "%{$cari}%")
                  ->orWhere('description', 'like', "%{$cari}%")
                  ->orWhere('pay_to_text', 'like', "%{$cari}%")
                  ->orWhereHas('requester', fn ($r) => $r->where('nama', 'like', "%{$cari}%"))
                  ->orWhereHas('vendor', fn ($v) => $v->where('nama', 'like', "%{$cari}%"));
            }));

        $urutkan = fn ($q) => match ($urut) {
            'nominal' => $q->reorder()->orderByDesc('amount'),
            'lama'    => $q->reorder()->orderBy('created_at'),
            default   => $q,
        };

        $daftar = match ($tab) {
            'disetujui' => $urutkan($saring(RopRequest::antrian(RopRequest::STATUS_DISETUJUI)))
                                ->with(['requester', 'vendor', 'costCentre'])->get(),
            'riwayat'   => $saring(RopRequest::whereIn('status', [
                                RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI,
                                RopRequest::STATUS_DITOLAK, RopRequest::STATUS_KADALUARSA,
                            ]))->with(['requester', 'vendor', 'costCentre'])
                              ->latest('id')->paginate(50)->withQueryString(),
            default     => $urutkan($saring(RopRequest::antrian()))
                                ->with(['requester', 'vendor', 'costCentre'])->get(),
        };

        return view('finance.rop.index', [
            'tab'       => $tab,
            'daftar'    => $daftar,
            'grup'      => $tab === 'riwayat' || $urut !== 'prioritas' ? null : $this->kelompokkan($daftar),
            'statistik' => $this->statistik(),
            'cari'      => $cari,
            'cc'        => $cc,
            'urut'      => $urut,
            'costCentres' => \App\Models\RopCostCentre::aktif()->get(),
            'jumlahTab' => [
                'menunggu'  => RopRequest::menunggu()->count(),
                'disetujui' => RopRequest::disetujui()->count(),
            ],
        ]);
    }

    /**
     * Kelompokkan antrian jadi 3 tingkat mendesak + subtotalnya. Ini yang bikin
     * "hari ini bayar apa dulu" terjawab tanpa membaca satu per satu baris.
     */
    private function kelompokkan($daftar): array
    {
        $grup = [
            'now'   => ['judul' => 'Bayar hari ini',        'baris' => [], 'total' => 0.0],
            'soon'  => ['judul' => 'Butuh 3 hari ke depan', 'baris' => [], 'total' => 0.0],
            'later' => ['judul' => 'Bisa menyusul',         'baris' => [], 'total' => 0.0],
        ];

        $hariIni = now()->startOfDay();
        foreach ($daftar as $r) {
            $butuh = $r->needed_by?->startOfDay();
            $kunci = ($r->is_urgent || ($butuh && $butuh->lte($hariIni))) ? 'now'
                   : (($butuh && $butuh->lte($hariIni->copy()->addDays(3))) ? 'soon' : 'later');

            $grup[$kunci]['baris'][] = $r;
            $grup[$kunci]['total'] += (float) $r->amount;
        }

        return array_filter($grup, fn ($g) => $g['baris'] !== []);
    }

    /** Detail satu pengajuan untuk panel ACC — termasuk daftar akun beban buku-nya. */
    public function show($id)
    {
        $this->guard();

        $rop = RopRequest::with(['requester', 'vendor', 'costCentre', 'reviewer', 'payer', 'asal'])
            ->findOrFail($id);

        return response()->json([
            'id'             => $rop->id,
            'number'         => $rop->number,
            'status'         => $rop->status,
            'status_label'   => $rop->labelStatus(),
            'pengaju'        => $rop->requester?->nama,
            'cost_centre'    => $rop->cost_centre,
            'cost_centre_nama' => $rop->costCentre?->nama,
            'penerima'       => $rop->namaPenerima(),
            'description'    => $rop->description,
            'amount'         => (float) $rop->amount,
            'needed_by'      => $rop->needed_by?->format('Y-m-d'),
            'is_urgent'      => (bool) $rop->is_urgent,
            'umur_hari'      => $rop->umurHari(),
            'attachment_path' => $rop->attachment_path,
            'paid_proof_path' => $rop->paid_proof_path,
            'nota_path'      => $rop->nota_path,
            'coa_no'         => $rop->coa_no,
            'coa_name'       => $rop->coa_name,
            'reject_reason'  => $rop->reject_reason,
            'diajukan_pada'  => $rop->created_at?->format('Y-m-d H:i'),
            'asal_number'    => $rop->asal?->number,
            'jabatan'        => $rop->requester?->group?->nama,
            // Daftar akun mengikuti BUKU Accurate cost centre ini — nomor akun
            // yang sama berarti lain di buku cabang lain, jadi jangan dicampur.
            'akun'           => $this->coa->untukCostCentre($rop->cost_centre),
            // Supaya Finance jarang mengetik: akun terakhir untuk penerima yang
            // sama + 4 akun terakhir dipakai.
            'bahan_akun'     => $this->rop->bahanAkun($rop),
            'riwayat_vendor' => $this->riwayatVendor($rop),
            'jejak'          => $rop->logs()->with('actor')->get()->map(fn ($l) => [
                'status' => RopRequest::LABEL[$l->ke_status] ?? $l->ke_status,
                'oleh'   => $l->actor?->nama ?: 'Sistem',
                'kapan'  => $l->created_at?->format('d M Y, H:i'),
                'catatan' => $l->catatan,
            ]),
        ]);
    }

    public function setujui(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'coa_no'  => 'required|string|max:20',
            'catatan' => 'nullable|string|max:500',
        ], [
            'coa_no.required' => 'Pilih dulu akun bebannya.',
        ]);

        try {
            $rop = RopRequest::findOrFail($id);

            // Nama akun di-snapshot dari buku cost centre ybs. Nomor yang tidak
            // ada di buku itu ditolak — mencegah salah ketik mendarat di akun
            // yang tidak ada saat diekspor ke Accurate nanti.
            $nama = $this->coa->namaAkun($rop->cost_centre, $data['coa_no']);
            if ($nama === null) {
                $pesan = 'Akun beban tidak ditemukan di buku ' . $rop->cost_centre . '.';
                if ($request->wantsJson()) return response()->json(['error' => $pesan], 422);
                return $this->kembali()->with('error', $pesan);
            }

            $this->rop->setujui($rop, [
                'coa_no'   => $data['coa_no'],
                'coa_name' => $nama,
                'catatan'  => $data['catatan'] ?? null,
            ], (string) session('id'));

            // Dikirim sebagai data toast (bukan pesan sukses biasa) supaya
            // layar bisa menawarkan "Urungkan" untuk salah klik.
            $urung = [
                'id'     => $rop->id,
                'number' => $rop->number,
                'akun'   => $data['coa_no'] . ' — ' . $nama,
            ];

            // Jalur fetch (tanpa reload): layar meneruskan toast + auto-lanjut
            // sendiri. Jalur form biasa tetap hidup sebagai fallback tanpa JS.
            if ($request->wantsJson()) {
                return response()->json(['ok' => true, 'urung' => $urung, 'amount' => (int) $rop->amount]);
            }
            return $this->kembali()->with('rop_urung', $urung);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage() ?: 'Gagal menyetujui pengajuan.'], $e->getStatusCode() ?: 422);
            }
            return $this->kembali()->with('error', $e->getMessage() ?: 'Gagal menyetujui pengajuan.');
        }
    }

    public function tolak(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'alasan' => 'required|string|max:500',
        ], [
            'alasan.required' => 'Alasan penolakan wajib diisi — pengaju perlu tahu apa yang harus diperbaiki.',
        ]);

        try {
            $rop = RopRequest::findOrFail($id);
            $this->rop->tolak($rop, $data['alasan'], (string) session('id'));

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'     => true,
                    'urung'  => ['id' => $rop->id, 'number' => $rop->number],
                    'amount' => (int) $rop->amount,
                ]);
            }
            return $this->kembali()->with('success', 'Pengajuan ' . $rop->number . ' ditolak. Pengaju sudah diberi tahu.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage() ?: 'Gagal menolak pengajuan.'], $e->getStatusCode() ?: 422);
            }
            return $this->kembali()->with('error', $e->getMessage() ?: 'Gagal menolak pengajuan.');
        }
    }

    /**
     * Tarik kembali keputusan yang baru saja dilakukan — tombol "Urungkan"
     * di toast. Melayani DUA arah sesuai status sekarang: disetujui→diajukan
     * dan ditolak→diajukan. Syarat ketatnya ada di RopService::urungkan*().
     */
    public function urungkan(Request $request, $id)
    {
        $this->guard();

        try {
            $rop = RopRequest::findOrFail($id);
            if ($rop->status === RopRequest::STATUS_DITOLAK) {
                $this->rop->urungkanPenolakan($rop, (string) session('id'));
                $pesan = 'Penolakan ' . $rop->number . ' diurungkan. Pengajuan kembali ke antrian.';
            } else {
                $this->rop->urungkanPersetujuan($rop, (string) session('id'));
                $pesan = 'Persetujuan ' . $rop->number . ' diurungkan. Pengajuan kembali ke antrian.';
            }

            if ($request->wantsJson()) return response()->json(['ok' => true]);
            return $this->kembali()->with('success', $pesan);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage() ?: 'Gagal mengurungkan.'], $e->getStatusCode() ?: 409);
            }
            return $this->kembali()->with('error', $e->getMessage() ?: 'Gagal mengurungkan.');
        }
    }

    public function bayar(Request $request, $id)
    {
        $this->guard();

        $data = $request->validate([
            'paid_at' => 'nullable|date',
            'bukti'   => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'catatan' => 'nullable|string|max:500',
        ], [
            'bukti.mimes' => 'Bukti transfer harus berupa foto (JPG/PNG/WebP) atau PDF.',
            'bukti.max'   => 'Ukuran bukti transfer maksimal 5 MB.',
        ]);

        try {
            $rop = RopRequest::findOrFail($id);
            $bukti = $this->lampiran->simpan($request, 'bukti', 'bukti-transfer');

            $this->rop->tandaiDibayar($rop, [
                'paid_at'         => $data['paid_at'] ?? now(),
                'paid_proof_path' => $bukti,
                'catatan'         => $data['catatan'] ?? null,
            ], (string) session('id'));

            if ($request->wantsJson()) {
                return response()->json(['ok' => true, 'number' => $rop->number, 'amount' => (int) $rop->amount]);
            }
            return $this->kembali()->with('success', 'Pengajuan ' . $rop->number . ' ditandai sudah dibayar.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage() ?: 'Gagal menandai pembayaran.'], $e->getStatusCode() ?: 422);
            }
            return $this->kembali()->with('error', $e->getMessage() ?: 'Gagal menandai pembayaran.');
        }
    }

    /**
     * Ringkas belanja sebelumnya ke penerima yang sama — konteks yang bikin
     * Finance bisa menilai "wajar tidak nominal segini" tanpa buka layar lain.
     */
    private function riwayatVendor(RopRequest $rop): ?array
    {
        $q = RopRequest::query()
            ->whereIn('status', [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI])
            ->where('id', '!=', $rop->id)
            ->when(
                $rop->vendor_id,
                fn ($w) => $w->where('vendor_id', $rop->vendor_id),
                fn ($w) => $w->where('pay_to_text', $rop->pay_to_text)
            );

        $jumlah = (clone $q)->count();
        if ($jumlah === 0) {
            return null;
        }

        $terakhir = (clone $q)->latest('paid_at')->first(['amount', 'paid_at', 'coa_no', 'coa_name']);

        return [
            'jumlah'  => $jumlah,
            'nominal' => (float) $terakhir->amount,
            'tanggal' => $terakhir->paid_at?->format('d M Y'),
            'akun'    => $terakhir->coa_no ? $terakhir->coa_no . ' — ' . $terakhir->coa_name : null,
        ];
    }

    /**
     * Angka kepala halaman. "Paling lama menunggu" sengaja dihitung dari
     * pengajuan tertua yang masih menunggu — itu yang bikin tidak ada
     * pengajuan tenggelam diam-diam.
     */
    private function statistik(): array
    {
        $menunggu = RopRequest::menunggu();

        // Pengajuan tertua disebut NOMOR & PENGAJU-nya, bukan cuma angka hari —
        // supaya yang paling lama tenggelam bisa langsung dicari, tidak berhenti
        // jadi statistik yang enak dilihat tapi tidak bisa ditindaklanjuti.
        $tertua = (clone $menunggu)->with('requester')->orderBy('created_at')->first();

        return [
            'menunggu_jumlah'   => (clone $menunggu)->count(),
            'menunggu_nominal'  => (float) (clone $menunggu)->sum('amount'),
            'disetujui_jumlah'  => RopRequest::disetujui()->count(),
            'disetujui_nominal' => (float) RopRequest::disetujui()->sum('amount'),
            'tunggu_terlama'    => $tertua?->umurHari() ?? 0,
            'tertua_nomor'      => $tertua?->number,
            'tertua_pengaju'    => $tertua?->requester?->nama,
            'darurat_jumlah'    => (clone $menunggu)->where('is_urgent', 1)->count(),
        ];
    }
}
