<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\ChecklistAuditLog;
use App\Models\ChecklistDivision;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionItem;
use App\Models\ChecklistWeeklySubmission;
use App\Models\ChecklistWeeklySubmissionItem;
use App\Models\ChecklistCategory;
use App\Models\GroupPath;
use App\Models\RawBranch;
use App\Models\Users;
use App\Models\UserPosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modul Checklist Akreditasi — sisi Supervisor.
 *
 * Layar:
 *   - dashboard() : ringkasan KPI hari ini + aktivitas terbaru
 *   - reviewIndex() / reviewShow() / approve() / reject() / reopen() : review pengisian karyawan
 *   - audit() : log semua state transition (untuk audit akreditasi)
 *
 * Sisi karyawan (pengisian harian + history) ada di MyChecklistController.
 * Setup katalog (kategori & task) ada di ChecklistSetupController.
 *
 * Access control: semua method di-gate via isChecklistSupervisor(). Branch
 * scoping pakai resolveChecklistBranchId() — admin bisa dropdown, sales
 * locked ke session('raw_branch_id').
 */
class DigitalChecklistController extends Controller
{
    public $perPage = 15;

    private function guard(): void
    {
        if (! isChecklistSupervisor()) abort(403, 'Tidak punya akses ke modul Checklist Akreditasi.');
    }

    /* ─── Dashboard ────────────────────────────────────────────────────── */

