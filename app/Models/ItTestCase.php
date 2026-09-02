<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Test case internal testing (FRM-IT-04 §B). */
class ItTestCase extends Model
{
    protected $table = 'it_test_case';

    public $timestamps = false;

    protected $fillable = ['ticket_id', 'kode', 'skenario', 'diharapkan', 'aktual', 'status'];

    public function ticket()
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
