<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Antrian review SPV — janggal = flag, BUKAN blokir (WiFi = saksi, bukan
 * gerbang). Siklus: baru → dicek → selesai (hasil wajib: wajar /
 * perlu_koreksi / pelanggaran). UNIQUE (user, tanggal, jenis) = idempoten.
 * Orang fleksibel tidak pernah dapat flag. Lihat docs/rencana/rencana-absen-hp.md §8.
 */
class AttFlag extends Model
{
    protected $table = 'att_flag';

    protected $fillable = [
        'user_id', 'work_date', 'jenis', 'ref_type', 'ref_id',
        'status', 'hasil', 'catatan', 'ditindak_oleh', 'ditindak_at',
    ];

    protected $casts = [
        'work_date'   => 'date',
        'ditindak_at' => 'datetime',
    ];

    /** Label awam per jenis — dipakai layar SPV & staff, jangan tampilkan kode. */
    public const LABEL = [
        'ip_asing'        => 'Absen dari luar WiFi cabang',
        'perangkat_ganda' => 'Satu HP dipakai lebih dari satu akun',
        'pola_ip'         => 'Sering absen dari luar WiFi cabang',
        'luar_seharian'   => 'Masuk & pulang dua-duanya dari luar WiFi',
        'jam_janggal'     => 'Jam absen janggal',
        'single_scan'     => 'Lupa absen pulang',
        'perangkat_desktop' => 'Absen dari laptop/PC',
        'cabang_beda_jadwal' => 'Dokter absen di cabang di luar jadwalnya',
        'pulang_cepat'    => 'Pulang lebih awal dari jam pulang jadwalnya',
    ];

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id', 'id');
    }

    public function penindak()
    {
        return $this->belongsTo(Users::class, 'ditindak_oleh', 'id');
    }

    /**
     * Tulis flag idempoten: kalau (user, tanggal, jenis) sudah ada, diamkan
     * (jangan reset status yang mungkin sudah dicek SPV).
     */
    public static function raise(string $userId, string $workDate, string $jenis, ?string $refType = null, ?int $refId = null): void
    {
        try {
            static::firstOrCreate(
                ['user_id' => $userId, 'work_date' => $workDate, 'jenis' => $jenis],
                ['ref_type' => $refType, 'ref_id' => $refId, 'status' => 'baru']
            );
        } catch (\Illuminate\Database\QueryException $e) {
            // race dua request paralel → insert kedua nabrak UNIQUE = flag
            // sudah ada, tujuan tercapai; selain duplicate-key tetap dilempar
            if (($e->errorInfo[0] ?? null) !== '23000') throw $e;
        }
    }
}
