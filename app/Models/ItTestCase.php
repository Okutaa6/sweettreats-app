<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Test case internal testing (FRM-IT-04 §B).
 * `terkunci` = lima pemeriksaan tetap TC-01..05: tak bisa dihapus / diganti
 * nama, tapi kolom aktual & Pass/Fail diisi normal dan ikut terhitung.
 */
class ItTestCase extends Model
{
    protected $table = 'it_test_case';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'kode', 'skenario', 'diharapkan', 'aktual', 'status', 'terkunci'];

    protected $casts = ['terkunci' => 'boolean'];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
