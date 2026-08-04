<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRequest;
use App\Models\Users;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Persetujuan" — inbox approver (MOBILE, sisi-TIM).
 *
 * Menampilkan form yang MENUNGGU tindakan user ini sebagai approver, dari
 * AttendanceRequestWorkflow::pendingFor(session id). "Siapa approver" itu data
 * att_approver (HR) — ORTHOGONAL dari app-role. Menu di-grant semua role
 * (AbsensiAccessPathSeeder); inbox otomatis kosong kalau kamu bukan approver.
 *
 * Route di segment(1) = 'persetujuan' (node access_path link='persetujuan').
 * Konteks: docs/rencana/rencana-rekonsiliasi-absensi.md §5.4.
 */
class PersetujuanController extends Controller
{
    /**
     * Satu sumber kebenaran label jenis pengajuan. Dulu tiap layar menyalin
     * daftarnya sendiri-sendiri, lalu melenceng: waktu jenis 'koreksi' lahir,
     * inbox atasan menampilkan kode mentah "koreksi", dan sebutan WFH beda-beda
     * antar layar ("WFH" vs "Kerja dari Rumah") untuk hal yang sama.
     */
    private const TIPE_LABEL = AkunPengajuanController::TIPE;

    private function userId(): string
    {
        $id = (string) session('id');
        if (! $id) abort(403);
        return $id;
    }

    public function index()
    {
        $userId = $this->userId();
        $wf = new AttendanceRequestWorkflow();

        // ── Menunggu: bungkus tiap pengajuan dgn data tampilan (umur, rantai, sanding-scan) ──
        $pending = $wf->pendingFor($userId)->load('user', 'branch')->map(function ($r) use ($wf) {
            $levels  = $wf->chainFor($r->user_id, $r->tipe);
            $idx     = $r->status === 'pending_l2' ? 1 : 0;   // lapis saat ini (0-based)
            $isFinal = ! isset($levels[$idx + 1]);
            $nextNames = $isFinal ? null : collect($levels[$idx + 1] ?? [])
                ->map(fn ($uid) => optional(Users::find($uid))->nama)
                ->filter()->implode(' / ');

            // sanding-scan (khusus lembur)
            $sanding = null;
            if ($r->tipe === 'lembur') {
                $sanding = [
                    'verifikasi'      => $r->verifikasi,                                   // nyambung|selisih|tidak_bisa_dicek
                    'klaim_menit'     => (int) $r->claimed_menit,
                    'supported_menit' => (int) data_get($r->scan_evidence, 'supported_menit', 0),
                ];
            }

            return (object) [
                'r'          => $r,
                'aging'      => $r->created_at ? (int) $r->created_at->diffInDays(Carbon::now()) : 0,
                'level'      => $idx + 1,
                'total'      => count($levels),
                'is_final'   => $isFinal,
                'next_names' => $nextNames ?: null,
                'sanding'    => $sanding,
            ];
        });

        // Sort: "perlu dicek" (selisih) di atas, lalu tanggal terlama
        $pending = $pending->sortBy(function ($c) {
            $needCheck = (($c->sanding['verifikasi'] ?? null) === 'selisih') ? 0 : 1;
            return sprintf('%d-%s', $needCheck, $c->r->tanggal_from);
        })->values();

        // ── Riwayat: yang pernah gua tindak (approve/reject) ──
        $recent = AttRequest::whereHas('approvals', fn ($q) => $q->where('approver_user_id', $userId))
            ->with('user', 'branch')
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        $tipeLabel = self::TIPE_LABEL;
        return view('absensi.persetujuan.inbox', compact('pending', 'recent', 'tipeLabel'));
    }

    public function act(Request $request, $id)
    {
        $userId = $this->userId();

        $data = $request->validate([
            'action'  => 'required|in:approve,reject',
            'catatan' => 'nullable|string|max:255',
        ]);

        $req = AttRequest::findOrFail($id);

        try {
            (new AttendanceRequestWorkflow())->act($req, $userId, $data['action'], $data['catatan'] ?? null);
        } catch (\RuntimeException $e) {
            // approver bukan utk tahap ini / status berubah (mis. keburu ditindak approver lain)
            return back()->with('error', $e->getMessage());
        }

        $verb = $data['action'] === 'approve' ? 'disetujui' : 'ditolak';
        return redirect()->route('persetujuan.inbox')->with('success', "Pengajuan {$verb}.");
    }
}