    public function dashboard(Request $request)
    {
        $this->guard();

        $branchId = resolveChecklistBranchId($request->get('branch'));
        $today    = Carbon::today();
        $visible  = checklistVisibleDivisionIds();   // divisi yang boleh disupervisi user ini

        // Karyawan aktif yang punya position hari ini (basis denominator),
        // dibatasi ke divisi yang disupervisi user ini.
        $activeUserIdsQuery = UserPosition::query()->activeOn($today)
            ->whereIn('division_id', $visible);
        if ($branchId) $activeUserIdsQuery->where('raw_branch_id', $branchId);
        $activeUserIds = $activeUserIdsQuery->pluck('user_id')->unique()->values();
        $totalActiveEmployees = $activeUserIds->count();

        // Submission hari ini, di-scope ke set karyawan aktif.
        $submissionsToday = ChecklistSubmission::query()
            ->whereDate('submission_date', $today)
            ->whereIn('user_id', $activeUserIds)
            ->get();

        $countByStatus = $submissionsToday->groupBy('status')->map->count();
        $kpis = [
            'total_active'  => $totalActiveEmployees,
            'submitted'     => ($countByStatus[ChecklistSubmission::STATUS_SUBMITTED] ?? 0)
                              + ($countByStatus[ChecklistSubmission::STATUS_APPROVED] ?? 0)
                              + ($countByStatus[ChecklistSubmission::STATUS_REJECTED] ?? 0),
            'pending'       => $countByStatus[ChecklistSubmission::STATUS_SUBMITTED] ?? 0,
            'approved'      => $countByStatus[ChecklistSubmission::STATUS_APPROVED] ?? 0,
            'rejected'      => $countByStatus[ChecklistSubmission::STATUS_REJECTED] ?? 0,
            // Excused (dikecualikan) TIDAK dihitung sebagai "belum diisi": baris
            // excused sudah ikut $submissionsToday->count() dan bukan draft, jadi
            // otomatis ter-exclude dari not_submitted di bawah.
            'excused'       => $countByStatus[ChecklistSubmission::STATUS_EXCUSED] ?? 0,
            'not_submitted' => max(0, $totalActiveEmployees - $submissionsToday->count()
                              + ($countByStatus[ChecklistSubmission::STATUS_DRAFT] ?? 0)),
        ];

        // Progres per divisi: berapa karyawan tiap divisi sudah submit hari ini.
        // Hanya divisi yang disupervisi user ini yang ditampilkan.
        $divisions = ChecklistDivision::query()->where('is_active', 1)
            ->whereIn('id', $visible)->orderBy('sort_order')->get();
        $perDivision = $divisions->map(function ($div) use ($activeUserIds, $branchId, $today) {
            $userIds = UserPosition::query()->activeOn($today)
                ->where('division_id', $div->id)
                ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId))
                ->pluck('user_id')->unique();

            $total = $userIds->count();
            $subToday = ChecklistSubmission::query()
                ->whereDate('submission_date', $today)
                ->whereIn('user_id', $userIds)
                ->get(['status']);
            $submitted = $subToday->whereIn('status', [
                ChecklistSubmission::STATUS_SUBMITTED,
                ChecklistSubmission::STATUS_APPROVED,
                ChecklistSubmission::STATUS_REJECTED,
            ])->count();
            $excused = $subToday->where('status', ChecklistSubmission::STATUS_EXCUSED)->count();

            // Karyawan yang dikecualikan tidak masuk denominator (tidak perlu isi).
            $effectiveTotal = max(0, $total - $excused);

            return (object) [
                'division'   => $div,
                'total'      => $total,
                'submitted'  => $submitted,
                'excused'    => $excused,
                // 100% bila semua yang wajib sudah isi; kalau semua dikecualikan → tampil 100%.
                'pct'        => $effectiveTotal ? (int) round($submitted / $effectiveTotal * 100) : ($total ? 100 : 0),
            ];
        });

        // Aktivitas terbaru (10 submission terakhir, lintas status).
        // Eager-load items supaya satisfiedCount() (dicentang + dikecualikan) tak N+1.
        $recent = ChecklistSubmission::query()
            ->with(['user', 'division', 'branch', 'items'])
            ->whereIn('division_id', $visible)
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $branches = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.dashboard', compact(
            'kpis', 'perDivision', 'recent', 'branches', 'branchId', 'today'
        ));
    }

    /* ─── Export Excel (rekap checklist harian per divisi) ─────────────────
       Satu file .xlsx untuk 1 divisi + 1 bulan; tiap karyawan jadi 1 sheet
       (tab) sendiri. Grid ala form kertas "CEKLIS HARIAN": baris = tugas per
       seksi, kolom = tanggal 1..akhir bulan, sel = centang bila tugas dikerjakan.
       Semua karyawan yang posisinya aktif di divisi pada bulan tsb dapat tab —
       walau belum mengisi (grid kosong). */

    public function exportDaily(Request $request): StreamedResponse
    {
        $this->guard();

        $branchId    = resolveChecklistBranchId($request->get('branch'));
        $division    = $this->resolveExportDivision($request);
        $start       = $this->exportMonthStart($request);
        $end         = $start->copy()->endOfMonth();
        $daysInMonth = $start->daysInMonth;

        // Karyawan: posisi aktif (confirmed) di divisi ini yang overlap bulan tsb.
        $posUserIds = $this->exportPositionUserIds($division->id, $branchId, $start, $end);

        // Union dgn siapa pun yang sempat submit di divisi ini bulan tsb (jaga-jaga pindah divisi).
        $subUserIds = ChecklistSubmission::query()
            ->where('division_id', $division->id)
            ->whereBetween('submission_date', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->pluck('user_id');

        $userIds = $posUserIds->merge($subUserIds)->unique()->values();
        $users   = Users::query()->whereIn('id', $userIds)->orderBy('nama')->get();

        // Submissions bulan tsb, dikelompokkan per user.
        $subsByUser = ChecklistSubmission::query()
            ->whereIn('user_id', $userIds)
            ->where('division_id', $division->id)
            ->whereBetween('submission_date', [$start, $end])
            ->with('items')
            ->get()
            ->groupBy('user_id');

        // Baris tugas (union snapshot lintas karyawan/tanggal).
        $rows = $this->exportTaskRows($subsByUser, $division->id);

        // ── Bangun workbook ──
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);   // buang sheet default kosong

        if ($users->isEmpty()) {
            $info = $spreadsheet->createSheet();
            $info->setTitle('Info');
            $info->setCellValue('A1', 'Tidak ada karyawan aktif di divisi ' . $division->name
                . ' pada ' . $start->translatedFormat('F Y') . '.');
        }

        $usedTitles = [];
        foreach ($users as $u) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->uniqueSheetTitle($u->nama ?: 'Karyawan', $usedTitles));
            $this->fillEmployeeSheet(
                $sheet, $u, $division, $start, $daysInMonth, $rows, $subsByUser->get($u->id)
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'checklist_harian_' . $division->id . '_' . $start->format('Y-m') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Kumpulkan baris tugas unik (grid) dari snapshot item seluruh karyawan.
     * Kalau bulan itu tak ada data sama sekali → fallback ke katalog aktif
     * sekarang supaya grid tetap punya baris.
     */
    private function exportTaskRows($subsByUser, string $divisionId): array
    {
        $rows = [];
        foreach ($subsByUser as $subs) {
            foreach ($subs as $sub) {
                foreach ($sub->items as $it) {
                    $key = $it->task_id ?: ('t:' . $it->category_title_snapshot . '|' . $it->task_title_snapshot);
                    if (! isset($rows[$key])) {
                        $rows[$key] = (object) [
                            'key'      => $key,
                            'category' => $it->category_title_snapshot,
                            'catSort'  => (int) $it->category_sort_order,
                            'title'    => $it->task_title_snapshot,
                            'taskSort' => (int) $it->task_sort_order,
                        ];
                    }
                }
            }
        }

        if (empty($rows)) {
            $cats = ChecklistCategory::query()
                ->where('division_id', $divisionId)
                ->where('cadence', ChecklistCategory::CADENCE_DAILY)
                ->where('is_active', 1)
                ->with('activeTasks')
                ->orderBy('sort_order')
                ->get();
            foreach ($cats as $cat) {
                foreach ($cat->activeTasks as $task) {
                    $rows[$task->id] = (object) [
                        'key'      => $task->id,
                        'category' => $cat->name,
                        'catSort'  => (int) $cat->sort_order,
                        'title'    => $task->title,
                        'taskSort' => (int) $task->sort_order,
                    ];
                }
            }
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$a->catSort, $a->taskSort, $a->category, $a->title]
                                <=> [$b->catSort, $b->taskSort, $b->category, $b->title]);
        return $rows;
    }

    /** Isi satu sheet karyawan dengan grid tugas × tanggal. */
    private function fillEmployeeSheet($sheet, $user, ChecklistDivision $division, Carbon $start, int $daysInMonth, array $rows, $userSubs): void
    {
        $userSubs = $userSubs ?? collect();

        // day => [rowKey => 'done'|'excepted'|false]
        $doneByDay = [];
        foreach ($userSubs as $sub) {
            $map = [];
            foreach ($sub->items as $it) {
                $key = $it->task_id ?: ('t:' . $it->category_title_snapshot . '|' . $it->task_title_snapshot);
                $map[$key] = $it->is_done ? 'done' : ($it->is_excepted ? 'excepted' : false);
            }
            $doneByDay[(int) $sub->submission_date->day] = $map;
        }

        $firstDateCol  = 3;                                   // A=No, B=Uraian, C.. = tanggal
        $lastDateCol   = $firstDateCol + $daysInMonth - 1;
        $lastColLetter = Coordinate::stringFromColumnIndex($lastDateCol);

        // ── Judul ──
        $sheet->setCellValue('A1', 'CEKLIS HARIAN ' . mb_strtoupper($division->name));
        $sheet->mergeCells('A1:' . $lastColLetter . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A2', 'Nama: ' . ($user->nama ?: '-'));
        $sheet->setCellValue('A3', 'Bulan: ' . $start->translatedFormat('F Y'));
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);

        // ── Header kolom (baris 5) ──
        $hdr = 5;
        $sheet->setCellValue('A' . $hdr, 'No');
        $sheet->setCellValue('B' . $hdr, 'Uraian');
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $col  = Coordinate::stringFromColumnIndex($firstDateCol + $d - 1);
            $sheet->setCellValue($col . $hdr, $d);
            if ($start->copy()->day($d)->dayOfWeekIso === 7) {   // tandai Minggu
                $sheet->getStyle($col . $hdr)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FBE3DE');
            }
        }
        $hdrRange = 'A' . $hdr . ':' . $lastColLetter . $hdr;
        $sheet->getStyle($hdrRange)->getFont()->setBold(true);
        $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1E6D7');
        $sheet->getStyle($hdrRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ── Baris tugas (grup per seksi) ──
        $r       = $hdr + 1;
        $no      = 0;
        $lastCat = null;
        $secNo   = 0;
        foreach ($rows as $row) {
            if ($row->category !== $lastCat) {
                $secNo++;
                $sheet->setCellValue('A' . $r, $secNo . '.');
                $sheet->setCellValue('B' . $r, mb_strtoupper($row->category));
                $sheet->mergeCells('B' . $r . ':' . $lastColLetter . $r);
                $sheet->getStyle('A' . $r . ':' . $lastColLetter . $r)->getFont()->setBold(true);
                $sheet->getStyle('A' . $r . ':' . $lastColLetter . $r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FBEFD9');
                $r++;
                $lastCat = $row->category;
                $no      = 0;
            }
            $no++;
            $sheet->setCellValue('A' . $r, $no);
            $sheet->setCellValue('B' . $r, $row->title);
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $st = $doneByDay[$d][$row->key] ?? false;
                if ($st) {
                    $col = Coordinate::stringFromColumnIndex($firstDateCol + $d - 1);
                    $sheet->setCellValue($col . $r, $st === 'excepted' ? '–' : '✓');
                }
            }
            $r++;
        }
        $lastRow = max($r - 1, $hdr);

        // Legend
        $lgRow = $lastRow + 2;
        $sheet->setCellValue('A' . $lgRow, 'Keterangan: ✓ = dikerjakan   ·   – = dikecualikan   ·   kosong = belum/tidak diisi');
        $sheet->mergeCells('A' . $lgRow . ':' . $lastColLetter . $lgRow);
        $sheet->getStyle('A' . $lgRow)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('8A6A4F');

        // ── Kosmetik ──
        $sheet->getColumnDimension('A')->setWidth(4);
        $sheet->getColumnDimension('B')->setWidth(42);
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($firstDateCol + $d - 1))->setWidth(3.4);
        }
        $sheet->getStyle(Coordinate::stringFromColumnIndex($firstDateCol) . $hdr . ':' . $lastColLetter . $lastRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $hdr . ':' . $lastColLetter . $lastRow)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9C9B5');
        $sheet->freezePane('C' . ($hdr + 1));   // kunci kolom No/Uraian + header
    }

    /* ─── Export Excel (rekap checklist MINGGUAN per divisi) ───────────────
       Konsep = matriks bersih (bukan tiru form kertas): baris = tugas
       dikelompokkan per HARI (Selasa..Minggu), kolom = minggu ke-1..N dalam
       bulan tsb, sel = centang bila dikerjakan (– = hari dikecualikan).
       Tiap karyawan jadi 1 sheet (tab) sendiri. */

    public function exportWeekly(Request $request): StreamedResponse
    {
        $this->guard();

        $branchId = resolveChecklistBranchId($request->get('branch'));
        $division = $this->resolveExportDivision($request);
        $start    = $this->exportMonthStart($request);
        $end      = $start->copy()->endOfMonth();

        // Kolom = tiap minggu (week_start_date = Senin) yang overlap bulan tsb.
        $weeks  = [];
        $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lte($end)) {
            $weeks[] = $cursor->copy();
            $cursor->addWeek();
        }
        $mondayKeys = array_map(fn ($m) => $m->toDateString(), $weeks);

        // Karyawan: posisi aktif overlap bulan + union yang sempat mengisi minggu2 tsb.
        $posUserIds = $this->exportPositionUserIds($division->id, $branchId, $start, $end);
        $subUserIds = ChecklistWeeklySubmission::query()
            ->where('division_id', $division->id)
            ->whereIn('week_start_date', $mondayKeys)
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->pluck('user_id');

        $userIds = $posUserIds->merge($subUserIds)->unique()->values();
        $users   = Users::query()->whereIn('id', $userIds)->orderBy('nama')->get();

        $subsByUser = ChecklistWeeklySubmission::query()
            ->whereIn('user_id', $userIds)
            ->where('division_id', $division->id)
            ->whereIn('week_start_date', $mondayKeys)
            ->with(['items', 'dayExcuses'])
            ->get()
            ->groupBy('user_id');

        $rows = $this->exportWeeklyTaskRows($subsByUser, $division->id);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        if ($users->isEmpty()) {
            $info = $spreadsheet->createSheet();
            $info->setTitle('Info');
            $info->setCellValue('A1', 'Tidak ada karyawan aktif di divisi ' . $division->name
                . ' pada ' . $start->translatedFormat('F Y') . '.');
        }

        $usedTitles = [];
        foreach ($users as $u) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->uniqueSheetTitle($u->nama ?: 'Karyawan', $usedTitles));
            $this->fillEmployeeWeeklySheet(
                $sheet, $u, $division, $start, $weeks, $rows, $subsByUser->get($u->id)
            );
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'checklist_mingguan_' . $division->id . '_' . $start->format('Y-m') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /** Baris tugas mingguan unik (grup per hari) dari snapshot; fallback katalog aktif. */
    private function exportWeeklyTaskRows($subsByUser, string $divisionId): array
    {
        $rows = [];
        foreach ($subsByUser as $subs) {
            foreach ($subs as $sub) {
                foreach ($sub->items as $it) {
                    $key = $it->task_id ?: ('t:' . $it->day_of_week . '|' . $it->task_title_snapshot);
                    if (! isset($rows[$key])) {
                        $rows[$key] = (object) [
                            'key'      => $key,
                            'day'      => (int) $it->day_of_week,
                            'title'    => $it->task_title_snapshot,
                            'taskSort' => (int) $it->task_sort_order,
                        ];
                    }
                }
            }
        }

        if (empty($rows)) {
            $cats = ChecklistCategory::query()
                ->where('division_id', $divisionId)
                ->where('cadence', ChecklistCategory::CADENCE_WEEKLY)
                ->where('is_active', 1)
                ->with('activeTasks')
                ->get();
            foreach ($cats as $cat) {
                foreach ($cat->activeTasks as $task) {
                    if (! $task->day_of_week) continue;
                    $rows[$task->id] = (object) [
                        'key'      => $task->id,
                        'day'      => (int) $task->day_of_week,
                        'title'    => $task->title,
                        'taskSort' => (int) $task->sort_order,
                    ];
                }
            }
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$a->day, $a->taskSort, $a->title] <=> [$b->day, $b->taskSort, $b->title]);
        return $rows;
    }

    /** Isi satu sheet karyawan: grid tugas (grup per hari) × minggu. */
    private function fillEmployeeWeeklySheet($sheet, $user, ChecklistDivision $division, Carbon $start, array $weeks, array $rows, $userSubs): void
    {
        $userSubs  = $userSubs ?? collect();
        $dayLabels = \App\Models\ChecklistTask::dayLabels();

        // weekKey => [rowKey => 'done'|'excepted'|false] ; weekKey => [hari dikecualikan]
        $doneByWeek = [];
        $excusedByWeek = [];
        foreach ($userSubs as $sub) {
            $wk  = $sub->week_start_date->toDateString();
            $map = [];
            foreach ($sub->items as $it) {
                $key = $it->task_id ?: ('t:' . $it->day_of_week . '|' . $it->task_title_snapshot);
                $map[$key] = $it->is_done ? 'done' : ($it->is_excepted ? 'excepted' : false);
            }
            $doneByWeek[$wk]    = $map;
            $excusedByWeek[$wk] = $sub->excusedDayNumbers();
        }

        $firstCol   = 3;                                  // A=No, B=Uraian, C.. = minggu
        $nWeeks     = count($weeks);
        $lastCol    = $firstCol + max($nWeeks, 1) - 1;
        $lastLetter = Coordinate::stringFromColumnIndex($lastCol);

        // ── Judul ──
        $sheet->setCellValue('A1', 'CEKLIS MINGGUAN ' . mb_strtoupper($division->name));
        $sheet->mergeCells('A1:' . $lastLetter . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A2', 'Nama: ' . ($user->nama ?: '-'));
        $sheet->setCellValue('A3', 'Bulan: ' . $start->translatedFormat('F Y'));
        $sheet->getStyle('A2:A3')->getFont()->setBold(true);

        // ── Header: baris 5 = "Minggu n", baris 6 = rentang tanggal ──
        $hdr  = 5;
        $shdr = 6;
        $sheet->setCellValue('A' . $hdr, 'No');
        $sheet->setCellValue('B' . $hdr, 'Hari / Uraian Tugas');
        $sheet->mergeCells('A' . $hdr . ':A' . $shdr);
        $sheet->mergeCells('B' . $hdr . ':B' . $shdr);
        foreach ($weeks as $i => $mon) {
            $col   = Coordinate::stringFromColumnIndex($firstCol + $i);
            $wkEnd = $mon->copy()->addDays(6);
            $range = $mon->month === $wkEnd->month
                ? $mon->translatedFormat('d') . '–' . $wkEnd->translatedFormat('d M')
                : $mon->translatedFormat('d M') . '–' . $wkEnd->translatedFormat('d M');
            $sheet->setCellValue($col . $hdr, 'Minggu ' . ($i + 1));
            $sheet->setCellValue($col . $shdr, $range);
        }
        $hdrRange = 'A' . $hdr . ':' . $lastLetter . $shdr;
        $sheet->getStyle($hdrRange)->getFont()->setBold(true);
        $sheet->getStyle($hdrRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1E6D7');
        $sheet->getStyle($hdrRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle(Coordinate::stringFromColumnIndex($firstCol) . $shdr . ':' . $lastLetter . $shdr)
            ->getFont()->setBold(false)->setSize(9)->setItalic(true);

        // ── Baris tugas (grup per hari) ──
        $r       = $shdr + 1;
        $lastDay = null;
        $no      = 0;
        foreach ($rows as $row) {
            if ($row->day !== $lastDay) {
                $sheet->setCellValue('A' . $r, ($dayLabels[$row->day] ?? '-'));
                $sheet->mergeCells('A' . $r . ':' . $lastLetter . $r);
                $sheet->getStyle('A' . $r . ':' . $lastLetter . $r)->getFont()->setBold(true);
                $sheet->getStyle('A' . $r . ':' . $lastLetter . $r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCEAF6');   // biru muda ala form mingguan
                $r++;
                $lastDay = $row->day;
                $no      = 0;
            }
            $no++;
            $sheet->setCellValue('A' . $r, $no);
            $sheet->setCellValue('B' . $r, $row->title);
            foreach ($weeks as $i => $mon) {
                $wk  = $mon->toDateString();
                $col = Coordinate::stringFromColumnIndex($firstCol + $i);
                $st = $doneByWeek[$wk][$row->key] ?? false;
                if (in_array($row->day, $excusedByWeek[$wk] ?? [], true) || $st === 'excepted') {
                    $sheet->setCellValue($col . $r, '–');
                } elseif ($st === 'done') {
                    $sheet->setCellValue($col . $r, '✓');
                }
            }
            $r++;
        }
        $lastRow = max($r - 1, $shdr);

        // Legend
        $lgRow = $lastRow + 2;
        $sheet->setCellValue('A' . $lgRow, 'Keterangan: ✓ = dikerjakan   ·   – = dikecualikan (hari/pertanyaan)   ·   kosong = belum/tidak diisi');
        $sheet->mergeCells('A' . $lgRow . ':' . $lastLetter . $lgRow);
        $sheet->getStyle('A' . $lgRow)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('8A6A4F');

        // ── Kosmetik ──
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(46);
        foreach ($weeks as $i => $mon) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($firstCol + $i))->setWidth(12);
        }
        $sheet->getStyle(Coordinate::stringFromColumnIndex($firstCol) . $hdr . ':' . $lastLetter . $lastRow)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . ($shdr + 1) . ':B' . $lastRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A' . $hdr . ':' . $lastLetter . $lastRow)->getBorders()
            ->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9C9B5');
        $sheet->freezePane('C' . ($shdr + 1));   // kunci kolom No/Uraian + header
    }

    /* ─── Shared export helpers ────────────────────────────────────────── */

    /** Bulan (YYYY-MM) → awal bulan; default bulan berjalan. */
    private function exportMonthStart(Request $request): Carbon
    {
        try {
            return $request->get('month')
                ? Carbon::createFromFormat('Y-m', (string) $request->get('month'))->startOfMonth()
                : Carbon::today()->startOfMonth();
        } catch (\Throwable $e) {
            return Carbon::today()->startOfMonth();
        }
    }

    /** Divisi export wajib & dalam cakupan supervisi user ini. */
    private function resolveExportDivision(Request $request): ChecklistDivision
    {
        $divisionId = (string) $request->get('division', '');
        if (! in_array($divisionId, checklistVisibleDivisionIds(), true)) {
            abort(403, 'Divisi ini di luar cakupan supervisi Anda.');
        }
        return ChecklistDivision::findOrFail($divisionId);
    }

    /** user_id karyawan yg posisinya aktif (confirmed) di divisi & overlap [start,end]. */
    private function exportPositionUserIds(string $divisionId, ?string $branchId, Carbon $start, Carbon $end)
    {
        return UserPosition::query()
            ->where('is_primary', 1)
            ->whereNotNull('confirmed_at')
            ->where('division_id', $divisionId)
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->whereDate('effective_from', '<=', $end)
            ->where(function ($q) use ($start) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start);
            })
            ->pluck('user_id');
    }

    /** Judul sheet valid (≤31 char, tanpa karakter ilegal Excel) & unik. */
    private function uniqueSheetTitle(string $name, array &$used): string
    {
        $title = preg_replace('#[\\\\/?*:\[\]]#', ' ', $name);
        $title = trim(preg_replace('/\s+/', ' ', $title));
        if ($title === '') $title = 'Karyawan';
        $title = mb_substr($title, 0, 31);

        $base = $title;
        $i    = 2;
        while (in_array(mb_strtolower($title), $used, true)) {
            $suffix = '~' . $i;
            $title  = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $i++;
        }
        $used[] = mb_strtolower($title);
        return $title;
    }

    /* ─── Review queue ─────────────────────────────────────────────────── */

    public function reviewIndex(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        $filterDate = $request->get('date');
        $filterDiv  = $request->get('division', 'all');
        $filterStat = $request->get('status', ChecklistSubmission::STATUS_SUBMITTED);
        $search     = trim((string) $request->get('q', ''));
        $visible    = checklistVisibleDivisionIds();   // divisi yang boleh disupervisi user ini

        $base = ChecklistSubmission::query()
            ->whereIn('division_id', $visible)
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId));

        // Status tab counts (hitung sebelum apply filter status).
        $countsBase = (clone $base);
        if ($filterDate) $countsBase->whereDate('submission_date', $filterDate);
        if ($filterDiv !== 'all') $countsBase->where('division_id', $filterDiv);
        if ($search !== '') {
            $countsBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $counts = [
            'all'       => (clone $countsBase)->count(),
            'submitted' => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_SUBMITTED)->count(),
            'approved'  => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_APPROVED)->count(),
            'rejected'  => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_REJECTED)->count(),
            'excused'   => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_EXCUSED)->count(),
            'missed'    => (clone $countsBase)->where('status', ChecklistSubmission::STATUS_MISSED)->count(),
        ];

        // Jumlah submission per divisi (untuk tab divisi) — ikut filter tanggal/status/cari, TANPA filter divisi.
        $divCountBase = (clone $base);
        if ($filterDate) $divCountBase->whereDate('submission_date', $filterDate);
        if ($filterStat !== 'all') $divCountBase->where('status', $filterStat);
        if ($search !== '') {
            $divCountBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $divCounts = (clone $divCountBase)
            ->select('division_id', DB::raw('count(*) as c'))
            ->groupBy('division_id')
            ->pluck('c', 'division_id');

        $query = (clone $base)->with(['user', 'division', 'branch']);
        if ($filterDate) $query->whereDate('submission_date', $filterDate);
        if ($filterDiv !== 'all') $query->where('division_id', $filterDiv);
        if ($filterStat !== 'all') $query->where('status', $filterStat);
        if ($search !== '') {
            $query->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }

        $submissions = $query
            ->orderByDesc('submission_date')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage)
            ->appends($request->query());

        $divisions = ChecklistDivision::query()->where('is_active', 1)
            ->whereIn('id', $visible)->orderBy('sort_order')->get();
        $branches  = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.review.index', compact(
            'submissions', 'counts', 'divCounts', 'divisions', 'branches', 'branchId',
            'filterDate', 'filterDiv', 'filterStat', 'search'
        ));
    }

    public function reviewShow(string $id)
    {
        $this->guard();

        $submission = ChecklistSubmission::query()
            ->with(['user', 'reviewer', 'division', 'branch', 'items'])
            ->findOrFail($id);

        $this->abortIfBranchMismatch($submission);
        $this->abortIfDivisionOutOfScope($submission->division_id);

        // Grup items per kategori untuk render rapi.
        $grouped = $submission->items->groupBy('category_title_snapshot');

        return view('crud.checklist.review.show', compact('submission', 'grouped'));
    }

    public function approve(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistSubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Submission ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistSubmission::STATUS_APPROVED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes ?: null,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_APPROVE, $before, $after, $notes ?: null);

        $this->notifyEmployee($sub, 'checklist.approved', 'Checklist anda disetujui',
            'Pengisian checklist tanggal ' . $sub->submission_date->format('d M Y') . ' telah disetujui supervisor.');

        return redirect()->route('checklist.review')->with('success', 'Checklist disetujui.');
    }

    public function reject(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistSubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Submission ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        if ($notes === '') {
            return back()->with('error', 'Catatan wajib diisi saat menolak checklist.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistSubmission::STATUS_REJECTED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REJECT, $before, $after, $notes);

        $this->notifyEmployee($sub, 'checklist.rejected', 'Checklist anda ditolak',
            'Pengisian ' . $sub->submission_date->format('d M Y') . ' perlu diperbaiki. Catatan: ' . $notes);

        return redirect()->route('checklist.review')->with('success', 'Checklist ditolak. Karyawan akan diberitahu.');
    }

    public function reopen(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if (! in_array($sub->status, [ChecklistSubmission::STATUS_APPROVED, ChecklistSubmission::STATUS_REJECTED], true)) {
            return back()->with('error', 'Hanya checklist yang sudah final yang bisa di-reopen.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        $before = ['status' => $sub->status];
        $sub->update([
            'status'      => ChecklistSubmission::STATUS_SUBMITTED,
            'reviewed_at' => null,
            'updated_at'  => now(),
        ]);
        $after = ['status' => $sub->status];

        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN, $before, $after, $notes ?: null);

        return redirect()->route('checklist.review.show', $sub->id)
            ->with('success', 'Checklist di-reopen — kembali ke status Menunggu Approval.');
    }

    /**
     * Override supervisor: batalkan pengecualian ("Dikecualikan" → draft) bila
     * dinilai tidak valid, sehingga karyawan diminta mengisi checklist.
     */
    public function revokeExcuse(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistSubmission::findOrFail($id);
        $this->abortIfBranchMismatch($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistSubmission::STATUS_EXCUSED) {
            return back()->with('error', 'Hanya checklist berstatus Dikecualikan yang bisa dibatalkan.');
        }

        $before = ['status' => $sub->status, 'excuse_reason' => $sub->excuse_reason];
        $sub->update([
            'status'        => ChecklistSubmission::STATUS_DRAFT,
            'excuse_reason' => null,
            'updated_at'    => now(),
        ]);
        ChecklistAuditLog::write('submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Supervisor membatalkan pengecualian.');

        $this->notifyEmployee($sub, 'checklist.excuse.revoked', 'Pengecualian dibatalkan',
            'Pengecualian checklist ' . $sub->submission_date->format('d M Y') . ' dibatalkan supervisor — silakan isi checklist.');

        return redirect()->route('checklist.review')->with('success', 'Pengecualian dibatalkan. Karyawan diminta mengisi checklist.');
    }

    /* ─── Audit Trail ──────────────────────────────────────────────────── */

    public function audit(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        $filterAct  = $request->get('action', 'all');
        $filterType = $request->get('target_type', 'all');
        $dateFrom   = $request->get('date_from');
        $dateTo     = $request->get('date_to');
        $actor      = trim((string) $request->get('actor', ''));

        // Supervisor divisi hanya lihat log yang menyangkut divisinya (submission
        // harian/mingguan atau posisi karyawan). Overseer global lihat semua.
        $visible = checklistVisibleDivisionIds();

        $query = ChecklistAuditLog::query()
            ->with(['actor', 'branch'])
            ->when(! checklistSeesAllDivisions(), function ($q) use ($visible) {
                $q->where(function ($outer) use ($visible) {
                    $outer->whereExists(function ($s) use ($visible) {
                            $s->select(DB::raw(1))->from('sys_checklist_submission')
                              ->whereColumn('sys_checklist_submission.id', 'sys_checklist_audit_log.target_id')
                              ->whereIn('sys_checklist_submission.division_id', $visible);
                        })
                        ->orWhereExists(function ($s) use ($visible) {
                            $s->select(DB::raw(1))->from('sys_checklist_weekly_submission')
                              ->whereColumn('sys_checklist_weekly_submission.id', 'sys_checklist_audit_log.target_id')
                              ->whereIn('sys_checklist_weekly_submission.division_id', $visible);
                        })
                        ->orWhereExists(function ($s) use ($visible) {
                            $s->select(DB::raw(1))->from('sys_user_position')
                              ->whereColumn('sys_user_position.id', 'sys_checklist_audit_log.target_id')
                              ->whereIn('sys_user_position.division_id', $visible);
                        });
                });
            })
            ->when($branchId,           fn($q) => $q->where('raw_branch_id', $branchId))
            ->when($filterAct !== 'all',  fn($q) => $q->where('action', $filterAct))
            ->when($filterType !== 'all', fn($q) => $q->where('target_type', $filterType))
            ->when($dateFrom,           fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo,             fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($actor !== '',       function ($q) use ($actor) {
                $q->whereExists(function ($s) use ($actor) {
                    $s->select(DB::raw(1))->from('users')
                      ->whereRaw('users.id = sys_checklist_audit_log.actor_user_id')
                      ->where('users.nama', 'like', "%{$actor}%");
                });
            });

        $logs = $query->orderByDesc('created_at')->paginate(30)->appends($request->query());

        $branches = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();
        $actionOptions = ChecklistAuditLog::actionOptions();

        return view('crud.checklist.audit', compact(
            'logs', 'branches', 'branchId', 'filterAct', 'filterType',
            'dateFrom', 'dateTo', 'actor', 'actionOptions'
        ));
    }

    /* ─── Kelola Posisi Karyawan ──────────────────────────────────────────
       Supervisor assign user dengan role "Operasional" ke divisi (FO / GS /
       Asdent) + cabang. Karyawan yang sudah punya posisi aktif bisa di-
       reassign (effective_to lama di-tutup, row baru dibuat) — history
       posisi tetap utuh untuk audit. */

    public function positions(Request $request)
    {
        $this->guard();

        $branchId  = resolveChecklistBranchId($request->get('branch'));
        $search    = trim((string) $request->get('q', ''));
        $filterDiv = $request->get('division', 'all');
        $visible   = checklistVisibleDivisionIds();   // divisi yang boleh disupervisi user ini

        // Model "role operasional = divisi": divisi dikenali dari NAMA role yang
        // sama persis dgn nama divisi (tanpa penautan manual).
        $divisionsInScope = ChecklistDivision::query()->where('is_active', 1)
            ->whereIn('id', $visible)->orderBy('sort_order')->get();
        $divByName        = $divisionsInScope->keyBy('name');
        $scopeRoles       = GroupPath::whereIn('nama', $divisionsInScope->pluck('name'))->get();
        $roleToDivision   = $scopeRoles->mapWithKeys(fn ($r) => [$r->id => $divByName->get($r->nama)]); // role id => divisi
        $visibleRoleIds   = $scopeRoles->pluck('id')->values();

        // Staf = user yang role-nya adalah role-divisi dalam cakupan supervisor.
        $query = Users::query()
            ->select('users.*', 'group_path.nama as group_name', 'group_path.id as gid')
            ->join('group_path', 'users.group_id', '=', 'group_path.id')
            ->whereIn('users.group_id', $visibleRoleIds);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('users.nama', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $users   = $query->orderBy('users.nama')->get()->keyBy('id');
        $userIds = $users->keys();

        // Posisi AKTIF (confirmed) & USULAN pending, per user.
        $today = Carbon::today();
        $active = UserPosition::query()->activeOn($today)
            ->whereIn('user_id', $userIds)
            ->with(['division', 'branch'])->get()->keyBy('user_id');
        $pending = UserPosition::query()->pending()
            ->whereIn('user_id', $userIds)
            ->with(['division', 'branch'])->get()->keyBy('user_id');

        // Semua staf dalam cakupan ditampilkan (termasuk yang belum punya posisi),
        // supaya supervisor bisa set cabang. Divisi otomatis dari role (dikunci).
        $rows = $users->map(function ($u) use ($active, $pending, $roleToDivision) {
            $pos  = $active->get($u->id);
            $prop = $pending->get($u->id);
            return (object) [
                'user'         => $u,
                'position'     => $pos,
                'division'     => $pos?->division,
                'branch'       => $pos?->branch,
                'pending'      => $prop,
                'roleDivision' => $roleToDivision->get($u->group_id),   // divisi otomatis dari role
            ];
        })->sortBy(fn($r) => $r->user->nama)->values();

        // Filter divisi = filter role (divisi ⇄ role). Filter cabang ke posisi/usulan.
        if ($filterDiv !== 'all') {
            $rows = $rows->filter(fn($r) => optional($r->roleDivision)->id === $filterDiv)->values();
        }
        if ($branchId) {
            $rows = $rows->filter(fn($r) =>
                ($r->position && $r->position->raw_branch_id === $branchId)
                || ($r->pending && $r->pending->raw_branch_id === $branchId)
                || (! $r->position && ! $r->pending)   // belum ada posisi → tetap tampil
            )->values();
        }

        $divisions = $divisionsInScope;
        $branches  = RawBranch::orderBy('name')->get();

        // Stats
        $stats = [
            'total'       => $users->count(),
            'assigned'    => $active->count(),
            'pending'     => $pending->count(),
            'by_division' => $active->groupBy('division_id')->map->count(),
        ];

        return view('crud.checklist.positions', compact(
            'rows', 'divisions', 'branches', 'branchId', 'search', 'filterDiv', 'stats'
        ));
    }

    public function positionAssign(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'user_id'       => 'required|string|exists:users,id',
            'raw_branch_id' => 'required|string|exists:raw_branch,id',
        ]);

        $userId = $data['user_id'];
        $user   = Users::find($userId);

        // Divisi OTOMATIS dari role user (model "role staf = divisi") — tidak dipilih manual.
        $division = checklistDivisionForGroup($user?->group_id);
        if (! $division) {
            return back()->with('error', 'Role user ini bukan role operasional checklist (nama role tak cocok divisi mana pun: Front Office / General Services / Assistant Dentist).');
        }
        $data['division_id'] = $division->id;

        // Cegah supervisor menempatkan staf ke divisi di luar cakupannya.
        if (! in_array($division->id, checklistVisibleDivisionIds(), true)) {
            abort(403, 'Divisi ini di luar cakupan supervisi Anda.');
        }

        $today = Carbon::today();

        // Cari posisi aktif user. Kalau ada & sudah identik → no-op.
        $current = UserPosition::query()->activeOn($today)->where('user_id', $userId)->first();

        if ($current
            && $current->division_id   === $data['division_id']
            && $current->raw_branch_id === $data['raw_branch_id']) {
            return back()->with('info', 'Posisi tidak berubah — sudah sama dengan yang aktif.');
        }

        // Kalau ada posisi aktif tapi beda, tutup yang lama (effective_to=kemarin).
        if ($current) {
            $current->update([
                'effective_to' => $today->copy()->subDay(),
                'updated_at'   => now(),
            ]);
        }

        // Assign manual oleh supervisor = langsung dikonfirmasi (confirmed_at terisi).
        // Buat row baru
        $pos = UserPosition::create([
            'user_id'        => $userId,
            'division_id'    => $data['division_id'],
            'raw_branch_id'  => $data['raw_branch_id'],
            'is_primary'     => 1,
            'effective_from' => $today,
            'effective_to'   => null,
            'confirmed_at'   => now(),
            'confirmed_by'   => session('id'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Bersihkan usulan pending user ini (kalau ada) — sudah digantikan assign manual.
        UserPosition::query()->pending()->where('user_id', $userId)
            ->where('id', '!=', $pos->id)->delete();

        $user = Users::find($userId);
        $div  = ChecklistDivision::find($data['division_id']);
        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            $current ? ChecklistAuditLog::ACTION_UPDATE : ChecklistAuditLog::ACTION_CREATE,
            $current ? ['division_id' => $current->division_id, 'raw_branch_id' => $current->raw_branch_id] : null,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            'Posisi karyawan ' . ($user?->nama ?? '-') . ' diset ke ' . ($div?->name ?? $data['division_id'])
        );

        return redirect()->route('checklist.positions')
            ->with('success', ($current ? 'Posisi karyawan diperbarui.' : 'Posisi karyawan ditetapkan.')
                . ' — ' . ($user?->nama ?? '-') . ' → ' . ($div?->name ?? $data['division_id']));
    }

    public function positionRemove(string $id)
    {
        $this->guard();

        $pos = UserPosition::with(['user', 'division'])->findOrFail($id);
        $this->abortIfDivisionOutOfScope($pos->division_id);

        // effective_to = kemarin, bukan hari ini — sebab scope activeOn() pakai
        // "effective_to >= hari ini" (inklusif). Kalau di-set hari ini, posisi
        // masih terhitung aktif seharian → seolah "tidak kecabut". Kemarin =
        // nonaktif seketika. (Konsisten dgn penutupan posisi lama saat reassign.)
        $pos->update([
            'effective_to' => Carbon::today()->subDay(),
            'updated_at'   => now(),
        ]);

        ChecklistAuditLog::write(
            'submission',
            $pos->id,
            ChecklistAuditLog::ACTION_DELETE,
            ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
            null,
            'Posisi karyawan ' . ($pos->user?->nama ?? '-') . ' (' . ($pos->division?->name ?? $pos->division_id) . ') dinonaktifkan'
        );

        return redirect()->route('checklist.positions')
            ->with('success', 'Posisi karyawan dinonaktifkan.');
    }

    /* ─── Review Form Mingguan (supervisor) ────────────────────────────── */

    public function weeklyReviewIndex(Request $request)
    {
        $this->guard();

        $branchId   = resolveChecklistBranchId($request->get('branch'));
        // Terima tanggal apa saja → normalisasi ke Senin minggu tsb (week_start_date
        // selalu Senin), supaya supervisor tak perlu tepat memilih hari Senin.
        $filterWeek = $request->get('week');
        if ($filterWeek) {
            try {
                $filterWeek = Carbon::parse($filterWeek)->startOfWeek(Carbon::MONDAY)->toDateString();
            } catch (\Throwable $e) {
                $filterWeek = null;
            }
        }
        $filterDiv  = $request->get('division', 'all');
        $filterStat = $request->get('status', ChecklistWeeklySubmission::STATUS_SUBMITTED);
        $search     = trim((string) $request->get('q', ''));
        $visible    = checklistVisibleDivisionIds();   // divisi yang boleh disupervisi user ini

        $base = ChecklistWeeklySubmission::query()
            ->whereIn('division_id', $visible)
            ->when($branchId, fn($q) => $q->where('raw_branch_id', $branchId));

        $countsBase = (clone $base);
        if ($filterWeek) $countsBase->whereDate('week_start_date', $filterWeek);
        if ($filterDiv !== 'all') $countsBase->where('division_id', $filterDiv);
        if ($search !== '') {
            $countsBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_weekly_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $counts = [
            'all'       => (clone $countsBase)->count(),
            'submitted' => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_SUBMITTED)->count(),
            'approved'  => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_APPROVED)->count(),
            'rejected'  => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_REJECTED)->count(),
            'excused'   => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_EXCUSED)->count(),
            'missed'    => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_MISSED)->count(),
            'draft'     => (clone $countsBase)->where('status', ChecklistWeeklySubmission::STATUS_DRAFT)->count(),
        ];

        // Jumlah submission per divisi (untuk tab divisi) — ikut filter minggu/status/cari, TANPA filter divisi.
        $divCountBase = (clone $base);
        if ($filterWeek) $divCountBase->whereDate('week_start_date', $filterWeek);
        if ($filterStat !== 'all') $divCountBase->where('status', $filterStat);
        if ($search !== '') {
            $divCountBase->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_weekly_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }
        $divCounts = (clone $divCountBase)
            ->select('division_id', DB::raw('count(*) as c'))
            ->groupBy('division_id')
            ->pluck('c', 'division_id');

        $query = (clone $base)->with(['user', 'division', 'branch']);
        if ($filterWeek) $query->whereDate('week_start_date', $filterWeek);
        if ($filterDiv !== 'all') $query->where('division_id', $filterDiv);
        if ($filterStat !== 'all') $query->where('status', $filterStat);
        if ($search !== '') {
            $query->whereExists(function ($q) use ($search) {
                $q->select(DB::raw(1))->from('users')
                  ->whereRaw('users.id = sys_checklist_weekly_submission.user_id')
                  ->where('users.nama', 'like', "%{$search}%");
            });
        }

        $submissions = $query
            ->orderByDesc('week_start_date')
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage)
            ->appends($request->query());

        $divisions = ChecklistDivision::query()->where('is_active', 1)
            ->whereIn('id', $visible)->orderBy('sort_order')->get();
        $branches  = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        return view('crud.checklist.review.weekly-index', compact(
            'submissions', 'counts', 'divCounts', 'divisions', 'branches', 'branchId',
            'filterWeek', 'filterDiv', 'filterStat', 'search'
        ));
    }

    public function weeklyReviewShow(string $id)
    {
        $this->guard();

        $submission = ChecklistWeeklySubmission::query()
            ->with(['user', 'reviewer', 'division', 'branch', 'items', 'dayExcuses'])
            ->findOrFail($id);

        $this->abortIfBranchMismatchWeekly($submission);
        $this->abortIfDivisionOutOfScope($submission->division_id);

        // Items di-group per day_of_week saja (tanpa kategori), supaya view bisa
        // render ala form kertas (Selasa, Kamis, dst) — task langsung per hari.
        $grouped = $submission->items
            ->sortBy(fn($i) => sprintf('%06d-%06d', $i->category_sort_order ?? 0, $i->task_sort_order ?? 0))
            ->groupBy('day_of_week');

        // Hari yang ditandai "tidak perlu diisi" (iso => alasan) untuk badge di view.
        $excusedDays = $submission->dayExcuses->pluck('reason', 'day_of_week')
            ->mapWithKeys(fn ($reason, $iso) => [(int) $iso => $reason])->all();

        return view('crud.checklist.review.weekly-show', compact('submission', 'grouped', 'excusedDays'));
    }

    public function weeklyApprove(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Form Mingguan ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistWeeklySubmission::STATUS_APPROVED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes ?: null,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_APPROVE, $before, $after, $notes ?: null);

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.approved', 'Form Mingguan anda disetujui',
            'Form Mingguan ' . $sub->weekLabel() . ' telah disetujui supervisor.');

        return redirect()->route('checklist.review.weekly')->with('success', 'Form Mingguan disetujui.');
    }

    public function weeklyReject(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_SUBMITTED) {
            return back()->with('error', 'Form Mingguan ini sudah tidak menunggu approval.');
        }

        $notes = trim((string) $request->get('review_notes', ''));
        if ($notes === '') {
            return back()->with('error', 'Catatan wajib diisi saat menolak Form Mingguan.');
        }

        $before = ['status' => $sub->status];
        $sub->update([
            'status'           => ChecklistWeeklySubmission::STATUS_REJECTED,
            'reviewer_user_id' => session('id'),
            'review_notes'     => $notes,
            'reviewed_at'      => now(),
            'updated_at'       => now(),
        ]);
        $after = ['status' => $sub->status, 'reviewer_user_id' => $sub->reviewer_user_id];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REJECT, $before, $after, $notes);

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.rejected', 'Form Mingguan anda ditolak',
            'Form Mingguan ' . $sub->weekLabel() . ' perlu diperbaiki. Catatan: ' . $notes);

        return redirect()->route('checklist.review.weekly')->with('success', 'Form Mingguan ditolak. Karyawan akan diberitahu.');
    }

    public function weeklyReopen(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if (! in_array($sub->status, [ChecklistWeeklySubmission::STATUS_APPROVED, ChecklistWeeklySubmission::STATUS_REJECTED], true)) {
            return back()->with('error', 'Hanya Form Mingguan yang sudah final yang bisa di-reopen.');
        }

        $notes  = trim((string) $request->get('review_notes', ''));
        $before = ['status' => $sub->status];
        $sub->update([
            'status'      => ChecklistWeeklySubmission::STATUS_SUBMITTED,
            'reviewed_at' => null,
            'updated_at'  => now(),
        ]);
        $after = ['status' => $sub->status];

        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN, $before, $after, $notes ?: null);

        return redirect()->route('checklist.review.weekly.show', $sub->id)
            ->with('success', 'Form Mingguan di-reopen — kembali ke status Menunggu Approval.');
    }

    /**
     * Override supervisor: batalkan pengecualian Form Mingguan (excused → draft).
     */
    public function weeklyRevokeExcuse(Request $request, string $id)
    {
        $this->guard();

        $sub = ChecklistWeeklySubmission::findOrFail($id);
        $this->abortIfBranchMismatchWeekly($sub);
        $this->abortIfDivisionOutOfScope($sub->division_id);

        if ($sub->status !== ChecklistWeeklySubmission::STATUS_EXCUSED) {
            return back()->with('error', 'Hanya Form Mingguan berstatus Dikecualikan yang bisa dibatalkan.');
        }

        $before = ['status' => $sub->status, 'excuse_reason' => $sub->excuse_reason];
        $sub->update([
            'status'        => ChecklistWeeklySubmission::STATUS_DRAFT,
            'excuse_reason' => null,
            'updated_at'    => now(),
        ]);
        ChecklistAuditLog::write('weekly_submission', $sub->id, ChecklistAuditLog::ACTION_REOPEN,
            $before, ['status' => $sub->status], 'Supervisor membatalkan pengecualian Form Mingguan.');

        $this->notifyEmployeeWeekly($sub, 'checklist.weekly.excuse.revoked', 'Pengecualian dibatalkan',
            'Pengecualian Form Mingguan ' . $sub->weekLabel() . ' dibatalkan supervisor — silakan isi form.');

        return redirect()->route('checklist.review.weekly')->with('success', 'Pengecualian dibatalkan. Karyawan diminta mengisi Form Mingguan.');
    }

    /* ─── Helpers ──────────────────────────────────────────────────────── */

    private function abortIfBranchMismatch(ChecklistSubmission $sub): void
    {
        $branchId = resolveChecklistBranchId(null);
        // Kalau user terkunci ke branch tertentu, hanya boleh akses submission di branch yang sama.
        if ($branchId && $sub->raw_branch_id !== $branchId) {
            abort(403, 'Tidak punya akses ke checklist cabang lain.');
        }
    }

    private function abortIfBranchMismatchWeekly(ChecklistWeeklySubmission $sub): void
    {
        $branchId = resolveChecklistBranchId(null);
        if ($branchId && $sub->raw_branch_id !== $branchId) {
            abort(403, 'Tidak punya akses ke Form Mingguan cabang lain.');
        }
    }

    /** Tolak akses kalau divisi di luar cakupan supervisi user (buka via id langsung). */
    private function abortIfDivisionOutOfScope(?string $divisionId): void
    {
        if (! in_array($divisionId, checklistVisibleDivisionIds(), true)) {
            abort(403, 'Divisi ini di luar cakupan supervisi Anda.');
        }
    }

    private function notifyEmployee(ChecklistSubmission $sub, string $type, string $title, string $body): void
    {
        try {
            AdminNotification::create([
                'user_id'       => $sub->user_id,
                'raw_branch_id' => $sub->raw_branch_id,
                'type'          => $type,
                'title'         => $title,
                'body'          => $body,
                'link'          => route('mychecklist'),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // jangan fail flow approval kalau notif error
        }
    }

    private function notifyEmployeeWeekly(ChecklistWeeklySubmission $sub, string $type, string $title, string $body): void
    {
        try {
            AdminNotification::create([
                'user_id'       => $sub->user_id,
                'raw_branch_id' => $sub->raw_branch_id,
                'type'          => $type,
                'title'         => $title,
                'body'          => $body,
                'link'          => route('mychecklist.weekform'),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
