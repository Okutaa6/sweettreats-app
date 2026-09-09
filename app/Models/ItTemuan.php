<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Temuan uji (FRM-IT-04 §C). Gerbang UJI→UAT: nol temuan Major berstatus
 * selain 'Selesai'.
 */
class ItTemuan extends Model
{
    protected $table = 'it_temuan';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'deskripsi', 'tingkat', 'pic_user_id', 'status'];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
