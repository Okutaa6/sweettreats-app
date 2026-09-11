<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ResolvesDoctorAvailability;
use App\Models\RawCusReservation;
use App\Models\RawCusReservationStaging;
use App\Models\RawCustomer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Smart auto-processor untuk batch impor reservasi.
 *
 * Untuk tiap row state=pending, klasifikasikan jadi 3 kategori:
 *
 *   - PROMOTE: auto-create RawCusReservation (+ RawCustomer kalau perlu)
 *     - syarat: doctor matched, patient lengkap, slot bisa di-alokasikan
 *   - SKIP: status_raw=cancelled atau customer sudah punya reservasi di
 *     tanggal yg sama (anti double-book)
 *   - REVIEW: leave pending dgn warning, user handle manual
 *     (dokter not resolved, dokter cuti, slot penuh, dll)
 *
 * Engine deterministik — tidak panggil LLM external. Reuse logic dari trait
 * ResolvesDoctorAvailability (slot allocation + working ranges + booked).
 */
class ReservationAutoProcessor
{
    use ResolvesDoctorAvailability;

    /** Durasi default per reservasi auto-promote (menit). */
    private const DEFAULT_DURATION = 30;

    private array $summary = [
        'promoted' => 0,
        'skipped'  => 0,
        'review'   => 0,
        'errors'   => 0,
        'details'  => [],
    ];

