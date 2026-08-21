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

        // Baris yang lahir sendiri dan belum disentuh manusia naik ke atas —
        // itu satu-satunya hal di halaman ini yang menunggu keputusan.
        $perluTinjau = $ips->where('sumber', 'otomatis')->where('status', 'aktif');
        $belajarAktif = \App\Services\WifiBelajarSendiri::aktif();

        return view('absensi.wifi', compact('ips', 'branches', 'perluTinjau', 'belajarAktif'));
    }

    /**
     * Saklar WiFi-belajar-sendiri. Disimpan sebagai data (sys_setting), bukan
     * .env: rencana §3 minta fiturnya bisa dimatikan dari halaman ini juga
     * ketika yang memegangnya bukan orang yang bisa deploy.
     */
    public function saklar()
    {
        $this->guard();

        $nyala = ! \App\Services\WifiBelajarSendiri::aktif();
        \App\Models\Setting::put(\App\Services\WifiBelajarSendiri::KUNCI_SAKLAR, $nyala ? '1' : '0');

        \App\Models\AuditLog::record('update',
            'WiFi belajar sendiri ' . ($nyala ? 'DINYALAKAN' : 'DIMATIKAN') . ' dari halaman WiFi Cabang');

        return back()->with('success', $nyala
            ? 'Belajar sendiri dinyalakan — jaringan baru bisa terdaftar otomatis lagi.'
            : 'Belajar sendiri dimatikan. Jaringan baru harus didaftarkan manual; baris yang sudah ada tidak berubah.');
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

        // Sejak pencocokan melebar ke /48 (AttBranchIp::matchBranch), satu /48
        // yang dipegang DUA cabang membuat absen di salah satunya terbaca
        // sebagai cabang yang lain — diam-diam, tanpa gejala apa pun. Seluruh
        // pelebaran itu bersandar pada asumsi "satu /48 = satu pelanggan
        // Telkom"; di sinilah asumsi itu diuji tiap kali ada baris baru.
        $bentrok48 = AttBranchIp::pemilik48Lain($data['ip'], $data['raw_branch_id']);
        if ($bentrok48) {
            return back()->with('error',
                'Blok /48 milik ' . $data['ip'] . ' sudah dipakai cabang ' .
                optional($bentrok48->branch)->name . ' (lewat ' . $bentrok48->ip . '). ' .
                'Karena pencocokan IPv6 memakai /48, mendaftarkan ini akan membuat absen ' .
                'kedua cabang tertukar. Nonaktifkan dulu baris yang lama kalau jalurnya memang pindah.');
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
