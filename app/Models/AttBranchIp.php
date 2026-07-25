<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IP internet terdaftar per cabang — penanda lokasi absen HP.
 * Satu cabang boleh banyak IP (multi-jalur ISP); satu IP hanya satu cabang
 * (UNIQUE). Diisi via halaman admin IT + tombol "daftarkan IP" dari flag SPV.
 * Lihat docs/rencana-absen-hp.md §5.
 */
class AttBranchIp extends Model
{
    protected $table = 'att_branch_ip';

    protected $fillable = [
        'raw_branch_id', 'ip', 'label', 'status', 'last_seen_at', 'created_by',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Cari cabang dari IP request. NULL = IP tak dikenal (bukan error — flag).
     *
     * IPv4 → cocok penuh. IPv6 → cocok prefix /64 (64 bit pertama = blok
     * jaringan cabang dari ISP). Sebab: IPv6 privacy extension memutar 64 bit
     * terakhir (interface id) tiap sesi/HP, jadi alamat PENUH tak pernah sama
     * dua kali walau di WiFi yang sama — cocok-penuh = selalu "tak terdaftar".
     * Mendaftarkan satu IPv6 dari WiFi cabang otomatis mengenali seluruh /64-nya.
     */
    public static function matchBranch(string $ip): ?self
    {
        $bin  = @inet_pton($ip);
        $isV6 = $bin !== false && strlen($bin) === 16;

        // IPv4 (atau string tak valid) → jalur cepat cocok-penuh.
        if (! $isV6) {
            return static::where('ip', $ip)->where('status', 'aktif')->first();
        }

        // IPv6 → bandingkan 64 bit pertama (8 byte) secara biner, supaya bentuk
        // tertulis apa pun (terkompresi '::', huruf besar/kecil) tetap cocok.
        $prefix = substr($bin, 0, 8);
        foreach (static::where('status', 'aktif')->get() as $row) {
            $rbin = @inet_pton($row->ip);
            if ($rbin !== false && strlen($rbin) === 16 && substr($rbin, 0, 8) === $prefix) {
                return $row;
            }
        }
        return null;
    }
}
