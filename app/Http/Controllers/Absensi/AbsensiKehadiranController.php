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
 * PENGELOMPOKAN (penting — lihat memory project-absensi-lintas-cabang §1 & §4):
 *   Satu hari kerja BISA lintas cabang. `att_day.raw_branch_id` cuma cabang scan
 *   PERTAMA — BUKAN pemilik hari itu. Jadi baris dikelompokkan pakai cabang ASAL
 *   (`users.raw_branch_id`), bukan tempat scan. Orang Sunter yang nutupin Kalideres
 *   tetap tampil di Sunter, dan harinya dikasih penanda "scan di cabang lain".
 *
 *   Grup "Kantor (ABG)" DITURUNKAN dari `att_work_rule.tipe = 'fleksibel'`
 *   (per-orang lewat att_user_rule), BUKAN daftar nama hardcode dan BUKAN dari
 *   jabatan/divisi. Konsekuensinya benar dengan sendirinya: Irma & Anisa yang
 *   office-tapi-operasional (baku + hak lembur) tetap di cabangnya, sementara
 *   orang kantor fleksibel berkumpul di ABG. Nambah orang ke rule fleksibel =
 *   otomatis pindah ke ABG, tanpa sentuh kode.
 *
 * Guard canSeeAllBranches. Segment(1)='absensi' → lolos AuthGuard via node parent.
 */
class AbsensiKehadiranController extends Controller
{
    private const ABG_KEY = 'abg';
    private const ABG_LABEL = 'Kantor (ABG)';
    private const NONE_KEY = 'none';
    private const NONE_LABEL = 'Belum ada cabang';

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

        // Ambil SEMUA att_day periode ini. Filter cabang dilakukan setelah grup
        // dihitung — karena grup datang dari cabang ASAL orang, bukan tempat scan.
        $days = DB::table('att_day')
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get(['user_id', 'raw_branch_id', 'work_date', 'status', 'telat_menit',
                   'single_scan', 'lembur_menit_disetujui', 'cabang_terlibat',
                   'scan_first', 'scan_last']);

        $branchName = RawBranch::pluck('name', 'id');

        // Foto selfie absen HP periode ini — dipetakan (user_id, work_date) → bukti,
        // buat ditampilkan saat sel kalender diklik. Rentang ikut dawn-pushback:
        // ambil s/d (to+1) 06:00 supaya scan dini hari besoknya kebawa hari 'to'.
        $buktiMap = [];
        $buktis = DB::table('att_hp_bukti')
            ->where('taken_at', '>=', $from->copy()->setTime(6, 0))
            ->where('taken_at', '<', $to->copy()->addDay()->setTime(6, 0))
            ->orderBy('taken_at')
            ->get(['id', 'user_id', 'jenis', 'taken_at']);
        foreach ($buktis as $b) {
            $mins = Carbon::parse($b->taken_at)->hour * 60 + Carbon::parse($b->taken_at)->minute;
            $wd = $mins < 360
                ? Carbon::parse($b->taken_at)->subDay()->toDateString()
                : Carbon::parse($b->taken_at)->toDateString();
            $buktiMap[$b->user_id][$wd][] = ['id' => $b->id, 'jenis' => $b->jenis];
        }

