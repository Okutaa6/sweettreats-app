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
        'id', 'user_id', 'division_id', 'raw_branch_id', 'is_primary', 'kind',
        'effective_from', 'effective_to', 'confirmed_at', 'confirmed_by', 'note',
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

    /**
     * Rentang penempatan yang BERAKHIR SEBELUM DIMULAI ditolak di titik simpan.
     *
     * Dulu tak berbahaya — baris nol-panjang cuma tak pernah terpilih. Sejak
     * penempatan ikut menentukan HARI LIBUR (dan libur menentukan gaji), rentang
     * rusak yang lolos diam-diam jadi jauh lebih mahal: satu baris terbalik
     * berarti satu orang libur/kerja di cabang yang salah tanpa ada yang tahu.
     * Prod sudah punya satu (Verini, id a2821d77: 15 Agu → 14 Agu, lahir dari
     * rollingRemove di hari yang sama dengan hari mulainya).
     *
     * Lihat §5 docs/rencana/rencana-libur-cabang-bertugas.md.
     */
    protected static function booted(): void
    {
        static::saving(function (self $pos) {
            $from = $pos->effective_from;
            $to   = $pos->effective_to;
            if ($from && $to && Carbon::parse($to)->lt(Carbon::parse($from))) {
                throw new \InvalidArgumentException(
                    'Rentang penempatan tidak sah: tanggal selesai ('
                    . Carbon::parse($to)->toDateString() . ') lebih awal daripada tanggal mulai ('
                    . Carbon::parse($from)->toDateString() . ').'
                );
            }
        });
    }

    /**
     * Akhiri penempatan ini SEKARANG — nonaktif seketika.
     *
     * `effective_to` = KEMARIN, bukan hari ini, sebab `activeOn()` memakai
     * "effective_to >= hari ini" (inklusif); di-set hari ini berarti masih aktif
     * seharian, seolah tidak tercabut.
     *
     * ⚠️ Kalau penempatannya baru MULAI hari ini (atau nanti), "kemarin" akan
     * membuat rentangnya terbalik. Penempatan yang tak pernah berjalan sehari
     * pun bukanlah catatan tentang apa-apa — barisnya DIHAPUS, bukan disimpan
     * cacat. Ini persis cara baris rusak Verini lahir.
     *
     * @return bool true kalau barisnya dihapus (bukan sekadar ditutup)
     */
    public function akhiriSekarang(): bool
    {
        $kemarin = Carbon::today()->subDay();

        // effective_from NULL = berlaku sejak kapan pun → tak mungkin terbalik,
        // cukup ditutup. Yang dihapus hanya yang mulainya SETELAH kemarin.
        if ($this->effective_from && Carbon::parse($this->effective_from)->gt($kemarin)) {
            $this->delete();
            return true;
        }

        $this->update(['effective_to' => $kemarin, 'updated_at' => now()]);
        return false;
    }

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
            })
            // Rolling (penugasan sementara) MENANG atas home selama window-nya,
            // supaya cabang efektif = cabang rolling. Begitu effective_to lewat,
            // row rolling gugur dari filter tanggal di atas → home menang lagi
            // (auto-revert tanpa tulis apa pun). Urutan: rolling dulu, lalu row
            // bounded dulu, lalu effective_from terbaru → ->first() deterministik.
            ->orderByRaw("CASE WHEN kind = 'rolling' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN effective_to IS NULL THEN 1 ELSE 0 END")
            ->orderByDesc('effective_from');
    }

    /**
     * Scope: usulan posisi yang menunggu konfirmasi supervisor
     * (confirmed_at NULL, masih open-ended, is_primary).
     */
    public function scopePending($q)
    {
        return $q->where('is_primary', 1)
            ->where('kind', 'home')
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
            // Lewat akhiriSekarang() satu per satu, bukan mass-update: posisi
            // yang baru dibuat HARI INI akan jadi rentang terbalik kalau
            // ditutup "kemarin" borongan.
            static::query()->activeOn(Carbon::today())->where('user_id', $user->id)
                ->where('kind', 'home')->get()
                ->each(fn (self $p) => $p->akhiriSekarang());
            return null;
        }

        $today = Carbon::today();

        // Posisi ASAL (home) aktif saat ini — syncFromUser hanya mengurus row home,
        // TIDAK boleh membaca/menutup row rolling (penugasan sementara punya alur
        // sendiri & harus tetap utuh saat cabang home diubah). Kalau sudah identik
        // → tidak ada perubahan.
        $current = static::query()->activeOn($today)->where('user_id', $user->id)
            ->where('kind', 'home')->first();
        if ($current
            && $current->division_id   === $division->id
            && $current->raw_branch_id === $user->raw_branch_id) {
            return $current;
        }

        // Beda divisi/cabang → tutup posisi lama (nonaktif mulai kemarin; kalau
        // baru lahir hari ini, dibuang — lihat akhiriSekarang()).
        if ($current) {
            $current->akhiriSekarang();
        }

        $pos = static::create([
            'user_id'        => $user->id,
            'division_id'    => $division->id,
            'raw_branch_id'  => $user->raw_branch_id,
            'is_primary'     => 1,
            'kind'           => 'home',
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
