<?php

namespace App\Http\Controllers\Crud;

use App\Http\Controllers\Controller;
use App\Models\RawBranch;
use App\Models\RawCusReservationStaging;
use App\Models\RawDoctor;
use App\Models\RawReservationImportBatch;
use App\Services\ReservationCsvImporter;
use App\Services\ReservationPromoter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman & endpoint impor jadwal reservasi dari CSV/Sheets.
 *
 * Flow:
 *   1. User pilih file (.csv / .xlsx) di tab "Impor Sheets"
 *   2. POST upload → ReservationCsvImporter parse → batch + staging rows
 *   3. User pilah staging rows → promote per row / bulk ke raw_cus_reservation
 *
 * Akses: re-use `isReservationViewOnly()` — view-only role tidak boleh
 * akses halaman impor.
 */
class ReservationImportController extends Controller
{
    private function guard(): void
    {
        // Fitur Impor Sheets khusus Super Admin — akses ke halaman + semua
        // endpoint (upload, promote, auto-process, edit, dst) di-block untuk
        // role lain. Defense in depth: UI hide tombol di list.blade.php,
        // controller hard-block sini.
        if (! isSuperAdmin()) {
            abort(403, 'Fitur Impor Sheets hanya untuk Super Admin.');
        }
    }

    /**
     * Resolve branch ID untuk operasi import. User yang ke-bind cabang
     * (sales/cs cabang) → session('raw_branch_id'). Admin global bisa
     * lewat query ?branch_id=... untuk impor lintas cabang.
     */
    private function resolveBranchId(Request $request): ?string
    {
        $session = session('raw_branch_id');
        if ($session) return (string) $session;
        if (! canSeeAllBranches()) return null;
        return $request->input('branch_id') ?: null;
    }

