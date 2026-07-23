<?php

namespace App\Http\Controllers\Absensi;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Absensi → Kehadiran" — matriks kehadiran karyawan × tanggal (DESKTOP, view-only).
 *
 * Baca att_day (hasil reconcile) satu periode. Baris = karyawan, kolom = tanggal,
 * sel = status harian (hadir/telat/cuti/wfh/libur/alpa) + penanda telat / scan tunggal.
 * Buat Finance lihat sekilas siapa yang bolong / sering telat sebelum tutup periode.
 *
 * Guard canSeeAllBranches (sama pola AbsensiRecapController). Segment(1) = 'absensi'
 * → lolos AuthGuard via grant node parent. Konteks: docs/rencana-rekonsiliasi-absensi.md.
 */
class AbsensiKehadiranController extends Controller
{
    /** status att_day → [huruf, kelas warna, label] */
    private const STATUS = [
        'hadir' => ['H', 'h', 'Hadir'],
        'telat' => ['T', 't', 'Telat'],
        'cuti'  => ['C', 'c', 'Cuti'],
        'wfh'   => ['W', 'w', 'Kerja dari Rumah'],
        'libur' => ['L', 'l', 'Libur'],
        'alpa'  => ['A', 'a', 'Tidak masuk'],
    ];

    private function guard(): void
    {
        if (! canSeeAllBranches()) {
            abort(403, 'Rekap kehadiran hanya untuk Finance / Admin.');
        }
    }

    public function index(Request $request)
    {
        $this->guard();

        $month = $request->input('m');
        $anchor = $month ? Carbon::parse($month . '-01') : Carbon::now()->startOfMonth();
        $from = $anchor->copy()->startOfMonth();
        $to = $anchor->copy()->endOfMonth();
        $daysInMonth = $anchor->daysInMonth;

        $branchFilter = $request->input('branch');

        // Ambil att_day satu periode
        $q = DB::table('att_day')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);
        if ($branchFilter) {
            $q->where('raw_branch_id', $branchFilter);
        }
        $days = $q->get(['user_id', 'raw_branch_id', 'work_date', 'status', 'telat_menit', 'single_scan', 'lembur_menit_disetujui']);

        // Resolusi nama + cabang home
        $userIds = $days->pluck('user_id')->unique()->values();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'nama', 'raw_branch_id'])->keyBy('id');
        $branchName = RawBranch::pluck('name', 'id');

        // Bangun matriks: user_id => ['nama','cabang','cells'[day]=>row, 'sum'[status]=>n, 'telat_menit'=>n]
        $matrix = [];
        foreach ($days as $d) {
            $uid = $d->user_id;
            if (! isset($matrix[$uid])) {
                $u = $users->get($uid);
                $matrix[$uid] = [
                    'nama'   => $u->nama ?? '—',
                    'cabang' => $branchName[$u->raw_branch_id ?? ''] ?? ($branchName[$d->raw_branch_id] ?? '—'),
                    'cells'  => [],
                    'sum'    => ['hadir' => 0, 'telat' => 0, 'cuti' => 0, 'wfh' => 0, 'libur' => 0, 'alpa' => 0],
                    'telat_menit' => 0,
                ];
            }
            $day = (int) Carbon::parse($d->work_date)->day;
            $matrix[$uid]['cells'][$day] = [
                'status'      => $d->status,
                'telat_menit' => (int) $d->telat_menit,
                'single'      => (int) $d->single_scan,
            ];
            if (isset($matrix[$uid]['sum'][$d->status])) {
                $matrix[$uid]['sum'][$d->status]++;
            }
            $matrix[$uid]['telat_menit'] += (int) $d->telat_menit;
        }

        // urut: nama cabang lalu nama orang
        uasort($matrix, fn ($a, $b) => [$a['cabang'], $a['nama']] <=> [$b['cabang'], $b['nama']]);

        // ringkasan atas
        $summary = [
            'karyawan' => count($matrix),
            'alpa'     => array_sum(array_column(array_column($matrix, 'sum'), 'alpa')),
            'telat'    => array_sum(array_column(array_column($matrix, 'sum'), 'telat')),
            'hadir'    => array_sum(array_column(array_column($matrix, 'sum'), 'hadir')),
        ];

        // hari akhir pekan (buat kolom redup) — 0=Min .. 6=Sab
        $weekend = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $dow = $anchor->copy()->day($i)->dayOfWeek;
            $weekend[$i] = ($dow === Carbon::SUNDAY);
        }

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $statusMeta = self::STATUS;

        return view('absensi.kehadiran', compact(
            'matrix', 'summary', 'daysInMonth', 'weekend', 'anchor',
            'branches', 'branchFilter', 'statusMeta'
        ));
    }
}
