<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * RopPaymentSheetItem — satu baris di lembar bayar.
 *
 * `amount` di-snapshot dari rop_requests saat lembar dibuat. Baca dari sini
 * kalau yang ditanya "berapa yang TERCETAK di lembar", baca dari relasi `rop`
 * kalau yang ditanya "berapa nominalnya sekarang". Untuk lembar yang sudah
 * dikirim ke bos, yang benar adalah yang pertama.
 */
class RopPaymentSheetItem extends Model
{
    protected $table = 'rop_payment_sheet_items';

    public const UPDATED_AT = null; // baris lembar tidak pernah diubah setelah dibuat

    protected $fillable = [
        'sheet_id', 'rop_id', 'amount', 'urutan', 'created_at',
        'tujuan_transfer', 'tujuan_oleh',
    ];

    protected $casts = [
        'sheet_id'   => 'integer',
        'rop_id'     => 'integer',
        'amount'     => 'decimal:2',
        'urutan'     => 'integer',
        'created_at' => 'datetime',
    ];

    public function sheet()      { return $this->belongsTo(RopPaymentSheet::class, 'sheet_id', 'id'); }
    public function rop()        { return $this->belongsTo(RopRequest::class, 'rop_id', 'id'); }
    public function pelengkap()  { return $this->belongsTo(Users::class, 'tujuan_oleh', 'id'); }

    /**
     * Tujuan transfer yang BERLAKU untuk baris ini: lengkapan Finance kalau ada,
     * kalau tidak apa yang ditulis pengaju. Satu-satunya tempat aturan ini
     * ditulis — layar & halaman cetak wajib lewat sini supaya tidak pernah beda.
     */
    public function tujuanBerlaku(): string
    {
        $lengkap = trim((string) $this->tujuan_transfer);

        return $lengkap !== '' ? $lengkap : (string) ($this->rop?->namaPenerima() ?: '-');
    }

    /**
     * SATU-SATUNYA definisi "teks ini memuat nomor rekening". Dipakai model,
     * layar pilih, dan layar isi lembar — jangan menyalin regexnya ke view,
     * karena dua salinan pasti berbeda cepat atau lambat.
     */
    public static function terlihatBernomor(?string $teks): bool
    {
        return (bool) preg_match('/\d[\d\s.\-]{5,}\d/', (string) $teks);
    }

    /** True kalau tujuan yang BERLAKU untuk baris ini memuat nomor rekening. */
    public function tujuanBernomor(): bool
    {
        return self::terlihatBernomor($this->tujuanBerlaku());
    }

    /** True kalau yang tercetak adalah tulisan Finance, bukan tulisan pengaju. */
    public function tujuanDilengkapi(): bool
    {
        return trim((string) $this->tujuan_transfer) !== '';
    }
}
