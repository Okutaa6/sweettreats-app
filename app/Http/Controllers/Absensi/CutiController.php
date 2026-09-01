<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CutiMutasi;
use App\Models\CutiPeriode;
use App\Models\GroupPath;
use App\Services\CutiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Cuti — rekap saldo semua karyawan + cuti bersama + koreksi + kontrak baru.
 * Layar Finance/admin (design_handoff_cuti). Karyawan melihat saldonya sendiri
 * di halaman Akun, BUKAN di sini. docs/rencana/rencana-jatah-cuti.md.
 */
class CutiController extends Controller
{
    /**
     * Finance = pemilik proses; IT akses penuh (keputusan Pusat Akun); admin.
     * Konsultan Finance/IT ikut karena menunya dikloning dari role induknya —
     * tanpa baris ini menu Cuti tampil lalu 403 (kegagalan senyap).
     */
    private const BOLEH = ['Finance', 'Head IT', 'IT', 'Konsultan Finance', 'Konsultan IT',
        'Super Admin', 'Admin General', 'Admin Backup', 'Admin'];

    public function __construct(private CutiService $svc)
    {
    }

    private function guardAksi(): void
    {
        $role = optional(GroupPath::find(session('group_id')))->nama;
        abort_unless(in_array($role, self::BOLEH, true), 403, 'Halaman cuti hanya untuk Finance / IT / Admin.');
    }

    public function index()
    {
        $this->guardAksi();

        $periodes = CutiPeriode::berjalan()
            ->with(['user.group', 'user.branch', 'mutasi' => fn ($q) => $q->orderByDesc('tanggal')->orderByDesc('id')])
            ->get()
            ->filter(fn ($p) => $p->user && (int) $p->user->status === 1)
            ->sortBy(fn ($p) => $p->user->nama, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $namaPencatat = \App\Models\Users::whereIn('id',
            $periodes->flatMap(fn ($p) => $p->mutasi->pluck('dibuat_oleh'))->filter()->unique()
        )->pluck('nama', 'id');

        $roster = $periodes->map(function ($p) use ($namaPencatat) {
            $pakai   = (float) $p->mutasi->where('hari', '>', 0)->sum('hari');
            $balik   = (float) $p->mutasi->where('hari', '<', 0)->sum('hari'); // negatif
            $bersama = (float) $p->mutasi->where('jenis', 'cuti_bersama')->where('hari', '>', 0)->sum('hari');
            $sisa    = (float) $p->saldo_awal - $pakai - $balik;

            // inisial: buang gelar ("drg. Komalawaty" → K); warna avatar stabil
            // per orang (hash user_id), tidak bergeser saat roster berubah.
            $polos = preg_replace('/^drg\.?\s*/i', '', $p->user->nama) ?: $p->user->nama;

            return [
                'uid'        => $p->user_id,
                'nama'       => $p->user->nama,
                'inisial'    => strtoupper(mb_substr($polos, 0, 1)),
                'role'       => optional($p->user->group)->nama ?? 'Karyawan',
                'cabang'     => optional($p->user->branch)->name ?? 'Semua Cabang',
                'av'         => abs(crc32($p->user_id)) % 8,
                'saldoAwal'  => (float) $p->saldo_awal,
                'sisa'       => $sisa,
                'terpakai'   => $pakai,
                'bersama'    => $bersama,
                'kontrak'    => $p->kontrak_akhir?->translatedFormat('j M Y'),
                'mutasi'     => $p->mutasi->map(fn ($m) => [
                    'tanggal'    => Carbon::parse($m->tanggal)->translatedFormat('j M Y'),
                    'jenis'      => $m->jenis,
                    'hari'       => (float) $m->hari,
                    'keterangan' => $m->keterangan,
                    'oleh'       => $m->dibuat_oleh
                        ? ($namaPencatat[$m->dibuat_oleh] ?? 'Admin')
                        : ($m->sumber === 'pengajuan' ? 'Diajukan sendiri' : 'Sistem'),
                ])->values(),
            ];
        })->values();

        $stats = [
            'total'        => $roster->count(),
            'minus'        => $roster->where('sisa', '<', 0)->count(),
            'tanpaKontrak' => $roster->whereNull('kontrak')->count(),
            'rata'         => $roster->count() ? round($roster->avg('sisa'), 1) : 0,
        ];

        $branches = \App\Models\RawBranch::orderBy('name')->pluck('name');

        return view('absensi.cuti', compact('roster', 'stats', 'branches'));
    }

    /** Terapkan cuti bersama (dari wizard 3 langkah). */
    public function bersama(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'tanggal' => 'required|date',
            'label'   => 'required|string|max:120',
            'hari'    => 'required|numeric|min:0.5|max:3',
            'kecuali' => 'nullable|string', // csv user_id
        ]);

