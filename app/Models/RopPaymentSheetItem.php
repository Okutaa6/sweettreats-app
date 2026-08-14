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

    protected $fillable = ['sheet_id', 'rop_id', 'amount', 'urutan', 'created_at'];

    protected $casts = [
        'sheet_id'   => 'integer',
        'rop_id'     => 'integer',
        'amount'     => 'decimal:2',
        'urutan'     => 'integer',
        'created_at' => 'datetime',
    ];

    public function sheet() { return $this->belongsTo(RopPaymentSheet::class, 'sheet_id', 'id'); }
    public function rop()   { return $this->belongsTo(RopRequest::class, 'rop_id', 'id'); }
}
