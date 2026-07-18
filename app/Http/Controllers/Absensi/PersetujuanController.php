<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttRequest;
use App\Services\AttendanceRequestWorkflow;
use Illuminate\Http\Request;

/**
 * "Persetujuan" — inbox approver (MOBILE, sisi-TIM).
 *
 * Menampilkan form yang MENUNGGU tindakan user ini sebagai approver, dari
 * AttendanceRequestWorkflow::pendingFor(session id). "Siapa approver" itu data
 * att_approver (HR) — ORTHOGONAL dari app-role. Menu di-grant semua role
 * (AbsensiAccessPathSeeder); inbox otomatis kosong kalau kamu bukan approver.
 *
 * Route di segment(1) = 'persetujuan' (node access_path link='persetujuan').
 * Konteks: docs/rencana-rekonsiliasi-absensi.md §5.4.
 */
class PersetujuanController extends Controller
{
    private const TIPE_LABEL = [
        'cuti' => 'Cuti', 'izin' => 'Izin', 'libur' => 'Libur / Tukar Hari',
        'lembur' => 'Lembur', 'wfh' => 'WFH',
    ];

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
        $pending = $wf->pendingFor($userId)
            ->load('user')
            ->sortBy('tanggal_from')
            ->values();

        // riwayat singkat: form yg pernah gua tindak (approve/reject)
        $recent = AttRequest::whereHas('approvals', function ($q) use ($userId) {
                $q->where('approver_user_id', $userId);
            })
            ->with('user')
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('updated_at')
            ->limit(10)
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
