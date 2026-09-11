<?php

namespace App\Services;

use App\Models\RawCusReservation;
use App\Models\RawCusReservationStaging;
use App\Models\RawCustomer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Promote satu (atau banyak) baris staging ke raw_cus_reservation.
 *
 * - Cek konflik slot per dokter+tanggal+jam (skip kalau is_waiting=1).
 * - Resolve/buat RawCustomer pakai pasangan phone+name.
 * - Tulis RawCusReservation + update staging.state=promoted.
 */
class ReservationPromoter
{
    public const ERR_CONFLICT = 'slot_conflict';
    public const ERR_NOT_PROMOTABLE = 'not_promotable';
    public const ERR_ALREADY_PROMOTED = 'already_promoted';

    /**
     * @param  array{force?: bool, user_id?: string|null}  $opts
     * @return array{ok: bool, error?: string, message?: string, reservation_id?: string, conflict?: array}
     */
    public function promote(RawCusReservationStaging $row, array $opts = []): array
    {
        $force = (bool) ($opts['force'] ?? false);
        $userId = $opts['user_id'] ?? null;

        $row->refresh();

        if ($row->state === RawCusReservationStaging::STATE_PROMOTED) {
            return [
                'ok' => false,
                'error' => self::ERR_ALREADY_PROMOTED,
                'message' => 'Baris ini sudah pernah dipromote.',
                'reservation_id' => $row->promoted_to_id,
            ];
        }

        // ── Draft path: row ada draft_payload (user save dari form lengkap) ──
        // Replay payload → bikin reservasi dgn services + end_hour + customer
        // yg sudah dipilih user di form. Skip promotable check karena draft
        // sudah dianggap "siap" oleh user.
        if (! empty($row->draft_payload) && is_array($row->draft_payload)) {
            return $this->promoteFromDraft($row, $opts);
        }

        if (! $row->isPromotable()) {
            return [
                'ok' => false,
                'error' => self::ERR_NOT_PROMOTABLE,
                'message' => 'Baris belum siap dipromote (cek nama pasien, dokter, dan tanggal).',
            ];
        }

        $isWaiting = (bool) $row->is_waiting || ! $row->treatment_date;

        // Konflik slot — hanya berlaku untuk reservasi terjadwal (bukan waiting list).
        if (! $isWaiting && $row->treatment_date && $row->start_hour) {
            $conflict = $this->findConflict(
                $row->resolved_raw_doctor_id,
                $row->treatment_date->format('Y-m-d'),
                $row->start_hour
            );
            if ($conflict && ! $force) {
                return [
                    'ok' => false,
                    'error' => self::ERR_CONFLICT,
                    'message' => 'Slot bentrok dengan reservasi lain.',
                    'conflict' => [
                        'id' => $conflict->id,
                        'patient' => optional($conflict->customer)->name,
                        'date' => optional($conflict->date)->format('Y-m-d'),
                        'start_hour' => $conflict->start_hour,
                    ],
                ];
            }
        }

        $reservationId = null;
        DB::transaction(function () use ($row, $isWaiting, $userId, &$reservationId) {
            $customer = $this->resolveOrCreateCustomer($row);

            // Map status: kalau waiting list, override → waitlist.
            $status = $isWaiting
                ? RawCusReservation::STATUS_WAITLIST
                : ($row->mapped_status ?: RawCusReservation::STATUS_PENDING);

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
                'raw_branch_id' => $row->raw_branch_id,
                'raw_customer_id' => $customer->id,
                'raw_doctor_id' => $row->resolved_raw_doctor_id,
                'date' => $isWaiting ? null : $row->treatment_date?->format('Y-m-d'),
                'start_hour' => $isWaiting ? null : $row->start_hour,
                'end_hour' => null,
                'status' => $status,
                'is_waiting' => $isWaiting ? 1 : 0,
                'keterangan' => $keterangan ?: null,
                'total_reservasi' => $row->total_reservasi,
                'created_at' => now(),
                'updated_at' => now(),
                'updated_by' => $userId,
                // Jejak: yang menekan tombol Promote-lah pembuatnya.
                'created_by' => $userId,
                'created_via' => RawCusReservation::VIA_IMPORT,
            ]);

            $reservationId = $reservation->id;

            $row->state = RawCusReservationStaging::STATE_PROMOTED;
            $row->promoted_to_id = $reservationId;
            $row->updated_at = now();
            $row->updated_by = $userId;
            $row->save();

            // Auto-assign cabang ke pasien kalau masih orphan (pola sama dgn
            // ReservationController::store()).
            RawCustomer::where('id', $customer->id)
                ->whereNull('raw_branch_id')
                ->update(['raw_branch_id' => $row->raw_branch_id]);

            // Increment counter di batch.
            DB::table('raw_reservation_import_batch')
                ->where('id', $row->batch_id)
                ->increment('promoted_rows');
        });

        return [
            'ok' => true,
            'reservation_id' => $reservationId,
            'message' => 'Reservasi berhasil dibuat.',
        ];
    }

    /**
     * Promote dari draft payload (form reservasi lengkap yg di-Simpan Draft).
     * Replay full POST body → bikin RawCusReservation + RawCusResService.
     */
    private function promoteFromDraft(RawCusReservationStaging $row, array $opts = []): array
    {
        $force = (bool) ($opts['force'] ?? false);
        $userId = $opts['user_id'] ?? null;
        $payload = $row->draft_payload;

        // Validasi minimum: customer, branch, dokter, tanggal.
        $customerId = $payload['raw_customer_id'] ?? null;
        $branchId = $payload['raw_branch_id'] ?? $row->raw_branch_id;
        $doctorId = $payload['raw_doctor_id'] ?? $row->resolved_raw_doctor_id;
        $isWaiting = (int) ($payload['is_waiting'] ?? 0) === 1;
        $date = $payload['date'] ?? null;
        $startHour = $payload['start_hour'] ?? null;
        $endHour = $payload['end_hour'] ?? null;

        if (! $customerId) {
            return [
                'ok' => false,
                'error' => self::ERR_NOT_PROMOTABLE,
                'message' => 'Draft belum punya pasien — buka draft & pilih pasien dulu.',
            ];
        }
        if (! $branchId || ! $doctorId) {
            return [
                'ok' => false,
                'error' => self::ERR_NOT_PROMOTABLE,
                'message' => 'Draft belum lengkap (cabang / dokter belum di-set).',
            ];
        }
        if (! $isWaiting && (! $date || ! $startHour)) {
            return [
                'ok' => false,
                'error' => self::ERR_NOT_PROMOTABLE,
                'message' => 'Draft belum lengkap (tanggal / jam belum di-set).',
            ];
        }

        // Cek konflik slot — sama logika dgn promote() non-draft.
        if (! $isWaiting && $date && $startHour) {
            $conflict = $this->findConflict($doctorId, $date, $startHour);
            if ($conflict && ! $force) {
                return [
                    'ok' => false,
                    'error' => self::ERR_CONFLICT,
                    'message' => 'Slot bentrok dengan reservasi lain.',
                    'conflict' => [
                        'id' => $conflict->id,
                        'patient' => optional($conflict->customer)->name,
                        'date' => optional($conflict->date)->format('Y-m-d'),
                        'start_hour' => $conflict->start_hour,
                    ],
                ];
            }
        }

        $reservationId = null;
        DB::transaction(function () use ($row, $payload, $customerId, $branchId, $doctorId, $isWaiting, $date, $startHour, $endHour, $userId, &$reservationId) {
            $status = $isWaiting
                ? RawCusReservation::STATUS_WAITLIST
                : ($payload['status'] ?? RawCusReservation::STATUS_PENDING);

            $reservation = RawCusReservation::create([
                'raw_branch_id'       => $branchId,
                'raw_customer_id'     => $customerId,
                'raw_doctor_id'       => $doctorId,
                'date'                => $isWaiting ? null : $date,
                'start_hour'          => $isWaiting ? null : $startHour,
                'end_hour'            => $isWaiting ? null : $endHour,
                'status'              => $status,
                'is_waiting'          => $isWaiting ? 1 : 0,
                'keterangan'          => $payload['keterangan'] ?? null,
                'raw_promo_id'        => $payload['raw_promo_id'] ?? null,
                'marketing_promo_id'  => $payload['marketing_promo_id'] ?? null,
                'total_reservasi'     => $row->total_reservasi,
                'created_at'          => now(),
                'updated_at'          => now(),
                'updated_by'          => $userId,
                'created_by'          => $userId,
                'created_via'         => RawCusReservation::VIA_IMPORT,
            ]);
            $reservationId = $reservation->id;

            // Insert services dari payload (struktur sama dgn store() di
            // ReservationController — services[svcId][checked] / [detail_id][] / [qty][detailId]).
            $servicesInput = $payload['services'] ?? [];
            if (is_array($servicesInput)) {
                foreach ($servicesInput as $svcId => $data) {
                    if (! is_array($data) || ! isset($data['checked'])) continue;
                    $detailIds = $data['detail_id'] ?? [];

                    if (! empty($detailIds) && is_array($detailIds)) {
                        foreach ($detailIds as $detailId) {
                            $qty = $data['qty'][$detailId] ?? 1;
                            \App\Models\RawCusResService::create([
                                'raw_cus_reservation_id'    => $reservation->id,
                                'raw_doctor_id'             => $doctorId,
                                'raw_doc_service_id'        => $svcId,
                                'raw_doc_service_detail_id' => $detailId,
                                'quantity'                  => $qty,
                            ]);
                        }
                    } else {
                        \App\Models\RawCusResService::create([
                            'raw_cus_reservation_id'    => $reservation->id,
                            'raw_doctor_id'             => $doctorId,
                            'raw_doc_service_id'        => $svcId,
                            'raw_doc_service_detail_id' => null,
                            'quantity'                  => 1,
                        ]);
                    }
                }
            }

            // Mark staging promoted + clear draft.
            $row->state = RawCusReservationStaging::STATE_PROMOTED;
            $row->promoted_to_id = $reservationId;
            $row->draft_payload = null;
            $row->updated_at = now();
            $row->updated_by = $userId;
            $row->save();

            DB::table('raw_reservation_import_batch')
                ->where('id', $row->batch_id)
                ->increment('promoted_rows');
        });

        return [
            'ok' => true,
            'reservation_id' => $reservationId,
            'message' => 'Draft berhasil di-promote ke reservasi.',
        ];
    }

    /**
     * Bulk promote — return ringkasan {promoted, conflict, errors}.
     *
     * @param  string[]  $ids
     */
    public function promoteBulk(array $ids, array $opts = []): array
    {
        $summary = ['promoted' => 0, 'conflict' => 0, 'errors' => 0, 'already' => 0, 'details' => []];

        foreach ($ids as $id) {
            $row = RawCusReservationStaging::find($id);
            if (! $row) {
                $summary['errors']++;
                $summary['details'][] = ['id' => $id, 'error' => 'not_found'];
                continue;
            }

            $res = $this->promote($row, $opts);
            if ($res['ok']) {
                $summary['promoted']++;
            } elseif (($res['error'] ?? '') === self::ERR_CONFLICT) {
                $summary['conflict']++;
                $summary['details'][] = ['id' => $id, 'error' => 'conflict', 'patient' => $row->patient_name];
            } elseif (($res['error'] ?? '') === self::ERR_ALREADY_PROMOTED) {
                $summary['already']++;
            } else {
                $summary['errors']++;
                $summary['details'][] = ['id' => $id, 'error' => $res['error'] ?? 'unknown', 'patient' => $row->patient_name];
            }
        }

        return $summary;
    }

    private function findConflict(string $doctorId, string $date, string $startHour): ?RawCusReservation
    {
        return RawCusReservation::with('customer')
            ->where('raw_doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('start_hour', $startHour)
            ->whereNotIn('status', [
                RawCusReservation::STATUS_CANCELLED,
                RawCusReservation::STATUS_WAITLIST,
            ])
            ->first();
    }

    public function resolveOrCreateCustomer(RawCusReservationStaging $row): RawCustomer
    {
        $phone = $row->patient_phone;
        $name = $row->patient_name;

        // Match: phone == phone AND (name LIKE OR phone non-null).
        $query = RawCustomer::query();
        if ($phone) {
            $query->where('phone', $phone);
        } else {
            $query->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            // Scope ke branch untuk hindari salah-match pasien same-name lintas cabang
            $query->where(function ($q) use ($row) {
                $q->where('raw_branch_id', $row->raw_branch_id)
                  ->orWhereNull('raw_branch_id');
            });
        }
        $existing = $query->first();
        if ($existing) {
            // Patch NIK/email kalau sebelumnya kosong tapi sheet ada
            $patch = [];
            if (! $existing->email && $row->patient_email) $patch['email'] = $row->patient_email;
            if (! $existing->nik && $row->patient_nik) $patch['nik'] = $row->patient_nik;
            if ($patch) {
                $existing->update($patch);
            }
            return $existing;
        }

        return RawCustomer::create([
            'id' => (string) Str::uuid(),
            'source_app' => 'csv_import',
            'name' => $name,
            'phone' => $phone,
            'email' => $row->patient_email,
            'nik' => $row->patient_nik,
            'raw_branch_id' => $row->raw_branch_id,
            'raw_doctor_id' => $row->resolved_raw_doctor_id,
            'tanggal_daftar' => now()->toDateString(),
        ]);
    }
}
