<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lookup divisi karyawan operasional (FO / GS / ASDENT). PK string = kode divisi.
 * Diseed via ChecklistDivisionSeeder. Murni untuk domain checklist — hierarki
 * organisasi/approval sekarang ada di Role (group_path), bukan di sini.
 */
class ChecklistDivision extends Model
{
    protected $table = 'sys_checklist_division';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'description', 'sort_order', 'is_active', 'group_id', 'created_at', 'updated_at'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const FO     = 'FO';
    const GS     = 'GS';
    const ASDENT = 'ASDENT';

    public function categories()
    {
        return $this->hasMany(ChecklistCategory::class, 'division_id', 'id');
    }

    public function activeCategories()
    {
        return $this->categories()
            ->whereNull('deleted_at')
            ->where('is_active', 1)
            ->orderBy('sort_order');
    }

    public function positions()
    {
        return $this->hasMany(UserPosition::class, 'division_id', 'id');
    }

    /** Role STAF pemilik divisi ini (anggotanya mengisi checklist divisi ini). Null = belum ditautkan. */
    public function group()
    {
        return $this->belongsTo(GroupPath::class, 'group_id', 'id');
    }
}
