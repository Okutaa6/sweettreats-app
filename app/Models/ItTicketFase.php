<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat fase tiket — baris keluar_at NULL = fase yang sedang berjalan.
 * Sumber tunggal hitungan "menunggu berapa hari" (dihitung dari masuk_at,
 * JANGAN pernah disimpan sebagai angka).
 */
class ItTicketFase extends Model
{
    protected $table = 'it_ticket_fase';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'fase', 'masuk_at', 'keluar_at', 'oleh_user_id', 'pihak_menunggu'];

    protected $casts = [
        'masuk_at'  => 'datetime',
        'keluar_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
