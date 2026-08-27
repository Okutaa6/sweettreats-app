<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\CutiPeriode;
use App\Services\CutiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lembar Gaji — rekap per karyawan per bulan untuk dasar hitung gaji MANUAL
 * Finance (sistem penggajian belum ada). Menyatukan yang selama ini terpencar
 * di 3 halaman (Kehadiran, Rekap Persetujuan, Cuti) jadi satu tabel siap
 * ekspor: hari kerja, telat, lembur di-ACC, alpa, cuti/izin/libur/wfh, sisa cuti.
 *
 * SUMBER: att_day (hasil rekonsiliasi — sudah lintas mesin, lintas cabang,
 * dan sudah menyerap pengajuan yang di-ACC). Angka di sini = angka yang sama
 * yang dilihat di Kehadiran, cuma diringkas per orang.
 *
 * Guard canSeeAllBranches (Finance/IT/admin). Bukan angka RUPIAH — sistem tak
 * tahu tarif; ini bahan mentah jam/hari, Finance yang mengalikan tarifnya.
 */
class LembarGajiController extends Controller
{
    /**
     * Samakan dgn grant SQL + halaman Cuti: Finance pemilik, IT akses penuh.
     * Konsultan Finance/IT ikut — menunya kloningan role induk (2026-08-21).
     */
    private const BOLEH = ['Finance', 'Head IT', 'IT', 'Konsultan Finance', 'Konsultan IT',
        'Super Admin', 'Admin General', 'Admin Backup', 'Admin'];

    private function guard(): void
    {
        abort_unless(in_array(session('group_name'), self::BOLEH, true), 403,
            'Lembar gaji hanya untuk Finance / IT / Admin.');
    }

    /** Bangun agregasi bulan → array baris + ringkasan. */
    private function rekap(Carbon $anchor, ?string $branch): array
    {
        $from = $anchor->copy()->startOfMonth()->toDateString();
        $to   = $anchor->copy()->endOfMonth()->toDateString();

        // Agregasi att_day per user dalam satu query berkondisi.
        $agg = DB::table('att_day')
            ->select('user_id',
                DB::raw("SUM(status IN ('hadir','telat')) AS hadir"),
                DB::raw("SUM(status = 'telat') AS telat_hari"),
                DB::raw("SUM(telat_menit) AS telat_menit"),
                DB::raw("SUM(lembur_menit_disetujui) AS lembur_menit"),
                DB::raw("SUM(status = 'alpa') AS alpa"),
                DB::raw("SUM(status = 'cuti') AS cuti"),
                DB::raw("SUM(status = 'izin') AS izin"),
                // Sakit dihitung TERPISAH dari izin & tak pernah dikurangi dari
                // apa pun — dibayar penuh (keputusan Ekky 2026-08-11). Kolomnya
                // ada supaya Finance bisa melihat polanya, bukan buat memotong.
                DB::raw("SUM(status = 'sakit') AS sakit"),
                DB::raw("SUM(status = 'libur') AS libur"),
                DB::raw("SUM(status = 'wfh') AS wfh"))
            ->whereBetween('work_date', [$from, $to])
            ->groupBy('user_id')
            ->get()->keyBy('user_id');

        // Roster: semua user aktif (yang tak punya att_day tetap tampil = 0),
        // supaya Finance tak kehilangan orang yang sebulan penuh tak ada data.
        $users = DB::table('users')
            ->leftJoin('group_path', 'group_path.id', '=', 'users.group_id')
            ->leftJoin('raw_branch', 'raw_branch.id', '=', 'users.raw_branch_id')
            ->where('users.status', 1)
            ->when($branch, fn ($q) => $q->where('users.raw_branch_id', $branch))
            ->orderBy('users.nama')
            ->get(['users.id', 'users.nama', 'group_path.nama as role', 'raw_branch.name as cabang']);

        $sisaCuti = CutiPeriode::berjalan()->get(['user_id', 'saldo_awal', 'id'])
            ->keyBy('user_id');
        $svc = app(CutiService::class);

        // DODO = lembur di hari libur, TARIFNYA BEDA (keputusan Ekky 2026-08-22).
        // Angkanya PECAHAN dari lembur_menit di atas, bukan tambahan — reconciler
        // menjumlahkan semua lembur yang di-ACC tanpa memeriksa jenisnya. Karena
        // itu 'lembur_biasa' ditulis sebagai PENGURANGAN: apa pun yang terjadi,
        // biasa + dodo selalu sama persis dengan total yang selama ini dipakai.
        $dodo = \App\Services\LemburDodo::menitPerOrang($from, $to);

        $baris = $users->map(function ($u) use ($agg, $sisaCuti, $svc, $dodo) {
            $a = $agg[$u->id] ?? null;
            $lemburTotal = (int) ($a->lembur_menit ?? 0);
            $dodoMenit   = min((int) ($dodo[$u->id] ?? 0), $lemburTotal);
            $biasaMenit  = $lemburTotal - $dodoMenit;

            return [
                'nama'         => $u->nama,
                'role'         => $u->role ?? 'Karyawan',
                'cabang'       => $u->cabang ?? '—',
                'hadir'        => (int) ($a->hadir ?? 0),
                'telat_hari'   => (int) ($a->telat_hari ?? 0),
                'telat_menit'  => (int) ($a->telat_menit ?? 0),
                'lembur_menit' => $lemburTotal,
                'lembur_jam'   => round($lemburTotal / 60, 1),          // total, buat rujukan
                'dodo_menit'   => $dodoMenit,
                'dodo_jam'     => round($dodoMenit / 60, 1),
                'biasa_menit'  => $biasaMenit,
                'biasa_jam'    => round($biasaMenit / 60, 1),
                'alpa'         => (int) ($a->alpa ?? 0),
                'cuti'         => (int) ($a->cuti ?? 0),
                'izin'         => (int) ($a->izin ?? 0),
                'sakit'        => (int) ($a->sakit ?? 0),
                'libur'        => (int) ($a->libur ?? 0),
                'wfh'          => (int) ($a->wfh ?? 0),
                'sisa_cuti'    => isset($sisaCuti[$u->id]) ? $svc->sisa($u->id) : null,
            ];
        })->values();

        $sum = [
            'orang'        => $baris->count(),
            'hadir'        => $baris->sum('hadir'),
            'telat_menit'  => $baris->sum('telat_menit'),
            'lembur_jam'   => round($baris->sum('lembur_menit') / 60, 1),
            'biasa_jam'    => round($baris->sum('biasa_menit') / 60, 1),
            'dodo_jam'     => round($baris->sum('dodo_menit') / 60, 1),
            'alpa'         => $baris->sum('alpa'),
        ];

        // Chip yang kemungkinan salah pilih → salah tarif. Ditampilkan di sini
        // karena inilah layar terakhir sebelum gaji dihitung.
        $curiga = \App\Services\LemburDodo::chipMencurigakan($from, $to);

        // Status kunci periode: kalau ada periode closed melingkupi bulan ini.
        $terkunci = DB::table('att_period')->where('status', 'closed')
            ->where('periode_from', '<=', $to)->where('periode_to', '>=', $from)
            ->when($branch, fn ($q) => $q->where(fn ($w) => $w->whereNull('raw_branch_id')->orWhere('raw_branch_id', $branch)))
            ->exists();

        return [$baris, $sum, $terkunci, $from, $to, $curiga];
    }

