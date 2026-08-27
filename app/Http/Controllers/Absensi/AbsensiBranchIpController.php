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

        // Baris yang lahir sendiri dan belum disentuh manusia — itu satu-satunya
        // hal di halaman ini yang menunggu keputusan.
        //
        // ⚠️ TIDAK semua baris otomatis menunggu keputusan (§6 rencana IPv4).
        // Yang se-/48 dengan jalur yang sudah disahkan manusia sudah cocok
        // dengan sendirinya sejak pencocokan /48 (21 Agu 2026) — terdaftar atau
        // tidak, hasilnya sama. Menandainya "perlu ditinjau" tak menambah apa
        // pun, dan halaman yang berteriak untuk hal yang bukan keputusan akan
        // berhenti dibaca. Kelima baris otomatis di prod semuanya jenis itu.
        //
        // Aturannya MILIK SERVIS, tidak disalin ke sini: label yang ditulis saat
        // mendaftarkan dan sorotan di layar ini harus selalu sepakat.
        $perluTinjau = $ips->where('sumber', 'otomatis')->where('status', 'aktif')
            ->filter(fn ($r) => \App\Services\WifiBelajarSendiri::perluTinjau(
                (string) $r->ip, $r->raw_branch_id, $ips
            ))
            ->values();
        $idTinjau = $perluTinjau->pluck('id')->all();
        $belajarAktif = \App\Services\WifiBelajarSendiri::aktif();

        return view('absensi.wifi', compact('ips', 'branches', 'perluTinjau', 'idTinjau', 'belajarAktif') + [
            // Angka diambil dari konstantanya, TIDAK diketik ulang: teks lama
            // menulis "3 orang" berbulan-bulan sesudah ambangnya turun jadi 2.
            'ambangOrang' => \App\Services\WifiBelajarSendiri::AMBANG_ORANG,
            'jendelaJam'  => \App\Services\WifiBelajarSendiri::JENDELA_JAM,
        ]);
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

        // Satu alamat, satu bentuk tulisan. Aturan `ip` Laravel meloloskan
        // ::ffff:a.b.c.d sebagai IP yang sah; kalau bentuk itu yang tersimpan,
        // barisnya tak akan pernah cocok dengan permintaan bertitik — DAN tak
        // bisa didaftarkan ulang, karena UNIQUE(ip) menolak kembarannya. Macet
        // senyap. Dibakukan di pintu masuk, sebelum pemeriksaan duplikat, sama
        // seperti yang dilakukan matchBranch saat mencari.
        $data['ip'] = AttBranchIp::teksIpv4($data['ip']) ?? $data['ip'];

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