        $kecuali = array_filter(explode(',', $data['kecuali'] ?? ''));
        $batch   = uniqid('cb', true);
        $n = $this->svc->cutiBersama($data['tanggal'], $data['label'], $kecuali, (string) session('id'), (float) $data['hari'], $batch);

        AuditLog::record('create', "Cuti bersama \"{$data['label']}\" ({$data['tanggal']}, {$data['hari']} hari): potong {$n} karyawan"
            . ($kecuali ? ', ' . count($kecuali) . ' dikecualikan' : ''));

        return redirect()->route('absensi.cuti')
            ->with('success', "{$n} karyawan dipotong {$data['hari']} hari — \"{$data['label']}\".")
            ->with('cb_undo', ['ref' => $batch, 'label' => $data['label']]);
    }

    /** Urungkan batch cuti bersama (tombol di toast) — TEPAT batch itu saja (by ref). */
    public function bersamaUndo(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate(['ref' => 'required|string|max:64', 'label' => 'nullable|string|max:120']);

        $n = CutiMutasi::where('jenis', 'cuti_bersama')
            ->where('sumber', 'massal')
            ->where('ref_id', $data['ref'])
            ->delete();

        AuditLog::record('delete', 'Urungkan cuti bersama "' . ($data['label'] ?? '') . "\": {$n} potongan dikembalikan");

        return redirect()->route('absensi.cuti')->with('success', "Cuti bersama diurungkan — {$n} potongan dikembalikan.");
    }

    /** Koreksi saldo per orang (kembalikan = ganti hari / kurangi). */
    public function koreksi(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'arah'    => 'required|in:kembalikan,kurangi',
            'hari'    => 'required|numeric|min:0.5|max:12',
            'catatan' => 'required|string|max:200',
        ]);

        $hari = $data['arah'] === 'kembalikan' ? -abs((float) $data['hari']) : abs((float) $data['hari']);
        $m = $this->svc->koreksi($data['user_id'], Carbon::today()->toDateString(), $hari, $data['catatan'], (string) session('id'));

        if (! $m) {
            return redirect()->route('absensi.cuti')->with('error', 'Karyawan ini tidak punya periode cuti berjalan.');
        }

        AuditLog::record('update', 'Koreksi cuti ' . ($hari < 0 ? '+' : '−') . abs($hari) . ' hari: ' . $data['catatan'], $m);

        return redirect()->route('absensi.cuti')->with('success', 'Koreksi tersimpan — saldo '
            . ($hari < 0 ? 'bertambah ' : 'berkurang ') . abs($hari) . ' hari.');
    }

    /** Tutup periode aktif & mulai kontrak baru (tanggal boleh kosong). */
    public function kontrakBaru(Request $request)
    {
        $this->guardAksi();
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tanggal' => 'nullable|date',
        ]);

        $p = $this->svc->mulaiKontrakBaru($data['user_id'], $data['tanggal'] ?? null);
        if (! $p) {
            return redirect()->route('absensi.cuti')->with('error', 'Karyawan ini tidak punya periode cuti berjalan.');
        }

        $nama = optional($p->user)->nama ?? $data['user_id'];
        AuditLog::record('create', "Kontrak cuti baru: {$nama} — saldo awal {$p->saldo_awal}"
            . ($p->kontrak_akhir ? ', s/d ' . $p->kontrak_akhir->translatedFormat('j M Y') : ', tanpa tanggal'));

        return redirect()->route('absensi.cuti')->with('success', "Kontrak baru {$nama} dimulai — saldo "
            . rtrim(rtrim(number_format((float) $p->saldo_awal, 1, ',', '.'), '0'), ',') . ' hari.');
    }
}
