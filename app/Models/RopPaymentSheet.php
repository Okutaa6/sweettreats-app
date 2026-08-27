<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RopPaymentSheet — lembar bayar ROP.
 *
 * Satu baris = satu rekap pembayaran yang Finance kirim ke bos sebagai SATU
 * berkas cetak, pengganti kebiasaan mengirim 30 berkas satu per satu di
 * WhatsApp. Rencana: docs/rencana/rencana-lembar-bayar-rop.md.
 *
 * `jumlah_item` dan `total` adalah SNAPSHOT saat lembar dibuat — sengaja tidak
 * dihitung ulang dari relasi. Lembar yang sudah dicetak dan dikirim ke bos
 * tidak boleh berubah angkanya kalau ROP-nya kelak diedit: yang tercetak =
 * yang tersimpan.
 */
class RopPaymentSheet extends Model
{
    protected $table = 'rop_payment_sheets';

    /**
     * Timestamps Eloquent dimatikan: kolom waktunya punya arti sendiri
     * (`dibuat_at` = tanggal yang tercetak di kepala lembar, `dikirim_at` =
     * kapan sampai ke bos), bukan created_at/updated_at generik.
     */
    public $timestamps = false;

    protected $fillable = [
        'number', 'dibuat_oleh', 'dibuat_at', 'dikirim_at', 'dikirim_oleh',
        'jumlah_item', 'total', 'catatan', 'updated_at',
    ];

    protected $casts = [
        'dibuat_at'   => 'datetime',
        'dikirim_at'  => 'datetime',
        'jumlah_item' => 'integer',
        'total'       => 'decimal:2',
    ];

    /* ───────── Relations ───────── */

    public function items()
    {
        return $this->hasMany(RopPaymentSheetItem::class, 'sheet_id', 'id')
                    ->orderBy('urutan')->orderBy('id');
    }

    public function pembuat()  { return $this->belongsTo(Users::class, 'dibuat_oleh', 'id'); }
    public function pengirim() { return $this->belongsTo(Users::class, 'dikirim_oleh', 'id'); }

    /* ───────── Helpers ───────── */

    public function sudahDikirim(): bool
    {
        return $this->dikirim_at !== null;
    }

    /**
     * Berapa item di lembar ini yang uangnya sudah keluar. Dibaca dari status
     * ROP-nya SEKARANG (bukan snapshot) — pertanyaannya memang "sudah dibayar
     * berapa dari yang diminta", jadi jawabannya harus hidup.
     */
    public function jumlahDibayar(): int
    {
        return $this->items->filter(fn ($i) => $i->rop && in_array(
            $i->rop->status,
            [RopRequest::STATUS_DIBAYAR, RopRequest::STATUS_SELESAI],
            true
        ))->count();
    }

    /** Item yang masih boleh ditandai dibayar (belum keluar uangnya). */
    public function itemBelumDibayar()
    {
        return $this->items->filter(
            fn ($i) => $i->rop && $i->rop->status === RopRequest::STATUS_DISETUJUI
        );
    }
}
