<?php

namespace App\Http\Controllers\Rop;

use App\Http\Controllers\Controller;
use App\Models\RopCostCentre;
use App\Models\RopRequest;
use App\Models\Vendor;
use App\Services\RopAttachment;
use App\Services\RopService;
use Illuminate\Http\Request;

/**
 * PengajuanController — sisi PENGAJU (zona cokelat, HP-first).
 *
 * Halaman kepala departemen: bikin pengajuan, lihat statusnya, konfirmasi
 * barang/dana diterima. Rujukan alur: docs/rencana/rencana-pengajuan-rop.md.
 *
 * Endpoint:
 *   GET  pengajuan                 → daftar pengajuanku
 *   GET  pengajuan/baru            → form (bisa prefill dari pengajuan lama)
 *   POST pengajuan                 → kirim pengajuan
 *   GET  pengajuan/vendor          → JSON pencarian vendor (combobox)
 *   GET  pengajuan/{id}            → detail + timeline
 *   POST pengajuan/{id}/batal      → batalkan (hanya selama belum di-ACC)
 *   POST pengajuan/{id}/selesai    → konfirmasi diterima + upload nota
 *
 * Akses: segment 'pengajuan' dijaga AuthGuard lewat node menu (grant BY LINK
 * di database/sql/2026_07_31_rop_access.sql). Kepemilikan baris dijaga di
 * milikku() — pengaju hanya boleh menyentuh barisnya sendiri; Finance & admin
 * lintas-cabang boleh melihat semua (mereka tetap tidak bisa mengubah dari sini).
 */
class PengajuanController extends Controller
{
    public function __construct(
        private RopService $rop,
        private RopAttachment $lampiran,
    ) {}

    /** Daftar pengajuanku + filter status. */
    public function index(Request $request)
    {
        $status = $request->query('status');

        $q = RopRequest::milik($this->userId())->with(['vendor', 'costCentre'])->latest('id');
        if ($status && array_key_exists($status, RopRequest::LABEL)) {
            $q->where('status', $status);
        }

        $daftar = $q->paginate(20)->withQueryString();
        $jumlah = $this->hitungPerStatus();

        // Di layar lebar daftar & detail tampil berdampingan; baris pertama
        // dipilih otomatis supaya panel kanan tidak pernah kosong.
        $aktif = null;
        if ($id = $request->query('lihat')) {
            $aktif = RopRequest::milik($this->userId())->find($id);
        }
        $aktif = $aktif ?: $daftar->first();

        return view('rop.pengajuan.index', [
            'daftar' => $daftar,
            'status' => $status,
            'jumlah' => $jumlah,
            'aktif'  => $aktif?->load(['vendor', 'costCentre', 'requester', 'reviewer', 'payer', 'completer', 'asal']),
            'logs'   => $aktif?->logs()->with('actor')->get(),
            'ringkas' => [
                'menunggu' => $jumlah[RopRequest::STATUS_DIAJUKAN] ?? 0,
                'nota'     => $jumlah[RopRequest::STATUS_DIBAYAR] ?? 0,
                'ditolak'  => $jumlah[RopRequest::STATUS_DITOLAK] ?? 0,
            ],
        ]);
    }

