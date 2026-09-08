<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawCusReservation;
use App\Models\RawDoctor;
use App\Models\RawDoctorSchedule;
use App\Models\RawDoctorScheduleException;
use App\Models\RawBranch;

class DoctorController extends Controller
{
    public function index()
    {
        // README: SEMUA user lihat SEMUA dokter (tidak difilter per cabang/akun).
        // 31 dokter → muat 1 halaman, jadi tanpa pagination: derive spesialisasi
        // (dari nama) + hitung tile/segment/filter in-memory.
        $filterSearch = trim((string) request('search'));
        $filterSpec   = request('spec');    // umum/ortho/prostho/bedah/konservasi
        $filterStatus = request('status');  // aktif/nonaktif
        $seg = in_array(request('seg'), ['aktif', 'nonaktif', 'spesialis'], true) ? request('seg') : 'semua';

        $all = RawDoctor::withCount(['schedules', 'reservations'])->orderBy('name')->get();
        foreach ($all as $d) {
            $spec = \App\Support\DoctorSpec::forName($d->name);
            $d->spec_key   = $spec['key'];
            $d->spec_label = $spec['label'];
            $d->spec_color = $spec['color'];
            $d->spec_bg    = $spec['bg'];
        }

        // ── Insight tiles (clinic-wide) ──
        $mon = now()->startOfWeek()->toDateString();
        $sun = now()->endOfWeek()->toDateString();
        $cutiMingguIni = \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('start_date', '<=', $sun)->where('end_date', '>=', $mon)
            ->distinct()->count('raw_doctor_id');
        $tiles = [
            'total'     => $all->count(),
            'aktif'     => $all->where('is_active', true)->count(),
            'cuti'      => $cutiMingguIni,
            'reservasi' => (int) $all->sum('reservations_count'),
        ];

        // ── Filter (search + spesialisasi + status), lalu hitung segment di atas base ter-filter ──
        $base = $all->filter(function ($d) use ($filterSearch, $filterSpec, $filterStatus) {
            if ($filterSearch !== '' && stripos($d->name, $filterSearch) === false) return false;
            if ($filterSpec && $d->spec_key !== $filterSpec) return false;
            if ($filterStatus === 'aktif' && !$d->is_active) return false;
            if ($filterStatus === 'nonaktif' && $d->is_active) return false;
            return true;
        })->values();

        $segCounts = [
            'semua'     => $base->count(),
            'aktif'     => $base->where('is_active', true)->count(),
            'nonaktif'  => $base->where('is_active', false)->count(),
            'spesialis' => $base->filter(fn ($d) => $d->spec_key !== 'umum')->count(),
        ];

        $doctors = $base->filter(function ($d) use ($seg) {
            if ($seg === 'aktif')     return (bool) $d->is_active;
            if ($seg === 'nonaktif')  return !$d->is_active;
            if ($seg === 'spesialis') return $d->spec_key !== 'umum';
            return true;
        })->values();

        $specOptions = [
            'umum'       => 'Dokter Gigi Umum',
            'ortho'      => 'Orthodonti',
            'prostho'    => 'Prosthodonti',
            'bedah'      => 'Bedah Mulut',
            'konservasi' => 'Konservasi Gigi',
        ];

        // Mode tampilan: 'tabel' (default) atau 'jadwal' (master "Jadwal Semua").
        // Server-side & ter-persist lewat query param (?view=jadwal) — nav minggu/
        // cabang/drill juga via URL, jadi ganti segment/filter tak balik ke tabel.
        $view = request('view') === 'jadwal' ? 'jadwal' : 'tabel';
        $master = $view === 'jadwal' ? $this->buildMaster($all) : null;

        return view('crud.doctor.list', compact(
            'doctors', 'tiles', 'segCounts', 'seg',
            'filterSearch', 'filterSpec', 'filterStatus', 'specOptions',
            'view', 'master'
        ));
    }

    /**
     * Data untuk master "Jadwal Semua" (matriks dokter × hari, per cabang +
     * per minggu). $all = koleksi RawDoctor yang SUDAH di-tempel spec_*.
     * C1: kontrol (cabang/minggu) + skeleton baris dokter. Sel/blok diisi C2.
     */
    private function buildMaster($all): array
    {
        $sessionBranch = session('raw_branch_id');

        // Cabang yang boleh dilihat (Sales → cabangnya saja, admin → semua).
        $branches = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->orderBy('name')->get()
            : RawBranch::orderBy('name')->get();

        // Warna dot cabang (stabil by urutan; ikut handoff: Sunter coklat, Cinere teal).
        $dotPalette = ['#6B2400', '#2E7D74', '#B5701A', '#6A53C9', '#C0392B', '#3C5FA8'];
        $dotMap = [];
        foreach ($branches as $i => $b) {
            $dotMap[$b->id] = $dotPalette[$i % count($dotPalette)];
        }

        // Cabang terpilih.
        $mbranch = request('mbranch');
        if (!$branches->firstWhere('id', $mbranch)) {
            $mbranch = optional($branches->first())->id;
        }
        $branchObj = $branches->firstWhere('id', $mbranch);

        // Minggu (Senin ISO). mweek tak valid → minggu ini.
        try {
            $weekStart = request('mweek')
                ? \Carbon\Carbon::parse(request('mweek'))->startOfWeek()
                : now()->startOfWeek();
        } catch (\Exception $e) {
            $weekStart = now()->startOfWeek();
        }
        $weekStart = $weekStart->startOfDay();
        $weekEnd   = (clone $weekStart)->addDays(6);

        // Drill ke timeline harian: mday = hari ISO (1-7), null = mode matriks minggu.
        $mday    = in_array((int) request('mday'), [1, 2, 3, 4, 5, 6, 7], true) ? (int) request('mday') : null;
        $dayMode = $mday !== null;

        // Label rentang minggu (mis. "24 – 30 Jun 2026" / "30 Jun – 6 Jul 2026").
        $cMonths = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        if ($weekStart->month === $weekEnd->month) {
            $weekLabel = $weekStart->day . ' – ' . $weekEnd->day . ' ' . $cMonths[$weekEnd->month - 1] . ' ' . $weekEnd->year;
        } elseif ($weekStart->year === $weekEnd->year) {
            $weekLabel = $weekStart->day . ' ' . $cMonths[$weekStart->month - 1] . ' – ' . $weekEnd->day . ' ' . $cMonths[$weekEnd->month - 1] . ' ' . $weekEnd->year;
        } else {
            $weekLabel = $weekStart->day . ' ' . $cMonths[$weekStart->month - 1] . ' ' . $weekStart->year . ' – ' . $weekEnd->day . ' ' . $cMonths[$weekEnd->month - 1] . ' ' . $weekEnd->year;
        }

        // 7 hari (Senin–Minggu).
        $dayShort = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = (clone $weekStart)->addDays($i);
            $days[] = [
                'iso'     => $i + 1,
                'date'    => $d->toDateString(),
                'short'   => $dayShort[$i + 1],
                'num'     => $d->day,
                'isToday' => $d->isSameDay(now()),
            ];
        }

