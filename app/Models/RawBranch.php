<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawBranch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_branch';

    protected $fillable = [
        'id',
        'name',
        'petty_cash_default_float',
        'transport_cost_default_float',
    ];

    protected $casts = [
        'petty_cash_default_float'     => 'decimal:2',
        'transport_cost_default_float' => 'decimal:2',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    /**
     * Default float (imprest) yang dipakai saat membuat periode pertama
     * untuk cabang ini. Fallback ke konstanta global kalau cabang belum
     * di-set (kolom NULL).
     */
    public function defaultFloatFor(string $kind, float $fallback): float
    {
        $col = $kind === 'transport' ? 'transport_cost_default_float' : 'petty_cash_default_float';
        $v = $this->{$col};
        return $v !== null ? (float) $v : $fallback;
    }

    public function doctorSchedules()
    {
        return $this->hasMany(RawDoctorSchedule::class, 'raw_branch_id', 'id');
    }

    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_branch_id', 'id');
    }

    public function targets()
    {
        return $this->hasMany(RawBranchTarget::class, 'cabang_id', 'id');
    }
}