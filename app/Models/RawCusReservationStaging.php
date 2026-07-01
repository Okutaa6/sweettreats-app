<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawCusReservationStaging extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_cus_reservation_staging';

    protected $fillable = [
        'id',
        'batch_id',
        'raw_branch_id',
        'source_row_index',
        'patient_name',
        'patient_phone',
        'patient_email',
        'patient_nik',
        'patient_note',
        'treatment_date',
        'start_hour',
        'doctor_name_raw',
        'resolved_raw_doctor_id',
        'status_raw',
        'mapped_status',
        'tindakan_text',
        'total_reservasi',
        'pic_name',
        'tanggal_reservasi_raw',
        'is_waiting',
        'state',
        'promoted_to_id',
        'parse_warnings',
        'raw_row',
        'draft_payload',
        'created_at',
        'updated_at',
        'updated_by',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    protected $casts = [
        'treatment_date' => 'date',
        'total_reservasi' => 'decimal:2',
        'is_waiting' => 'integer',
        'parse_warnings' => 'array',
        'raw_row' => 'array',
        'draft_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATE_PENDING = 'pending';
    const STATE_PROMOTED = 'promoted';
    const STATE_SKIPPED = 'skipped';
    const STATE_ERROR = 'error';

    public function batch()
    {
        return $this->belongsTo(RawReservationImportBatch::class, 'batch_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(RawDoctor::class, 'resolved_raw_doctor_id', 'id');
    }

    public function promotedReservation()
    {
        return $this->belongsTo(RawCusReservation::class, 'promoted_to_id', 'id');
    }

    /**
     * True kalau row siap di-promote (sudah ada dokter resolved + tanggal
     * atau is_waiting=1). Pasien tanpa nama / tanpa dokter resolved tidak
     * boleh dipromote sampai user perbaiki via edit.
     */
    public function isPromotable(): bool
    {
        if ($this->state !== self::STATE_PENDING) {
            return false;
        }
        // Draft path: kalau ada draft_payload, anggap row sudah lengkap
        // (validasi detail di ReservationPromoter::promoteFromDraft).
        if (! empty($this->draft_payload)) {
            return true;
        }
        if (! $this->patient_name) {
            return false;
        }
        if (! $this->resolved_raw_doctor_id) {
            return false;
        }
        // Non-waiting butuh tanggal; waiting list boleh tanpa tanggal.
        if (! $this->is_waiting && ! $this->treatment_date) {
            return false;
        }
        return true;
    }

    public function hasDraft(): bool
    {
        return ! empty($this->draft_payload) && is_array($this->draft_payload);
    }
}
