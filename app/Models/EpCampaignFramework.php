<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpCampaignFramework extends Model
{
    protected $table = 'ep_campaign_framework';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'bulan',
        'tahun',
        'campaign_name',
        'big_idea',
        'target',
        'status',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'bulan'      => 'integer',
        'tahun'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function statusOptions(): array
    {
        return [
            'planning' => ['label' => 'Planning', 'color' => 'warning'],
            'aktif'    => ['label' => 'Aktif',    'color' => 'success'],
            'selesai'  => ['label' => 'Selesai',  'color' => 'neutral'],
        ];
    }

    public static function bulanOptions(): array
    {
        return [
            1=>'Januari', 2=>'Februari', 3=>'Maret',     4=>'April',
            5=>'Mei',     6=>'Juni',     7=>'Juli',      8=>'Agustus',
            9=>'September',10=>'Oktober',11=>'November', 12=>'Desember',
        ];
    }

    public function weeks()
    {
        return $this->hasMany(EpCampaignWeek::class, 'ep_campaign_framework_id', 'id')
            ->orderBy('minggu_ke');
    }

    public function kontens()
    {
        return $this->hasMany(EpKonten::class, 'ep_campaign_framework_id', 'id');
    }
}
