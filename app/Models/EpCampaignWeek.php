<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpCampaignWeek extends Model
{
    protected $table = 'ep_campaign_week';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ep_campaign_framework_id',
        'minggu_ke',
        'fokus',
        'deliverables',
        'created_at',
    ];

    protected $casts = [
        'minggu_ke'  => 'integer',
        'created_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(EpCampaignFramework::class, 'ep_campaign_framework_id', 'id');
    }
}
