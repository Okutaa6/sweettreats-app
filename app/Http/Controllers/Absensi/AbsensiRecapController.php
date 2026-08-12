<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRequest;
use App\Models\RawBranch;
use App\Services\AttendanceReconciler;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Absensi → Rekap Persetujuan" — recap Finance (DESKTOP, view-only).
 *
 * Semua pengajuan APPROVED dalam satu periode, per cabang. Finance TIDAK meng-approve
 * per item (kontrol dipindah ke tutup periode, §5.4) — halaman ini buat spot-check:
 * lihat apa yg sudah lolos, sandingkan klaim lembur vs scan (§2.11), sebelum tutup.
 *
 * Guard canSeeAllBranches (sama pola AbsensiUploadController). Route di segment(1) =
 * 'absensi' (node sudah di-grant Finance/admin), jadi tak butuh access node baru.
 *
 * Konteks: docs/rencana/rencana-rekonsiliasi-absensi.md §2.11, §5.4, Fase D.
 */
class AbsensiRecapController extends Controller
{
    /** Ikut satu sumber kebenaran — lihat catatan di PersetujuanController. */
    private const TIPE_LABEL = AkunPengajuanController::TIPE;

    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Rekap absensi hanya untuk Finance / Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        // periode (default bulan berjalan)
        $month = $request->input('m');
        $anchor = $month ? Carbon::parse($month . '-01') : Carbon::now()->startOfMonth();
        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();

        $branchFilter = $request->input('branch');

        $items = (new AttendanceRequestWorkflow())->recapForFinance($from, $to)
            ->load(['user', 'branch']);

        if ($branchFilter) {
            $items = $items->where('raw_branch_id', $branchFilter)->values();
        }

        // "Sanding scan" LIVE: recompute verifikasi lembur dari scan TERKINI tiap dibuka
        // (bukan snapshot saat submit). Supaya upload scan susulan langsung kebaca di
        // rekap finance. Override hanya di memori (tak di-save); snapshot tetap utuh.
        $rec = new AttendanceReconciler();
        foreach ($items as $it) {
            if ($it->tipe === 'lembur') {
                [$v, $ev] = $rec->checkLemburClaim(
                    (string) $it->user_id,
                    Carbon::parse($it->tanggal_from)->toDateString(),
                    $it->claimed_menit !== null ? (int) $it->claimed_menit : null,
                    $it->sub_tipe ?: 'jam'
                );
                $it->verifikasi = $v;
                $it->live_supported = $ev['supported_menit'] ?? null;   // dukungan scan (menit)
            }
        }

        // ringkasan: hitung per tipe + total lembur jam disetujui (SEBELUM filter verif)
        $summary = [
            'total'  => $items->count(),
            'cuti'   => $items->where('tipe', 'cuti')->count(),
            'izin'   => $items->where('tipe', 'izin')->count(),
            'sakit'  => $items->where('tipe', 'sakit')->count(),
            'libur'  => $items->where('tipe', 'libur')->count(),
            'wfh'    => $items->where('tipe', 'wfh')->count(),
            'lembur' => $items->where('tipe', 'lembur')->count(),
            'lembur_menit' => $items->where('tipe', 'lembur')->sum('claimed_menit'),
            'selisih' => $items->where('verifikasi', 'selisih')->count(),
            'tidak_bisa_dicek' => $items->where('verifikasi', 'tidak_bisa_dicek')->count(),
        ];

        // filter tabel (ringkasan tetap total): jenis + "cuma perlu dicek" (selisih)
        $tipeFilter = $request->input('jenis');
        if ($tipeFilter && isset(self::TIPE_LABEL[$tipeFilter])) {
            $items = $items->where('tipe', $tipeFilter)->values();
        }
        $verifFilter = $request->input('verif');
        if ($verifFilter === 'selisih') {
            $items = $items->where('verifikasi', 'selisih')->values();
        }

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $tipeLabel = self::TIPE_LABEL;

        return view('absensi.recap', compact(
            'items', 'summary', 'branches', 'branchFilter', 'verifFilter', 'tipeFilter', 'anchor', 'from', 'to', 'tipeLabel'
        ));
    }
}
