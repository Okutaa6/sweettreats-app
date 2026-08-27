<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RawBranch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_branch';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'petty_cash_default_float',
    ];

    protected $casts = [
        'petty_cash_default_float' => 'decimal:2',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    /**
     * Jaring pengaman: cabang yang dibuat lewat jalur mana pun (UI, seeder,
     * tinker) selalu punya slug, supaya link publik /pasien-baru/{slug}
     * tidak pernah 404 hanya karena slug lupa diisi.
     */
    protected static function booted()
    {
        static::creating(function (self $branch) {
            if (blank($branch->slug)) {
                $branch->slug = self::makeUniqueSlug((string) $branch->name);
            }
        });
    }

    /**
     * Slug URL yang dijamin unik di raw_branch (kolom punya UNIQUE index).
     * Bentrok diberi suffix -2, -3, … . $ignoreId dipakai saat edit supaya
     * cabang tidak dianggap bentrok dengan dirinya sendiri.
     */
    public static function makeUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'cabang';
        $slug = $base;
        $i    = 1;

        while (
            self::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }

    /** URL publik form "Pasien Baru" untuk cabang ini (null bila slug kosong). */
    public function pasienBaruUrl(): ?string
    {
        return $this->slug ? route('pasien-baru.show', $this->slug) : null;
    }

    /**
     * Default float (imprest) yang dipakai saat membuat periode pertama
     * untuk cabang ini. Fallback ke konstanta global kalau cabang belum
     * di-set (kolom NULL).
     */
    public function defaultFloatFor(string $kind, float $fallback): float
    {
        $v = $this->petty_cash_default_float;
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