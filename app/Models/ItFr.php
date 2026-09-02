<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Baris Kebutuhan Fungsional (FRM-IT-02 §B) — dinormalkan karena gerbang
 * ANALISIS→DEV membacanya (≥1 baris dengan kriteria_diterima terisi).
 */
class ItFr extends Model
{
    protected $table = 'it_fr';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'kode', 'kebutuhan', 'prioritas', 'kriteria_diterima', 'urutan'];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
