<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Payload naratif per formulir (FRM-IT-01..07), JSON.
 * dikonfirmasi_at = paraf Kelas B ("Benar, ini yang kukerjakan — konfirmasi").
 */
class ItForm extends Model
{
    protected $table = 'it_form';

    protected $fillable = ['ticket_id', 'kode', 'payload', 'dikonfirmasi_at', 'dikonfirmasi_oleh'];

    protected $casts = [
        'payload'         => 'array',
        'dikonfirmasi_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
