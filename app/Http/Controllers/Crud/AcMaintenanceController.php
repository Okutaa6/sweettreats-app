<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\AcServiceItem;
use App\Models\AcServiceRecord;
use App\Models\AcUnit;
use App\Models\ChecklistAuditLog;
use App\Models\RawBranch;
use App\Models\UserPosition;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Modul Jadwal Cuci AC.
 *
 * Dua sisi dalam satu controller:
 *   - GS cabang  : myIndex() / storeService() / destroyOwnService()
 *                  — catat servis (cuci / isi freon) untuk unit AC cabangnya.
 *   - Supervisor : monitor() / storeUnit() / updateUnit() / destroyUnit() /
 *                  destroyService() — monitoring lintas cabang + kelola unit.
 *
 * Jatuh tempo dihitung di AcUnit (last_wash_date + wash_interval_days),
 * status: Aman / Segera (≤14 hari) / Terlambat. Akses supervisor pakai
 * isChecklistSupervisor() (sama dengan DigitalChecklistController), akses GS
 * pakai currentUserPosition() dengan division_id 'GS'.
 */
class AcMaintenanceController extends Controller
{
    private function guardGs(): UserPosition
    {
        $position = currentUserPosition();
        if (! $position || $position->division_id !== 'GS' || ! $position->raw_branch_id) {
            abort(403, 'Halaman Jadwal Cuci AC hanya untuk karyawan divisi GS.');
        }

        return $position;
    }

    private function guardSupervisor(): void
    {
        if (! isChecklistSupervisor()) abort(403, 'Tidak punya akses ke monitoring Jadwal Cuci AC.');
    }

    /* ─── Sisi GS ──────────────────────────────────────────────────────── */

    public function myIndex()
    {
        $position = $this->guardGs();

        $units = AcUnit::query()
            ->withServiceSummary()
            ->where('raw_branch_id', $position->raw_branch_id)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('room_name')
            ->get();

        $statusCounts = [
            AcUnit::STATUS_AMAN      => 0,
            AcUnit::STATUS_SEGERA    => 0,
            AcUnit::STATUS_TERLAMBAT => 0,
            AcUnit::STATUS_BELUM     => 0,
        ];
        foreach ($units as $unit) {
            $statusCounts[$unit->due_status]++;
        }

        // Urgency first (keputusan UX handoff): Terlambat → Segera → Aman,
        // lalu urutan unit.
        $prio  = [AcUnit::STATUS_BELUM => 0, AcUnit::STATUS_TERLAMBAT => 1, AcUnit::STATUS_SEGERA => 2, AcUnit::STATUS_AMAN => 3];
        $units = $units
            ->sortBy(fn ($u) => sprintf('%d-%05d-%s', $prio[$u->due_status], $u->sort_order, $u->room_name))
            ->values();

        return view('crud.checklist.ac.my', [
            'position'     => $position,
            'branch'       => RawBranch::find($position->raw_branch_id),
            'units'        => $units,
            'statusCounts' => $statusCounts,
            'today'        => Carbon::today(),
        ]);
    }

    private function validateService(Request $request): array
    {
        $minDate = Carbon::today()->subYears(2)->toDateString();
        return $request->validate([
            'ac_unit_id'            => 'required|string',
            'service_date'          => 'required|date|after_or_equal:' . $minDate . '|before_or_equal:today',
            'wash_amount'           => 'nullable|integer|min:0|max:1000000000',
            'freon_amount'          => 'nullable|integer|min:0|max:1000000000',
            'others'                => 'nullable|array',
            'others.*.description'  => 'nullable|string|max:500',
            'others.*.amount'       => 'nullable|integer|min:0|max:1000000000',
        ], [
            'service_date.after_or_equal'  => 'Tanggal servis terlalu lama (maksimal 2 tahun ke belakang).',
            'service_date.before_or_equal' => 'Tanggal servis tidak boleh melewati hari ini.',
            'wash_amount.max'              => 'Nominal terlalu besar (maksimal Rp 1 miliar).',
            'freon_amount.max'             => 'Nominal terlalu besar (maksimal Rp 1 miliar).',
            'others.*.amount.max'          => 'Nominal terlalu besar (maksimal Rp 1 miliar).',
        ]);
    }

    /** GS mencatat servis → status pending (menunggu approval supervisor). */
    public function storeService(Request $request)
    {
        $position = $this->guardGs();
        $data = $this->validateService($request);

        $unit = AcUnit::find($data['ac_unit_id']);
        if (! $unit || $unit->raw_branch_id !== $position->raw_branch_id) {
            abort(403, 'Unit AC bukan milik cabang Anda.');
        }

        return $this->persistService($request, $data, $unit,
            AcServiceRecord::STATUS_PENDING, null,
            'dicatat — menunggu approval supervisor.');
    }