    /**
     * Form pengajuan baru. `?dari={id}` mengisi ulang form dari pengajuan lama —
     * dipakai dua fitur sekaligus: "Ajukan lagi" (belanja rutin) dan "Ajukan
     * ulang" setelah ditolak. Bedanya cuma satu: yang ditolak dirantai lewat
     * resubmit_of supaya jejaknya nyambung.
     */
    public function create(Request $request)
    {
        $dari = null;
        if ($id = $request->query('dari')) {
            $dari = RopRequest::milik($this->userId())->find($id);
        }

        $ccDefault = $dari?->cost_centre ?: $this->costCentreDefault();

        return view('rop.pengajuan.form', [
            'costCentres' => RopCostCentre::aktif()->get(),
            'ccDefault'   => $ccDefault,
            'vendors'     => Vendor::aktif()->get(['id', 'nama']),
            'dari'        => $dari,
            'ulangDari'   => $dari && $dari->bisaDiajukanUlang() ? $dari->id : null,
            'terakhir'    => $this->pintasanTerakhir(),
            'nomorBerikutnya' => $ccDefault ? $this->rop->nomorBerikutnya($ccDefault) : 'Nomor dibuat saat dikirim',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Harus cost centre yang masih AKTIF: `exists` saja meloloskan yang
            // sudah dipensiunkan, dan pengajuannya lalu memakai kode yang tidak
            // muncul di dropdown mana pun.
            'cost_centre'  => ['required', 'string', \Illuminate\Validation\Rule::exists('rop_cost_centre', 'kode')->where('aktif', 1)],
            'vendor_id'    => 'nullable|integer|exists:vendors,id',
            'pay_to_text'  => 'nullable|string|max:191',
            'description'  => 'required|string|max:500',
            'amount'       => 'required|numeric|min:1|max:9999999999',
            'needed_by'    => 'nullable|date',
            'is_urgent'    => 'nullable|boolean',
            'resubmit_of'  => 'nullable|integer|exists:rop_requests,id',
            'lampiran'     => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'abaikan_dobel' => 'nullable|boolean',
        ], [
            // Pesan bawaan Laravel berbahasa Inggris; seluruh layar ini
            // berbahasa Indonesia awam, jadi ditulis sendiri.
            'cost_centre.required' => 'Pilih dulu pengajuannya dibebankan ke mana.',
            'cost_centre.exists'   => 'Cost centre itu sudah tidak dipakai. Pilih yang lain.',
            'description.required' => 'Keterangan wajib diisi.',
            'description.max'      => 'Keterangan terlalu panjang (maksimal 500 huruf).',
            'amount.required'      => 'Jumlah wajib diisi.',
            'amount.numeric'       => 'Jumlah harus berupa angka.',
            'amount.min'           => 'Jumlah harus lebih dari nol.',
            'needed_by.date'       => 'Tanggal "butuh kapan" tidak terbaca.',
            'pay_to_text.max'      => 'Nama penerima terlalu panjang (maksimal 191 huruf).',
            'lampiran.mimes'       => 'Lampiran harus berupa foto (JPG/PNG/WEBP) atau PDF.',
            'lampiran.max'         => 'Lampiran terlalu besar (maksimal 5 MB).',
        ]);

        if (empty($data['vendor_id']) && empty($data['pay_to_text'])) {
            return $this->kembaliKeForm()->with('error', 'Isi dulu dibayarkan kepada siapa.');
        }

        // Rantai "ajukan ulang" hanya boleh menunjuk ke pengajuan SENDIRI yang
        // memang layak diulang. Validasi `exists` saja tidak cukup: id milik
        // orang lain akan lolos, lalu nomor pengajuan mereka ikut tampil di
        // halaman detail sebagai "pengajuan ulang dari …".
        if (! empty($data['resubmit_of'])) {
            $asal = RopRequest::milik($this->userId())->find($data['resubmit_of']);
            if (! $asal || ! $asal->bisaDiajukanUlang()) {
                unset($data['resubmit_of']);
            }
        }

        // Kalau yang diketik persis sama dengan vendor terdaftar, tautkan ke
        // masternya. Pengaju tidak perlu tahu bedanya "pilih dari daftar" dan
        // "ketik nama yang sama" — laporan per vendor tetap rapi.
        if (empty($data['vendor_id']) && ! empty($data['pay_to_text'])) {
            $cocok = Vendor::aktif()->where('nama', trim($data['pay_to_text']))->first();
            if ($cocok) {
                $data['vendor_id']   = $cocok->id;
                $data['pay_to_text'] = null;
            }
        }

        // Peringatan bayar dobel — pengaju boleh menembusnya secara sadar,
        // tapi harus lihat dulu pengajuan mana yang mirip.
        if (empty($data['abaikan_dobel'])) {
            $mirip = $this->rop->cariSerupa($data);
            if ($mirip) {
                return $this->kembaliKeForm()->with('rop_dobel', [
                    'id'      => $mirip->id,
                    'number'  => $mirip->number,
                    'pengaju' => $mirip->requester?->nama ?: 'pengaju lain',
                    'amount'  => (float) $mirip->amount,
                    'status'  => $mirip->labelStatus(),
                ]);
            }
        }

        $data['attachment_path'] = $this->lampiran->simpan($request, 'lampiran', 'lampiran');

        $rop = $this->rop->buat($data, $this->userId());

        return redirect()->route('rop.pengajuan.show', $rop->id)
            ->with('rop_sukses', $rop->number);
    }

    public function show($id)
    {
        $rop = $this->milikku($id, boleh_lihat_semua: true);

        return view('rop.pengajuan.detail', [
            'rop'   => $rop->load(['vendor', 'costCentre', 'requester', 'reviewer', 'payer', 'completer', 'asal']),
            'logs'  => $rop->logs()->with('actor')->get(),
            'milikSendiri' => $rop->requester_id === $this->userId(),
        ]);
    }

    public function batal($id)
    {
        $rop = $this->milikku($id);
        $this->rop->batalkan($rop, $this->userId());

        return redirect()->route('rop.pengajuan.index')
            ->with('success', 'Pengajuan ' . $rop->number . ' dibatalkan.');
    }

