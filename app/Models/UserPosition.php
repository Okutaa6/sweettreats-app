<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Posisi/jabatan karyawan saat ini (atau histori). 1 user bisa punya
 * banyak row historis tapi hanya 1 yg aktif (is_primary=1 dan tanggal
 * masih berlaku).
 */
class UserPosition extends Model
{
    use HasUuids;

    protected $table = 'sys_user_position';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'division_id', 'raw_branch_id', 'is_primary',
        'effective_from', 'effective_to', 'confirmed_at', 'confirmed_by',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'is_primary'     => 'boolean',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'confirmed_at'   => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(ChecklistDivision::class, 'division_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Scope: posisi AKTIF per tanggal tertentu (default hari ini). Aktif =
     * sudah dikonfirmasi supervisor (confirmed_at terisi), is_primary, dan
     * tanggal masih berlaku. Usulan pending (confirmed_at NULL) TIDAK aktif.
     */
    public function scopeActiveOn($q, ?Carbon $date = null)
    {
        $d = ($date ?: Carbon::today())->toDateString();
        return $q->where('is_primary', 1)
            ->whereNotNull('confirmed_at')
            ->where(function ($q2) use ($d) {
                $q2->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            })
            ->where(function ($q2) use ($d) {
                $q2->whereNull('effective_from')->orWhere('effective_from', '<=', $d);
            });
    }

    /**
     * Scope: usulan posisi yang menunggu konfirmasi supervisor
     * (confirmed_at NULL, masih open-ended, is_primary).
     */
    public function scopePending($q)
    {
        return $q->where('is_primary', 1)
            ->whereNull('confirmed_at')
            ->whereNull('effective_to');
    }

    /**
     * Auto-tetapkan posisi AKTIF karyawan langsung dari data user-nya —
     * tanpa usulan/konfirmasi. Divisi otomatis dari role (group_id), cabang
     * dari user.raw_branch_id. Dipakai saat user dibuat/diubah admin dan saat
     * karyawan membuka /my-checklist (self-heal).
     *
     * Return posisi aktif (baru/eksisting), atau null kalau user tak bisa
     * di-assign (role belum ditautkan ke divisi, atau cabang belum diset).
     */
    public static function syncFromUser(?Users $user, ?string $confirmedBy = null): ?self
    {
        if (! $user) return null;

        $division = $user->raw_branch_id ? checklistDivisionForGroup($user->group_id) : null;

        // User tak lagi punya divisi checklist (pindah ke role non-operasional)
        // atau cabangnya dikosongkan → posisi aktifnya HARUS ditutup, kalau
        // tidak cron mark-missed terus mencetak "Tidak Diisi" di divisi lamanya.
        if (! $division) {
            static::query()->activeOn(Carbon::today())->where('user_id', $user->id)
                ->update(['effective_to' => Carbon::today()->subDay(), 'updated_at' => now()]);
            return null;
        }

        $today = Carbon::today();

        // Posisi aktif saat ini. Kalau sudah identik → tidak ada perubahan.
        $current = static::query()->activeOn($today)->where('user_id', $user->id)->first();
        if ($current
            && $current->division_id   === $division->id
            && $current->raw_branch_id === $user->raw_branch_id) {
            return $current;
        }

        // Beda divisi/cabang → tutup posisi lama (nonaktif mulai kemarin).
        if ($current) {
            $current->update([
                'effective_to' => $today->copy()->subDay(),
                'updated_at'   => now(),
            ]);
        }

        $pos = static::create([
            'user_id'        => $user->id,
            'division_id'    => $division->id,
            'raw_branch_id'  => $user->raw_branch_id,
            'is_primary'     => 1,
            'effective_from' => $today,
            'effective_to'   => null,
            'confirmed_at'   => now(),
            'confirmed_by'   => $confirmedBy,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Bersihkan sisa usulan pending lama (kalau ada dari alur sebelumnya).
        static::query()->pending()->where('user_id', $user->id)
            ->where('id', '!=', $pos->id)->delete();

        try {
            ChecklistAuditLog::write(
                'submission',
                $pos->id,
                $current ? ChecklistAuditLog::ACTION_UPDATE : ChecklistAuditLog::ACTION_CREATE,
                $current ? ['division_id' => $current->division_id, 'raw_branch_id' => $current->raw_branch_id] : null,
                ['division_id' => $pos->division_id, 'raw_branch_id' => $pos->raw_branch_id],
                'Posisi ' . ($user->nama ?? '-') . ' otomatis diset ke ' . ($division->name ?? $division->id)
            );
        } catch (\Throwable $e) {}

        return $pos;
    }
}