    /** Supervisor mencatat servis langsung → status approved (tanpa perlu approval). */
    public function storeServiceBySupervisor(Request $request)
    {
        $this->guardSupervisor();
        $data = $this->validateService($request);

        $unit = AcUnit::find($data['ac_unit_id']);
        if (! $unit) abort(404, 'Unit AC tidak ditemukan.');
        if (! canSeeAllBranches() && session('raw_branch_id') !== $unit->raw_branch_id) {
            abort(403, 'Unit AC di luar cabang Anda.');
        }

        return $this->persistService($request, $data, $unit,
            AcServiceRecord::STATUS_APPROVED, (string) session('id'),
            'dicatat & langsung masuk riwayat.');
    }

    /** Bangun baris rincian + simpan record servis. Dipakai GS (pending) & SPV (approved). */
    private function persistService(Request $request, array $data, AcUnit $unit, string $status, ?string $reviewerId, string $successSuffix)
    {
        if (! $unit->is_active) {
            return back()->with('error', 'Unit AC ini sudah tidak aktif — muat ulang halaman.')->withInput();
        }

        $isWash  = $request->boolean('wash');
        $isFreon = $request->boolean('freon');

        // Susun baris rincian: Cuci AC & Isi Freon jadi baris standar (kalau
        // dicentang), lalu baris "biaya lainnya" yang keterangannya terisi.
        $items = [];
        if ($isWash)  $items[] = ['description' => 'Cuci AC',   'amount' => (int) ($data['wash_amount'] ?? 0)];
        if ($isFreon) $items[] = ['description' => 'Isi Freon', 'amount' => (int) ($data['freon_amount'] ?? 0)];
        foreach ($data['others'] ?? [] as $other) {
            $desc = trim($other['description'] ?? '');
            if ($desc === '') continue;
            $items[] = ['description' => $desc, 'amount' => (int) ($other['amount'] ?? 0)];
        }

        if (empty($items)) {
            return back()
                ->withErrors(['service' => 'Pilih minimal Cuci AC / Isi Freon, atau isi rincian biaya lainnya.'])
                ->withInput();
        }

        $record = AcServiceRecord::create([
            'id'                  => (string) Str::uuid(),
            'ac_unit_id'          => $unit->id,
            'raw_branch_id'       => $unit->raw_branch_id,
            'service_date'        => $data['service_date'],
            'is_wash'             => $isWash,
            'status'              => $status,
            'created_by_user_id'  => (string) session('id'),
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at'         => $reviewerId ? now() : null,
        ]);

        $itemsLog = [];
        foreach ($items as $i => $item) {
            AcServiceItem::create([
                'id'                => (string) Str::uuid(),
                'service_record_id' => $record->id,
                'description'       => $item['description'],
                'amount'            => $item['amount'],
                'sort_order'        => $i,
            ]);
            $itemsLog[] = $item['description'] . ' = ' . $item['amount'];
        }

        ChecklistAuditLog::write('ac_service', $record->id,
            $status === AcServiceRecord::STATUS_APPROVED ? 'create_approved' : 'create', null, [
                'unit'         => $unit->room_name,
                'service_date' => $data['service_date'],
                'is_wash'      => $isWash,
                'items'        => $itemsLog,
            ]);

        return back()->with('success', 'Servis ' . $unit->room_name . ' tanggal ' . $record->service_date->format('d/m/Y') . ' ' . $successSuffix);
    }

    /** Undo salah input: hanya record milik sendiri (belum di-approve) dalam 24 jam. */
    public function destroyOwnService(string $id)
    {
        $position = $this->guardGs();

        $record = AcServiceRecord::query()
            ->where('id', $id)
            ->where('raw_branch_id', $position->raw_branch_id)
            ->first();

        if (! $record) abort(404);

        if ($record->created_by_user_id !== (string) session('id')
            || ! $record->created_at || $record->created_at->lt(now()->subDay())) {
            return back()->with('error', 'Hanya catatan milik sendiri yang dibuat dalam 24 jam terakhir yang bisa dihapus.');
        }
        if ($record->status === AcServiceRecord::STATUS_APPROVED) {
            return back()->with('error', 'Catatan yang sudah disetujui hanya bisa dihapus supervisor.');
        }

        $record->delete();

        ChecklistAuditLog::write('ac_service', $record->id, 'delete_self', [
            'service_date' => $record->service_date?->toDateString(),
            'items'        => $record->items->map(fn ($i) => $i->description . ' = ' . $i->amount)->all(),
        ], null);

        return back()->with('success', 'Catatan servis dihapus.');
    }

