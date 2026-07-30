<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AttBranchIp;
use App\Models\RawBranch;
use Illuminate\Http\Request;

/**
 * Admin "WiFi Cabang" — daftar IP internet terdaftar per cabang.
 * Ganti WiFi / tambah jalur / buka cabang = urusan DATA di sini, BUKAN deploy.
 * Diisi juga lewat tombol "daftarkan IP" dari layar Pengawasan (lingkaran belajar).
 * Guard canSeeAllBranches (pola AbsensiRecapController), segment(1)='absensi'.
 * Lihat docs/rencana/rencana-absen-hp.md §7 layar 3.
 */
class AbsensiBranchIpController extends Controller
{
    /**
     * Kelola IP = kerjaan INFRASTRUKTUR → IT (Head IT + IT) + admin.
     * BUKAN Finance (keputusan user 2026-07-24) — Finance cukup lihat efeknya
     * di Pengawasan/periode. SPV tetap bisa daftarkan IP dari flag (jalur
     * lingkaran-belajar di PengawasanController).
     */
    private function guard(): void
    {
        if (! in_array(session('group_name'), ['Head IT', 'IT', 'Super Admin', 'Admin General', 'Admin Backup', 'Admin'], true)) {
            abort(403, 'Halaman ini khusus IT/Admin.');
        }
    }

    public function index()
    {
        $this->guard();

        $ips = AttBranchIp::with('branch')
            ->orderBy('status')->orderBy('raw_branch_id')->orderByDesc('last_seen_at')
            ->get();
        $branches = RawBranch::orderBy('name')->get(['id', 'name']);

        return view('absensi.wifi', compact('ips', 'branches'));
    }

    public function store(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'raw_branch_id' => 'required|exists:raw_branch,id',
            'ip'            => 'required|ip',
            'label'         => 'nullable|string|max:191',
        ]);

        $sudahAda = AttBranchIp::where('ip', $data['ip'])->first();
        if ($sudahAda) {
            return back()->with('error',
                'IP ' . $data['ip'] . ' sudah terdaftar di cabang ' . optional($sudahAda->branch)->name .
                ' — satu IP hanya boleh satu cabang. Nonaktifkan dulu yang lama kalau memang pindah.');
        }

        AttBranchIp::create($data + [
            'status'     => 'aktif',
            'created_by' => (string) session('id') ?: null,
        ]);

        return back()->with('success', 'IP terdaftar. Absen dari jaringan ini sekarang dikenali sebagai ' .
            optional(RawBranch::find($data['raw_branch_id']))->name . '.');
    }

    /** Aktif ⇄ nonaktif. Riwayat tidak pernah dihapus. */
    public function toggle($id)
    {
        $this->guard();

        $ip = AttBranchIp::findOrFail($id);
        $ip->update(['status' => $ip->status === 'aktif' ? 'nonaktif' : 'aktif']);

        return back()->with('success', 'Status IP ' . $ip->ip . ' sekarang: ' . $ip->status . '.');
    }
}
