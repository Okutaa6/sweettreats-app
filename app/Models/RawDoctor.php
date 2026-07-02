<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDoctor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'raw_doctor';

    protected $fillable = [
        'id',
        'name',
        'photo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? asset('uploads/doctors/' . $this->photo) : null;
    }

    /**
     * Scope: hanya dokter aktif.
     * Dipakai di dropdown form reservasi baru & endpoint API publik.
     * Dokter nonaktif tetap valid untuk reservasi yang sudah terjadwal.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Jadwal dokter (per cabang & hari)
     */
    public function schedules()
    {
        return $this->hasMany(RawDoctorSchedule::class, 'raw_doctor_id', 'id');
    }

    /**
     * Reservasi yang ditangani dokter ini
     */
    public function reservations()
    {
        return $this->hasMany(RawCusReservation::class, 'raw_doctor_id', 'id');
    }

    /**
     * Service/perawatan yang dikerjakan di reservasi (historical)
     */
    public function reservationServices()
    {
        return $this->hasMany(RawCusResService::class, 'raw_doctor_id', 'id');
    }

    /**
     * Service/perawatan yang BISA dilakukan dokter ini (master data)
     */
    public function doctorServices()
    {
        return $this->hasMany(RawDoctorService::class, 'raw_doctor_id', 'id');
    }

    /**
     * get list RawDocService yang bisa dilakukan dokter ini
     * via raw_doctor_service pivot
     */
    public function capableServices()
    {
        return $this->belongsToMany(
            RawDocService::class,
            'raw_doctor_service',
            'raw_doctor_id',
            'raw_doc_service_id'
        );
    }

    /**
     * Resolve dokter dari input bebas vendor (ID / nama persis / nama fuzzy).
     * Dipakai endpoint Open API (mis. Cekat customer_profile.dokter) DAN
     * importer CSV reservasi (header "Dokter" sering punya varian penulisan).
     *
     * Matching pass (urutan dieksekusi):
     *   1. Exact ID
     *   2. Exact lowercase name match
     *   3. Normalized exact match — strip title (drg./dr.) + suffix (Sp.Ort)
     *      + tanda baca, lalu compare ("drg. Panji Sp. KG" → "panji" match
     *      "drg. Panji" → "panji")
     *   4. Token-overlap match — semua token CSV (length >= 3) muncul di
     *      master normalized ("drg yasnelly sp ort" → tokens ['yasnelly']
     *      match master "drg. Yasnelly, Sp. Ort")
     *   5. Legacy LIKE %input% fallback
     *
     * Doctor list di-cache static per-request supaya batch matching (mis.
     * 1000+ row CSV) tidak query DB berulang.
     *
     * Return null bila input kosong / tak ada match.
     */
    public static function resolveByIdOrName(?string $dokter): ?self
    {
        $d = trim((string) $dokter);
        if ($d === '') {
            return null;
        }

        // Pass 1: exact ID
        $byId = self::find($d);
        if ($byId) {
            return $byId;
        }

        // Pass 2: exact lowercase name
        $byName = self::whereRaw('LOWER(name) = ?', [mb_strtolower($d)])->first();
        if ($byName) {
            return $byName;
        }

        // Pass 3 & 4: normalize + token match
        $normalized = self::normalizeDoctorName($d);
        if ($normalized !== '') {
            $allDoctors = self::cachedDoctorList();

            // Pass 3: normalized exact match
            foreach ($allDoctors as $doc) {
                if (self::normalizeDoctorName($doc->name) === $normalized) {
                    return self::find($doc->id);
                }
            }

            // Pass 4: token-overlap match
            $csvTokens = array_filter(
                explode(' ', $normalized),
                fn ($t) => mb_strlen($t) >= 3
            );
            if (count($csvTokens) > 0) {
                foreach ($allDoctors as $doc) {
                    $masterNorm = self::normalizeDoctorName($doc->name);
                    $allMatch = true;
                    foreach ($csvTokens as $tok) {
                        if (! str_contains($masterNorm, $tok)) {
                            $allMatch = false;
                            break;
                        }
                    }
                    if ($allMatch) {
                        return self::find($doc->id);
                    }
                }
            }
        }

        // Pass 5: legacy LIKE %input% fallback
        return self::where('name', 'like', '%' . $d . '%')->first();
    }

    /**
     * Normalize nama dokter untuk fuzzy match — strip title, spesialisasi
     * suffix, dan tanda baca. Lowercase + collapse spaces.
     *
     *   "drg. Panji"            → "panji"
     *   "drg. Panji Sp. KG"     → "panji"
     *   "drg Yasnelly Sp Ort"   → "yasnelly"
     *   "Dr. Anin, Sp.Ort"      → "anin"
     */
    private static function normalizeDoctorName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        // Strip title prefix (drg./dr./dokter)
        $n = preg_replace('/^(drg\.?|dr\.?|dokter)\s+/u', '', $n);
        // Strip specialization suffix — "sp.ort", ", sp ort", "sp. kg", dsb.
        $n = preg_replace('/,?\s*sp\.?\s*[a-z]+\.?\s*$/u', '', $n);
        // Replace punctuation with space
        $n = preg_replace('/[\.\,\-]+/u', ' ', $n);
        // Collapse spaces
        $n = preg_replace('/\s+/u', ' ', $n);
        return trim($n);
    }

    /**
     * Static cache untuk list dokter (id + name) per-request. Hindari
     * query berulang saat batch matching CSV impor (>1000 panggilan).
     *
     * @return \Illuminate\Support\Collection<int, object{id:string,name:string}>
     */
    private static \Illuminate\Support\Collection|null $cachedDoctors = null;
    private static function cachedDoctorList(): \Illuminate\Support\Collection
    {
        if (self::$cachedDoctors === null) {
            self::$cachedDoctors = self::query()
                ->select(['id', 'name'])
                ->get();
        }
        return self::$cachedDoctors;
    }
}