    /**
     * GS cabang menambah unit AC untuk cabangnya sendiri — langsung aktif.
     * Cabang dipaksa = cabang GS; nilai raw_branch_id yang diposting diabaikan
     * supaya GS tak bisa menambah unit ke cabang lain.
     */
    public function storeOwnUnit(Request $request)
    {
        $position = $this->guardGs();

        $request->merge(['raw_branch_id' => $position->raw_branch_id]);
        $data = $this->validateUnit($request);

        $unit = AcUnit::create(array_merge($data, [
            'id' => (string) Str::uuid(),
        ]));

        ChecklistAuditLog::write('ac_unit', $unit->id, 'create_gs', null, [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'interval'      => $unit->wash_interval_days,
        ]);

        return back()->with('success', 'Unit AC "' . $unit->room_name . '" ditambahkan.');
    }

    /**
     * GS cabang mengedit info unit AC cabangnya sendiri (nama ruangan, PK,
     * interval cuci, urutan, catatan). Cabang dikunci = cabang GS; unit lain
     * cabang ditolak. Optimistic locking sama seperti sisi supervisor.
     */
    public function updateOwnUnit(Request $request, string $id)
    {
        $position = $this->guardGs();

        $unit = AcUnit::findOrFail($id);
        if ($unit->raw_branch_id !== $position->raw_branch_id) {
            abort(403, 'Unit AC bukan milik cabang Anda.');
        }

        // Optimistic locking: tolak kalau unit sudah berubah sejak form dibuka
        // (edit bersamaan dari 2 tab/orang) — cegah timpa-menimpa diam-diam.
        $expected = (string) $request->input('expected_updated_at', '');
        $current  = $unit->updated_at ? $unit->updated_at->toIso8601String() : '';
        if ($expected !== $current) {
            return back()->with('error', 'Unit ini baru saja diubah dari sesi/perangkat lain. Muat ulang halaman lalu ulangi.');
        }

        // Cabang dipaksa = cabang GS; nilai raw_branch_id yang diposting diabaikan.
        $request->merge(['raw_branch_id' => $position->raw_branch_id]);
        $data = $this->validateUnit($request, $unit->id);

        $before = [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'capacity_pk'   => $unit->capacity_pk,
            'interval'      => $unit->wash_interval_days,
        ];

        $unit->fill($data);
        $unit->updated_at = now();
        $unit->save();

        ChecklistAuditLog::write('ac_unit', $unit->id, 'update_gs', $before, [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'capacity_pk'   => $unit->capacity_pk,
            'interval'      => $unit->wash_interval_days,
        ]);

        return back()->with('success', 'Unit AC "' . $unit->room_name . '" diperbarui.');
    }

    /* ─── Sisi Supervisor ──────────────────────────────────────────────── */

    public function monitor(Request $request)
    {
        $this->guardSupervisor();

        $branchId = resolveChecklistBranchId($request->get('branch'));
        $branches = canSeeAllBranches() ? RawBranch::orderBy('name')->get() : collect();

        // Tidak ada opsi "Semua" — admin default ke cabang pertama.
        if (! $branchId && $branches->isNotEmpty()) {
            $branchId = $branches->first()->id;
        }

        $units = AcUnit::query()
            ->withServiceSummary()
            ->with('branch')
            ->where('is_active', 1)
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->orderBy('sort_order')
            ->orderBy('room_name')
            ->get();

        $statusCounts = [
            AcUnit::STATUS_AMAN      => 0,
            AcUnit::STATUS_SEGERA    => 0,
            AcUnit::STATUS_TERLAMBAT => 0,
            AcUnit::STATUS_BELUM     => 0,
        ];
        foreach ($units as $unit) {
            $statusCounts[$unit->due_status]++;
        }

        // Catatan servis yang masih menunggu approval (dalam scope cabang).
        $pendingCount = AcServiceRecord::query()
            ->where('status', AcServiceRecord::STATUS_PENDING)
            ->when($branchId, fn ($q) => $q->where('raw_branch_id', $branchId))
            ->count();

        return view('crud.checklist.ac.monitor', [
            'branchId'     => $branchId,
            'branches'     => $branches,
            'units'        => $units,
            'statusCounts' => $statusCounts,
            'pendingCount' => $pendingCount,
            'today'        => Carbon::today(),
        ]);
    }