    public function index(Request $request)
    {
        $this->guard();

        $branchId = $this->resolveBranchId($request);

        $batchQuery = RawReservationImportBatch::query();
        if ($branchId) {
            $batchQuery->where('raw_branch_id', $branchId);
        }
        $batches = $batchQuery->orderByDesc('uploaded_at')->limit(10)->get();

        $activeBatchId = $request->input('batch_id') ?: optional($batches->first())->id;
        $stateFilter = $request->input('state', 'pending');
        $searchFilter = trim((string) $request->input('q'));
        $monthFilter = trim((string) $request->input('month')); // format Y-m (mis. 2026-03)
        $dateFromFilter = trim((string) $request->input('date_from'));
        $dateToFilter = trim((string) $request->input('date_to'));

        $stagingQuery = RawCusReservationStaging::with(['doctor', 'batch', 'branch', 'promotedReservation.customer']);
        if ($branchId) {
            $stagingQuery->where('raw_branch_id', $branchId);
        }
        if ($activeBatchId) {
            $stagingQuery->where('batch_id', $activeBatchId);
        }
        if ($stateFilter && $stateFilter !== 'all') {
            $stagingQuery->where('state', $stateFilter);
        }
        if ($searchFilter !== '') {
            $stagingQuery->where(function ($q) use ($searchFilter) {
                $q->where('patient_name', 'like', "%{$searchFilter}%")
                  ->orWhere('patient_phone', 'like', "%{$searchFilter}%")
                  ->orWhere('doctor_name_raw', 'like', "%{$searchFilter}%");
            });
        }
        // Filter bulan: format Y-m (input type="month"). Match treatment_date.
        if ($monthFilter !== '' && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
            $stagingQuery->where('treatment_date', 'like', $monthFilter . '%');
        }
        // Filter rentang tanggal (overrides month kalau di-set bersamaan).
        if ($dateFromFilter !== '') {
            $stagingQuery->whereDate('treatment_date', '>=', $dateFromFilter);
        }
        if ($dateToFilter !== '') {
            $stagingQuery->whereDate('treatment_date', '<=', $dateToFilter);
        }

        // Daftar bulan unik yg ada di batch aktif → dropdown picker.
        $availableMonths = [];
        if ($activeBatchId) {
            $availableMonths = RawCusReservationStaging::where('batch_id', $activeBatchId)
                ->whereNotNull('treatment_date')
                ->selectRaw("DATE_FORMAT(treatment_date, '%Y-%m') as ym")
                ->distinct()
                ->orderBy('ym')
                ->pluck('ym')
                ->toArray();
        }

        $stagingRows = $stagingQuery
            ->orderByRaw("FIELD(state, 'pending', 'error', 'promoted', 'skipped')")
            ->orderBy('treatment_date')
            ->orderBy('start_hour')
            ->orderBy('source_row_index')
            ->paginate(50)
            ->withQueryString();

        // Phone-based dedup detection — match staging phone vs raw_customer
        // existing. Pakai 1 query whereIn buat semua phone di halaman supaya
        // gak N+1. Output: $existingByPhone[$normalizedPhone] = customer.
        $phones = $stagingRows->getCollection()
            ->pluck('patient_phone')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $existingByPhone = collect();
        $upcomingByCustomer = collect();
        if (! empty($phones)) {
            $existingByPhone = \App\Models\RawCustomer::whereIn('phone', $phones)
                ->with('latestReservation')
                ->get(['id', 'name', 'phone', 'email', 'raw_branch_id'])
                ->keyBy('phone');

            // Fetch reservasi UPCOMING (date >= today, exclude cancelled) untuk
            // tiap customer existing — dipakai buat warn user "nomor ini sudah
            // ada reservasi" supaya tidak nge-double schedule pasien yg sama.
            $customerIds = $existingByPhone->pluck('id')->values()->all();
            if (! empty($customerIds)) {
                $upcomingByCustomer = \App\Models\RawCusReservation::with('doctor:id,name')
                    ->whereIn('raw_customer_id', $customerIds)
                    ->whereDate('date', '>=', now()->toDateString())
                    ->whereNotIn('status', [\App\Models\RawCusReservation::STATUS_CANCELLED])
                    ->orderBy('date')
                    ->orderBy('start_hour')
                    ->get(['id', 'raw_customer_id', 'raw_doctor_id', 'date', 'start_hour', 'status'])
                    ->groupBy('raw_customer_id');
            }
        }

        // Filter opsional: sembunyikan row yg customer-nya sudah punya reservasi.
        $hideExistingFilter = (bool) $request->boolean('hide_existing');
        if ($hideExistingFilter && $upcomingByCustomer->isNotEmpty()) {
            $busyCustomerIds = $upcomingByCustomer->keys()->all();
            $busyPhones = $existingByPhone
                ->filter(fn ($c) => in_array($c->id, $busyCustomerIds, true))
                ->keys()
                ->all();
            if (! empty($busyPhones)) {
                $stagingRows->setCollection(
                    $stagingRows->getCollection()->reject(
                        fn ($r) => $r->patient_phone && in_array($r->patient_phone, $busyPhones, true)
                    )->values()
                );
            }
        }

        // Dropdown dokter (untuk fix row tanpa resolved doctor).
        $doctors = RawDoctor::orderBy('name')->get();

        // Branches list — admin global perlu dropdown.
        $branches = $branchId
            ? RawBranch::where('id', $branchId)->get()
            : RawBranch::orderBy('name')->get();

        $activeBranch = $branchId ? RawBranch::find($branchId) : null;

        // Counter ringkasan untuk batch aktif.
        $counters = [
            'pending' => 0,
            'promoted' => 0,
            'skipped' => 0,
            'error' => 0,
        ];
        if ($activeBatchId) {
            $rawCounters = RawCusReservationStaging::where('batch_id', $activeBatchId)
                ->select('state', DB::raw('count(*) as c'))
                ->groupBy('state')
                ->pluck('c', 'state')
                ->toArray();
            foreach ($counters as $k => $_) {
                $counters[$k] = (int) ($rawCounters[$k] ?? 0);
            }
        }

        // Hitung jumlah row pending yg cocok dgn filter aktif — dipakai untuk
        // tombol Auto-Process supaya user tahu "Proses Otomatis (N dari M)".
        $filteredPendingCount = 0;
        if ($activeBatchId) {
            $fpq = RawCusReservationStaging::where('batch_id', $activeBatchId)
                ->where('state', RawCusReservationStaging::STATE_PENDING);
            if ($monthFilter !== '' && preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
                $fpq->where('treatment_date', 'like', $monthFilter . '%');
            }
            if ($dateFromFilter !== '') {
                $fpq->whereDate('treatment_date', '>=', $dateFromFilter);
            }
            if ($dateToFilter !== '') {
                $fpq->whereDate('treatment_date', '<=', $dateToFilter);
            }
            if ($searchFilter !== '') {
                $fpq->where(function ($q) use ($searchFilter) {
                    $q->where('patient_name', 'like', "%{$searchFilter}%")
                      ->orWhere('patient_phone', 'like', "%{$searchFilter}%")
                      ->orWhere('doctor_name_raw', 'like', "%{$searchFilter}%");
                });
            }
            $filteredPendingCount = $fpq->count();
        }

        return view('crud.reservation._import', compact(
            'batches',
            'activeBatchId',
            'stagingRows',
            'existingByPhone',
            'upcomingByCustomer',
            'hideExistingFilter',
            'stateFilter',
            'searchFilter',
            'monthFilter',
            'dateFromFilter',
            'dateToFilter',
            'availableMonths',
            'filteredPendingCount',
            'doctors',
            'branches',
            'activeBranch',
            'branchId',
            'counters'
        ));
    }

