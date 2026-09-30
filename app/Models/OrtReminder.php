<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * OrtReminder — pengingat ke admin cabang lain.
 *
 * Muncul di tab cabang lain: kalau pasien kami tertinggal di sana (jadwal
 * terlewat >14 hari, atau belum dijadwalkan >30 hari), barisnya dapat tombol
 * "Ingatkan <Cabang>". Menekannya membuka WhatsApp ke admin cabang tujuan dan
 * mencatat barisnya di sini.
 *
 * Tabel sendiri, bukan kolom last_reminded_at di ort_case, supaya riwayatnya
 * utuh dan bisa dihitung ("cabang mana yang paling sering harus diingatkan").
 *
 * ⚠ Chip "Diingatkan 18 Jan" membaca yang TERAKHIR. Jangan pakai window
 *   function dan jangan derived table: ambil halamannya dulu (≤10 baris), lalu
 *   satu kueri `whereIn('ort_case_id', $ids)->orderByDesc('reminded_at')` dan
 *   reduksi di PHP. Dua kueri berindex, nol N+1.
 */
class OrtReminder extends Model
{
    protected $table = 'ort_reminder';

    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false; // hanya punya reminded_at

    protected $fillable = [
        'id', 'ort_case_id', 'from_place_id', 'to_place_id',
        'reminded_at', 'reminded_by', 'channel', 'note',
    ];

    protected $casts = [
        'reminded_at' => 'datetime',
    ];

    /**
     * Tombol "Ingatkan" hidup lagi setelah sekian hari kalau belum ada
     * kemajuan — supaya cabang tujuan tidak diberondong pesan yang sama.
     */
    public const JEDA_HARI = 7;

    public function case()
    {
        return $this->belongsTo(OrtCase::class, 'ort_case_id');
    }

    /** Sudah lewat jeda, jadi boleh diingatkan lagi. */
    public function bolehDiingatkanLagi(): bool
    {
        return $this->reminded_at === null
            || $this->reminded_at->diffInDays(now()) >= self::JEDA_HARI;
    }
}