    /**
     * Konfirmasi barang/dana diterima — pengganti kolom "Received by" di kertas.
     * Nota pembelian diminta di sini (bukan bukti transfer, itu punya Finance):
     * ini yang menutup loop pertanggungjawaban.
     */
    public function selesai(Request $request, $id)
    {
        $request->validate([
            'nota'     => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'catatan'  => 'nullable|string|max:500',
        ]);

        $rop = $this->milikku($id);
        $nota = $this->lampiran->simpan($request, 'nota', 'nota');

        // Nota sengaja TIDAK diwajibkan: kalau ditutup keras, pengajuan yang
        // notanya benar-benar hilang akan menggantung selamanya di status
        // "dibayar" dan angka rekap ikut melenceng. Tapi penutupan tanpa nota
        // dicatat di jejak audit, jadi kelonggarannya tetap terlihat.
        $catatan = $request->input('catatan');
        if (! $nota && ! $rop->nota_path) {
            $catatan = trim(($catatan ? $catatan . ' — ' : '') . 'ditutup tanpa nota');
        }

        $this->rop->tandaiSelesai($rop, [
            'nota_path' => $nota,
            'catatan'   => $catatan,
        ], $this->userId());

        return redirect()->route('rop.pengajuan.show', $rop->id)
            ->with('success', 'Terima kasih — pengajuan ' . $rop->number . ' ditutup.');
    }

    /* ───────── Dapur ───────── */

    private function userId(): string
    {
        return (string) session('id');
    }

    /**
     * Kembali ke form dengan isian tetap utuh. `back()` polos jatuh ke '/'
     * kalau request datang tanpa Referer — isian pengaju ikut hilang.
     */
    private function kembaliKeForm()
    {
        return back(302, [], route('rop.pengajuan.create'))->withInput();
    }

    /**
     * Ambil baris milik user ini.
     *
     * Yang berhak membuka antrian Finance juga boleh MELIHAT detail pengajuan
     * siapa pun — daftar rolenya sengaja sama persis dengan gerbang antrian
     * (RopService::ROLE_ANTRIAN + canSeeAllBranches). Sebelumnya di sini hanya
     * canSeeAllBranches, jadi Head IT bisa melihat isi pengajuan lewat drawer
     * antrian tapi kena 403 saat membuka halaman detailnya: informasi yang
     * sama, dua pintu, dua aturan.
     *
     * Aksi mengubah (batal, konfirmasi selesai) tetap khusus pemilik —
     * pemanggilnya cukup tidak mengirim $boleh_lihat_semua.
     */
    private function milikku($id, bool $boleh_lihat_semua = false): RopRequest
    {
        $rop = RopRequest::findOrFail($id);

        $bolehLihat = canSeeAllBranches()
            || in_array(session('group_name'), RopService::ROLE_ANTRIAN, true);

        if ($rop->requester_id !== $this->userId() && ! ($boleh_lihat_semua && $bolehLihat)) {
            abort(403, 'Pengajuan ini bukan milikmu.');
        }
        return $rop;
    }

    private function hitungPerStatus(): array
    {
        return RopRequest::milik($this->userId())
            ->selectRaw('status, COUNT(*) c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();
    }

    /**
     * Cost centre yang dipilih duluan di form: ikut pilihan terakhir orang ini
     * (paling akurat untuk yang lintas cabang seperti Supervisor), kalau belum
     * pernah mengajukan baru jatuh ke cabang yang tercatat di akunnya.
     */
    private function costCentreDefault(): ?string
    {
        $terakhir = RopRequest::milik($this->userId())->latest('id')->value('cost_centre');
        if ($terakhir) return $terakhir;

        return RopCostCentre::untukBranch(session('raw_branch_id'))?->kode;
    }

    /**
     * Pintasan "Ajukan lagi": pengeluaran yang paling sering diulang orang ini.
     * Diambil dari yang sudah tuntas supaya yang muncul benar-benar pola
     * belanja rutin, bukan pengajuan yang masih mengambang.
     *
     * Di-dedup per penerima + keterangan: tanpa ini, orang yang mengajukan
     * galon enam kali berturut-turut mendapat enam kartu yang sama persis dan
     * pintasannya kehilangan guna. Diambil lebih banyak dari yang ditampilkan
     * supaya setelah dedup tetap ada 6 pilihan.
     */
    private function pintasanTerakhir()
    {
        return RopRequest::milik($this->userId())
            ->whereIn('status', [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI])
            ->with('vendor')
            ->latest('id')
            ->limit(40)
            ->get()
            ->unique(fn (RopRequest $r) => mb_strtolower($r->namaPenerima() . '|' . $r->description))
            ->take(6)
            ->values();
    }
}