        // Baris = dokter yang punya jadwal di cabang terpilih (urut nama).
        $schedDoctorIds = RawDoctorSchedule::where('raw_branch_id', $mbranch)
            ->distinct()->pluck('raw_doctor_id')->toArray();
        $rows = $all->whereIn('id', $schedDoctorIds)->values();
        $rowIds = $rows->pluck('id')->all();

        // ── C2: isi sel (blok shift + utilisasi, cuti, jadwal khusus) ──
        $weekStartStr = $weekStart->toDateString();
        $weekEndStr   = $weekEnd->toDateString();

        $schedAll = RawDoctorSchedule::where('raw_branch_id', $mbranch)
            ->whereIn('raw_doctor_id', $rowIds)->get();
        $leaves = \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->whereIn('raw_doctor_id', $rowIds)
            ->where('start_date', '<=', $weekEndStr)
            ->where('end_date', '>=', $weekStartStr)
            ->get(['raw_doctor_id', 'start_date', 'end_date']);
        $excAll = RawDoctorScheduleException::where('raw_branch_id', $mbranch)
            ->whereIn('raw_doctor_id', $rowIds)
            ->whereBetween('date', [$weekStartStr, $weekEndStr])
            ->get();
        $resAll = \Illuminate\Support\Facades\DB::table('raw_cus_reservation')
            ->where('raw_branch_id', $mbranch)
            ->whereIn('raw_doctor_id', $rowIds)
            ->whereBetween('date', [$weekStartStr, $weekEndStr])
            ->get(['raw_doctor_id', 'date', 'start_hour']);

        $toDec = fn ($t) => $t ? ((int) substr($t, 0, 2) + ((int) substr($t, 3, 2)) / 60) : null;

        // Index untuk lookup cepat.
        $schedByDocDay = [];   // [docId][dayIso] => [schedule...]
        foreach ($schedAll as $s) { $schedByDocDay[$s->raw_doctor_id][(int) $s->day][] = $s; }
        $leaveByDoc = [];      // [docId] => [[start,end]...]
        foreach ($leaves as $l) { $leaveByDoc[$l->raw_doctor_id][] = [$l->start_date, $l->end_date]; }
        $excByDocDate = [];    // [docId][date] => [exc...]
        // NB: model meng-cast `date` ke Carbon → normalkan ke string 'Y-m-d' utk key.
        foreach ($excAll as $e) { $excByDocDate[$e->raw_doctor_id][\Carbon\Carbon::parse($e->date)->toDateString()][] = $e; }
        $resByDocDate = [];    // [docId][date] => [hourDec...]
        foreach ($resAll as $r) { $resByDocDate[$r->raw_doctor_id][$r->date][] = $toDec($r->start_hour); }

        // [docId][dayIso] => ['isCuti'=>bool, 'blocks'=>[...], 'khusus'=>[...]]
        $cells = [];
        foreach ($rows as $row) {
            foreach ($days as $d) {
                $iso = $d['iso']; $date = $d['date'];

                $isCuti = false;
                foreach (($leaveByDoc[$row->id] ?? []) as $lv) {
                    if ($date >= $lv[0] && $date <= $lv[1]) { $isCuti = true; break; }
                }

                $blocks = [];
                foreach (($schedByDocDay[$row->id][$iso] ?? []) as $s) {
                    $sd = $toDec($s->start_hour); $ed = $toDec($s->end_hour);
                    $cap = max(0, (int) round((($ed ?? 0) - ($sd ?? 0)) * 2));
                    $fill = 0;
                    foreach (($resByDocDate[$row->id][$date] ?? []) as $rh) {
                        if ($rh !== null && $sd !== null && $ed !== null && $rh >= $sd && $rh < $ed) $fill++;
                    }
                    $blocks[] = [
                        'time'  => substr($s->start_hour, 0, 5) . '–' . substr($s->end_hour, 0, 5),
                        'start' => substr($s->start_hour, 0, 5),
                        'end'   => substr($s->end_hour, 0, 5),
                        'fill'  => $fill,
                        'cap'   => $cap,
                        'pct'   => $cap > 0 ? min(100, (int) round($fill / $cap * 100)) : 0,
                    ];
                }

                $khusus = [];
                foreach (($excByDocDate[$row->id][$date] ?? []) as $e) {
                    $khusus[] = [
                        'time'  => substr($e->start_hour, 0, 5) . '–' . substr($e->end_hour, 0, 5),
                        'start' => substr($e->start_hour, 0, 5),
                        'end'   => substr($e->end_hour, 0, 5),
                        'type'  => $e->type,
                    ];
                }

                $cells[$row->id][$iso] = ['isCuti' => $isCuti, 'blocks' => $blocks, 'khusus' => $khusus];
            }
        }