        // ROSTER absensi = orang yang punya aturan kerja aktif di periode ini.
        // Baris diambil dari roster, BUKAN dari att_day — supaya orang yang bulan
        // ini tak punya scan sama sekali TETAP tampil (barisnya "belum ada data").
        // Krusial buat orang kantor: tak nge-tap itu normal, bukan berarti tak kerja
        // (§3.3) — kalau baris ikut att_day, mereka hilang diam-diam dari rekap.
        $roster = DB::table('att_user_rule as ur')
            ->join('att_work_rule as wr', 'wr.id', '=', 'ur.rule_id')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->where('ur.effective_from', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('ur.effective_to')->orWhere('ur.effective_to', '>=', $from->toDateString());
            })
            ->get(['u.id', 'u.nama', 'u.raw_branch_id', 'wr.tipe'])
            ->keyBy('id');

        // jaring-pengaman: ada att_day tapi orangnya tak punya aturan (data lama)
        $orphan = $days->pluck('user_id')->unique()->diff($roster->keys());
        if ($orphan->isNotEmpty()) {
            foreach (DB::table('users')->whereIn('id', $orphan)->get(['id', 'nama', 'raw_branch_id']) as $o) {
                $o->tipe = 'baku';
                $roster->put($o->id, $o);
            }
        }

        $flex = $roster->where('tipe', 'fleksibel')->keys()->flip();

        // header hari (dow + tanggal + minggu)
        $head = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dow = $anchor->copy()->day($d)->dayOfWeek;
            $head[$d] = ['n' => $d, 'dow' => self::DOW[$dow], 'sun' => ($dow === 0)];
        }

        // semua baris dibuat dulu dari roster (termasuk yg nol scan bulan ini)
        $matrix = [];
        foreach ($roster as $u) {
            $nama = $u->nama ?? '—';
            $homeId = $u->raw_branch_id ?? null;
            $isFlex = isset($flex[$u->id]);

            if ($isFlex) {                                   // orang kantor fleksibel
                $key = self::ABG_KEY;   $label = self::ABG_LABEL;
            } elseif ($homeId && isset($branchName[$homeId])) {
                $key = $homeId;         $label = $branchName[$homeId];
            } else {
                $key = self::NONE_KEY;  $label = self::NONE_LABEL;
            }

            $matrix[$u->id] = [
                'id'      => $u->id,
                'nama'    => $nama,
                'initial' => mb_strtoupper(mb_substr(trim($nama), 0, 1)),
                'home_id' => $homeId,
                'key'     => $key,
                'label'   => $label,
                'flex'    => $isFlex,
                'cells'   => [],
                'h' => 0, 't' => 0, 'a' => 0,
            ];
        }

        foreach ($days as $d) {
            $uid = $d->user_id;
            if (! isset($matrix[$uid])) continue;

            // "scan di cabang lain": hari ini melibatkan cabang selain cabang ASAL.
            // Pakai cabang_terlibat (semua mesin hari itu), bukan raw_branch_id saja.
            $home = $matrix[$uid]['home_id'];
            $lain = [];
            if ($home) {
                $terlibat = json_decode($d->cabang_terlibat ?: '[]', true) ?: [];
                if (! $terlibat && $d->raw_branch_id) $terlibat = [$d->raw_branch_id];
                foreach ($terlibat as $bid) {
                    if ($bid !== $home && isset($branchName[$bid])) $lain[$branchName[$bid]] = true;
                }
            }

            $day = (int) Carbon::parse($d->work_date)->day;
            $matrix[$uid]['cells'][$day] = [
                'status' => $d->status,
                'telat'  => (int) $d->telat_menit,
                'single' => (int) $d->single_scan,
                'lembur' => (int) $d->lembur_menit_disetujui,
                'lain'   => array_keys($lain),
                'jam_in' => $d->scan_first ? substr($d->scan_first, 11, 5) : null,
                'jam_out'=> $d->scan_last && $d->scan_last !== $d->scan_first ? substr($d->scan_last, 11, 5) : null,
                'foto'   => $buktiMap[$uid][$d->work_date] ?? [],
            ];

            if ($d->status === 'hadir') $matrix[$uid]['h']++;
            elseif ($d->status === 'telat') $matrix[$uid]['t']++;
            elseif ($d->status === 'alpa') $matrix[$uid]['a']++;
        }

        // filter berdasarkan GRUP (cabang asal / ABG), bukan tempat scan
        if ($branchFilter) {
            $matrix = array_filter($matrix, fn ($r) => $r['key'] === $branchFilter);
        }

        // urut: klinik dulu (abjad), lalu Kantor (ABG), lalu yang belum bercabang
        $rank = fn ($k) => $k === self::NONE_KEY ? 2 : ($k === self::ABG_KEY ? 1 : 0);
        uasort($matrix, fn ($a, $b) =>
            [$rank($a['key']), $a['label'], $a['nama']] <=> [$rank($b['key']), $b['label'], $b['nama']]
        );

        // kelompokkan (pembatas per cabang)
        $groups = [];
        foreach ($matrix as $row) {
            $k = $row['key'];
            if (! isset($groups[$k])) $groups[$k] = ['label' => $row['label'], 'flex' => $row['flex'], 'rows' => []];
            $groups[$k]['rows'][] = $row;
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
        $abgKey = self::ABG_KEY;
        $abgLabel = self::ABG_LABEL;

        return view('absensi.kehadiran', compact(
            'groups', 'summary', 'daysInMonth', 'head', 'anchor', 'monthShort',
            'branches', 'branchFilter', 'statusMeta', 'abgKey', 'abgLabel'
        ));
    }
}