    /** Riwayat servis satu unit (JSON untuk modal). SPV atau GS cabang ybs. */
    public function unitHistory(string $id)
    {
        $unit = AcUnit::query()->withServiceSummary()->where('id', $id)->first();
        if (! $unit) abort(404);

        $isSupervisor = isChecklistSupervisor();
        if (! $isSupervisor) {
            $position = currentUserPosition();
            if (! $position || $position->division_id !== 'GS'
                || $position->raw_branch_id !== $unit->raw_branch_id) {
                abort(403);
            }
        }

        $meta = AcServiceRecord::statusMeta();
        $records = AcServiceRecord::query()
            ->with(['creator', 'reviewer', 'items'])
            ->where('ac_unit_id', $unit->id)
            ->orderByDesc('service_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'date'         => $r->service_date?->format('d/m/Y'),
                'iso'          => $r->service_date?->format('Y-m-d'),
                'ym'           => $r->service_date?->format('Y-m'),
                'month_label'  => $r->service_date?->translatedFormat('F Y'),
                'total'        => $r->total_amount,
                'total_fmt'    => 'Rp ' . number_format($r->total_amount, 0, ',', '.'),
                'creator'      => $r->creator?->nama,
                'status'       => $r->status,
                'status_label' => $meta[$r->status]['label'] ?? $r->status,
                'status_color' => $meta[$r->status]['color'] ?? '#6B4423',
                'status_bg'    => $meta[$r->status]['bg'] ?? '#F5EFE6',
                'review_note'  => $r->review_note,
                'reviewer'     => $r->reviewer?->nama,
                // SPV bisa review kalau masih pending.
                'can_review'   => $isSupervisor && $r->status === AcServiceRecord::STATUS_PENDING,
                // SPV boleh hapus (koreksi) kapan pun; GS hanya record sendiri yang
                // dibuat dalam 24 jam terakhir DAN belum di-approve (undo salah input).
                'can_delete'   => $isSupervisor
                    || ($r->created_by_user_id === (string) session('id')
                        && $r->status !== AcServiceRecord::STATUS_APPROVED
                        && $r->created_at && $r->created_at->gte(now()->subDay())),
                'items'        => $r->items->map(fn ($i) => [
                    'description' => $i->description,
                    'amount_fmt'  => 'Rp ' . number_format((int) $i->amount, 0, ',', '.'),
                ])->values(),
            ]);

        $lastWash = $unit->getAttributes()['last_wash_date'] ?? null;

