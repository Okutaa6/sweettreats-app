<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrtTransfer — satu baris = satu kejadian pindah cabang.
 *
 * Transfer DICATAT, bukan disimpulkan dari membandingkan cabang dua kontrol
 * berurutan. Tanpa tabel ini, tiga hal tidak punya tempat: alasan, transfer
 * sementara, dan hitungan "bulan ini" yang tidak rapuh.
 *
 * ALASAN WAJIB. Brief konsultan menyisakan satu teka-teki yang hanya bisa
 * dijawab kalau alasannya tercatat: sheet Kalideres menandai 46 "Pindah
 * Kontrol" tapi tak satu pun pasiennya berkontrol terakhir di cabang lain —
 * kumulatif bertahun-tahun, atau "pindah" di sana berarti keluar dari klinik?
 *
 * DUA ASAL YANG BERBEDA MAKNA (kolom `detected`):
 *   manual      RENCANA  — Sales membuka lembar Pindahkan; pasien belum tentu
 *                          jadi datang. Mudah diurungkan.
 *   rekam_medis KENYATAAN — pasien ternyata muncul di cabang lain, ketahuan
 *                          dari tarikan malam. Urungkan hanya mengembalikan
 *                          kepemilikan, TIDAK menghapus kunjungannya.
 */
class OrtTransfer extends Model
{
    protected $table = 'ort_transfer';

    public $incrementing = false;
    protected $keyType   = 'string';
    public const UPDATED_AT = null; // tabel ini hanya punya created_at

    protected $fillable = [
        'id', 'ort_case_id', 'ort_control_id',
        'from_place_id', 'to_place_id', 'transfer_date',
        'reason', 'reason_note',
        'is_temporary', 'return_after_control_id', 'returned_at',
        'detected', 'created_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'is_temporary'  => 'boolean',
        'returned_at'   => 'datetime',
    ];

    /** Alasan pindah. Wajib diisi — lihat catatan kelas. */
    public const REASONS = [
        'pasien_minta'    => 'Pasien minta pindah',
        'dokter_pindah'   => 'Dokternya pindah cabang',
        'lebih_dekat'     => 'Cabang tujuan lebih dekat',
        'pindah_domisili' => 'Pasien pindah domisili',
        'lainnya'         => 'Lainnya',
    ];

    public const DETECTED_MANUAL      = 'manual';
    public const DETECTED_REKAM_MEDIS = 'rekam_medis';

    public function case()
    {
        return $this->belongsTo(OrtCase::class, 'ort_case_id');
    }

    public function control()
    {
        return $this->belongsTo(OrtControl::class, 'ort_control_id');
    }

    /** Transfer sementara yang belum kembali — kandidat pengembalian otomatis. */
    public function scopeMenungguKembali($q)
    {
        return $q->where('is_temporary', 1)->whereNull('returned_at');
    }

    public function labelAlasan(): string
    {
        if ($this->reason === 'lainnya' && filled($this->reason_note)) {
            return $this->reason_note;
        }

        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