    public function upload(Request $request)
    {
        $this->guard();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $branchId = $this->resolveBranchId($request);
        if (! $branchId) {
            return back()->withErrors(['file' => 'Cabang tidak terdeteksi. Pilih cabang dulu di filter atau hubungi admin.']);
        }

        // ── Optional override mapping dari Column Mapping Wizard ──
        // Format: column_mapping = JSON {patient_name: 3, tanggal_treatment: 2, ...}
        // Kalau ada → skip auto-detect, pakai mapping manual.
        $options = [];
        if ($mappingJson = $request->input('column_mapping')) {
            $decoded = is_string($mappingJson) ? json_decode($mappingJson, true) : $mappingJson;
            if (is_array($decoded) && ! empty($decoded)) {
                $options['column_mapping'] = $decoded;
            }
        }
        if ($request->filled('header_row_index')) {
            $options['header_row_index'] = (int) $request->input('header_row_index');
        }

        try {
            $importer = new ReservationCsvImporter();
            $batch = $importer->import(
                $request->file('file'),
                $branchId,
                (string) (session('id') ?: ''),
                $options
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal mem-parse file: ' . $e->getMessage()]);
        }

        $msg = "Berhasil impor {$batch->parsed_rows} baris (skip {$batch->skipped_rows} baris noise) dari {$batch->file_name}.";

        // Diagnostic: warn kalau kolom kunci tidak ke-detect — bantu user
        // diagnose kalau Jam / Dokter / dst muncul kosong di staging.
        $warnings = [];
        if ($importer->lastDiagnostic) {
            $mapping = $importer->lastDiagnostic['mapping'] ?? [];
            $headers = $importer->lastDiagnostic['headers'] ?? [];
            $headerList = implode(', ', array_filter($headers));
            $criticals = [
                'start_hour' => 'Jam Mulai Praktek',
                'tanggal_treatment' => 'Tanggal Treatment',
                'patient_name' => 'Nama Pasien',
                'doctor' => 'Dokter',
            ];
            foreach ($criticals as $key => $label) {
                if (! isset($mapping[$key])) {
                    $warnings[] = "Kolom '{$label}' tidak terdeteksi dari header.";
                }
            }
            if ($warnings) {
                $warnings[] = 'Header yang terbaca: ' . ($headerList ?: '(kosong)');
            }
        }

        $redirect = redirect()->route('rawreservation.import', ['batch_id' => $batch->id])
            ->with('success', $msg);
        if ($warnings) {
            $redirect = $redirect->with('warning', implode(' | ', $warnings));
        }
        return $redirect;
    }

    public function update(Request $request, string $id)
    {
        $this->guard();

        $row = RawCusReservationStaging::findOrFail($id);
        $this->guardRowAccess($row);

        $data = $request->validate([
            'patient_name' => 'nullable|string|max:255',
            'patient_phone' => 'nullable|string|max:64',
            'patient_email' => 'nullable|email|max:255',
            'patient_nik' => 'nullable|string|max:32',
            'raw_branch_id' => 'nullable|exists:raw_branch,id',
            'treatment_date' => 'nullable|date',
            'start_hour' => 'nullable|date_format:H:i',
            'resolved_raw_doctor_id' => 'nullable|exists:raw_doctor,id',
            'mapped_status' => 'nullable|string|in:pending,confirmed,cancelled,completed,waitlist',
            'tindakan_text' => 'nullable|string|max:65535',
            'total_reservasi' => 'nullable|numeric|min:0',
            'is_waiting' => 'nullable|boolean',
        ]);

        // Normalisasi start_hour ke H:i:s untuk konsistensi dengan kolom TIME.
        if (! empty($data['start_hour'])) {
            $data['start_hour'] = $data['start_hour'] . ':00';
        }

        $row->fill($data);
        $row->updated_at = now();
        $row->updated_by = session('id');
        $row->save();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'row' => $row->fresh()]);
        }
        return back()->with('success', 'Baris staging diperbarui.');
    }

    /**
     * Simpan form reservasi sebagai DRAFT — tidak langsung promote.
     * User pilih slot + layanan di form reservasi penuh, klik "Simpan Draft" →
     * data full di-simpan ke staging.draft_payload (JSON). Bulk-promote nanti
     * replay payload ini ke RawCusReservation (termasuk services + end_hour
     * yg tidak fit di staging columns).
     *
     * Endpoint ini terima POST dgn body sama persis dgn ReservationController@store
     * tapi tidak create reservasi — cuma persist ke staging.
     */
    public function saveDraft(Request $request, string $id)
    {
        $this->guard();

        $row = \App\Models\RawCusReservationStaging::findOrFail($id);
        $this->guardRowAccess($row);

        // Full payload (kecuali CSRF / method override) → simpan as-is.
        $payload = $request->except(['_token', '_method', 'staging_id']);

        // Mirror display fields ke staging columns supaya tabel staging tampil
        // info terbaru (dokter, tanggal, jam, dst). End-user lihat row sudah
        // ter-config tanpa harus klik draft lagi.
        $row->draft_payload = $payload;
        if ($v = $request->input('raw_branch_id')) $row->raw_branch_id = $v;
        if ($v = $request->input('raw_doctor_id')) $row->resolved_raw_doctor_id = $v;
        if ($v = $request->input('date'))          $row->treatment_date = $v;
        if ($v = $request->input('start_hour'))    $row->start_hour = strlen($v) === 5 ? $v . ':00' : $v;
        if ($v = $request->input('keterangan'))    $row->tindakan_text = $v;
        if ($v = $request->input('status'))        $row->mapped_status = $v;
        $row->is_waiting = (int) $request->input('is_waiting', 0);
        $row->updated_at = now();
        $row->updated_by = session('id');
        $row->save();

        $batchId = $row->batch_id;

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'message' => 'Draft tersimpan']);
        }

        return redirect()->route('rawreservation.import', ['batch_id' => $batchId])
            ->with('success', 'Draft tersimpan. Bisa di-bulk promote nanti via "Promote Terpilih".');
    }

    public function promoteOne(Request $request, string $id)
    {
        $this->guard();

        $row = RawCusReservationStaging::findOrFail($id);
        $this->guardRowAccess($row);

        $promoter = new ReservationPromoter();
        $res = $promoter->promote($row, [
            'force' => $request->boolean('force'),
            'user_id' => session('id'),
        ]);

        if ($request->wantsJson()) {
            return response()->json($res, $res['ok'] ? 200 : 422);
        }
        return $res['ok']
            ? back()->with('success', $res['message'])
            : back()->withErrors(['promote' => $res['message']]);
    }

    public function skipOne(Request $request, string $id)
    {
        $this->guard();

        $row = RawCusReservationStaging::findOrFail($id);
        $this->guardRowAccess($row);

        $row->state = RawCusReservationStaging::STATE_SKIPPED;
        $row->updated_at = now();
        $row->updated_by = session('id');
        $row->save();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return back()->with('success', 'Baris di-skip.');
    }

    public function promoteBulk(Request $request)
    {
        $this->guard();

        $ids = $this->validateBulkIds($request);
        $promoter = new ReservationPromoter();
        $summary = $promoter->promoteBulk($ids, [
            'force' => $request->boolean('force'),
            'user_id' => session('id'),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'summary' => $summary]);
        }
        $msg = "Berhasil promote: {$summary['promoted']}. Konflik slot: {$summary['conflict']}. Error: {$summary['errors']}.";
        return back()->with('success', $msg);
    }

    public function skipBulk(Request $request)
    {
        $this->guard();

        $ids = $this->validateBulkIds($request);
        $affected = RawCusReservationStaging::whereIn('id', $ids)
            ->where('state', RawCusReservationStaging::STATE_PENDING)
            ->update([
                'state' => RawCusReservationStaging::STATE_SKIPPED,
                'updated_at' => now(),
                'updated_by' => session('id'),
            ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'affected' => $affected]);
        }
        return back()->with('success', "{$affected} baris di-skip.");
    }

    /**
     * Auto-process semua row pending dalam batch via smart rule-based engine.
     * Klasifikasikan tiap row jadi promote/skip/review berdasarkan kelengkapan
     * data, ketersediaan slot, dan anti double-book. Output summary di flash
     * supaya UI tampilkan hasil "X promoted · Y skipped · Z perlu review".
     */
    public function autoProcess(Request $request, string $batchId)
    {
        $this->guard();

        $batch = \App\Models\RawReservationImportBatch::findOrFail($batchId);
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch && $batch->raw_branch_id !== $sessionBranch) {
            abort(403, 'Batch ini bukan dari cabang Anda.');
        }

        // Batch besar bisa makan waktu (sequential per row). Long-term pakai
        // queue/job; MVP: increase max_execution_time sementara.
        @set_time_limit(300);

        // Filter opsional — user bisa proses subset (mis. cuma bulan Juli 2026).
        $filter = $request->only(['month', 'date_from', 'date_to', 'q']);

        $summary = (new \App\Services\ReservationAutoProcessor())
            ->processBatch($batchId, session('id'), $filter);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'summary' => $summary]);
        }

        // Preserve filter di redirect supaya user kembali ke view yg sama.
        $redirectQuery = array_filter(array_merge(
            ['batch_id' => $batchId],
            $filter
        ), fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('rawreservation.import', $redirectQuery)
            ->with('auto_process_summary', $summary);
    }

    /**
     * Re-run resolveByIdOrName untuk SEMUA staging row pending dgn
     * resolved_raw_doctor_id NULL. Berguna saat user baru tambah dokter
     * ke master setelah upload — gak perlu re-upload CSV.
     */
    public function reMatchDoctors(Request $request, string $batchId)
    {
        $this->guard();

        $batch = \App\Models\RawReservationImportBatch::findOrFail($batchId);
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch && $batch->raw_branch_id !== $sessionBranch) {
            abort(403, 'Batch ini bukan dari cabang Anda.');
        }

        $rows = \App\Models\RawCusReservationStaging::where('batch_id', $batchId)
            ->where('state', \App\Models\RawCusReservationStaging::STATE_PENDING)
            ->whereNull('resolved_raw_doctor_id')
            ->whereNotNull('doctor_name_raw')
            ->get();

        $matched = 0;
        $stillUnmatched = 0;
        foreach ($rows as $row) {
            $doc = \App\Models\RawDoctor::resolveByIdOrName($row->doctor_name_raw);
            if ($doc) {
                $row->resolved_raw_doctor_id = $doc->id;
                // Clean review/auto warnings yg terkait dokter — sudah resolved
                if (is_array($row->parse_warnings)) {
                    $row->parse_warnings = array_values(array_filter(
                        $row->parse_warnings,
                        fn ($w) => ! str_contains((string) $w, 'doctor_not_resolved')
                            && ! str_contains((string) $w, 'doctor_not_found')
                    ));
                }
                $row->updated_at = now();
                $row->updated_by = session('id');
                $row->save();
                $matched++;
            } else {
                $stillUnmatched++;
            }
        }

        $summary = [
            'matched'         => $matched,
            'still_unmatched' => $stillUnmatched,
            'total_checked'   => $rows->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'summary' => $summary]);
        }

        $msg = "Re-Match Dokter selesai: {$matched} ke-match";
        if ($stillUnmatched > 0) {
            $msg .= ", {$stillUnmatched} masih belum match (tambah ke master Dokter dulu).";
        } else {
            $msg .= '.';
        }
        return redirect()
            ->route('rawreservation.import', ['batch_id' => $batchId])
            ->with('success', $msg);
    }

    public function destroyBatch(Request $request, string $id)
    {
        $this->guard();

        $batch = RawReservationImportBatch::findOrFail($id);
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch && $batch->raw_branch_id !== $sessionBranch) {
            abort(403, 'Batch ini bukan dari cabang Anda.');
        }

        DB::transaction(function () use ($batch) {
            RawCusReservationStaging::where('batch_id', $batch->id)->delete();
            $batch->delete();
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }
        return redirect()->route('rawreservation.import')
            ->with('success', 'Batch dihapus.');
    }

    private function guardRowAccess(RawCusReservationStaging $row): void
    {
        $sessionBranch = session('raw_branch_id');
        if ($sessionBranch && $row->raw_branch_id !== $sessionBranch) {
            abort(403, 'Baris ini bukan dari cabang Anda.');
        }
    }

    private function validateBulkIds(Request $request): array
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);
        return $data['ids'];
    }
}
