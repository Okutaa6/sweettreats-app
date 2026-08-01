<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\RopCostCentre;
use App\Models\RopRequest;
use App\Services\RopService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RopRekapController — laporan pengeluaran ROP (zona burgundy).
 *
 * Untuk Finance & Executive: berapa yang keluar bulan ini, ke cost centre
 * mana, lewat akun beban apa, dan berapa lama prosesnya.
 *
 * Endpoint:
 *   GET finance/rop/rekap            → layar rekap bulanan
 *   GET finance/rop/rekap/export     → CSV rekap (daftar transaksi periode)
 *   GET finance/rop/export/accurate  → CSV untuk diimpor ke Accurate
 */
class RopRekapController extends Controller
{
    /**
     * Rekap dibaca Finance DAN Executive (rencana §9: laporan untuk keduanya).
     * Executive tidak masuk canSeeAllBranches() — perannya memang cuma membaca,
     * tidak menyentuh antrian — jadi ditambahkan lewat RopService::ROLE_REKAP,
     * bukan dengan melonggarkan helper global yang dipakai layar lain.
     */
    private function guard(): void
    {
        if (! canSeeAllBranches() && ! in_array(session('group_name'), RopService::ROLE_REKAP, true)) {
            abort(403, 'Halaman ini untuk Finance dan Executive.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();
        [$mulai, $selesai, $periode] = $this->periode($request);
        $cc = $request->query('cc');

        $dasar = fn () => RopRequest::query()
            ->when($cc, fn ($q) => $q->where('cost_centre', $cc));

        // Yang dihitung "keluar" = yang sudah dibayar, memakai TANGGAL BAYAR —
        // bukan tanggal diajukan. Pengajuan akhir Juli yang ditransfer Agustus
        // adalah pengeluaran Agustus, sama seperti pembukuan.
        $dibayar = $dasar()
            ->whereIn('status', [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI])
            ->whereBetween('paid_at', [$mulai, $selesai]);

        return view('finance.rop.rekap', [
            'periode'     => $periode,
            'cc'          => $cc,
            'costCentres' => RopCostCentre::aktif()->get(),
            'ringkas'     => [
                'total_dibayar'  => (float) (clone $dibayar)->sum('amount'),
                'jumlah'         => (clone $dibayar)->count(),
                'rata_hari'      => $this->rataLamaProses(clone $dibayar),
                'masih_antre'    => (float) $dasar()->whereIn('status', [
                                        RopRequest::STATUS_DIAJUKAN, RopRequest::STATUS_DISETUJUI,
                                    ])->sum('amount'),
            ],
            'perCostCentre' => (clone $dibayar)
                ->selectRaw('cost_centre, COUNT(*) jumlah, SUM(amount) total')
                ->groupBy('cost_centre')->orderByDesc('total')->get(),
            'perAkun' => (clone $dibayar)
                ->selectRaw('coa_no, coa_name, COUNT(*) jumlah, SUM(amount) total')
                ->groupBy('coa_no', 'coa_name')->orderByDesc('total')->get(),
            'transaksi' => (clone $dibayar)
                ->with(['requester', 'vendor', 'costCentre'])
                ->orderBy('paid_at')->get(),
        ]);
    }

    /** CSV rekap — daftar transaksi periode, untuk arsip & olah sendiri. */
    public function exportCsv(Request $request): StreamedResponse
    {
        $this->guard();
        [$mulai, $selesai, $periode] = $this->periode($request);
        $cc = $request->query('cc');

        $baris = RopRequest::query()
            ->when($cc, fn ($q) => $q->where('cost_centre', $cc))
            ->whereIn('status', [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI])
            ->whereBetween('paid_at', [$mulai, $selesai])
            ->with(['requester', 'vendor', 'costCentre'])
            ->orderBy('paid_at')
            ->get();

        return $this->kirimCsv(
            'rekap-rop-' . $periode->format('Y-m') . ($cc ? '-' . strtolower($cc) : '') . '.csv',
            ['Nomor', 'Tanggal Bayar', 'Cost Centre', 'Pengaju', 'Dibayarkan Kepada', 'Keterangan',
             'Akun Beban', 'Nama Akun', 'Jumlah', 'Status', 'Tanggal Diajukan'],
            $baris->map(fn (RopRequest $r) => [
                $r->number,
                $r->paid_at?->format('Y-m-d'),
                $r->costCentre?->nama ?: $r->cost_centre,
                $r->requester?->nama,
                $r->namaPenerima(),
                $r->description,
                $r->coa_no,
                $r->coa_name,
                (int) $r->amount,
                $r->labelStatus(),
                $r->created_at?->format('Y-m-d'),
            ])
        );
    }

    /**
     * CSV untuk diimpor ke Accurate — supaya Finance tidak mengetik ulang
     * pengeluaran yang sudah lengkap datanya di sini.
     *
     * ⚠️ HEADER MASIH PERLU DICOCOKKAN dengan berkas template impor resmi
     * Accurate (belum ada di repo ini — lihat catatan di
     * docs/rencana/rencana-pengajuan-rop.md §10 improvement 5). Kolom di bawah
     * berisi semua data yang Accurate butuhkan, tapi urutan & nama header-nya
     * belum diverifikasi terhadap template asli, jadi JANGAN dipakai impor
     * produksi sebelum dicocokkan. Ini disengaja: menebak nama header lalu
     * mengaku "format resmi" lebih berbahaya daripada mengaku belum yakin.
     */
    public function exportAccurate(Request $request): StreamedResponse
    {
        $this->guard();
        [$mulai, $selesai, $periode] = $this->periode($request);
        $cc = $request->query('cc');

        $baris = RopRequest::query()
            ->when($cc, fn ($q) => $q->where('cost_centre', $cc))
            ->whereIn('status', [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI])
            ->whereBetween('paid_at', [$mulai, $selesai])
            ->whereNotNull('coa_no')
            ->with(['vendor', 'costCentre'])
            ->orderBy('paid_at')
            ->get();

        // Urutan kolom & pemisah koma mengikuti handoff desain
        // (design_handoff_rop/README.md, bagian "Endpoint yang dibutuhkan").
        return $this->kirimCsv(
            'impor-accurate-rop-' . $periode->format('Y-m') . '.csv',
            ['Tanggal', 'No Bukti', 'Kode Akun', 'Nama Akun', 'Cost Centre', 'Penerima', 'Keterangan', 'Nominal'],
            $baris->map(fn (RopRequest $r) => [
                $r->paid_at?->format('d/m/Y'),
                $r->number,
                $r->coa_no,
                $r->coa_name,
                $r->cost_centre,
                $r->namaPenerima(),
                $r->description,
                (int) $r->amount,
            ]),
            ','
        );
    }

    /* ───────── Dapur ───────── */

    /**
     * Periode bulan dari query (?bulan=YYYY-MM), default bulan berjalan.
     *
     * Parsing-nya dibungkus: `Carbon::createFromFormat` MELEMPAR untuk masukan
     * yang tidak berbentuk tanggal ("?bulan=ngawur" → halaman 500). Nilai dari
     * query string tidak pernah bisa dipercaya bentuknya — entah salah ketik,
     * tautan lama, atau perayap.
     */
    private function periode(Request $request): array
    {
        $mentah = (string) $request->query('bulan', '');

        try {
            $periode = $mentah !== ''
                ? Carbon::createFromFormat('Y-m', $mentah)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $periode = now()->startOfMonth();
        }

        return [$periode->copy()->startOfMonth(), $periode->copy()->endOfMonth(), $periode];
    }

    /** Rata-rata hari dari diajukan sampai dibayar — ukuran kecepatan proses. */
    private function rataLamaProses($query): ?float
    {
        $rata = $query->selectRaw('AVG(DATEDIFF(paid_at, created_at)) r')->value('r');
        return $rata === null ? null : round((float) $rata, 1);
    }

    private function kirimCsv(string $namaFile, array $header, $baris, string $pemisah = ';'): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $baris, $pemisah) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM — biar Excel Windows tidak merusak karakter Indonesia
            fputcsv($out, $header, $pemisah);
            foreach ($baris as $b) {
                fputcsv($out, $b, $pemisah);
            }
            fclose($out);
        }, $namaFile, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
