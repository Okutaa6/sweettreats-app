<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttDay;
use App\Models\AttPeriod;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Absensi → Periode Gaji" — kontrol finance (DESKTOP). Fase D.
 *
 * Finance: buat periode → tutup (att_day beku, §D2) → export payroll. Kontrol
 * per-periode, bukan per-item (§5.4). Ini yang bikin cut-off balik ke tgl 25.
 *
 * Guard canSeeAllBranches (pola AbsensiUploadController). Route segment(1)='absensi'.
 * Konteks: docs/rencana-rekonsiliasi-absensi.md §5.2, §5.4, §7 Fase D.
 */
class AbsensiPeriodController extends Controller
{
    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Periode absensi hanya untuk Finance / Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();
        $periods = AttPeriod::with('closer')->orderByDesc('periode_from')->limit(24)->get();
        return view('absensi.period', compact('periods'));
    }

    public function store(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'nama'         => 'nullable|string|max:100',
            'periode_from' => 'required|date',
            'periode_to'   => 'required|date|after_or_equal:periode_from',
        ]);

        // cegah tumpang-tindih dengan periode lain (global)
        $overlap = AttPeriod::whereNull('raw_branch_id')
            ->where('periode_from', '<=', $data['periode_to'])
            ->where('periode_to', '>=', $data['periode_from'])
            ->exists();
        if ($overlap) {
            return back()->with('error', 'Rentang tanggal tumpang-tindih dengan periode yang sudah ada.');
        }

        AttPeriod::create([
            'nama'         => $data['nama'] ?: ('Periode ' . Carbon::parse($data['periode_from'])->format('d/m') . '–' . Carbon::parse($data['periode_to'])->format('d/m/y')),
            'periode_from' => $data['periode_from'],
            'periode_to'   => $data['periode_to'],
            'status'       => 'open',
            'created_by'   => (string) session('id') ?: null,
        ]);
        return redirect()->route('absensi.period')->with('success', 'Periode dibuat.');
    }

    /** Tutup periode → att_day di rentangnya beku (reconcile tak menimpanya lagi). */
    public function close(Request $request, $id)
    {
        $this->guard();
        $period = AttPeriod::findOrFail($id);
        if ($period->isClosed()) {
            return back()->with('error', 'Periode sudah ditutup.');
        }

        // Garis finis antrian SPV: periode TAK BOLEH ditutup selama masih ada
        // flag terbuka di rentangnya — angka yang dikunci buat gajian harus
        // sudah bersih dari kejanggalan yang belum diputuskan (rencana-absen-hp §8).
        $flagTerbuka = \App\Models\AttFlag::whereBetween('work_date', [
                $period->periode_from->toDateString(),
                $period->periode_to->toDateString(),
            ])
            ->where('status', '!=', 'selesai')
            ->count();
        if ($flagTerbuka > 0) {
            return back()->with('error',
                "Masih ada {$flagTerbuka} flag absen yang belum diselesaikan di rentang periode ini. " .
                'Selesaikan dulu lewat halaman Pengawasan Absen, baru periode bisa ditutup.');
        }

        $period->update([
            'status'    => 'closed',
            'closed_by' => (string) session('id') ?: null,
            'closed_at' => Carbon::now(),
            'catatan'   => $request->input('catatan'),
        ]);
        return redirect()->route('absensi.period')->with('success', 'Periode ditutup — data absen di rentang ini terkunci.');
    }

    /** Export CSV payroll: rekap att_day per orang untuk periode ini. */
    public function export(Request $request, $id)
    {
        $this->guard();
        $period = AttPeriod::findOrFail($id);
        $from = $period->periode_from->toDateString();
        $to   = $period->periode_to->toDateString();

        $agg = AttDay::whereBetween('work_date', [$from, $to])
            ->select('user_id',
                DB::raw("SUM(status='hadir') AS hadir"),
                DB::raw("SUM(status='telat') AS telat"),
                DB::raw("SUM(status='alpa') AS alpa"),
                DB::raw("SUM(status='cuti') AS cuti"),
                DB::raw("SUM(status='izin') AS izin"),
                DB::raw("SUM(status='wfh') AS wfh"),
                DB::raw("SUM(status='libur') AS libur"),
                DB::raw("SUM(telat_menit) AS telat_menit"),
                DB::raw("SUM(lembur_menit_disetujui) AS lembur_menit"),
                DB::raw("SUM(single_scan) AS single_scan"),
                DB::raw("COUNT(*) AS total_hari"))
            ->groupBy('user_id')
            ->get();

        $names = Users::whereIn('id', $agg->pluck('user_id'))->pluck('nama', 'id');

        $filename = 'payroll_absensi_' . $from . '_' . $to . '.csv';
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($agg, $names) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF");   // BOM UTF-8 biar Excel benar
            fputcsv($out, ['Nama', 'Hadir', 'Telat', 'Alpa', 'Cuti', 'Izin', 'WFH', 'Libur',
                'Total Telat (menit)', 'Lembur (menit)', 'Lembur (jam)', 'Single-scan', 'Total Hari']);
            foreach ($agg as $r) {
                fputcsv($out, [
                    $names[$r->user_id] ?? '(?)',
                    $r->hadir, $r->telat, $r->alpa, $r->cuti, $r->izin, $r->wfh, $r->libur,
                    $r->telat_menit, $r->lembur_menit, round($r->lembur_menit / 60, 2),
                    $r->single_scan, $r->total_hari,
                ]);
            }
            fclose($out);
        }, $filename, $headers);
    }
}
