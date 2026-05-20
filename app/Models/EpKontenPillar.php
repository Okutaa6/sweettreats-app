<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpKontenPillar extends Model
{
    protected $table = 'ep_konten_pillar';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nama',
        'slug',
        'deskripsi',
        'color',
        'sort_order',
        'is_active',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1)->orderBy('sort_order')->orderBy('nama');
    }

    public function kontens()
    {
        return $this->belongsToMany(
            EpKonten::class,
            'ep_konten_pillar_pivot',
            'ep_konten_pillar_id',
            'ep_konten_id'
        );
    }
}