    public function index(Request $request)
    {
        $this->guard();
        $anchor = $request->filled('m')
            ? Carbon::createFromFormat('Y-m', $request->input('m'))->startOfMonth()
            : Carbon::now()->startOfMonth();
        $branch = $request->input('branch');

        [$baris, $sum, $terkunci, , , $curiga] = $this->rekap($anchor, $branch);
        $branches = DB::table('raw_branch')->orderBy('name')->get(['id', 'name']);

        return view('absensi.lembar_gaji', compact('baris', 'sum', 'terkunci', 'anchor', 'branch', 'branches', 'curiga'));
    }

    /** Ekspor CSV bulan terpilih — dibuka Finance di Excel. */
    public function export(Request $request)
    {
        $this->guard();
        $anchor = $request->filled('m')
            ? Carbon::createFromFormat('Y-m', $request->input('m'))->startOfMonth()
            : Carbon::now()->startOfMonth();
        $branch = $request->input('branch');

        [$baris] = $this->rekap($anchor, $branch);
        $nama = 'lembar-gaji-' . $anchor->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($baris) {
            $out = fopen('php://output', 'w');
            fprintf($out, "\xEF\xBB\xBF"); // BOM biar Excel baca UTF-8
            // ⚠️ Kolom 'Lembur (jam)' SENGAJA DIGANTI NAMA, bukan disisipi kolom
            // baru di sebelahnya. Sejak 2026-08-22 isinya bukan lagi total: DODO
            // (lembur hari libur, tarifnya beda) dipisah ke kolomnya sendiri.
            // Kalau namanya dibiarkan sama, spreadsheet Finance yang lama akan
            // diam-diam memakai angka yang mengecil tanpa satu pun tanda.
            // Nama baru = perubahannya berisik, langsung ketahuan.
            // Keduanya SALING LEPAS: Lembur Biasa + DODO = seluruh lembur di-ACC.
            fputcsv($out, ['Nama', 'Role', 'Cabang', 'Hari Hadir', 'Hari Telat',
                'Total Telat (menit)', 'Lembur Biasa (jam)', 'DODO (jam)',
                'Alpa', 'Cuti', 'Izin', 'Sakit',
                'Libur', 'WFH', 'Sisa Cuti (hari)']);
            foreach ($baris as $b) {
                fputcsv($out, [
                    $b['nama'], $b['role'], $b['cabang'], $b['hadir'], $b['telat_hari'],
                    $b['telat_menit'], $b['biasa_jam'], $b['dodo_jam'], $b['alpa'], $b['cuti'],
                    $b['izin'], $b['sakit'], $b['libur'], $b['wfh'],
                    $b['sisa_cuti'] === null ? '-' : $b['sisa_cuti'],
                ]);
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