    /**
     * Process row pending dalam batch (optional filter).
     *
     * @param  array{month?:string, date_from?:string, date_to?:string, q?:string}  $filter
     *         Filter opsional supaya user bisa proses subset (mis. cuma bulan
     *         tertentu / rentang tanggal / nama pasien).
     * @return array{promoted:int, skipped:int, review:int, errors:int, details:array}
     */
    public function processBatch(string $batchId, ?string $userId = null, array $filter = []): array
    {
        $query = RawCusReservationStaging::where('batch_id', $batchId)
            ->where('state', RawCusReservationStaging::STATE_PENDING);

        // Apply filter — mirror logic dari ReservationImportController@index
        // supaya hasil filter dari UI sama dgn yg di-process auto-engine.
        $month = trim((string) ($filter['month'] ?? ''));
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->where('treatment_date', 'like', $month . '%');
        }
        $dateFrom = trim((string) ($filter['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $query->whereDate('treatment_date', '>=', $dateFrom);
        }
        $dateTo = trim((string) ($filter['date_to'] ?? ''));
        if ($dateTo !== '') {
            $query->whereDate('treatment_date', '<=', $dateTo);
        }
        $search = trim((string) ($filter['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                  ->orWhere('patient_phone', 'like', "%{$search}%")
                  ->orWhere('doctor_name_raw', 'like', "%{$search}%");
            });
        }

        $rows = $query
            ->orderBy('treatment_date')
            ->orderBy('start_hour')
            ->get();

        foreach ($rows as $row) {
            try {
                $decision = $this->classify($row);
                $this->apply($row, $decision, $userId);
            } catch (\Throwable $e) {
                $this->summary['errors']++;
                $this->summary['details'][] = [
                    'row_id'    => $row->id,
                    'patient'   => $row->patient_name,
                    'action'    => 'error',
                    'reason'    => 'exception: ' . $e->getMessage(),
                ];
            }
        }

        return $this->summary;
    }

    /**
     * Decide what to do with a row. Pure function — no side effects.
     *
     * @return array{action:string, reason?:string, slot?:array}
     */
    private function classify(RawCusReservationStaging $row): array
    {
        // ── 1. Skip: CSV status cancelled ──
        if ($row->mapped_status === RawCusReservation::STATUS_CANCELLED) {
            return ['action' => 'skip', 'reason' => 'csv_cancelled'];
        }

        // ── 2. Data lengkap? ──
        if (! $row->patient_name) {
            return ['action' => 'review', 'reason' => 'patient_name_missing'];
        }
        if (! $row->patient_phone) {
            return ['action' => 'review', 'reason' => 'patient_phone_missing'];
        }
        if (! $row->resolved_raw_doctor_id) {
            return ['action' => 'review', 'reason' => 'doctor_not_resolved'];
        }

        // ── 3. Anti double-book: customer existing sudah punya reservasi di
        //      tanggal yg sama (only kalau row punya tanggal). ──
        if ($row->treatment_date) {
            $existing = RawCustomer::where('phone', $row->patient_phone)->first();
            if ($existing) {
                $sameDay = RawCusReservation::where('raw_customer_id', $existing->id)
                    ->whereDate('date', $row->treatment_date)
                    ->whereNotIn('status', [RawCusReservation::STATUS_CANCELLED])
                    ->exists();
                if ($sameDay) {
                    return ['action' => 'skip', 'reason' => 'same_date_reservation_exists'];
                }
            }
        }

        // ── 4. Waiting list path: skip slot allocation ──
        if ((int) $row->is_waiting === 1) {
            return ['action' => 'promote', 'slot' => null];
        }

        // ── 5. Non-waiting: butuh tanggal ──
        if (! $row->treatment_date) {
            return ['action' => 'review', 'reason' => 'treatment_date_missing'];
        }

        $dateStr = $row->treatment_date->format('Y-m-d');

        // ── 6. Cek apakah dokter praktek di hari itu ──
        $ranges = $this->resolveWorkingRanges($row->raw_branch_id, $row->resolved_raw_doctor_id, $dateStr);
        if ($ranges->isEmpty()) {
            return ['action' => 'review', 'reason' => 'doctor_not_working_that_day'];
        }

        // ── 7. Allocate slot via existing logic (handle conflict + cursor) ──
        $defaultStart = $row->start_hour ? substr($row->start_hour, 0, 5) : null;
        $assigned = $this->assignFamilySlots(
            $row->raw_branch_id,
            $dateStr,
            [['key' => 0, 'doctor_id' => $row->resolved_raw_doctor_id, 'duration' => self::DEFAULT_DURATION]],
            $defaultStart
        );
        $slot = $assigned[0] ?? ['waiting' => true, 'reason' => 'unknown'];

        if (! empty($slot['waiting'])) {
            // Map reason dari trait ke reason kanonik kita
            $traitReason = $slot['reason'] ?? '';
            $reason = match (true) {
                str_contains($traitReason, 'tidak praktik') => 'doctor_not_working_that_day',
                str_contains($traitReason, 'penuh')         => 'all_slots_full',
                default                                      => 'slot_unavailable',
            };
            return ['action' => 'review', 'reason' => $reason];
        }

        return ['action' => 'promote', 'slot' => $slot];
    }

    /**
     * Apply the decision — mutate row + create reservation kalau perlu.
     */
    private function apply(RawCusReservationStaging $row, array $decision, ?string $userId): void
    {
        $action = $decision['action'];

        if ($action === 'skip') {
            $this->markSkipped($row, $decision['reason'], $userId);
            $this->summary['skipped']++;
            $this->summary['details'][] = [
                'row_id'  => $row->id,
                'patient' => $row->patient_name,
                'action'  => 'skip',
                'reason'  => $decision['reason'],
            ];
            return;
        }

        if ($action === 'review') {
            $this->markReview($row, $decision['reason'], $userId);
            $this->summary['review']++;
            $this->summary['details'][] = [
                'row_id'  => $row->id,
                'patient' => $row->patient_name,
                'action'  => 'review',
                'reason'  => $decision['reason'],
            ];
            return;
        }

        // action === 'promote'
        $reservationId = $this->doPromote($row, $decision['slot'] ?? null, $userId);
        if ($reservationId) {
            $this->summary['promoted']++;
            $this->summary['details'][] = [
                'row_id'         => $row->id,
                'patient'        => $row->patient_name,
                'action'         => 'promote',
                'reservation_id' => $reservationId,
            ];
        } else {
            $this->summary['errors']++;
        }
    }

    /**
     * Promote row → create reservasi pakai slot yg sudah di-alokasikan
     * (atau null untuk waiting list). Re-use resolveOrCreateCustomer dari
     * ReservationPromoter supaya logic create RawCustomer konsisten.
     */
    private function doPromote(RawCusReservationStaging $row, ?array $slot, ?string $userId): ?string
    {
        $isWaiting = (int) $row->is_waiting === 1 || $slot === null;
        $reservationId = null;

        DB::transaction(function () use ($row, $slot, $isWaiting, $userId, &$reservationId) {
            // Customer: reuse logic existing (cari by phone, create kalau ga ada)
            $customer = (new ReservationPromoter())->resolveOrCreateCustomer($row);

            // Status mapping
            $status = $isWaiting
                ? RawCusReservation::STATUS_WAITLIST
                : ($row->mapped_status ?: RawCusReservation::STATUS_PENDING);

            // Keterangan: tindakan + patient_note + PIC
            $keterangan = trim((string) $row->tindakan_text);
            if ($row->patient_note) {
                $keterangan = $keterangan
                    ? ($keterangan . ' — ' . $row->patient_note)
                    : $row->patient_note;
            }
            if ($row->pic_name) {
                $keterangan = ($keterangan ? ($keterangan . ' ') : '') . '[PIC: ' . $row->pic_name . ']';
            }

            $reservation = RawCusReservation::create([
                'raw_branch_id'    => $row->raw_branch_id,
                'raw_customer_id'  => $customer->id,
                'raw_doctor_id'    => $row->resolved_raw_doctor_id,
                'date'             => $isWaiting ? null : $row->treatment_date?->format('Y-m-d'),
                'start_hour'       => $isWaiting ? null : ($slot['start'] ?? null),
                'end_hour'         => $isWaiting ? null : ($slot['end'] ?? null),
                'status'           => $status,
                'is_waiting'       => $isWaiting ? 1 : 0,
                'keterangan'       => $keterangan ?: null,
                'total_reservasi'  => $row->total_reservasi,
                'created_at'       => now(),
                'updated_at'       => now(),
                'updated_by'       => $userId,
                // Jejak: dibuat mesin auto-process, tapi atas perintah $userId
                // yang menekan "Proses otomatis" — created_via yang membedakan
                // ini dari promote satuan.
                'created_by'       => $userId,
                'created_via'      => RawCusReservation::VIA_IMPORT_AUTO,
            ]);
            $reservationId = $reservation->id;

            // Update staging
            $row->state = RawCusReservationStaging::STATE_PROMOTED;
            $row->promoted_to_id = $reservationId;
            // Sync display columns supaya tabel staging tampilkan slot yg dialokasi
            if (! $isWaiting && ! empty($slot['start'])) {
                $row->start_hour = $slot['start'] . ':00';
            }
            $row->updated_at = now();
            $row->updated_by = $userId;
            $this->appendWarning($row, 'auto_promoted');
            $row->save();

            // Auto-assign cabang ke customer kalau orphan
            RawCustomer::where('id', $customer->id)
                ->whereNull('raw_branch_id')
                ->update(['raw_branch_id' => $row->raw_branch_id]);

            // Increment batch counter
            DB::table('raw_reservation_import_batch')
                ->where('id', $row->batch_id)
                ->increment('promoted_rows');
        });

        return $reservationId;
    }

    private function markSkipped(RawCusReservationStaging $row, string $reason, ?string $userId): void
    {
        $row->state = RawCusReservationStaging::STATE_SKIPPED;
        $row->updated_at = now();
        $row->updated_by = $userId;
        $this->appendWarning($row, 'auto_skipped: ' . $reason);
        $row->save();
    }

    private function markReview(RawCusReservationStaging $row, string $reason, ?string $userId): void
    {
        // Stay pending — cuma append warning supaya UI tahu kenapa
        $row->updated_at = now();
        $row->updated_by = $userId;
        $this->appendWarning($row, 'review: ' . $reason);
        $row->save();
    }

    private function appendWarning(RawCusReservationStaging $row, string $warning): void
    {
        $warnings = is_array($row->parse_warnings) ? $row->parse_warnings : [];
        // Dedupe — kalau warning identik sudah ada, skip
        if (! in_array($warning, $warnings, true)) {
            $warnings[] = $warning;
        }
        $row->parse_warnings = $warnings;
    }
}
