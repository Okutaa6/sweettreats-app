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
 * Baca att_day (hasil reconcile) satu periode. Baris = karyawan (dikelompokkan per
 * cabang, ada pembatas), kolom = tanggal, sel = status harian sebagai kotak pastel
 * (hadir/telat/cuti/wfh/libur/alpa) + titik kecil bila ada lembur; sel tanpa data =
 * kotak putus-putus. Buat Finance lihat sekilas pola hadir/telat/bolong sebelum tutup.
 *
 * Tampilan mengikuti handoff_absensi (grid pastel, avatar, legend bawah).
 * Guard canSeeAllBranches. Segment(1)='absensi' → lolos AuthGuard via node parent.
 */
class AbsensiKehadiranController extends Controller
{
    /** status att_day → token warna (samakan dgn handoff: ATT{bg,c}) */
    private const STATUS = [
        'hadir' => ['label' => 'Hadir', 'bg' => '#E3F4E8', 'c' => '#2F8E55'],
        'telat' => ['label' => 'Telat', 'bg' => '#FBE7CF', 'c' => '#B5701A'],
        'alpa'  => ['label' => 'Alpa',  'bg' => '#FBE3DE', 'c' => '#C0392B'],
        'cuti'  => ['label' => 'Cuti',  'bg' => '#E4ECFB', 'c' => '#3C5FA8'],
        'izin'  => ['label' => 'Izin',  'bg' => '#F4E7D6', 'c' => '#9A6614'],
        'libur' => ['label' => 'Libur', 'bg' => '#F1E9DF', 'c' => '#8A6A52'],
        'wfh'   => ['label' => 'WFH',   'bg' => '#DFEDE9', 'c' => '#2E7D74'],
    ];

    /** singkatan hari: 0=Minggu .. 6=Sabtu */
    private const DOW = ['Mg', 'Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb'];

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

        $q = DB::table('att_day')->whereBetween('work_date', [$from->toDateString(), $to->toDateString()]);
        if ($branchFilter) {
            $q->where('raw_branch_id', $branchFilter);
        }
        $days = $q->get(['user_id', 'raw_branch_id', 'work_date', 'status', 'telat_menit', 'single_scan', 'lembur_menit_disetujui']);

        $userIds = $days->pluck('user_id')->unique()->values();
        $users = DB::table('users')->whereIn('id', $userIds)->get(['id', 'nama', 'raw_branch_id'])->keyBy('id');
        $branchName = RawBranch::pluck('name', 'id');

        // header hari (dow + tanggal + weekend)
        $head = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = $anchor->copy()->day($d)->dayOfWeek;
            $head[$d] = ['n' => $d, 'dow' => self::DOW[$dow], 'sun' => ($dow === 0)];
        }

        // bangun baris per user
        $matrix = [];
        foreach ($days as $d) {
            $uid = $d->user_id;
            if (! isset($matrix[$uid])) {
                $u = $users->get($uid);
                $nama = $u->nama ?? '—';
                $matrix[$uid] = [
                    'nama'    => $nama,
                    'initial' => mb_strtoupper(mb_substr(trim($nama), 0, 1)),
                    'cabang'  => $branchName[$u->raw_branch_id ?? ''] ?? ($branchName[$d->raw_branch_id] ?? '—'),
                    'cells'   => [],
                    'h' => 0, 't' => 0, 'a' => 0,
                ];
            }
            $day = (int) Carbon::parse($d->work_date)->day;
            $matrix[$uid]['cells'][$day] = [
                'status' => $d->status,
                'telat'  => (int) $d->telat_menit,
                'single' => (int) $d->single_scan,
                'lembur' => (int) $d->lembur_menit_disetujui,
            ];
            if ($d->status === 'hadir') $matrix[$uid]['h']++;
            elseif ($d->status === 'telat') $matrix[$uid]['t']++;
            elseif ($d->status === 'alpa') $matrix[$uid]['a']++;
        }

        // urut cabang lalu nama
        uasort($matrix, fn ($a, $b) => [$a['cabang'], $a['nama']] <=> [$b['cabang'], $b['nama']]);

        // kelompokkan per cabang (buat pembatas)
        $groups = [];
        foreach ($matrix as $row) {
            $cab = $row['cabang'];
            if (! isset($groups[$cab])) $groups[$cab] = ['cabang' => $cab, 'rows' => []];
            $groups[$cab]['rows'][] = $row;
        }
        $groups = array_values($groups);

        $summary = [
            'karyawan' => count($matrix),
            'hadir'    => array_sum(array_column($matrix, 'h')),
            'telat'    => array_sum(array_column($matrix, 't')),
            'alpa'     => array_sum(array_column($matrix, 'a')),
        ];

        $branches = RawBranch::orderBy('name')->get(['id', 'name']);
        $statusMeta = self::STATUS;
        $monthShort = $anchor->translatedFormat('M');

        return view('absensi.kehadiran', compact(
            'groups', 'summary', 'daysInMonth', 'head', 'anchor', 'monthShort',
            'branches', 'branchFilter', 'statusMeta'
        ));
    }
}