        // ── C3: coverage per hari + banner gap (hari tanpa dokter) ──
        // Dokter "praktik" pada satu hari = punya shift & tidak cuti, ATAU ada
        // jadwal khusus (hari tambahan / jam berbeda) pada tanggal itu.
        // Hari yang TIDAK ADA jadwal sama sekali di pola mingguan cabang =
        // "libur" (mis. Senin) → bukan gap & badge netral. Otomatis: kalau nanti
        // ada yang isi jadwal di hari itu, ia jadi hari operasi & gap aktif lagi.
        $fullDay = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $openDayset = [];
        foreach ($schedAll as $s) { $openDayset[(int) $s->day] = true; }
        foreach ($days as $i => $d) {
            $cnt = 0;
            foreach ($rows as $row) {
                $c = $cells[$row->id][$d['iso']];
                $working = (count($c['blocks']) > 0 && !$c['isCuti']) || count($c['khusus']) > 0;
                if ($working) $cnt++;
            }
            $isOpen = !empty($openDayset[$d['iso']]);
            $days[$i]['count']  = $cnt;
            $days[$i]['status'] = !$isOpen ? 'libur' : ($cnt === 0 ? 'kosong' : 'ada');
        }
        // Gap = hari operasi tapi 0 dokter (cuti massal dll), bukan hari libur.
        $gapDays  = array_values(array_filter($days, fn ($d) => $d['status'] === 'kosong'));
        $gapShow  = $rows->count() > 0 && count($gapDays) > 0;
        $gapNames = array_map(fn ($d) => $fullDay[$d['iso']], $gapDays);
        $gapText  = count($gapDays) . ' hari tanpa dokter praktik di ' . (optional($branchObj)->name ?? '—')
            . ': ' . implode(', ', $gapNames) . '.';

        // ── C4: timeline harian (drill). Sumbu jam 08–21 (span 13 jam). ──
        $H0 = 8; $H1 = 21; $span = $H1 - $H0;
        $dayHourMarks = [];
        for ($h = $H0; $h <= 20; $h += 2) {
            $dayHourMarks[] = ['pct' => round(($h - $H0) / $span * 100, 3), 'label' => sprintf('%02d:00', $h)];
        }
        $dayLabel = ''; $dayRows = [];
        if ($dayMode) {
            $dd   = $days[$mday - 1];
            $dObj = \Carbon\Carbon::parse($dd['date']);
            $dayLabel = $fullDay[$mday] . ', ' . $dObj->day . ' ' . $cMonths[$dObj->month - 1] . ' ' . $dObj->year;
            $pos = function ($start, $end) use ($toDec, $H0, $span) {
                $sd = $toDec($start); $ed = $toDec($end);
                $left  = max(0, min(100, ($sd - $H0) / $span * 100));
                $width = max(2, min(100 - $left, ($ed - $sd) / $span * 100));
                return ['left' => round($left, 3), 'width' => round($width, 3)];
            };
            foreach ($rows as $row) {
                $c = $cells[$row->id][$mday];
                if (!count($c['blocks']) && !count($c['khusus']) && !$c['isCuti']) continue;
                $tb = [];
                foreach ($c['blocks'] as $blk) {
                    $tb[] = array_merge($pos($blk['start'], $blk['end']), [
                        'label' => $blk['time'] . ' · ' . $blk['fill'] . '/' . $blk['cap'],
                        'kind'  => 'shift', 'color' => $row->spec_color,
                    ]);
                }
                foreach ($c['khusus'] as $k) {
                    $tb[] = array_merge($pos($k['start'], $k['end']), [
                        'label' => $k['time'] . ' ★', 'kind' => 'khusus', 'color' => null,
                    ]);
                }
                $dayRows[] = ['row' => $row, 'isCuti' => $c['isCuti'], 'blocks' => $tb];
            }
        }
        $dayEmpty = $dayMode && count($dayRows) === 0;

