<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris rincian biaya dalam satu catatan servis AC.
 * Contoh: "Cuci AC" 340.000, "Isi freon 1/2 PK" 270.000.
 */
class AcServiceItem extends Model
{
    use HasUuids;

    protected $table = 'sys_ac_service_item';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'service_record_id', 'description', 'amount', 'sort_order', 'created_at',
    ];

    protected $casts = [
        'amount'     => 'integer',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(AcServiceRecord::class, 'service_record_id', 'id');
    }
}