        return response()->json([
            'unit' => [
                'id'            => $unit->id,
                'room_name'     => $unit->room_name,
                'capacity_pk'   => $unit->capacity_pk,
                'interval_days' => $unit->wash_interval_days,
                'last_wash'     => $lastWash ? Carbon::parse($lastWash)->format('d/m/Y') : null,
                'next_due'      => $unit->next_due_date?->format('d/m/Y'),
                'status'        => $unit->due_status,
                'status_label'  => $unit->due_status_label,
            ],
            'records' => $records,
        ]);
    }

    /** Supervisor menyetujui catatan servis → resmi masuk riwayat & reset jadwal cuci. */
    public function approveService(string $id)
    {
        $this->guardSupervisor();
        $record = AcServiceRecord::with('unit')->findOrFail($id);

        if ($record->status !== AcServiceRecord::STATUS_PENDING) {
            return back()->with('error', 'Catatan ini sudah direview.');
        }

        $record->update([
            'status'              => AcServiceRecord::STATUS_APPROVED,
            'reviewed_by_user_id' => (string) session('id'),
            'reviewed_at'         => now(),
            'review_note'         => null,
        ]);

        ChecklistAuditLog::write('ac_service', $record->id, 'approve',
            ['status' => AcServiceRecord::STATUS_PENDING],
            ['status' => AcServiceRecord::STATUS_APPROVED, 'unit' => $record->unit?->room_name]);

        return back()->with('success', 'Catatan servis disetujui & masuk riwayat.');
    }

    /** Supervisor menolak catatan servis (wajib alasan). Tidak masuk riwayat. */
    public function rejectService(Request $request, string $id)
    {
        $this->guardSupervisor();
        $record = AcServiceRecord::with('unit')->findOrFail($id);

        if ($record->status !== AcServiceRecord::STATUS_PENDING) {
            return back()->with('error', 'Catatan ini sudah direview.');
        }

        $request->merge(['reason' => trim((string) $request->input('reason'))]);
        $data = $request->validate(
            ['reason' => 'required|string|min:3|max:500'],
            ['reason.required' => 'Alasan penolakan wajib diisi.']
        );

        $record->update([
            'status'              => AcServiceRecord::STATUS_REJECTED,
            'reviewed_by_user_id' => (string) session('id'),
            'reviewed_at'         => now(),
            'review_note'         => $data['reason'],
        ]);

        ChecklistAuditLog::write('ac_service', $record->id, 'reject',
            ['status' => AcServiceRecord::STATUS_PENDING],
            ['status' => AcServiceRecord::STATUS_REJECTED, 'reason' => $data['reason'], 'unit' => $record->unit?->room_name]);

        return back()->with('success', 'Catatan servis ditolak.');
    }

    public function storeUnit(Request $request)
    {
        $this->guardSupervisor();

        $data = $this->validateUnit($request);

        $unit = AcUnit::create(array_merge($data, [
            'id' => (string) Str::uuid(),
        ]));

        ChecklistAuditLog::write('ac_unit', $unit->id, 'create', null, [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'interval'      => $unit->wash_interval_days,
        ]);

        return back()->with('success', 'Unit AC "' . $unit->room_name . '" ditambahkan.');
    }

    public function updateUnit(Request $request, string $id)
    {
        $this->guardSupervisor();

        $unit = AcUnit::findOrFail($id);

        // Optimistic locking: tolak kalau unit sudah berubah sejak form dibuka
        // (edit bersamaan dari 2 tab/orang) — cegah timpa-menimpa diam-diam.
        $expected = (string) $request->input('expected_updated_at', '');
        $current  = $unit->updated_at ? $unit->updated_at->toIso8601String() : '';
        if ($expected !== $current) {
            return back()->with('error', 'Unit ini baru saja diubah dari sesi/perangkat lain. Muat ulang halaman lalu ulangi.');
        }

        $data = $this->validateUnit($request, $unit->id);

        $before = [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'capacity_pk'   => $unit->capacity_pk,
            'interval'      => $unit->wash_interval_days,
        ];

        $unit->fill($data);
        $unit->updated_at = now();
        $unit->save();

        ChecklistAuditLog::write('ac_unit', $unit->id, 'update', $before, [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
            'capacity_pk'   => $unit->capacity_pk,
            'interval'      => $unit->wash_interval_days,
        ]);

        return back()->with('success', 'Unit AC "' . $unit->room_name . '" diperbarui.');
    }

    public function destroyUnit(string $id)
    {
        $this->guardSupervisor();

        $unit = AcUnit::findOrFail($id);
        $unit->delete();

        ChecklistAuditLog::write('ac_unit', $unit->id, 'delete', [
            'room_name'     => $unit->room_name,
            'raw_branch_id' => $unit->raw_branch_id,
        ], null);

        return back()->with('success', 'Unit AC "' . $unit->room_name . '" dihapus. Riwayat servis tetap tersimpan.');
    }

    /** Koreksi supervisor: hapus (soft delete) record servis mana pun. */
    public function destroyService(string $id)
    {
        $this->guardSupervisor();

        $record = AcServiceRecord::findOrFail($id);
        $record->delete();

        ChecklistAuditLog::write('ac_service', $record->id, 'delete_supervisor', [
            'service_date' => $record->service_date?->toDateString(),
            'items'        => $record->items->map(fn ($i) => $i->description . ' = ' . $i->amount)->all(),
        ], null);

        return back()->with('success', 'Catatan servis dihapus.');
    }

    private function validateUnit(Request $request, ?string $ignoreId = null): array
    {
        $data = $request->validate([
            'raw_branch_id'      => 'required|string|exists:raw_branch,id',
            // Nama ruangan wajib unik per cabang (abaikan yang sudah soft-deleted &
            // unit yang sedang diedit) supaya GS tak bingung "DEPAN FO" yang mana.
            'room_name'          => [
                'required', 'string', 'max:255',
                Rule::unique('sys_ac_unit', 'room_name')
                    ->where(fn ($q) => $q->where('raw_branch_id', $request->input('raw_branch_id'))->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'capacity_pk'        => 'nullable|numeric|min:0.25|max:10',
            'wash_interval_days' => 'required|integer|min:7|max:365',
            'notes'              => 'nullable|string|max:1000',
            'sort_order'         => 'nullable|integer|min:0',
        ], [
            'room_name.unique' => 'Nama ruangan ini sudah ada di cabang tersebut.',
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