        return [
            'cells'        => $cells,
            'dayMode'      => $dayMode,
            'mday'         => $mday,
            'dayLabel'     => $dayLabel,
            'dayRows'      => $dayRows,
            'dayHourMarks' => $dayHourMarks,
            'dayEmpty'     => $dayEmpty,
            'gapShow'      => $gapShow,
            'gapText'      => $gapText,
            'branches'   => $branches,
            'dotMap'     => $dotMap,
            'mbranch'    => $mbranch,
            'branchName' => optional($branchObj)->name ?? '—',
            'weekStart'  => $weekStart->toDateString(),
            'weekLabel'  => $weekLabel,
            'prevWeek'   => (clone $weekStart)->subDays(7)->toDateString(),
            'nextWeek'   => (clone $weekStart)->addDays(7)->toDateString(),
            'thisWeek'   => now()->startOfWeek()->toDateString(),
            'days'       => $days,
            'rows'       => $rows,
        ];
    }

    public function create()
    {
        // Tambah dokter = non-Sales saja (Admin/Admin General/Super Admin/
        // Admin Backup), konsisten dengan update()/deletePhoto(). Sales
        // read-only untuk profil dokter tapi tetap kelola jadwal/cuti/service.
        if (session('group_name') === 'Sales') {
            return redirect()->route('rawdoctor')
                ->with('error', 'Anda tidak memiliki akses untuk menambah dokter.');
        }

        return view('crud.doctor.new');
    }

    public function store()
    {
        if (session('group_name') === 'Sales') {
            return redirect()->route('rawdoctor')
                ->with('error', 'Anda tidak memiliki akses untuk menambah dokter.');
        }

        request()->validate([
            'name'  => 'required|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $doctor = RawDoctor::create([
            'name' => request('name'),
        ]);

        if (request()->hasFile('photo')) {
            $filename = $this->saveDoctorPhoto(request()->file('photo'), $doctor->id);
            $doctor->update(['photo' => $filename]);
        }

        return redirect()->route('rawdoctor')->with('success', 'Doctor berhasil ditambahkan');
    }

    private function saveDoctorPhoto($file, string $doctorId): string
    {
        $uploadDir = public_path('uploads/doctors');
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $filename  = $doctorId . '_' . time() . '.' . $extension;
        $file->move($uploadDir, $filename);

        return $filename;
    }

    /**
     * Pastikan user boleh mengelola data cabang $branchId. User yang terkunci
     * 1 cabang (Sales — session raw_branch_id terisi) HANYA boleh cabangnya
     * sendiri; admin pusat (raw_branch_id NULL) boleh semua cabang.
     */
    private function assertBranchAllowed($branchId): void
    {
        $sessionBranch = session('raw_branch_id');
        abort_if(
            $sessionBranch && (string) $branchId !== (string) $sessionBranch,
            403,
            'Anda hanya dapat mengelola jadwal/perawatan untuk cabang Anda sendiri.'
        );
    }

    public function edit($id)
    {
        $doctor = RawDoctor::findOrFail($id);
        $sessionBranch = session('raw_branch_id');
        $branches = $sessionBranch
            ? RawBranch::where('id', $sessionBranch)->get()
            : RawBranch::orderBy('name')->get();

        $schedules = RawDoctorSchedule::with('branch')
            ->where('raw_doctor_id', $id)
            ->orderBy('day')
            ->orderBy('start_hour')
            ->get();

            //new
        $allServices     = \App\Models\RawDocService::with(['allDetails'])->orderBy('name')->get();
        // Capability (perawatan yang dilayani dokter) — UNIVERSAL, bukan per-cabang.
        $doctorServiceIds = \App\Models\RawDoctorService::where('raw_doctor_id', $id)
            ->pluck('raw_doc_service_id')->toArray();

        // Cabang tempat dokter ini punya jadwal (target editing durasi per-cabang).
        // Sales yang terkunci 1 cabang → hanya cabang itu.
        $doctorBranchIds = RawDoctorSchedule::where('raw_doctor_id', $id)
            ->distinct()->pluck('raw_branch_id')->toArray();
        if ($sessionBranch) {
            $doctorBranchIds = array_values(array_intersect($doctorBranchIds, [$sessionBranch]));
        }
        $doctorBranches = RawBranch::whereIn('id', $doctorBranchIds)->orderBy('name')->get();

        // Durasi override PER-CABANG: [branchId][serviceId] => durasi.
        $branchServiceDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')
            ->where('raw_doctor_id', $id)
            ->get()
            ->groupBy('raw_branch_id')
            ->map(fn($rows) => $rows->pluck('duration_minutes', 'raw_doc_service_id')->toArray())
            ->toArray();

        // Durasi override sub-detail PER-CABANG: [branchId][detailId] => durasi.
        $branchDetailDurations = \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')
            ->where('raw_doctor_id', $id)
            ->get()
            ->groupBy('raw_branch_id')
            ->map(fn($rows) => $rows->pluck('duration_minutes', 'raw_doc_service_detail_id')->toArray())
            ->toArray();

        $leaves = \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $id)
            ->orderByDesc('start_date')
            ->get();

        // Scope ke cabang sesi (Sales) — admin pusat (NULL) lihat semua.
        $exceptions = RawDoctorScheduleException::with('branch')
            ->where('raw_doctor_id', $id)
            ->when($sessionBranch, fn ($q) => $q->where('raw_branch_id', $sessionBranch))
            ->orderByDesc('date')
            ->get();

        // Tanggal reservasi mendatang — utk peringatan bentrok saat isi rentang cuti.
        $futureReservationDates = $doctor->reservations()
            ->whereDate('date', '>=', now()->toDateString())
            ->pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())
            ->unique()->values()->all();

        // ── REDESIGN DETAIL: data header + tab Informasi ──
        $spec = \App\Support\DoctorSpec::forName($doctor->name);
        $reservationCount = $doctor->reservations()->count();
        // Sesi/shift per minggu. Sales (terkunci cabang) → hanya cabangnya,
        // biar match dengan grid Jadwal yang juga di-scope. Admin → semua cabang.
        $jamKerja = $sessionBranch
            ? $schedules->where('raw_branch_id', $sessionBranch)->count()
            : $schedules->count();

        // ── Utilisasi per shift (terisi/kapasitas) — reservasi NYATA minggu ini.
        //    Kapasitas = (jam_selesai − jam_mulai) × 2 (slot 30 menit, per handoff).
        //    "terisi" = reservasi dokter ini di cabang+hari+jam shift, dalam minggu berjalan
        //    (detail tab tak punya navigasi minggu → konteks = minggu ini). ──
        $weekMon = now()->startOfWeek()->toDateString();
        $weekSun = now()->endOfWeek()->toDateString();
        $weekRes = \Illuminate\Support\Facades\DB::table('raw_cus_reservation')
            ->where('raw_doctor_id', $id)
            ->whereBetween('date', [$weekMon, $weekSun])
            ->get(['raw_branch_id', 'date', 'start_hour']);
        $toDec = fn ($t) => $t ? ((int) substr($t, 0, 2) + ((int) substr($t, 3, 2)) / 60) : null;
        $shiftFill = [];
        foreach ($schedules as $s) {
            $sd = $toDec($s->start_hour); $ed = $toDec($s->end_hour);
            $fill = 0;
            foreach ($weekRes as $r) {
                if ($r->raw_branch_id !== $s->raw_branch_id) continue;
                if (\Carbon\Carbon::parse($r->date)->dayOfWeekIso !== (int) $s->day) continue;
                $rh = $toDec($r->start_hour);
                if ($rh !== null && $rh >= $sd && $rh < $ed) $fill++;
            }
            $shiftFill[$s->id] = $fill;
        }

        // Cabang tempat dokter praktik (SEMUA, tak difilter sesi — utk display header/info).
        $practiceBranchIds = RawDoctorSchedule::where('raw_doctor_id', $id)
            ->distinct()->pluck('raw_branch_id')->toArray();
        $practiceBranches = RawBranch::whereIn('id', $practiceBranchIds)->orderBy('name')->get();

        // Tab aktif awal (post-redirect dari simpan child form).
        $activeTab = in_array(request('tab'), ['informasi', 'jadwal', 'cuti', 'perawatan'], true)
            ? request('tab') : 'informasi';

        return view('crud.doctor.edit', compact(
            'doctor', 'schedules', 'branches',
            'allServices', 'doctorServiceIds',
            'doctorBranches', 'branchServiceDurations', 'branchDetailDurations',
            'leaves', 'exceptions',
            'spec', 'reservationCount', 'jamKerja', 'practiceBranches', 'activeTab', 'shiftFill',
            'futureReservationDates'
        ));
    }

    // === Doctor Leave (Cuti) ===

    public function leaveStore($doctorId)
    {
        request()->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|max:255',
        ]);

        \Illuminate\Support\Facades\DB::table('raw_doctor_leave')->insert([
            'id'            => \Illuminate\Support\Str::uuid()->toString(),
            'raw_doctor_id' => $doctorId,
            'start_date'    => request('start_date'),
            'end_date'      => request('end_date'),
            'reason'        => request('reason'),
            'created_by'    => session('id'),
            'created_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'cuti'])->with('success', 'Cuti berhasil ditambahkan');
    }

    public function leaveDestroy($doctorId, $leaveId)
    {
        \Illuminate\Support\Facades\DB::table('raw_doctor_leave')
            ->where('raw_doctor_id', $doctorId)
            ->where('id', $leaveId)
            ->delete();

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'cuti'])->with('success', 'Cuti berhasil dihapus');
    }

    public function servicesUpdate($doctorId)
    {
        $doctor = RawDoctor::findOrFail($doctorId);
        $userId = session('id');

        // ── 1. Capability (perawatan yang dilayani dokter) — UNIVERSAL ──
        // Tidak menyimpan durasi di sini lagi; durasi pindah ke per-cabang.
        $services = request('services', []);
        \App\Models\RawDoctorService::where('raw_doctor_id', $doctorId)->delete();
        foreach ($services as $serviceId) {
            \Illuminate\Support\Facades\DB::table('raw_doctor_service')->insert([
                'id'                => \Illuminate\Support\Str::uuid()->toString(),
                'raw_doctor_id'     => $doctorId,
                'raw_doc_service_id'=> $serviceId,
                'duration_minutes'  => null, // legacy column — durasi kini per-cabang
                'created_by'        => $userId,
                'created_at'        => now(),
            ]);
        }

        // ── 2. Durasi override PER-CABANG ──────────────────────────────
        // Format input: services_duration[$branchId][$serviceId] = '10' / ''
        //               details_duration[$branchId][$detailId]   = '15' / ''
        // Delete-then-insert HANYA untuk cabang yang dikirim di form (jadi
        // Sales yang terkunci 1 cabang tidak menghapus durasi cabang lain).
        if (config('features.flexible_scheduling')) {
            $allowedDetailIds = \App\Models\RawDocServiceDetail::whereIn('raw_doc_service_id', $services)
                ->pluck('id')->all();

            $svcDurByBranch    = request('services_duration', []);
            $detailDurByBranch = request('details_duration', []);
            $branchIds = array_unique(array_merge(
                array_keys($svcDurByBranch), array_keys($detailDurByBranch)
            ));

            // Guard: user yang terkunci 1 cabang (Sales) HANYA boleh menulis
            // durasi cabangnya sendiri — walau POST menyelipkan cabang lain.
            // Admin pusat (raw_branch_id NULL) tetap boleh semua cabang.
            $sessionBranch = session('raw_branch_id');
            if ($sessionBranch) {
                $branchIds = array_values(array_intersect($branchIds, [$sessionBranch]));
            }

            foreach ($branchIds as $branchId) {
                // service-level
                \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->delete();
                foreach (($svcDurByBranch[$branchId] ?? []) as $serviceId => $rawDur) {
                    if ($rawDur === null || $rawDur === '') {
                        continue; // kosong = pakai durasi default service
                    }
                    if (!in_array($serviceId, $services, true)) {
                        continue; // skip kalau service tidak dicentang
                    }
                    \Illuminate\Support\Facades\DB::table('raw_doctor_branch_service_duration')->insert([
                        'id'                 => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_doctor_id'      => $doctorId,
                        'raw_branch_id'      => $branchId,
                        'raw_doc_service_id' => $serviceId,
                        'duration_minutes'   => (float) $rawDur,
                        'created_by'         => $userId,
                        'created_at'         => now(),
                    ]);
                }

                // sub-detail
                \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')
                    ->where('raw_doctor_id', $doctorId)
                    ->where('raw_branch_id', $branchId)
                    ->delete();
                foreach (($detailDurByBranch[$branchId] ?? []) as $detailId => $rawDur) {
                    if ($rawDur === null || $rawDur === '') {
                        continue;
                    }
                    if (!in_array($detailId, $allowedDetailIds, true)) {
                        continue; // skip kalau parent service tidak dicentang
                    }
                    \Illuminate\Support\Facades\DB::table('raw_doctor_branch_detail_duration')->insert([
                        'id'                       => \Illuminate\Support\Str::uuid()->toString(),
                        'raw_doctor_id'            => $doctorId,
                        'raw_branch_id'            => $branchId,
                        'raw_doc_service_detail_id'=> $detailId,
                        'duration_minutes'         => (float) $rawDur,
                        'created_by'               => $userId,
                        'created_at'               => now(),
                    ]);
                }
            }
        }

        // Auto-save (AJAX): cukup balikin status + jumlah perawatan dilayani.
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'ok'     => true,
                'served' => count(request('services', [])),
            ]);
        }

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'perawatan'])
            ->with('success', 'Perawatan & durasi dokter berhasil diperbarui');
    }

    // === Doctor Schedule Exception (pengecualian jadwal per tanggal) ===

    public function exceptionStore($doctorId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        request()->validate([
            'raw_branch_id' => 'required',
            'date'          => 'required|date',
            'type'          => 'required|in:replace,add',
            'start_hour'    => 'required',
            'end_hour'      => 'required|after:start_hour',
            'reason'        => 'nullable|max:255',
        ]);

        $this->assertBranchAllowed(request('raw_branch_id'));

        RawDoctor::findOrFail($doctorId);

        RawDoctorScheduleException::create([
            'raw_doctor_id' => $doctorId,
            'raw_branch_id' => request('raw_branch_id'),
            'date'          => request('date'),
            'type'          => request('type'),
            'start_hour'    => request('start_hour'),
            'end_hour'      => request('end_hour'),
            'reason'        => request('reason'),
            'created_by'    => session('id'),
            'created_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'cuti'])
            ->with('success', 'Pengecualian jadwal berhasil ditambahkan');
    }

    public function exceptionUpdate($doctorId, $exceptionId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        request()->validate([
            'raw_branch_id' => 'required',
            'date'          => 'required|date',
            'type'          => 'required|in:replace,add',
            'start_hour'    => 'required',
            'end_hour'      => 'required|after:start_hour',
            'reason'        => 'nullable|max:255',
        ]);

        $exception = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
            ->findOrFail($exceptionId);

        // Guard: cabang asal pengecualian & cabang tujuan harus diizinkan.
        $this->assertBranchAllowed($exception->raw_branch_id);
        $this->assertBranchAllowed(request('raw_branch_id'));

        $exception->update([
            'raw_branch_id' => request('raw_branch_id'),
            'date'          => request('date'),
            'type'          => request('type'),
            'start_hour'    => request('start_hour'),
            'end_hour'      => request('end_hour'),
            'reason'        => request('reason'),
            'updated_by'    => session('id'),
            'updated_at'    => now(),
        ]);

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'cuti'])
            ->with('success', 'Pengecualian jadwal berhasil diubah');
    }

    public function exceptionDestroy($doctorId, $exceptionId)
    {
        abort_unless(config('features.flexible_scheduling'), 404);

        $exception = RawDoctorScheduleException::where('raw_doctor_id', $doctorId)
            ->findOrFail($exceptionId);

        $this->assertBranchAllowed($exception->raw_branch_id);

        $exception->delete();

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'cuti'])
            ->with('success', 'Pengecualian jadwal berhasil dihapus');
    }

    public function update($id)
    {
        // Edit profil dokter (nama/foto) = non-Sales saja. Form-nya memang
        // disembunyikan untuk Sales, tapi tetap ditolak server-side agar POST
        // langsung tidak bisa menembus (konsisten dengan deletePhoto()).
        if (session('group_name') === 'Sales') {
            return redirect()->route('rawdoctor.edit', $id)
                ->with('error', 'Anda tidak memiliki akses untuk mengubah profil dokter.');
        }

        request()->validate([
            'name'  => 'required|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $doctor = RawDoctor::findOrFail($id);
        $data   = ['name' => request('name')];

        if (request()->hasFile('photo')) {
            if ($doctor->photo) {
                $oldPath = public_path('uploads/doctors/' . $doctor->photo);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $data['photo'] = $this->saveDoctorPhoto(request()->file('photo'), $doctor->id);
        }

        $doctor->update($data);

        return redirect()->route('rawdoctor.edit', $id)->with('success', 'Doctor berhasil diubah');
    }

    public function deletePhoto($id)
    {
        if (session('group_name') === 'Sales') {
            return redirect()->route('rawdoctor.edit', $id)
                ->with('error', 'Anda tidak memiliki akses untuk menghapus foto dokter.');
        }

        $doctor = RawDoctor::findOrFail($id);

        if ($doctor->photo) {
            $path = public_path('uploads/doctors/' . $doctor->photo);
            if (is_file($path)) {
                @unlink($path);
            }
            $doctor->update(['photo' => null]);
        }

        return redirect()->route('rawdoctor.edit', $id)->with('success', 'Foto dokter berhasil dihapus');
    }

    public function destroy($id)
    {
        $doctor = RawDoctor::findOrFail($id);

        // Check if doctor has schedules or reservations
        if ($doctor->schedules()->count() > 0) {
            return redirect()->route('rawdoctor')->with('error', 'Data tidak dapat dihapus karena masih memiliki jadwal');
        }

        if ($doctor->reservations()->count() > 0) {
            return redirect()->route('rawdoctor')->with('error', 'Data tidak dapat dihapus karena masih memiliki reservasi');
        }

        $doctor->delete();
        return redirect()->route('rawdoctor')->with('success', 'Doctor berhasil dihapus');
    }

    /**
     * Toggle status aktif/nonaktif dokter.
     * Dokter nonaktif tidak muncul di dropdown form reservasi baru,
     * tapi reservasi lama yg sudah pakai dokter ini tetap valid.
     *
     * URL: PATCH /rawdoctor/{id}/toggle-active
     */
    public function toggleActive($id)
    {
        $doctor = RawDoctor::findOrFail($id);
        $doctor->update(['is_active' => ! $doctor->is_active]);

        $label = $doctor->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return redirect()->route('rawdoctor.edit', $id)
            ->with('success', "Dokter {$doctor->name} berhasil {$label}.");
    }

    // === Doctor Schedule (Child CRUD) ===

    public function scheduleStore($doctorId)
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'day' => 'required|numeric|min:1|max:7',
            'start_hour' => 'required',
            // Jam istirahat di bawah sudah dijaga 'after' sejak awal, jam PRAKTIK
            // tidak — dan itu terbukti meloloskan baris terbalik ke prod (Dharma,
            // Minggu, 22:00-20:00). Rentang seperti itu tak menghasilkan satu slot
            // pun, jadi gagalnya diam: pemesanan tampak wajar, tapi mesin absensi
            // tetap menganggap dokternya dijadwalkan hari itu.
            'end_hour' => 'required|after:start_hour',
            'breaks'   => 'nullable|array',
            'breaks.*.start_hour' => 'required_with:breaks.*.end_hour|date_format:H:i',
            'breaks.*.end_hour'   => 'required_with:breaks.*.start_hour|date_format:H:i|after:breaks.*.start_hour',
        ]);

        $this->assertBranchAllowed(request('raw_branch_id'));

        $doctor = RawDoctor::findOrFail($doctorId);

        $work = RawDoctorSchedule::create([
            'raw_doctor_id' => $doctor->id,
            'raw_branch_id' => request('raw_branch_id'),
            'day' => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour' => request('end_hour'),
            'type' => RawDoctorSchedule::TYPE_WORK,
            'created_by' => session('id'),
            'created_at' => now(),
        ]);

        $this->syncBreaks($work, request('breaks', []));

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'jadwal'])->with('success', 'Jadwal berhasil ditambahkan');
    }

    public function scheduleUpdate($doctorId, $scheduleId)
    {
        request()->validate([
            'raw_branch_id' => 'required',
            'day' => 'required|numeric|min:1|max:7',
            'start_hour' => 'required',
            // Jam istirahat di bawah sudah dijaga 'after' sejak awal, jam PRAKTIK
            // tidak — dan itu terbukti meloloskan baris terbalik ke prod (Dharma,
            // Minggu, 22:00-20:00). Rentang seperti itu tak menghasilkan satu slot
            // pun, jadi gagalnya diam: pemesanan tampak wajar, tapi mesin absensi
            // tetap menganggap dokternya dijadwalkan hari itu.
            'end_hour' => 'required|after:start_hour',
            'breaks'   => 'nullable|array',
            'breaks.*.start_hour' => 'required_with:breaks.*.end_hour|date_format:H:i',
            'breaks.*.end_hour'   => 'required_with:breaks.*.start_hour|date_format:H:i|after:breaks.*.start_hour',
        ]);

        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        // Guard: cabang asal jadwal & cabang tujuan dua-duanya harus diizinkan.
        $this->assertBranchAllowed($schedule->raw_branch_id);
        $this->assertBranchAllowed(request('raw_branch_id'));

        $schedule->update([
            'raw_branch_id' => request('raw_branch_id'),
            'day' => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour' => request('end_hour'),
            'type' => RawDoctorSchedule::TYPE_WORK,
            'updated_by' => session('id'),
            'updated_at' => now(),
        ]);

        $this->syncBreaks($schedule, request('breaks', []));

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'jadwal'])->with('success', 'Jadwal berhasil diubah');
    }

    /**
     * Sinkronisasi child istirahat untuk sebuah row jam kerja. Strategi
     * "replace": hapus semua child existing lalu insert ulang dari input.
     * Sederhana + aman (jumlah break umumnya <5 per shift), IDs dari
     * frontend hanya dipakai untuk consistency check (bisa di-ignore).
     * Validasi range di dalam work + warning kalau overlap reservasi.
     */
    private function syncBreaks(RawDoctorSchedule $work, array $breaks): void
    {
        // Hapus semua child existing dulu (idempotent replace).
        RawDoctorSchedule::where('parent_id', $work->id)
            ->where('type', RawDoctorSchedule::TYPE_BREAK)
            ->delete();

        if (empty($breaks)) return;

        $workStart = \Carbon\Carbon::parse($work->start_hour)->format('H:i:s');
        $workEnd   = \Carbon\Carbon::parse($work->end_hour)->format('H:i:s');
        $overlapTotal = 0;

        foreach ($breaks as $b) {
            $bs = trim($b['start_hour'] ?? '');
            $be = trim($b['end_hour'] ?? '');
            if ($bs === '' || $be === '') continue;

            $bsN = \Carbon\Carbon::parse($bs)->format('H:i:s');
            $beN = \Carbon\Carbon::parse($be)->format('H:i:s');

            // Silent skip kalau out-of-range (frontend sudah validasi, backend
            // guard tambahan; kalau lolos backend berarti data invalid dari
            // manual POST — jangan bocor tapi jangan hard-error juga).
            if ($bsN < $workStart || $beN > $workEnd || $bsN >= $beN) continue;

            RawDoctorSchedule::create([
                'raw_doctor_id' => $work->raw_doctor_id,
                'raw_branch_id' => $work->raw_branch_id,
                'day'           => $work->day,
                'start_hour'    => $bsN,
                'end_hour'      => $beN,
                'type'          => RawDoctorSchedule::TYPE_BREAK,
                'parent_id'     => $work->id,
                'created_by'    => session('id'),
                'created_at'    => now(),
            ]);

            $overlapTotal += $this->countReservationOverlap(
                $work->raw_doctor_id, $work->raw_branch_id, (int) $work->day, $bsN, $beN
            );
        }

        if ($overlapTotal > 0) {
            session()->flash('warning', "Ada {$overlapTotal} reservasi existing yang jam-nya overlap dengan jam istirahat. Mohon review manual.");
        }
    }

    /**
     * Hitung reservasi future yang jam-nya overlap dengan window istirahat
     * pada hari kerja (day) itu. Dipakai buat generate warning message.
     */
    private function countReservationOverlap($doctorId, $branchId, int $day, string $bs, string $be): int
    {
        // ISO day 1..7 (Mon..Sun) → MySQL DAYOFWEEK 1..7 (Sun..Sat).
        $mysqlDow = $day === 7 ? 1 : $day + 1;

        return RawCusReservation::where('raw_doctor_id', $doctorId)
            ->where('raw_branch_id', $branchId)
            ->whereNotNull('start_hour')
            ->whereNotNull('end_hour')
            ->where('start_hour', '<', $be)
            ->where('end_hour', '>', $bs)
            ->whereRaw('DAYOFWEEK(date) = ?', [$mysqlDow])
            ->where('date', '>=', now()->toDateString())
            ->where(function ($q) {
                $q->where('status', '!=', RawCusReservation::STATUS_CANCELLED)
                  ->orWhereNull('status');
            })
            ->count();
    }

    public function scheduleDestroy($doctorId, $scheduleId)
    {
        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        $this->assertBranchAllowed($schedule->raw_branch_id);

        // Cascade: kalau parent work row dihapus, semua child istirahat ikut
        // hilang (tidak boleh jadi orphan).
        if (($schedule->type ?? RawDoctorSchedule::TYPE_WORK) === RawDoctorSchedule::TYPE_WORK) {
            RawDoctorSchedule::where('parent_id', $schedule->id)
                ->where('type', RawDoctorSchedule::TYPE_BREAK)
                ->delete();
        }

        $schedule->delete();

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'jadwal'])->with('success', 'Jadwal berhasil dihapus');
    }

    public function schedulesJson($doctorId)
    {
        // FullCalendar payload — hanya row jam kerja (work). Break rows di-load
        // dari relasi child (bukan sebagai event mandiri di FullCalendar) supaya
        // event feed tidak dobel dgn overlay istirahat.
        $schedules = RawDoctorSchedule::with('branch')
            ->where('raw_doctor_id', $doctorId)
            ->where(function ($q) { $q->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)->orWhereNull('type'); })
            ->get();

        $dayNames = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];
        $branchColors = ['#42a5f5', '#66bb6a', '#ffa726', '#ab47bc', '#ef5350', '#26c6da'];

        $branchColorMap = [];
        $colorIdx = 0;

        $events = $schedules->map(function ($s) use ($dayNames, $branchColors, &$branchColorMap, &$colorIdx) {
            if (!isset($branchColorMap[$s->raw_branch_id])) {
                $branchColorMap[$s->raw_branch_id] = $branchColors[$colorIdx % count($branchColors)];
                $colorIdx++;
            }

            return [
                'id'        => $s->id,
                'title'     => ($s->branch->name ?? '-') . "\n" . substr($s->start_hour, 0, 5) . '-' . substr($s->end_hour, 0, 5),
                'daysOfWeek'=> [$s->day % 7], // FullCalendar: 0=Sun, 1=Mon. DB: 1=Mon, 7=Sun
                'startTime' => substr($s->start_hour, 0, 5),
                'endTime'   => substr($s->end_hour, 0, 5),
                'color'     => $branchColorMap[$s->raw_branch_id],
                'extendedProps' => [
                    'branch_id'   => $s->raw_branch_id,
                    'branch_name' => $s->branch->name ?? '-',
                    'day'         => $s->day,
                    'day_name'    => $dayNames[$s->day] ?? '-',
                ],
            ];
        });

        return response()->json($events);
    }

    public function scheduleUpdateAjax($doctorId, $scheduleId)
    {
        $schedule = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
            ->findOrFail($scheduleId);

        $this->assertBranchAllowed($schedule->raw_branch_id);

        // Drag/resize cuma ubah jam & hari — tipe (work/break) di-preserve
        // apa adanya. Tidak perlu validasi khusus antar-tipe di sini karena
        // work & break bisa overlap dalam kolom yang sama (memang begitu
        // by design: break diletakkan di atas work).
        $schedule->update([
            'day'        => request('day'),
            'start_hour' => request('start_hour'),
            'end_hour'   => request('end_hour'),
            'updated_by' => session('id'),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Salin jadwal: ANTAR HARI (hari sumber → beberapa hari tujuan, dalam 1
     * cabang) atau ANTAR CABANG (semua jadwal cabang sumber → cabang tujuan).
     * Lewati otomatis shift yang bentrok (overlap hari+cabang sama).
     */
    public function scheduleCopy($doctorId)
    {
        request()->validate(['mode' => 'required|in:days,branch']);
        RawDoctor::findOrFail($doctorId);
        $mode = request('mode');

        // Cek overlap terhadap kumpulan shift yang sudah ada (live, termasuk yg
        // baru disalin) — dibandingkan sebagai string jam 'HH:MM:SS'.
        // HANYA cek antar-sesama work.
        $overlaps = function ($existing, $day, $branch, $start, $end) {
            foreach ($existing as $e) {
                if (($e->type ?? RawDoctorSchedule::TYPE_WORK) === RawDoctorSchedule::TYPE_BREAK) continue;
                if ((int) $e->day === (int) $day && $e->raw_branch_id === $branch
                    && $start < $e->end_hour && $e->start_hour < $end) return true;
            }
            return false;
        };
        // Copy 1 work row + semua child istirahat-nya.
        $make = function ($doctorId, $branch, $day, $srcWork) {
            $newWork = RawDoctorSchedule::create([
                'raw_doctor_id' => $doctorId,
                'raw_branch_id' => $branch,
                'day'           => $day,
                'start_hour'    => $srcWork->start_hour,
                'end_hour'      => $srcWork->end_hour,
                'type'          => RawDoctorSchedule::TYPE_WORK,
                'created_by'    => session('id'),
                'created_at'    => now(),
            ]);
            $childBreaks = RawDoctorSchedule::where('parent_id', $srcWork->id)
                ->where('type', RawDoctorSchedule::TYPE_BREAK)
                ->get();
            foreach ($childBreaks as $cb) {
                RawDoctorSchedule::create([
                    'raw_doctor_id' => $doctorId,
                    'raw_branch_id' => $branch,
                    'day'           => $day,
                    'start_hour'    => $cb->start_hour,
                    'end_hour'      => $cb->end_hour,
                    'type'          => RawDoctorSchedule::TYPE_BREAK,
                    'parent_id'     => $newWork->id,
                    'created_by'    => session('id'),
                    'created_at'    => now(),
                ]);
            }
            return $newWork;
        };

        $added = 0;

        if ($mode === 'days') {
            request()->validate([
                'raw_branch_id' => 'required',
                'src_day'       => 'required|numeric|min:1|max:7',
                'days'          => 'nullable|array',
                'days.*'        => 'numeric|min:1|max:7',
            ]);
            $branch  = request('raw_branch_id');
            $this->assertBranchAllowed($branch);
            $srcDay  = (int) request('src_day');
            $targets = array_unique(array_map('intval', request('days', [])));

            // Cuma copy work rows (break ikut lewat parent).
            $srcShifts = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branch)->where('day', $srcDay)
                ->where(function ($q) { $q->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)->orWhereNull('type'); })
                ->get();
            $existing  = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $branch)->get();

            foreach ($targets as $day) {
                if ($day === $srcDay) continue;
                foreach ($srcShifts as $sh) {
                    if ($overlaps($existing, $day, $branch, $sh->start_hour, $sh->end_hour)) continue;
                    $existing->push($make($doctorId, $branch, $day, $sh));
                    $added++;
                }
            }
        } else { // branch
            request()->validate([
                'src_branch' => 'required',
                'to_branch'  => 'required|different:src_branch',
            ]);
            $src = request('src_branch');
            $to  = request('to_branch');
            $this->assertBranchAllowed($src);
            $this->assertBranchAllowed($to);

            $srcShifts = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $src)
                ->where(function ($q) { $q->where('type', '!=', RawDoctorSchedule::TYPE_BREAK)->orWhereNull('type'); })
                ->get();
            $existing  = RawDoctorSchedule::where('raw_doctor_id', $doctorId)
                ->where('raw_branch_id', $to)->get();

            foreach ($srcShifts as $sh) {
                if ($overlaps($existing, $sh->day, $to, $sh->start_hour, $sh->end_hour)) continue;
                $existing->push($make($doctorId, $to, $sh->day, $sh));
                $added++;
            }
        }

        $msg = $added > 0
            ? "{$added} jam kerja berhasil disalin."
            : 'Tidak ada jam kerja yang disalin (kosong atau semua bentrok dengan jadwal yang ada).';

        return redirect()->route('rawdoctor.edit', [$doctorId, 'tab' => 'jadwal'])
            ->with($added > 0 ? 'success' : 'error', $msg);
    }
}
